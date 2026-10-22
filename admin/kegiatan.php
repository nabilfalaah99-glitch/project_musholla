<?php
require_once __DIR__ . '/../config/koneksi.php';

$pesan = "";

// PROSES SIMPAN / UPLOAD MEDIA
if (isset($_POST['submit'])) {
    $keterangan  = trim((string)($_POST['keterangan'] ?? ''));
    
    // Tangkap input jadwal khusus (jika kosong, di-set NULL)
    $tanggal     = !empty($_POST['tanggal']) ? $_POST['tanggal'] : null;
    $jam_mulai   = !empty($_POST['jam_mulai']) ? $_POST['jam_mulai'] . ':00' : null;
    $jam_selesai = !empty($_POST['jam_selesai']) ? $_POST['jam_selesai'] . ':00' : null;

    $file_name = $_FILES['file_media']['name'] ?? '';
    $file_tmp  = $_FILES['file_media']['tmp_name'] ?? '';
    $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    $ext_gambar = ['jpg', 'jpeg', 'png', 'webp'];
    $ext_video  = ['mp4', 'webm', 'ogg'];

    if (in_array($file_ext, $ext_gambar, true)) {
        $tipe = 'gambar';
    } elseif (in_array($file_ext, $ext_video, true)) {
        $tipe = 'video';
    } else {
        $tipe = 'invalid';
    }

    if ($tipe != 'invalid') {
        $nama_file_baru = bin2hex(random_bytes(8)) . '.' . $file_ext;
        $target_dir     = "../assets/img/" . $nama_file_baru;

        if ($file_tmp !== '' && move_uploaded_file($file_tmp, $target_dir)) {
            $statement = mysqli_prepare(
                $koneksi,
                "INSERT INTO galeri (file, nama_file, tipe, caption, keterangan, tanggal, tanggal_acara, jam_mulai, jam_selesai, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'aktif')"
            );
            $save_result = false;
            if ($statement) {
                $save_result = mysqli_stmt_bind_param(
                    $statement,
                    'sssssssss',
                    $nama_file_baru,
                    $nama_file_baru,
                    $tipe,
                    $keterangan,
                    $keterangan,
                    $tanggal,
                    $tanggal,
                    $jam_mulai,
                    $jam_selesai
                ) && mysqli_stmt_execute($statement);
                mysqli_stmt_close($statement);
            }
            if ($save_result) {
                $pesan = "<div class='bg-emerald-500/20 border border-emerald-500 text-emerald-300 p-3 rounded-xl mb-4 text-xs font-semibold'>Berhasil mengunggah media baru!</div>";
            } else {
                if (is_file($target_dir)) {
                    unlink($target_dir);
                }
                $pesan = "<div class='bg-rose-500/20 border border-rose-500 text-rose-300 p-3 rounded-xl mb-4 text-xs font-semibold'>Gagal menyimpan data ke database.</div>";
            }
        } else {
            $pesan = "<div class='bg-rose-500/20 border border-rose-500 text-rose-300 p-3 rounded-xl mb-4 text-xs font-semibold'>Gagal mengunggah file ke folder assets/img.</div>";
        }
    } else {
        $pesan = "<div class='bg-rose-500/20 border border-rose-500 text-rose-300 p-3 rounded-xl mb-4 text-xs font-semibold'>Format file tidak didukung (Gunakan JPG, PNG, WEBP, MP4, WEBM).</div>";
    }
}

