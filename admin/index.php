<?php
require_once __DIR__ . '/../config/koneksi.php';

// ====== PROSES SIMPAN RUNNING TEXT ======
if (isset($_POST['simpan_teks'])) {
    $running_text = mysqli_real_escape_string($koneksi, $_POST['running_text']);

    $check = mysqli_query($koneksi, "SELECT id FROM app_pengaturan WHERE id=1 LIMIT 1");
    if ($check && mysqli_num_rows($check) > 0) {
        $saved = mysqli_query($koneksi, "UPDATE app_pengaturan SET running_text='$running_text' WHERE id=1");
    } elseif ($check) {
        $saved = mysqli_query($koneksi, "INSERT INTO app_pengaturan (id, running_text) VALUES (1, '$running_text')");
    } else {
        $saved = false;
    }

    header('Location: index.php?status=' . ($saved ? 'success' : 'error'));
    exit;
}

// ====== AMBIL DATA RUNNING TEXT ======
$current_running = 'Selamat Datang di Musholla Al-Muhajirin Menara Samawa Jakarta Timur. Mohon menonaktifkan atau mengheningkan suara HP saat berada di dalam area sholat.';
$q_running = mysqli_query($koneksi, "SELECT running_text FROM app_pengaturan WHERE id=1 LIMIT 1");

