<?php
require_once __DIR__ . '/../config/koneksi.php';

function iqomah_db_query($sql)
{
    global $koneksi;

    for ($attempt = 0; $attempt < 2; $attempt++) {
        try {
            if (!($koneksi instanceof mysqli) || !mysqli_ping($koneksi)) {
                $koneksi = musholla_reconnect_database();
            }
        } catch (mysqli_sql_exception $exception) {
            error_log('Iqomah database ping failed: ' . $exception->getMessage());
            $koneksi = musholla_reconnect_database();
        }

        if (!($koneksi instanceof mysqli)) {
            return false;
        }

        try {
            return mysqli_query($koneksi, $sql);
        } catch (mysqli_sql_exception $exception) {
            error_log('Iqomah database query failed: ' . $exception->getMessage());
            if (!in_array((int)$exception->getCode(), [2006, 2013, 2055], true) || $attempt === 1) {
                return false;
            }
            $koneksi = musholla_reconnect_database();
        }
    }

    return false;
}

// ====== PROSES SIMPAN / UPLOAD AUDIO ALARM ======
if (isset($_POST['upload_audio'])) {
    $upload = $_FILES['sound_file'] ?? null;
    $target_dir = '../assets/audio/';
    $max_audio_size = 10 * 1024 * 1024;

    if (!$upload || $upload['error'] !== UPLOAD_ERR_OK) {
        header('Location: iqomah.php?status=audio_failed&reason=upload');
        exit;
    }

    if ($upload['size'] > $max_audio_size) {
        header('Location: iqomah.php?status=audio_failed&reason=size');
        exit;
    }

    if (!is_dir($target_dir) && !mkdir('../assets/audio/', 0777, true) && !is_dir($target_dir)) {
        header('Location: iqomah.php?status=audio_failed&reason=directory');
        exit;
    }

    $new_audio_name = 'alarm_' . time() . '_' . bin2hex(random_bytes(4)) . '.mp3';
    $target_file = $target_dir . $new_audio_name;
    if (!move_uploaded_file($upload['tmp_name'], $target_file)) {
        header('Location: iqomah.php?status=audio_failed&reason=move');
        exit;
    }

    require_once __DIR__ . '/../config/alarm_audio.php';
    $audio_column = alarm_audio_column($koneksi);
    $audio_saved = false;
    if ($audio_column !== null) {
        $escaped_audio_name = mysqli_real_escape_string($koneksi, $new_audio_name);
        $update_result = mysqli_query(
            $koneksi,
            "UPDATE app_timer_iqomah SET `$audio_column`='$escaped_audio_name' WHERE id=1"
        );
        $audio_saved = $update_result && mysqli_affected_rows($koneksi) > 0;
    } else {
        $audio_saved = file_put_contents($target_dir . 'current_alarm.txt', $new_audio_name, LOCK_EX) !== false;
    }

    if (!$audio_saved) {
        unlink($target_file);
        header('Location: iqomah.php?status=audio_failed&reason=database');
        exit;
    }

    $old_audio_files = array_merge(
        glob($target_dir . 'alarm_*.mp3') ?: [],
        [$target_dir . 'alarm.mp3']
    );
    foreach (array_unique($old_audio_files) as $old_audio_file) {
        if ($old_audio_file !== $target_file && is_file($old_audio_file) && !unlink($old_audio_file)) {
            error_log('Could not remove old alarm audio file: ' . $old_audio_file);
            header('Location: iqomah.php?status=audio_failed&reason=cleanup');
            exit;
        }
    }

    header('Location: iqomah.php?status=audio_success');
    exit;
}

// ====== PROSES SIMPAN TIMER IQOMAH ======
if (isset($_POST['simpan_timer'])) {
    $timer_inputs = ['subuh', 'dzuhur', 'ashar', 'maghrib', 'isya'];
    $timers = [];
    $valid_timers = true;

    foreach ($timer_inputs as $prayer) {
        $value = filter_input(INPUT_POST, $prayer, FILTER_VALIDATE_INT);
        if ($value === false || $value === null || $value < 1 || $value > 60) {
            $valid_timers = false;
            break;
        }
        $timers[$prayer] = $value;
    }

    $check = $valid_timers
        ? mysqli_query($koneksi, "SELECT id FROM app_timer_iqomah WHERE id=1 LIMIT 1")
        : false;
    if ($check && mysqli_num_rows($check) > 0) {
        $saved = mysqli_query(
            $koneksi,
            "UPDATE app_timer_iqomah
             SET subuh={$timers['subuh']}, dzuhur={$timers['dzuhur']}, ashar={$timers['ashar']},
                 maghrib={$timers['maghrib']}, isya={$timers['isya']}
             WHERE id=1"
        );
    } elseif ($check) {
        $saved = mysqli_query(
            $koneksi,
            "INSERT INTO app_timer_iqomah (id, subuh, dzuhur, ashar, maghrib, isya)
             VALUES (1, {$timers['subuh']}, {$timers['dzuhur']}, {$timers['ashar']}, {$timers['maghrib']}, {$timers['isya']})"
        );
    } else {
        $saved = false;
    }
    header("Location: iqomah.php?status=" . ($saved ? 'timer_success' : 'timer_failed'));
    exit;
}