// PROSES HAPUS MEDIA
if (isset($_GET['hapus'])) {
    $id_hapus = (int)$_GET['hapus'];
    $nama_file = '';
    $file_path = '';

    if ($id_hapus < 1) {
        $pesan = "<div class='bg-rose-500/20 border border-rose-500 text-rose-300 p-3 rounded-xl mb-4 text-xs font-semibold'>ID media tidak valid.</div>";
    } else {
        try {
            if (!mysqli_ping($koneksi)) {
                $koneksi = musholla_reconnect_database();
            }
        } catch (mysqli_sql_exception $exception) {
            error_log('Database ping failed before gallery delete: ' . $exception->getMessage());
            $koneksi = musholla_reconnect_database();
        }

        if (!($koneksi instanceof mysqli)) {
            $pesan = "<div class='bg-rose-500/20 border border-rose-500 text-rose-300 p-3 rounded-xl mb-4 text-xs font-semibold'>Koneksi database terputus. Media belum dihapus; coba lagi setelah database tersambung.</div>";
        } else {
            try {
                $q_get = mysqli_query($koneksi, "SELECT nama_file, `file` FROM galeri WHERE id=$id_hapus LIMIT 1");
                $row_get = $q_get ? mysqli_fetch_assoc($q_get) : null;

                if (!$row_get) {
                    $pesan = "<div class='bg-amber-500/20 border border-amber-500 text-amber-300 p-3 rounded-xl mb-4 text-xs font-semibold'>Media tidak ditemukan atau sudah dihapus.</div>";
                } else {
                    $nama_file = basename((string)($row_get['file'] ?: $row_get['nama_file']));
                    $file_path = __DIR__ . '/../assets/img/' . $nama_file;
                    $delete_result = mysqli_query($koneksi, "DELETE FROM galeri WHERE id=$id_hapus");

                    if ($delete_result) {
                        if ($nama_file !== '' && is_file($file_path) && !unlink($file_path)) {
                            error_log('Gallery row deleted but media file could not be removed: ' . $file_path);
                        }
                        header("Location: kegiatan.php");
                        exit;
                    }
                }
            } catch (mysqli_sql_exception $exception) {
                error_log('Gallery delete operation failed: ' . $exception->getMessage());

                try {
                    $koneksi = musholla_reconnect_database();
                    if ($koneksi instanceof mysqli) {
                        $verify_result = mysqli_query(
                            $koneksi,
                            "SELECT id FROM galeri WHERE id=$id_hapus LIMIT 1"
                        );
                        if ($verify_result && mysqli_num_rows($verify_result) === 0) {
                            if ($nama_file !== '' && is_file($file_path) && !unlink($file_path)) {
                                error_log('Deleted gallery file cleanup failed: ' . $file_path);
                            }
                            header("Location: kegiatan.php");
                            exit;
                        }
                    }
                } catch (mysqli_sql_exception $verification_exception) {
                    error_log('Could not verify gallery delete result: ' . $verification_exception->getMessage());
                }

                $pesan = "<div class='bg-rose-500/20 border border-rose-500 text-rose-300 p-3 rounded-xl mb-4 text-xs font-semibold'>Koneksi database terputus saat menghapus. Muat ulang halaman untuk memeriksa status media sebelum mencoba lagi.</div>";
            }
        }
    }
}

// PROSES UBAH STATUS (AKTIF / NONAKTIF)
if (isset($_GET['toggle'])) {
    $id_toggle = (int)$_GET['toggle'];
    $st = ($_GET['st'] ?? '') === 'aktif' ? 'nonaktif' : 'aktif';
    try {
        if (!mysqli_ping($koneksi)) {
            $koneksi = musholla_reconnect_database();
        }
        $toggle_result = $koneksi instanceof mysqli
            ? mysqli_query($koneksi, "UPDATE galeri SET status='$st' WHERE id=$id_toggle")
            : false;
        if ($toggle_result) {
            header("Location: kegiatan.php");
            exit;
        }
    } catch (mysqli_sql_exception $exception) {
        error_log('Gallery status update failed: ' . $exception->getMessage());
    }
    $pesan = "<div class='bg-rose-500/20 border border-rose-500 text-rose-300 p-3 rounded-xl mb-4 text-xs font-semibold'>Gagal memperbarui status media.</div>";
}

