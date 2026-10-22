<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';

$error = "";

if (isset($_POST['login'])) {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $statement = mysqli_prepare($koneksi, 'SELECT id, username, password, role FROM users WHERE username=? LIMIT 1');
    $query = false;
    if ($statement) {
        if (
            mysqli_stmt_bind_param($statement, 's', $username)
            && mysqli_stmt_execute($statement)
        ) {
            $query = mysqli_stmt_get_result($statement);
        }
    }

    if ($query && mysqli_num_rows($query) > 0) {
        $data = mysqli_fetch_assoc($query);
        if ($data && password_verify($password, $data['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['user_id'] = (int)$data['id'];
            $_SESSION['username'] = $data['username'];
            $_SESSION['role'] = $data['role'];

            if ($statement) {
                mysqli_stmt_close($statement);
            }
            header("Location: index.php");
            exit;
        }
    }

    if ($statement) {
        mysqli_stmt_close($statement);
    }
    if (!$query) {
        $error = "Login tidak dapat diproses karena tabel akun atau koneksi database bermasalah.";
    } else {
        $error = "Username atau password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Musholla Al-Muhajirin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Poppins', sans-serif; }</style>
</head>
<body class="bg-slate-950 text-white min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md bg-slate-900 border border-slate-800 p-8 rounded-2xl shadow-2xl">
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-emerald-950 border border-emerald-500/30 rounded-2xl flex items-center justify-center text-emerald-400 text-2xl mx-auto mb-3 shadow-lg">
                <i class="fa-solid fa-user-shield"></i>
            </div>
            <h1 class="text-xl font-bold text-white uppercase tracking-wider">Login Admin DKM</h1>
            <p class="text-xs text-slate-400 mt-1">Musholla Al-Muhajirin</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="bg-rose-500/20 border border-rose-500 text-rose-300 p-3 rounded-xl mb-4 text-xs font-semibold text-center">
                <?= $error; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1.5">Username</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500 text-xs">
                        <i class="fa-solid fa-user"></i>
                    </span>
                    <input type="text" name="username" required placeholder="Masukkan username" class="w-full bg-slate-950 border border-slate-700 rounded-xl pl-9 pr-3 py-2.5 text-xs text-white focus:outline-none focus:border-emerald-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1.5">Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500 text-xs">
                        <i class="fa-solid fa-lock"></i>
                    </span>
                    <input type="password" name="password" required placeholder="Masukkan password" class="w-full bg-slate-950 border border-slate-700 rounded-xl pl-9 pr-3 py-2.5 text-xs text-white focus:outline-none focus:border-emerald-500">
                </div>
            </div>

            <button type="submit" name="login" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs py-3 rounded-xl transition shadow-lg shadow-emerald-900/30 mt-2">
                <i class="fa-solid fa-right-to-bracket mr-1"></i> Masuk Sistem
            </button>
        </form>

        <div class="text-center mt-6 pt-4 border-t border-slate-800">
            <a href="../index.php" class="text-xs text-slate-400 hover:text-emerald-400 transition flex items-center justify-center gap-1.5">
                <i class="fa-solid fa-arrow-left"></i> Kembali ke Display Utama
            </a>
        </div>
    </div>

</body>
</html>