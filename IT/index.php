<?php
require_once __DIR__ . '/../config/koneksi.php';

$pesan = "";

// FITUR RESET PASS INSTAN
if (isset($_POST['reset_pass'])) {
    $user_id  = (int)$_POST['user_id'];
    $new_pass = (string)($_POST['new_password'] ?? '');
    
    if ($koneksi && !empty($new_pass)) {
        $password_hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $statement = mysqli_prepare($koneksi, 'UPDATE users SET password = ? WHERE id = ?');
        $q_reset = false;
        if ($statement) {
            if (mysqli_stmt_bind_param($statement, 'si', $password_hash, $user_id)) {
                $q_reset = mysqli_stmt_execute($statement);
            }
            mysqli_stmt_close($statement);
        }
        if ($q_reset) {
            $pesan = "✅ Password akun ID #$user_id berhasil diubah.";
        } else {
            $pesan = "❌ Gagal mereset password. Periksa koneksi dan tabel users.";
        }
    } else {
        $pesan = "❌ Password baru tidak boleh kosong.";
    }
}

// FITUR TAMBAH USER BARU
if (isset($_POST['tambah_user'])) {
    $username_baru = trim((string)($_POST['username'] ?? ''));
    $email_baru    = trim((string)($_POST['email'] ?? ''));
    $pass_baru     = (string)($_POST['password'] ?? '');
    $role_baru     = (string)($_POST['role'] ?? '');
    $nama_baru     = trim((string)($_POST['nama_lengkap'] ?? ''));

    if ($koneksi && $username_baru !== '' && $email_baru !== '' && $pass_baru !== '' && in_array($role_baru, ['admin', 'super_it'], true)) {
        $password_hash = password_hash($pass_baru, PASSWORD_DEFAULT);
        $statement = mysqli_prepare(
            $koneksi,
            'INSERT INTO users (username, email, password, nama_lengkap, role) VALUES (?, ?, ?, ?, ?)'
        );
        $q_add = false;
        if ($statement) {
            if (mysqli_stmt_bind_param($statement, 'sssss', $username_baru, $email_baru, $password_hash, $nama_baru, $role_baru)) {
                $q_add = mysqli_stmt_execute($statement);
            }
            $database_error = mysqli_stmt_error($statement);
            mysqli_stmt_close($statement);
        } else {
            $database_error = mysqli_error($koneksi);
        }
        if ($q_add) {
            $pesan = "✅ Akun baru '$username_baru' berhasil ditambahkan!";
        } else {
            $pesan = "❌ Gagal menambah user: " . htmlspecialchars($database_error, ENT_QUOTES, 'UTF-8');
        }
    } else {
        $pesan = "❌ Data akun tidak valid atau koneksi database tidak tersedia.";
    }
}

// FITUR HAPUS USER
if (isset($_POST['hapus_user'])) {
    $user_id = (int)$_POST['user_id'];
    if ($koneksi) {
        $q_del = mysqli_query($koneksi, "DELETE FROM users WHERE id = $user_id");
        if ($q_del) {
            $pesan = "🗑️ Akun ID #$user_id berhasil dihapus!";
        } else {
            $pesan = "❌ Gagal menghapus akun. Periksa koneksi dan tabel users.";
        }
    }
}

// AMBIL DATA USER DAN TABEL DATABASE
$list_users  = [];
$list_tables = [];
$users_query_failed = false;