// AMBIL DATA GALERI
$query = "SELECT * FROM galeri ORDER BY id DESC LIMIT 100";
$data_galeri = false;
if ($koneksi instanceof mysqli) {
    try {
        if (!mysqli_ping($koneksi)) {
            $koneksi = musholla_reconnect_database();
        }
        if ($koneksi instanceof mysqli) {
            $data_galeri = mysqli_query($koneksi, $query);
        }
    } catch (mysqli_sql_exception $exception) {
        error_log('Gallery list query failed: ' . $exception->getMessage());
    }
}
$total_media = $data_galeri ? mysqli_num_rows($data_galeri) : 0;
if (!$data_galeri && $pesan === '') {
    $pesan = "<div class='bg-rose-500/20 border border-rose-500 text-rose-300 p-3 rounded-xl mb-4 text-xs font-semibold'>Gagal mengambil data galeri dari database. Periksa koneksi lalu muat ulang.</div>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Galeri Media - Admin Musholla</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Poppins', sans-serif; }</style>
</head>
<body class="bg-slate-950 text-white min-h-screen p-6">

    <div class="max-w-6xl mx-auto">
        <!-- HEADER -->
        <div class="flex justify-between items-center mb-6 pb-4 border-b border-slate-800">
            <div>
                <h1 class="text-2xl font-bold text-emerald-400 flex items-center gap-2">
                    <i class="fa-solid fa-images"></i> Kelola Galeri & Slide Media
                </h1>
                <p class="text-xs text-slate-400 mt-1">Upload foto kegiatan umum atau video acara khusus berjadwal.</p>
            </div>
            
            <!-- TOMBOL KEMBALI SEKARANG PASTI KE DASHBOARD UTAMA -->
            <a href="index.php" class="bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs px-4 py-2 rounded-xl border border-slate-700 font-medium transition flex items-center gap-2">
                <i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard
            </a>
        </div>

        <?= $pesan; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- FORM UPLOAD MEDIA -->
            <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-xl h-fit">
                <h2 class="text-sm font-bold text-white uppercase tracking-wider mb-4 border-b border-slate-800 pb-2">
                    <i class="fa-solid fa-cloud-arrow-up text-emerald-400 mr-1"></i> Upload Media Baru
                </h2>

                <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Pilih File (Gambar / Video)</label>
                        <input type="file" name="file_media" required class="w-full bg-slate-950 border border-slate-700 rounded-xl p-2 text-xs text-slate-300 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-950 file:text-emerald-300 hover:file:bg-emerald-900">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Keterangan / Caption Kegiatan</label>
                        <textarea name="keterangan" rows="2" placeholder="Contoh: Dok. Kajian Bulanan Jamaah" class="w-full bg-slate-950 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-emerald-500"></textarea>
                    </div>

                    <div class="border-t border-slate-800 pt-3">
                        <span class="text-[11px] font-bold text-amber-400 uppercase tracking-wider block mb-2">
                            <i class="fa-solid fa-clock mr-1"></i> Jadwal Tayang Acara (Opsional)
                        </span>

                        <div class="mb-3">
                            <label class="block text-[11px] text-slate-400 mb-1">Tanggal Acara</label>
                            <input type="date" name="tanggal" class="w-full bg-slate-950 border border-slate-700 rounded-xl p-2 text-xs text-white">
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] text-slate-400 mb-1">Jam Mulai</label>
                                <input type="time" name="jam_mulai" class="w-full bg-slate-950 border border-slate-700 rounded-xl p-2 text-xs text-white">
                            </div>
                            <div>
                                <label class="block text-[11px] text-slate-400 mb-1">Jam Selesai</label>
                                <input type="time" name="jam_selesai" class="w-full bg-slate-950 border border-slate-700 rounded-xl p-2 text-xs text-white">
                            </div>
                        </div>
                        <p class="text-[10px] text-slate-500 italic mt-2">*Biarkan tanggal & jam kosong jika ingin ditayangkan setiap hari tanpa batas waktu.</p>
                    </div>

                    <button type="submit" name="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs py-2.5 rounded-xl transition shadow-lg shadow-emerald-900/30">
                        <i class="fa-solid fa-upload mr-1"></i> Simpan & Unggah
                    </button>
                </form>
            </div>

            <!-- TABEL DAFTAR MEDIA -->
            <div class="lg:col-span-2 bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-xl">
                <h2 class="text-sm font-bold text-white uppercase tracking-wider mb-4 border-b border-slate-800 pb-2 flex justify-between items-center">
                    <span><i class="fa-solid fa-list text-emerald-400 mr-1"></i> Daftar Media Terpasang</span>
                    <span class="text-xs font-normal text-slate-400">Total: <?= $total_media; ?> Items</span>
                </h2>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-slate-800 text-slate-400 uppercase text-[10px]">
                                <th class="py-2.5 px-2">Preview</th>
                                <th class="py-2.5 px-2">Info / Caption</th>
                                <th class="py-2.5 px-2">Jadwal Tayang</th>
                                <th class="py-2.5 px-2 text-center">Status</th>
                                <th class="py-2.5 px-2 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <?php if ($total_media > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($data_galeri)): ?>
                                    <tr class="hover:bg-slate-800/40 transition">
                                        <td class="py-2.5 px-2">
                                            <?php if ($row['tipe'] == 'video'): ?>
                                                <div class="w-16 h-12 bg-slate-950 rounded-lg overflow-hidden border border-slate-700 flex items-center justify-center relative">
                                                    <i class="fa-solid fa-circle-play text-amber-400 text-lg"></i>
                                                </div>
                                            <?php else: ?>
                                                <img src="../assets/img/<?= htmlspecialchars(basename((string)($row['file'] ?: $row['nama_file'])), ENT_QUOTES, 'UTF-8'); ?>" class="w-16 h-12 object-cover rounded-lg border border-slate-700">
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-2.5 px-2">
                                            <div class="font-medium text-white max-w-[180px] truncate"><?= htmlspecialchars(trim((string)($row['caption'] ?? '')) ?: trim((string)($row['keterangan'] ?? '')) ?: 'Tanpa Caption', ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="text-[10px] text-slate-500 uppercase font-semibold mt-0.5"><?= $row['tipe']; ?></div>
                                        </td>
                                        <td class="py-2.5 px-2 text-[11px]">
                                            <?php if (!empty($row['tanggal']) && !empty($row['jam_mulai'])): ?>
                                                <span class="text-amber-400 font-medium block"><?= date('d/m/Y', strtotime($row['tanggal'])); ?></span>
                                                <span class="text-slate-400 text-[10px]"><?= substr($row['jam_mulai'],0,5); ?> - <?= substr($row['jam_selesai'],0,5); ?></span>
                                            <?php else: ?>
                                                <span class="bg-slate-800 text-emerald-400 px-2 py-0.5 rounded text-[10px]">Setiap Hari</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-2.5 px-2 text-center">
                                            <a href="?toggle=<?= $row['id']; ?>&st=<?= $row['status']; ?>" class="inline-block px-2.5 py-1 rounded-full text-[10px] font-bold uppercase transition <?= $row['status'] == 'aktif' ? 'bg-emerald-950 text-emerald-400 border border-emerald-500/40' : 'bg-rose-950 text-rose-400 border border-rose-500/40'; ?>">
                                                <?= $row['status']; ?>
                                            </a>
                                        </td>
                                        <td class="py-2.5 px-2 text-right">
                                            <a href="?hapus=<?= $row['id']; ?>" onclick="return confirm('Yakin ingin menghapus media ini?')" class="bg-rose-500/20 hover:bg-rose-500 text-rose-400 hover:text-white p-1.5 rounded-lg border border-rose-500/40 transition">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php elseif ($data_galeri): ?>
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-slate-500 italic">Belum ada media galeri tersimpan.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

</body>
</html>