// ====== AMBIL SETTINGAN TERKINI ======
$settings = [];
$q_set = iqomah_db_query("SELECT * FROM pengaturan ORDER BY id ASC LIMIT 100");
if ($q_set) {
    while ($r = mysqli_fetch_assoc($q_set)) {
        $settings[$r['nama_setting']] = $r['nilai_setting'];
    }
}

require_once __DIR__ . '/../config/alarm_audio.php';
$current_audio = 'alarm.mp3';
try {
    $current_audio = current_alarm_audio($koneksi, __DIR__ . '/../assets/audio/');
} catch (Throwable $exception) {
    error_log('Could not load iqomah alarm audio setting: ' . $exception->getMessage());
}
$current_audio_exists = is_file('../assets/audio/' . $current_audio);
$timer_settings = [
    'subuh' => 10,
    'dzuhur' => 10,
    'ashar' => 10,
    'maghrib' => 10,
    'isya' => 10,
    'sound_file' => $current_audio,
];
try {
    $q_timer = iqomah_db_query(
        "SELECT subuh, dzuhur, ashar, maghrib, isya, sound_file
         FROM app_timer_iqomah
         WHERE id=1
         LIMIT 1"
    );
    if ($q_timer) {
        $data_timer = mysqli_fetch_assoc($q_timer);
        if ($data_timer) {
            $timer_settings = array_merge($timer_settings, $data_timer);
            $current_audio = current_alarm_audio($koneksi, __DIR__ . '/../assets/audio/');
            $current_audio_exists = is_file('../assets/audio/' . $current_audio);
        }
    }
} catch (mysqli_sql_exception $exception) {
    error_log('Could not load iqomah timer settings: ' . $exception->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timer Iqomah & Alarm - Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Poppins', sans-serif; background-color: #050b14; color: #ffffff; } </style>
</head>
<body class="p-6 min-h-screen">

    <div class="max-w-5xl mx-auto">
        <!-- HEADER -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <a href="index.php" class="text-xs text-emerald-400 font-semibold uppercase tracking-wider flex items-center gap-2 mb-1 hover:underline">
                    <i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard
                </a>
                <h1 class="text-2xl font-black text-white uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-clock text-amber-400"></i> Timer Iqomah & Sound Alarm
                </h1>
            </div>
            <a href="../index.php" target="_blank" class="bg-rose-600/80 hover:bg-rose-600 text-white text-xs font-bold py-2.5 px-4 rounded-xl transition-all shadow-lg flex items-center gap-2">
                <i class="fa-solid fa-desktop"></i> Lihat TV
            </a>
        </div>

        <!-- NOTIFIKASI -->
        <?php if (isset($_GET['status'])): ?>
            <?php if ($_GET['status'] == 'audio_success'): ?>
                <div class="mb-6 bg-emerald-950/80 border border-emerald-500/50 text-emerald-300 p-4 rounded-xl text-xs font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-circle-check text-base"></i> File sound alarm berhasil diperbarui!
                </div>
            <?php elseif ($_GET['status'] == 'timer_success'): ?>
                <div class="mb-6 bg-emerald-950/80 border border-emerald-500/50 text-emerald-300 p-4 rounded-xl text-xs font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-circle-check text-base"></i> Durasi timer Iqomah berhasil disimpan!
                </div>
            <?php elseif ($_GET['status'] == 'timer_failed'): ?>
                <div class="mb-6 bg-rose-950/80 border border-rose-500/50 text-rose-300 p-4 rounded-xl text-xs font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-circle-xmark text-base"></i> Gagal menyimpan durasi timer. Pastikan nilainya antara 1 sampai 60 menit dan database tersedia.
                </div>
            <?php elseif ($_GET['status'] == 'audio_failed'): ?>
                <div class="mb-6 bg-rose-950/80 border border-rose-500/50 text-rose-300 p-4 rounded-xl text-xs font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-circle-xmark text-base"></i>
                    <?php
                    $audio_upload_errors = [
                        'upload' => 'Upload tidak berhasil diterima oleh server. Silakan pilih file dan coba lagi.',
                        'size' => 'Ukuran file melebihi batas maksimum 10 MB.',
                        'directory' => 'Folder assets/audio gagal dibuat. Periksa izin akses folder aplikasi.',
                        'move' => 'File diterima, tetapi gagal dipindahkan ke folder assets/audio. Periksa izin folder dan ruang penyimpanan.',
                        'database' => 'File audio tersimpan, tetapi nama file aktif gagal disimpan ke database atau file manifest.',
                        'cleanup' => 'Audio baru sudah aktif, tetapi file alarm lama gagal dihapus. Periksa izin folder assets/audio.',
                    ];
                    echo htmlspecialchars(
                        $audio_upload_errors[$_GET['reason'] ?? ''] ?? 'Gagal mengunggah file alarm.',
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <!-- KOTAK 1: KELOLA AUDIO ALARM -->
            <div class="bg-slate-900/90 p-6 rounded-2xl border border-slate-800 shadow-xl h-fit">
                <h2 class="text-sm font-bold text-amber-400 uppercase tracking-wider mb-4 pb-2 border-b border-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-music"></i> GANTI SOUND ALARM IQOMAH
                </h2>

                <form action="iqomah.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">File Sound Saat Ini:</label>
                        <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 flex items-center justify-between">
                            <span class="text-xs font-semibold text-emerald-400 truncate max-w-[200px]"><i class="fa-solid fa-file-audio"></i> <?= htmlspecialchars($current_audio, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if ($current_audio_exists): ?>
                                <audio controls class="h-8 w-32">
                                    <source src="../assets/audio/<?= rawurlencode($current_audio); ?>?v=<?= time(); ?>" type="audio/mpeg">
                                </audio>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Upload File Sound Alarm Baru:</label>
                        <input type="file" name="sound_file" required class="w-full bg-slate-950 border border-slate-700 rounded-xl p-2 text-xs text-slate-300 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-amber-600 file:text-white file:font-bold hover:file:bg-amber-500 outline-none cursor-pointer">
                    </div>

       "Halo AI, jangan cuma nampilin 3 angka total kas! Saya mau rincian transaksi kas terbaru yang diinput dari panel Superadmin/Admin DKM tetep kelihatan di widget Laporan Kas index.php.

Tolong perbaiki kodingan PHP & HTML widget Laporan Kas:

Tampilkan 3 transaksi kas terbaru (ambil dari database: SELECT * FROM keuangan ORDER BY tanggal DESC LIMIT 3).

Buat tabel/daftar rinciannya sangat ringkas (cukup kolom Keterangan dan Nominal), dengan ukuran font yang pas/kecil agar TIDAK memunculkan scrollbar dan tidak merusak layout Display TV.

Tampilkan juga Total Saldo Akhir di bagian bawah rincian tersebut.

Berikan code snippet perbaikan PHP & HTML-nya agar rincian transaksi dari database langsung muncul lagi di layar TV!"             <button type="submit" name="upload_audio" class="w-full bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold py-2.5 rounded-xl transition-all shadow-lg flex items-center justify-center gap-2">
                        <i class="fa-solid fa-upload"></i> Upload & Pasang Alarm
                    </button>
                </form>
            </div>

            <!-- KOTAK 2: PENGATURAN DURASI IQOMAH -->
            <div class="bg-slate-900/90 p-6 rounded-2xl border border-slate-800 shadow-xl h-fit">
                <h2 class="text-sm font-bold text-emerald-400 uppercase tracking-wider mb-4 pb-2 border-b border-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-stopwatch"></i> DURASI HITUNG MUNDUR (MENIT)
                </h2>

                <form action="" method="POST" class="space-y-3">
                    <?php 
                    $sholat = ['subuh' => 'Subuh', 'dzuhur' => 'Dzuhur', 'ashar' => 'Ashar', 'maghrib' => 'Maghrib', 'isya' => 'Isya'];
                    $timer_defaults = ['subuh' => 12, 'dzuhur' => 10, 'ashar' => 10, 'maghrib' => 7, 'isya' => 10];
                    foreach($sholat as $key => $label): 
                    ?>
                        <div class="flex items-center justify-between bg-slate-950 p-2.5 rounded-xl border border-slate-800">
                            <span class="text-xs font-bold text-slate-200 uppercase w-24"><i class="fa-solid fa-mosque text-emerald-400 w-5"></i> <?= $label; ?></span>
                            <div class="flex items-center gap-2">
                                <input type="number" name="<?= $key; ?>" value="<?= htmlspecialchars((string)($timer_settings[$key] ?? $timer_defaults[$key]), ENT_QUOTES, 'UTF-8'); ?>" min="1" max="60" required class="w-20 bg-slate-900 border border-slate-700 rounded-lg p-1.5 text-center text-xs font-bold text-white focus:border-emerald-500 outline-none">
                                <span class="text-xs text-slate-400">Menit</span>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <button type="submit" name="simpan_timer" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold py-2.5 rounded-xl transition-all shadow-lg flex items-center justify-center gap-2 mt-2">
                        <i class="fa-solid fa-save"></i> Simpan Durasi Timer
                    </button>
                </form>
            </div>

        </div>
    </div>

</body>
</html>