if ($koneksi) {
    // Ambil daftar semua user
    $q_u = mysqli_query($koneksi, "SELECT id, username, nama_lengkap, email, role FROM users ORDER BY id ASC LIMIT 100");
    if ($q_u) {
        while ($r = mysqli_fetch_assoc($q_u)) {
            $list_users[] = $r;
        }
    } else {
        $users_query_failed = true;
    }

    // Ambil daftar tabel
    $q_t = mysqli_query(
        $koneksi,
        "SELECT TABLE_NAME FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
         ORDER BY TABLE_NAME
         LIMIT 100"
    );
    if ($q_t) {
        while ($rt = mysqli_fetch_assoc($q_t)) {
            $list_tables[] = $rt['TABLE_NAME'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SUPER IT CONTROL CENTER - Musholla Al-Muhajirin</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Fira Code', 'Segoe UI', monospace, sans-serif; }
        body { background:#090d16; color:#e2e8f0; padding:25px; }
        .header-it { display:flex; justify-content:space-between; align-items:center; background:#111827; padding:20px 25px; border-radius:12px; border:1px solid #1f2937; margin-bottom:25px; }
        .header-it h1 { font-size:1.4rem; color:#38bdf8; display:flex; align-items:center; gap:10px; }
        .badge-role { background:#0284c7; color:white; padding:4px 10px; border-radius:6px; font-size:0.75rem; font-weight:bold; }
        .nav-quick { display:flex; gap:10px; }
        .btn-quick { text-decoration:none; background:#1f2937; color:#9ca3af; padding:8px 14px; border-radius:8px; font-size:0.85rem; border:1px solid #374151; transition:0.2s; }
        .btn-quick:hover { background:#374151; color:white; }
        .alert { background:#065f46; color:#a7f3d0; padding:12px 20px; border-radius:8px; margin-bottom:25px; border:1px solid #059669; }
        
        .grid-layout { display:grid; grid-template-columns: 1.2fr 0.8fr; gap:25px; }
        @media(max-width:900px){ .grid-layout { grid-template-columns:1fr; } }

        .card { background:#111827; border:1px solid #1f2937; border-radius:12px; padding:20px; margin-bottom:25px; }
        .card h2 { font-size:1.1rem; color:#f3f4f6; margin-bottom:15px; border-bottom:1px solid #1f2937; padding-bottom:10px; display:flex; justify-content:space-between; align-items:center; }

        table { width:100%; border-collapse:collapse; margin-top:10px; font-size:0.85rem; }
        th, td { padding:10px; text-align:left; border-bottom:1px solid #1f2937; vertical-align:middle; }
        th { color:#9ca3af; background:#1f2937; }

        .form-inline { display:flex; gap:5px; }
        .input-sm { padding:6px; background:#090d16; border:1px solid #374151; color:white; border-radius:4px; font-size:0.8rem; width:110px; }
        .btn-action { background:#0284c7; color:white; border:none; padding:6px 12px; border-radius:4px; cursor:pointer; font-size:0.8rem; }
        .btn-action:hover { background:#0369a1; }
        .btn-danger { background:#ef4444; color:white; border:none; padding:6px 10px; border-radius:4px; cursor:pointer; font-size:0.8rem; }
        .btn-danger:hover { background:#dc2626; }

        .form-group { margin-bottom:12px; }
        .form-group label { display:block; font-size:0.8rem; color:#9ca3af; margin-bottom:4px; }
        .form-group input, .form-group select { width:100%; padding:8px; background:#090d16; border:1px solid #374151; color:white; border-radius:6px; font-size:0.85rem; }

        .status-dot { width:10px; height:10px; border-radius:50%; display:inline-block; margin-right:6px; }
        .online { background:#10b981; }
        .offline { background:#ef4444; }
        .tag-dkm { background:rgba(16, 185, 129, 0.2); color:#34d399; padding:3px 8px; border-radius:4px; font-size:0.75rem; border:1px solid #059669; }
        .tag-it { background:rgba(2, 132, 199, 0.2); color:#38bdf8; padding:3px 8px; border-radius:4px; font-size:0.75rem; border:1px solid #0284c7; }
    </style>
</head>
<body>

    <div class="header-it">
        <div>
            <h1>💻 SUPER IT CONTROL CENTER <span class="badge-role">DIRECT ACCESS</span></h1>
            <p style="font-size:0.85rem; color:#9ca3af; margin-top:4px;">Ruangan khusus Pengelola Server, Database, dan Kodingan (Drive E: Flashdisk)</p>
        </div>
        <div class="nav-quick">
            <a href="http://localhost/phpmyadmin/" target="_blank" class="btn-quick">🗄️ phpMyAdmin</a>
            <a href="../index.php" target="_blank" class="btn-quick">📺 TV Display</a>
            <a href="../admin/index.php" target="_blank" class="btn-quick">🔑 Panel Admin DKM</a>
        </div>
    </div>

    <?php if ($pesan): ?>
        <div class="alert"><?= $pesan; ?></div>
    <?php endif; ?>

    <div class="grid-layout">
        
        <!-- KIRI: DAFTAR AKUN YANG TERHUBUNG / TERDAFTAR -->
        <div>
            <div class="card">
                <h2>
                    <span>👥 Akun Admin DKM & IT Terdaftar</span>
                    <span style="font-size:0.8rem; color:#10b981;">Total: <?= count($list_users); ?> Akun</span>
                </h2>
                <table>
                    <thead>
                        <tr>
                            <th>User & Nama</th>
                            <th>Role & Akses</th>
                            <th>Reset Pass Instan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users_query_failed): ?>
                            <tr><td colspan="4" style="color:#ef4444;">Gagal memuat data akun dari tabel users. Periksa koneksi dan struktur database.</td></tr>
                        <?php elseif (empty($list_users)): ?>
                            <tr><td colspan="4" style="color:#ef4444;">Tabel users tidak ditemukan / kosong.</td></tr>
                        <?php else: ?>
                            <?php foreach ($list_users as $u): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($u['username']); ?></strong>
                                        <br>
                                        <small style="color:#9ca3af;"><?= htmlspecialchars($u['nama_lengkap'] ?? $u['email'] ?? '-'); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($u['role'] === 'super_it' || $u['username'] === 'superit'): ?>
                                            <span class="tag-it">Super IT</span>
                                        <?php else: ?>
                                            <span class="tag-dkm">Admin DKM</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" class="form-inline">
                                            <input type="hidden" name="user_id" value="<?= $u['id']; ?>">
                                            <input type="text" name="new_password" class="input-sm" placeholder="Pass baru" required>
                                            <button type="submit" name="reset_pass" class="btn-action">Set</button>
                                        </form>
                                    </td>
                                    <td>
                                        <?php if ($u['username'] !== 'superit'): ?>
                                            <form method="POST" onsubmit="return confirm('Hapus akun ini?');">
                                                <input type="hidden" name="user_id" value="<?= $u['id']; ?>">
                                                <button type="submit" name="hapus_user" class="btn-danger">Hapus</button>
                                            </form>
                                        <?php else: ?>
                                            <small style="color:#64748b;">Utama</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- KANAN: FORM TAMBAH USER & KESEHATAN SERVER -->
        <div>
            <div class="card">
                <h2>➕ Buat Akun Admin Baru</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" required placeholder="Contoh: dkm_muhajirin">
                    </div>
                    <div class="form-group">
                        <label>Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" placeholder="Pengurus DKM Harian">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" required placeholder="admin@musholla.id">
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div class="form-group">
                            <label>Password Initial</label>
                            <input type="text" name="password" required placeholder="Password">
                        </div>
                        <div class="form-group">
                            <label>Role</label>
                            <select name="role">
                                <option value="admin">Admin DKM Operasional</option>
                                <option value="super_it">Super IT</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="tambah_user" class="btn-action" style="width:100%; padding:10px; font-weight:bold; margin-top:5px;">Tambah Akun Baru</button>
                </form>
            </div>

            <div class="card">
                <h2>⚙️ Status Server XAMPP</h2>
                <p style="font-size:0.85rem; margin-bottom:8px;">
                    <span class="status-dot <?= $koneksi ? 'online' : 'offline'; ?>"></span> 
                    <strong>Database:</strong> <?= $koneksi ? 'Terhubung (db_musholla)' : 'Gagal Konek'; ?>
                </p>
                <p style="font-size:0.85rem; margin-bottom:8px;">📁 <strong>Root:</strong> <code>E:\xampp\htdocs\project_musholla\</code></p>
                <p style="font-size:0.85rem;">🐘 <strong>PHP Version:</strong> <?= phpversion(); ?></p>
            </div>
        </div>

    </div>

</body>
</html>