if ($q_running && mysqli_num_rows($q_running) > 0) {
    $row_r = mysqli_fetch_assoc($q_running);
    if (!empty($row_r['running_text'])) {
        $current_running = $row_r['running_text'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin DKM - Musholla Al-Muhajirin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        };
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        function applyTheme() {
            const theme = localStorage.getItem('admin_theme') || 'auto';
            let isDark = false;

            if (theme === 'auto') {
                const hour = new Date().getHours();
                isDark = (hour < 6 || hour >= 18);
            } else if (theme === 'dark') {
                isDark = true;
            }

            if (isDark) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }

        applyTheme();
    </script>
    <style>
        body { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="p-6 min-h-screen bg-slate-50 dark:bg-[#050b14] text-slate-800 dark:text-white transition-colors duration-300">

    <div class="max-w-7xl mx-auto w-full">
        <!-- HEADER UTAMA -->
        <div class="flex justify-between items-center mb-6 bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 transition-colors duration-300">
            <div>
                <h1 class="text-2xl font-black text-emerald-400 uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-gauge-high"></i> Panel Utama Super Admin DKM
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Pusat kontrol operasional Display Utama Musholla Al-Muhajirin.</p>
            </div>
            <a href="../index.php" target="_blank" class="bg-rose-600/80 hover:bg-rose-600 text-white text-xs font-bold py-2.5 px-4 rounded-xl transition-all shadow-lg flex items-center gap-2">
                <i class="fa-solid fa-desktop"></i> Lihat Display TV
            </a>
        </div>

        <!-- NOTIFIKASI SUKSES -->
        <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
            <div class="mb-6 bg-emerald-100 dark:bg-emerald-950/80 border border-emerald-300 dark:border-emerald-500/50 text-emerald-700 dark:text-emerald-300 p-4 rounded-xl text-xs font-semibold flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-base"></i> Running text berhasil disimpan!
            </div>
        <?php elseif (isset($_GET['status']) && $_GET['status'] == 'error'): ?>
            <div class="mb-6 bg-rose-100 dark:bg-rose-950/80 border border-rose-300 dark:border-rose-500/50 text-rose-700 dark:text-rose-300 p-4 rounded-xl text-xs font-semibold flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-base"></i> Running text gagal disimpan. Periksa koneksi database dan tabel app_pengaturan.
            </div>
        <?php endif; ?>

        <!-- LAYOUT UTAMA 2 KOLOM (SAMPING-SAMPINGAN) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- KOLOM KIRI: MENU NAVIGASI (SUSUN VERTIKAL) -->
            <div class="space-y-3.5">
                <h2 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest flex items-center gap-2 mb-2">
                    <i class="fa-solid fa-bars"></i> MENU NAVIGASI & FITUR
                </h2>

                <!-- KARTU 1: GALERI MEDIA -->
                <a href="kegiatan.php" class="bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-emerald-500/50 transition-all duration-300 shadow-xl group flex items-start gap-3.5">
                    <div class="bg-emerald-100 dark:bg-emerald-950 p-3 rounded-xl border border-emerald-300 dark:border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-lg group-hover:scale-110 transition-all shrink-0">
                        <i class="fa-solid fa-images"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 dark:text-white group-hover:text-emerald-500 dark:group-hover:text-emerald-400 transition-all text-xs mb-1">Kelola Galeri & Video Acara</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">Upload foto kegiatan & atur jadwal jam tayang video acara.</p>
                    </div>
                </a>

                <!-- KARTU 2: TIMER IQOMAH -->
                <a href="iqomah.php" class="bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-amber-500/50 transition-all duration-300 shadow-xl group flex items-start gap-3.5">
                    <div class="bg-amber-100 dark:bg-amber-950 p-3 rounded-xl border border-amber-300 dark:border-amber-500/30 text-amber-600 dark:text-amber-400 text-lg group-hover:scale-110 transition-all shrink-0">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 dark:text-white group-hover:text-amber-500 dark:group-hover:text-amber-400 transition-all text-xs mb-1">Pengaturan Timer Iqomah</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">Atur durasi hitung mundur waktu Iqomah sholat 5 waktu.</p>
                    </div>
                </a>

                <!-- KARTU 3: KELOLA KAS -->
                <a href="kas.php" class="bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-blue-500/50 transition-all duration-300 shadow-xl group flex items-start gap-3.5">
                    <div class="bg-blue-100 dark:bg-blue-950 p-3 rounded-xl border border-blue-300 dark:border-blue-500/30 text-blue-600 dark:text-blue-400 text-lg group-hover:scale-110 transition-all shrink-0">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 dark:text-white group-hover:text-blue-500 dark:group-hover:text-blue-400 transition-all text-xs mb-1">Kelola Kas Musholla</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">Catat laporan keuangan pemasukan & pengeluaran kas.</p>
                    </div>
                </a>

                <!-- KARTU 4: PETUGAS JUMAT -->
                <a href="petugas_jumat.php" class="bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-indigo-500/50 transition-all duration-300 shadow-xl group flex items-start gap-3.5">
                    <div class="bg-indigo-100 dark:bg-indigo-950 p-3 rounded-xl border border-indigo-300 dark:border-indigo-500/30 text-indigo-600 dark:text-indigo-400 text-lg group-hover:scale-110 transition-all shrink-0">
                        <i class="fa-solid fa-user-group"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 dark:text-white group-hover:text-indigo-500 dark:group-hover:text-indigo-400 transition-all text-xs mb-1">Kelola Petugas Sholat Jumat</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">Atur jadwal Khatib, Imam, dan Muadzin Sholat Jumat.</p>
                    </div>
                </a>

                <!-- KARTU 5: PENGATURAN AKUN -->
                <a href="profil.php" class="bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-pink-500/50 transition-all duration-300 shadow-xl group flex items-start gap-3.5">
                    <div class="bg-pink-100 dark:bg-pink-950 p-3 rounded-xl border border-pink-300 dark:border-pink-500/30 text-pink-600 dark:text-pink-400 text-lg group-hover:scale-110 transition-all shrink-0">
                        <i class="fa-solid fa-user-gear"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 dark:text-white group-hover:text-pink-500 dark:group-hover:text-pink-400 transition-all text-xs mb-1">Pengaturan Akun</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">Ubah username dan password login Super Admin.</p>
                    </div>
                </a>
            </div>

            <!-- KOLOM KANAN: FORM RUNNING TEXT (SEBELAH MENU) -->
            <div class="lg:col-span-2 bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl h-fit transition-colors duration-300">
                <h2 class="text-sm font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider mb-4 border-b border-slate-200 dark:border-slate-800 pb-2 flex items-center gap-2">
                    <i class="fa-solid fa-bullhorn text-amber-400"></i> Kelola Running Text Display TV
                </h2>
                <form action="" method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm text-slate-500 dark:text-slate-400 mb-2">Teks Pengumuman Berjalan (Marquee):</label>
                        <textarea name="running_text" rows="3" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl p-3 text-slate-800 dark:text-white outline-none focus:border-emerald-500" required><?= htmlspecialchars($current_running, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    <div class="mt-4">
                        <button type="submit" name="simpan_teks" class="bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold py-2.5 px-5 rounded-xl flex items-center gap-2">
                            <i class="fa-solid fa-floppy-disk"></i> Simpan Running Text
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>

</body>
</html>