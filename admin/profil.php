<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';

$id_admin = (int)($_SESSION['user_id'] ?? 1);

$q_admin = mysqli_query($koneksi, "SELECT id, username, password FROM users WHERE id = $id_admin LIMIT 1");
$data_admin = $q_admin ? mysqli_fetch_assoc($q_admin) : false;

if (isset($_POST['update_akun'])) {
    $username_baru = mysqli_real_escape_string($koneksi, $_POST['username']);
    $pass_lama     = $_POST['pass_lama'];
    $pass_baru     = $_POST['pass_baru'];
    $konfirmasi    = $_POST['konfirmasi_pass'];

    // Cek apakah password lama diisi dan cocok
    if (!$data_admin) {
        header("Location: profil.php?status=db_error");
        exit;
    }

    if (!empty($pass_lama) || !empty($pass_baru)) {
        if (!password_verify($pass_lama, $data_admin['password'])) {
            header("Location: profil.php?status=pass_salah");
            exit;
        }
        if ($pass_baru !== $konfirmasi) {
            header("Location: profil.php?status=pass_beda");
            exit;
        }
        $password_hash = password_hash($pass_baru, PASSWORD_DEFAULT);
        $saved = mysqli_query($koneksi, "UPDATE users SET username='$username_baru', password='$password_hash' WHERE id=$id_admin");
    } else {
        $saved = mysqli_query($koneksi, "UPDATE users SET username='$username_baru' WHERE id=$id_admin");
    }
    header("Location: profil.php?status=" . ($saved ? 'sukses' : 'db_error'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Akun - Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Konfigurasi Tailwind untuk Dark Mode -->
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>

    <!-- Script Logika Tema (Ditaruh di head biar layar tidak kedip) -->
    <script>
        function applyTheme(theme, save = true) {
            let isDark = false;
            
            if (theme === 'auto') {
                const hour = new Date().getHours();
                // Jam 06.00 sampai 17.59 = Terang, sisanya = Gelap
                isDark = (hour < 6 || hour >= 18);
            } else if (theme === 'dark') {
                isDark = true;
            }
            
            if (isDark) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
            
            if (save) {
                localStorage.setItem('admin_theme', theme);
            }
        }

        // Inisialisasi tema saat halaman pertama dimuat
        const savedTheme = localStorage.getItem('admin_theme') || 'auto';
        applyTheme(savedTheme, false);
    </script>

    <style> 
        body { font-family: 'Poppins', sans-serif; } 
    </style>
</head>
<body class="p-6 min-h-screen bg-slate-50 dark:bg-[#050b14] transition-colors duration-300">

    <div class="max-w-3xl mx-auto">
        <!-- HEADER -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <a href="index.php" class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold uppercase tracking-wider flex items-center gap-2 mb-1 hover:underline">
                    <i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard
                </a>
                <h1 class="text-2xl font-black text-slate-800 dark:text-white uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-user-gear text-pink-500 dark:text-pink-400"></i> Pengaturan Akun
                </h1>
            </div>
        </div>

        <!-- NOTIFIKASI -->
        <?php if (isset($_GET['status'])): ?>
            <?php if ($_GET['status'] == 'sukses'): ?>
                <div class="mb-6 bg-emerald-100 dark:bg-emerald-950/80 border border-emerald-400 dark:border-emerald-500/50 text-emerald-700 dark:text-emerald-300 p-4 rounded-xl text-xs font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-circle-check text-base"></i> Profil dan Akun berhasil diperbarui!
                </div>
            <?php elseif ($_GET['status'] == 'pass_salah'): ?>
                <div class="mb-6 bg-rose-100 dark:bg-rose-950/80 border border-rose-400 dark:border-rose-500/50 text-rose-700 dark:text-rose-300 p-4 rounded-xl text-xs font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-circle-xmark text-base"></i> Gagal! Password lama yang Anda masukkan salah.
                </div>
            <?php elseif ($_GET['status'] == 'pass_beda'): ?>
                <div class="mb-6 bg-amber-100 dark:bg-amber-950/80 border border-amber-400 dark:border-amber-500/50 text-amber-700 dark:text-amber-300 p-4 rounded-xl text-xs font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-base"></i> Gagal! Konfirmasi password baru tidak sama.
                </div>
            <?php elseif ($_GET['status'] == 'db_error'): ?>
                <div class="mb-6 bg-rose-100 dark:bg-rose-950/80 border border-rose-400 dark:border-rose-500/50 text-rose-700 dark:text-rose-300 p-4 rounded-xl text-xs font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-circle-xmark text-base"></i> Data akun gagal dimuat atau disimpan. Periksa koneksi dan tabel users.
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- KOTAK PENGATURAN TEMA (LIGHTNING) -->
        <div class="bg-white dark:bg-slate-900/90 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl mb-6 transition-colors duration-300">
            <h3 class="text-sm font-bold text-slate-800 dark:text-white mb-4 flex items-center gap-2">
                <i class="fa-solid fa-palette text-indigo-500"></i> Pengaturan Tema (Lightning)
            </h3>
            <div class="flex flex-wrap gap-3">
                <label class="cursor-pointer">
                    <input type="radio" name="theme_selector" value="light" class="peer sr-only" onchange="applyTheme('light')" id="theme-light">
                    <div class="p-3 px-4 rounded-xl border border-slate-300 dark:border-slate-700 peer-checked:border-indigo-500 peer-checked:bg-indigo-50 dark:peer-checked:bg-indigo-950/30 flex items-center gap-2 text-xs font-semibold text-slate-600 dark:text-slate-400 peer-checked:text-indigo-600 dark:peer-checked:text-indigo-400 transition-all">
                        <i class="fa-solid fa-sun text-amber-500"></i> Terang
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="theme_selector" value="dark" class="peer sr-only" onchange="applyTheme('dark')" id="theme-dark">
                    <div class="p-3 px-4 rounded-xl border border-slate-300 dark:border-slate-700 peer-checked:border-indigo-500 peer-checked:bg-indigo-50 dark:peer-checked:bg-indigo-950/30 flex items-center gap-2 text-xs font-semibold text-slate-600 dark:text-slate-400 peer-checked:text-indigo-600 dark:peer-checked:text-indigo-400 transition-all">
                        <i class="fa-solid fa-moon text-indigo-400"></i> Gelap
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="theme_selector" value="auto" class="peer sr-only" onchange="applyTheme('auto')" id="theme-auto">
                    <div class="p-3 px-4 rounded-xl border border-slate-300 dark:border-slate-700 peer-checked:border-indigo-500 peer-checked:bg-indigo-50 dark:peer-checked:bg-indigo-950/30 flex items-center gap-2 text-xs font-semibold text-slate-600 dark:text-slate-400 peer-checked:text-indigo-600 dark:peer-checked:text-indigo-400 transition-all">
                        <i class="fa-solid fa-clock-rotate-left text-emerald-500"></i> Mengikuti Waktu
                    </div>
                </label>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-3">
                <i class="fa-solid fa-info-circle"></i> Opsi 'Mengikuti Waktu' akan otomatis terang di siang hari (06:00 - 18:00) dan gelap di malam hari.
            </p>
        </div>

        <!-- FORM UPDATE PROFIL -->
        <div class="bg-white dark:bg-slate-900/90 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl transition-colors duration-300">
            <h3 class="text-sm font-bold text-slate-800 dark:text-white mb-4 flex items-center gap-2">
                <i class="fa-solid fa-shield-halved text-pink-500"></i> Kredensial Login
            </h3>
            <form action="profil.php" method="POST" class="space-y-5 text-sm">
                
                <div>
                    <label class="block text-xs text-slate-600 dark:text-slate-400 mb-2 font-medium">Username Login</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-user text-slate-400 dark:text-slate-500"></i>
                        </div>
                        <input type="text" name="username" value="<?= htmlspecialchars($data_admin['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 pl-10 pr-3 text-slate-800 dark:text-white focus:border-pink-500 outline-none transition-colors">
                    </div>
                </div>

                <hr class="border-slate-200 dark:border-slate-800 my-4">
                
                <p class="text-[11px] text-amber-600 dark:text-amber-400 mb-2"><i class="fa-solid fa-circle-info"></i> Kosongkan kolom password di bawah ini jika hanya ingin mengubah username.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-slate-600 dark:text-slate-400 mb-2 font-medium">Password Lama</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fa-solid fa-lock text-slate-400 dark:text-slate-500"></i>
                            </div>
                            <input type="password" name="pass_lama" placeholder="Ketik password lama..." class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 pl-10 pr-3 text-slate-800 dark:text-white focus:border-pink-500 outline-none transition-colors">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-slate-600 dark:text-slate-400 mb-2 font-medium">Password Baru</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fa-solid fa-key text-emerald-500"></i>
                            </div>
                            <input type="password" name="pass_baru" placeholder="Password baru..." class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 pl-10 pr-3 text-slate-800 dark:text-white focus:border-pink-500 outline-none transition-colors">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-600 dark:text-slate-400 mb-2 font-medium">Konfirmasi Password Baru</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fa-solid fa-check-double text-emerald-500"></i>
                            </div>
                            <input type="password" name="konfirmasi_pass" placeholder="Ulangi password baru..." class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 pl-10 pr-3 text-slate-800 dark:text-white focus:border-pink-500 outline-none transition-colors">
                        </div>
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" name="update_akun" class="w-full md:w-auto bg-pink-600 hover:bg-pink-500 text-white text-xs font-bold py-3 px-6 rounded-xl transition-all shadow-lg flex items-center justify-center gap-2">
                        <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan Akun
                    </button>
                </div>
            </form>
        </div>

    </div>

    <!-- Script untuk set status checked pada radio button sesuai penyimpanan -->
    <script>
        const activeTheme = localStorage.getItem('admin_theme') || 'auto';
        document.getElementById('theme-' + activeTheme).checked = true;
    </script>
</body>
</html>