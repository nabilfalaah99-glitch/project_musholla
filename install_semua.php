<?php
session_start();

$remote_address = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remote_address, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Installer hanya dapat digunakan dari browser lokal.');
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

if (empty($_SESSION['install_semua_token'])) {
    $_SESSION['install_semua_token'] = bin2hex(random_bytes(32));
}

$message = '';
$success = false;
$installer_lock = null;

function installer_result($message, $success)
{
    $color = $success ? '#16a34a' : '#dc2626';
    $safe_message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Installer Musholla</title><body style="font-family:Arial,sans-serif;background:#0f172a;color:#e2e8f0;padding:32px">';
    echo '<main style="max-width:720px;margin:5vh auto;background:#1e293b;border-radius:16px;padding:28px">';
    echo '<h1 style="color:' . $color . '">' . ($success ? 'Pemasangan Berhasil' : 'Pemasangan Gagal') . '</h1>';
    echo '<p>' . nl2br($safe_message) . '</p>';
    echo '</main></body></html>';
}

function installer_fail($message, $lock = null)
{
    if (is_resource($lock)) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    installer_result($message, false);
    exit;
}

function installer_ensure_column($connection, $table, $column, $definition)
{
    $allowed_tables = [
        'users', 'keuangan', 'keuangan_pekanan', 'jadwal_jumat', 'galeri',
        'app_timer_iqomah', 'app_pengaturan', 'app_hari_besar',
    ];
    if (!in_array($table, $allowed_tables, true)) {
        return false;
    }

    $result = mysqli_query($connection, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    if (!$result) {
        return false;
    }
    if (mysqli_num_rows($result) > 0) {
        return true;
    }
    return mysqli_query($connection, "ALTER TABLE `$table` ADD COLUMN `$column` $definition") !== false;
}

$lock_path = __DIR__ . DIRECTORY_SEPARATOR . '.install_semua.lock';
$installer_lock = fopen($lock_path, 'c+');
if (!$installer_lock || !flock($installer_lock, LOCK_EX | LOCK_NB)) {
    installer_fail('Installer sedang berjalan di tab/browser lain. Tunggu hingga selesai lalu muat ulang halaman.', $installer_lock);
}

rewind($installer_lock);
$lock_state = stream_get_contents($installer_lock);
if (is_string($lock_state) && strpos($lock_state, 'installed:') === 0) {
    installer_fail('Installer sudah pernah berhasil dijalankan. Hapus file .install_semua.lock hanya jika Anda memang ingin mengulang proses pemeriksaan/seed.', $installer_lock);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = (string)($_POST['token'] ?? '');
    if (!hash_equals($_SESSION['install_semua_token'], $posted_token)) {
        installer_fail('Token formulir tidak valid. Muat ulang halaman dan coba kembali.', $installer_lock);
    }

    require_once __DIR__ . '/config/koneksi.php';
    if (!isset($koneksi) || !($koneksi instanceof mysqli)) {
        installer_fail('Koneksi database tidak tersedia.', $installer_lock);
    }

    $database_result = mysqli_query($koneksi, 'SELECT DATABASE() AS database_name LIMIT 1');
    if (!$database_result) {
        installer_fail('Tidak dapat memeriksa database: ' . mysqli_error($koneksi), $installer_lock);
    }
    $database_row = mysqli_fetch_assoc($database_result);
    if (!$database_row || $database_row['database_name'] !== 'db_musholla') {
        installer_fail('Installer hanya diizinkan untuk database db_musholla.', $installer_lock);
    }

    $table_definitions = [
        "CREATE TABLE IF NOT EXISTS `users` (
            `id` int NOT NULL AUTO_INCREMENT,
            `username` varchar(50) NOT NULL,
            `password` varchar(255) NOT NULL,
            `nama` varchar(100) DEFAULT NULL,
            `nama_lengkap` varchar(100) NOT NULL DEFAULT '',
            `email` varchar(190) DEFAULT NULL,
            `role` varchar(30) NOT NULL DEFAULT 'admin',
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_users_username` (`username`),
            UNIQUE KEY `uq_users_email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `keuangan` (
            `id` int NOT NULL AUTO_INCREMENT,
            `tanggal` date NOT NULL,
            `keterangan` varchar(255) NOT NULL,
            `jenis` enum('Pemasukan','Pengeluaran') NOT NULL,
            `nominal` decimal(15,2) NOT NULL DEFAULT 0.00,
            `pekan_ke` tinyint unsigned DEFAULT NULL,
            `bulan` varchar(30) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_keuangan_tanggal` (`tanggal`),
            KEY `idx_keuangan_jenis` (`jenis`),
            KEY `idx_keuangan_pekan_ke` (`pekan_ke`),
            KEY `idx_keuangan_bulan` (`bulan`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `keuangan_pekanan` (
            `id` int NOT NULL AUTO_INCREMENT,
            `tanggal` date NOT NULL,
            `keterangan` varchar(255) NOT NULL,
            `jenis` enum('Pemasukan','Pengeluaran') NOT NULL,
            `nominal` decimal(15,2) NOT NULL DEFAULT 0.00,
            `pekan_ke` tinyint unsigned DEFAULT NULL,
            `bulan` varchar(30) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_keuangan_pekanan_tanggal` (`tanggal`),
            KEY `idx_keuangan_pekanan_jenis` (`jenis`),
            KEY `idx_keuangan_pekanan_pekan` (`pekan_ke`),
            KEY `idx_keuangan_pekanan_bulan` (`bulan`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `jadwal_jumat` (
            `id` int NOT NULL AUTO_INCREMENT,
            `tanggal` date NOT NULL,
            `khatib` varchar(100) NOT NULL,
            `imam` varchar(100) NOT NULL,
            `muadzin` varchar(100) NOT NULL,
            `status_tayang` tinyint(1) NOT NULL DEFAULT 0,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_jadwal_jumat_tanggal` (`tanggal`),
            KEY `idx_jadwal_jumat_status_tayang` (`status_tayang`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `galeri` (
            `id` int NOT NULL AUTO_INCREMENT,
            `file` varchar(255) DEFAULT NULL,
            `nama_file` varchar(255) DEFAULT NULL,
            `tipe` varchar(20) NOT NULL DEFAULT 'gambar',
            `caption` varchar(255) DEFAULT NULL,
            `keterangan` varchar(255) DEFAULT NULL,
            `tanggal` date DEFAULT NULL,
            `tanggal_acara` date DEFAULT NULL,
            `jam_mulai` time DEFAULT NULL,
            `jam_selesai` time DEFAULT NULL,
            `status` varchar(20) NOT NULL DEFAULT 'aktif',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_galeri_aktif` (`status`, `tipe`),
            KEY `idx_galeri_acara` (`tanggal_acara`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `app_timer_iqomah` (
            `id` int NOT NULL,
            `subuh` int NOT NULL DEFAULT 12,
            `dzuhur` int NOT NULL DEFAULT 10,
            `ashar` int NOT NULL DEFAULT 10,
            `maghrib` int NOT NULL DEFAULT 7,
            `isya` int NOT NULL DEFAULT 10,
            `sound_file` varchar(255) DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `app_pengaturan` (
            `id` int NOT NULL,
            `running_text` text NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `app_hari_besar` (
            `id` int NOT NULL AUTO_INCREMENT,
            `nama_event` varchar(150) NOT NULL,
            `tanggal_masehi` date NOT NULL,
            `tahun_hijriah` varchar(30) NOT NULL DEFAULT '',
            PRIMARY KEY (`id`),
            KEY `idx_app_hari_besar_tanggal` (`tanggal_masehi`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `pengaturan` (
            `id` int NOT NULL AUTO_INCREMENT,
            `nama_setting` varchar(50) NOT NULL,
            `nilai_setting` text NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_pengaturan_nama_setting` (`nama_setting`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($table_definitions as $definition) {
        if (!mysqli_query($koneksi, $definition)) {
            installer_fail('Gagal membuat tabel: ' . mysqli_error($koneksi), $installer_lock);
        }
    }

    $required_columns = [
        ['users', 'username', "varchar(50) NOT NULL DEFAULT ''"],
        ['users', 'password', "varchar(255) NOT NULL DEFAULT ''"],
        ['users', 'nama', 'varchar(100) DEFAULT NULL'],
        ['users', 'nama_lengkap', "varchar(100) NOT NULL DEFAULT ''"],
        ['users', 'email', 'varchar(190) DEFAULT NULL'],
        ['users', 'role', "varchar(30) NOT NULL DEFAULT 'admin'"],
        ['keuangan', 'tanggal', 'date DEFAULT NULL'],
        ['keuangan', 'keterangan', "varchar(255) NOT NULL DEFAULT ''"],
        ['keuangan', 'jenis', "varchar(20) NOT NULL DEFAULT 'Pemasukan'"],
        ['keuangan', 'nominal', 'decimal(15,2) NOT NULL DEFAULT 0.00'],
        ['keuangan', 'pekan_ke', 'tinyint unsigned DEFAULT NULL'],
        ['keuangan', 'bulan', 'varchar(30) DEFAULT NULL'],
        ['keuangan_pekanan', 'tanggal', 'date DEFAULT NULL'],
        ['keuangan_pekanan', 'keterangan', "varchar(255) NOT NULL DEFAULT ''"],
        ['keuangan_pekanan', 'jenis', "varchar(20) NOT NULL DEFAULT 'Pemasukan'"],
        ['keuangan_pekanan', 'nominal', 'decimal(15,2) NOT NULL DEFAULT 0.00'],
        ['keuangan_pekanan', 'pekan_ke', 'tinyint unsigned DEFAULT NULL'],
        ['keuangan_pekanan', 'bulan', 'varchar(30) DEFAULT NULL'],
        ['jadwal_jumat', 'tanggal', "date NOT NULL DEFAULT '2000-01-01'"],
        ['jadwal_jumat', 'khatib', "varchar(100) NOT NULL DEFAULT ''"],
        ['jadwal_jumat', 'imam', "varchar(100) NOT NULL DEFAULT ''"],
        ['jadwal_jumat', 'muadzin', "varchar(100) NOT NULL DEFAULT ''"],
        ['jadwal_jumat', 'status_tayang', "varchar(1) NOT NULL DEFAULT '0'"],
        ['galeri', 'file', 'varchar(255) DEFAULT NULL'],
        ['galeri', 'nama_file', 'varchar(255) DEFAULT NULL'],
        ['galeri', 'tipe', "varchar(20) NOT NULL DEFAULT 'gambar'"],
        ['galeri', 'caption', 'varchar(255) DEFAULT NULL'],
        ['galeri', 'keterangan', 'varchar(255) DEFAULT NULL'],
        ['galeri', 'tanggal', 'date DEFAULT NULL'],
        ['galeri', 'tanggal_acara', 'date DEFAULT NULL'],
        ['galeri', 'jam_mulai', 'time DEFAULT NULL'],
        ['galeri', 'jam_selesai', 'time DEFAULT NULL'],
        ['galeri', 'status', "varchar(20) NOT NULL DEFAULT 'aktif'"],
        ['app_timer_iqomah', 'subuh', 'int NOT NULL DEFAULT 12'],
        ['app_timer_iqomah', 'dzuhur', 'int NOT NULL DEFAULT 10'],
        ['app_timer_iqomah', 'ashar', 'int NOT NULL DEFAULT 10'],
        ['app_timer_iqomah', 'maghrib', 'int NOT NULL DEFAULT 7'],
        ['app_timer_iqomah', 'isya', 'int NOT NULL DEFAULT 10'],
        ['app_timer_iqomah', 'sound_file', 'varchar(255) DEFAULT NULL'],
        ['app_pengaturan', 'running_text', "text NOT NULL"],
        ['app_hari_besar', 'id', 'int NOT NULL DEFAULT 0'],
        ['app_hari_besar', 'nama_event', "varchar(150) NOT NULL DEFAULT ''"],
        ['app_hari_besar', 'tanggal_masehi', 'date DEFAULT NULL'],
        ['app_hari_besar', 'tahun_hijriah', "varchar(30) NOT NULL DEFAULT ''"],
    ];

    foreach ($required_columns as [$table, $column, $definition]) {
        if (!installer_ensure_column($koneksi, $table, $column, $definition)) {
            installer_fail("Gagal menyiapkan kolom {$table}.{$column}: " . mysqli_error($koneksi), $installer_lock);
        }
    }

    if (
        !mysqli_query(
            $koneksi,
            "UPDATE `jadwal_jumat`
             SET `status_tayang` = CASE WHEN CAST(`status_tayang` AS CHAR) IN ('Y', '1') THEN '1' ELSE '0' END"
        )
        || !mysqli_query($koneksi, "ALTER TABLE `jadwal_jumat` MODIFY `status_tayang` tinyint(1) NOT NULL DEFAULT 0")
    ) {
        installer_fail('Gagal menyelaraskan status_tayang ke 0/1: ' . mysqli_error($koneksi), $installer_lock);
    }

    $default_running_text = 'Selamat Datang di Musholla Al-Muhajirin Menara Samawa Jakarta Timur.';
    $seed_date = date('Y-m-d', strtotime('next Friday'));
    $admin_password_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $seed_operations = [
        [
            'SELECT `id` FROM `users` LIMIT 1',
            'INSERT INTO `users` (`username`, `password`, `nama`, `nama_lengkap`, `email`, `role`) VALUES (?, ?, ?, ?, ?, ?)',
            'ssssss',
            ['admin', $admin_password_hash, 'Superadmin', 'Superadmin', 'admin@musholla.local', 'admin'],
        ],
        [
            'SELECT `id` FROM `keuangan` LIMIT 1',
            'INSERT INTO `keuangan` (`tanggal`, `keterangan`, `jenis`, `nominal`, `pekan_ke`, `bulan`) VALUES (CURDATE(), ?, ?, 0, ?, ?)',
            'ssis',
            ['Saldo awal', 'Pemasukan', (int)ceil((int)date('j') / 7), date('F')],
        ],
        [
            'SELECT `id` FROM `jadwal_jumat` LIMIT 1',
            'INSERT INTO `jadwal_jumat` (`tanggal`, `khatib`, `imam`, `muadzin`, `status_tayang`) VALUES (?, ?, ?, ?, 1)',
            'ssss',
            [$seed_date, 'Ust. Ahmad', 'Ust. Ahmad Ridwan', 'Bilal Hasby'],
        ],
        [
            'SELECT `id` FROM `app_timer_iqomah` LIMIT 1',
            'INSERT INTO `app_timer_iqomah` (`id`, `subuh`, `dzuhur`, `ashar`, `maghrib`, `isya`) VALUES (1, 12, 10, 10, 7, 10)',
            '',
            [],
        ],
        [
            'SELECT `id` FROM `app_pengaturan` LIMIT 1',
            'INSERT INTO `app_pengaturan` (`id`, `running_text`) VALUES (1, ?)',
            's',
            [$default_running_text],
        ],
        [
            "SELECT `id` FROM `pengaturan` WHERE `nama_setting`='running_text' LIMIT 1",
            'INSERT INTO `pengaturan` (`nama_setting`, `nilai_setting`) VALUES (?, ?)',
            'ss',
            ['running_text', $default_running_text],
        ],
    ];

    foreach ($seed_operations as [$check_sql, $insert_sql, $types, $values]) {
        $check_result = mysqli_query($koneksi, $check_sql);
        if (!$check_result) {
            installer_fail('Gagal memeriksa data bawaan: ' . mysqli_error($koneksi), $installer_lock);
        }
        if (mysqli_num_rows($check_result) > 0) {
            continue;
        }

        $statement = mysqli_prepare($koneksi, $insert_sql);
        if (!$statement) {
            installer_fail('Gagal menyiapkan data bawaan: ' . mysqli_error($koneksi), $installer_lock);
        }
        if ($types !== '') {
            $bind_arguments = [$statement, $types];
            foreach ($values as &$value) {
                $bind_arguments[] = &$value;
            }
            unset($value);
            if (!call_user_func_array('mysqli_stmt_bind_param', $bind_arguments)) {
                $error = mysqli_stmt_error($statement);
                mysqli_stmt_close($statement);
                installer_fail('Gagal mengikat data bawaan: ' . $error, $installer_lock);
            }
        }
        if (!mysqli_stmt_execute($statement)) {
            $error = mysqli_stmt_error($statement);
            mysqli_stmt_close($statement);
            installer_fail('Gagal memasukkan data bawaan: ' . $error, $installer_lock);
        }
        mysqli_stmt_close($statement);
    }

    foreach (['galeri_app', 'galeri_media'] as $legacy_gallery_table) {
        $legacy_table_result = mysqli_query(
            $koneksi,
            "SELECT `TABLE_NAME` FROM `information_schema`.`TABLES`
             WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = '" . mysqli_real_escape_string($koneksi, $legacy_gallery_table) . "'
             LIMIT 1"
        );
        if (!$legacy_table_result) {
            installer_fail('Gagal memeriksa tabel galeri lama: ' . mysqli_error($koneksi), $installer_lock);
        }
        if (mysqli_num_rows($legacy_table_result) === 0) {
            continue;
        }

        $migration_result = mysqli_query(
            $koneksi,
            "INSERT INTO `galeri` (`file`, `nama_file`, `tipe`, `caption`, `keterangan`, `tanggal`, `tanggal_acara`, `jam_mulai`, `jam_selesai`, `status`)
             SELECT `nama_file`, `nama_file`, `tipe`, `keterangan`, `keterangan`, `tanggal`, `tanggal`, `jam_mulai`, `jam_selesai`, `status`
             FROM `" . $legacy_gallery_table . "` AS legacy
             WHERE NOT EXISTS (
                 SELECT 1 FROM `galeri` AS current_gallery
                 WHERE current_gallery.`file` = legacy.`nama_file`
             )"
        );
        if (!$migration_result) {
            installer_fail('Gagal menyalin media dari ' . $legacy_gallery_table . ': ' . mysqli_error($koneksi), $installer_lock);
        }
    }

    rewind($installer_lock);
    ftruncate($installer_lock, 0);
    fwrite($installer_lock, 'installed:' . date(DATE_ATOM));
    fflush($installer_lock);
    $success = true;
    $message = "Tabel dan data bawaan berhasil diperiksa/dipasang.\nLogin awal Superadmin: admin / admin123.\nSegera ubah password awal setelah masuk.";
    flock($installer_lock, LOCK_UN);
    fclose($installer_lock);
    unset($_SESSION['install_semua_token']);
} elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    installer_fail('Metode request tidak didukung.', $installer_lock);
} else {
    flock($installer_lock, LOCK_UN);
    fclose($installer_lock);
    $installer_lock = null;
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install Musholla</title>
    <style>
        body { font-family: Arial, sans-serif; background: #0f172a; color: #e2e8f0; padding: 32px; }
        main { max-width: 720px; margin: 5vh auto; background: #1e293b; border-radius: 16px; padding: 28px; }
        button { border: 0; border-radius: 8px; background: #059669; color: #fff; padding: 12px 20px; font-weight: bold; cursor: pointer; }
        .warning { color: #fbbf24; }
    </style>
</head>
<body>
<main>
    <?php if ($success): ?>
        <h1 style="color:#22c55e">Pemasangan Berhasil</h1>
        <p><?= nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')); ?></p>
        <p>Installer telah dikunci agar tidak dijalankan berulang kali. Hapus atau pindahkan file <code>install_semua.php</code> sekarang.</p>
    <?php else: ?>
        <h1>Installer Database Musholla</h1>
        <p class="warning">Installer ini membuat tabel yang belum ada dan mengisi seed hanya jika tabel terkait kosong. Tidak ada tabel yang di-DROP.</p>
        <p>Akun awal jika belum ada: <strong>admin / admin123</strong>. Pastikan ini lingkungan lokal dan segera ganti password setelah login.</p>
        <form method="post">
            <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['install_semua_token'], ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit">Pasang / Periksa Database</button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
