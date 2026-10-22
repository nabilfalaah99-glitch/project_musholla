<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This destructive setup script can only be run from the command line.\n");
}

if (!in_array('--confirm-drop', $argv, true)) {
    fwrite(STDERR, "This will permanently drop and recreate application tables.\n");
    fwrite(STDERR, "Run: php setup_database.php --confirm-drop\n");
    exit(2);
}

require_once __DIR__ . '/config/koneksi.php';

if (!$koneksi || mysqli_get_host_info($koneksi) === '') {
    fwrite(STDERR, "Database connection is unavailable.\n");
    exit(1);
}

$database_result = mysqli_query($koneksi, 'SELECT DATABASE() AS database_name LIMIT 1');
if (!$database_result) {
    fwrite(STDERR, 'Could not verify active database: ' . mysqli_error($koneksi) . PHP_EOL);
    exit(1);
}

$database_row = mysqli_fetch_assoc($database_result);
if (!$database_row || $database_row['database_name'] !== 'db_musholla') {
    fwrite(STDERR, "Refusing to reset any database other than db_musholla.\n");
    exit(1);
}

$tables = [
    'admin',
    'app_hari_besar',
    'app_pengaturan',
    'app_timer_iqomah',
    'galeri',
    'galeri_app',
    'galeri_media',
    'jadwal_jumat',
    'keuangan',
    'keuangan_pekanan',
    'pengaturan',
    'users',
];

foreach ($tables as $table) {
    if (!mysqli_query($koneksi, "DROP TABLE IF EXISTS `$table`")) {
        fwrite(STDERR, "Failed to drop {$table}: " . mysqli_error($koneksi) . PHP_EOL);
        exit(1);
    }
}

$definitions = [
    "CREATE TABLE `users` (
        `id` int NOT NULL AUTO_INCREMENT,
        `nama_lengkap` varchar(100) NOT NULL,
        `username` varchar(50) NOT NULL,
        `email` varchar(190) DEFAULT NULL,
        `password` varchar(255) NOT NULL,
        `role` varchar(30) NOT NULL DEFAULT 'admin',
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_users_username` (`username`),
        UNIQUE KEY `uq_users_email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE `admin` (
        `id` int NOT NULL AUTO_INCREMENT,
        `username` varchar(50) NOT NULL,
        `password` varchar(255) NOT NULL,
        `role` varchar(30) NOT NULL DEFAULT 'admin',
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_admin_username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE `keuangan` (
        `id` int NOT NULL AUTO_INCREMENT,
        `tanggal` date NOT NULL,
        `jenis` enum('Pemasukan','Pengeluaran') NOT NULL,
        `keterangan` varchar(255) NOT NULL,
        `pekan_ke` tinyint unsigned DEFAULT NULL,
        `bulan` varchar(30) DEFAULT NULL,
        `nominal` decimal(15,2) NOT NULL DEFAULT 0.00,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_keuangan_tanggal` (`tanggal`),
        KEY `idx_keuangan_jenis` (`jenis`),
        KEY `idx_keuangan_pekan_ke` (`pekan_ke`),
        KEY `idx_keuangan_bulan` (`bulan`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE `keuangan_pekanan` (
        `id` int NOT NULL AUTO_INCREMENT,
        `tanggal` date NOT NULL,
        `jenis` enum('Pemasukan','Pengeluaran') NOT NULL,
        `keterangan` varchar(255) NOT NULL,
        `pekan_ke` tinyint unsigned DEFAULT NULL,
        `bulan` varchar(30) DEFAULT NULL,
        `nominal` decimal(15,2) NOT NULL DEFAULT 0.00,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_keuangan_pekanan_tanggal` (`tanggal`),
        KEY `idx_keuangan_pekanan_jenis` (`jenis`),
        KEY `idx_keuangan_pekanan_pekan_ke` (`pekan_ke`),
        KEY `idx_keuangan_pekanan_bulan` (`bulan`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE `jadwal_jumat` (
        `id` int NOT NULL AUTO_INCREMENT,
        `tanggal` date NOT NULL,
        `khatib` varchar(100) NOT NULL,
        `imam` varchar(100) NOT NULL,
        `muadzin` varchar(100) NOT NULL,
        `status_tayang` enum('Y','N') NOT NULL DEFAULT 'N',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_jadwal_jumat_tanggal` (`tanggal`),
        KEY `idx_jadwal_jumat_status_tayang` (`status_tayang`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE `galeri` (
        `id` int NOT NULL AUTO_INCREMENT,
        `nama_file` varchar(255) NOT NULL,
        `tipe` varchar(20) NOT NULL,
        `caption` varchar(255) DEFAULT NULL,
        `keterangan` varchar(255) DEFAULT NULL,
        `tanggal` date DEFAULT NULL,
        `jam_mulai` time DEFAULT NULL,
        `jam_selesai` time DEFAULT NULL,
        `status` varchar(20) NOT NULL DEFAULT 'aktif',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_galeri_status_tipe` (`status`, `tipe`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE `galeri_app` (
        `id` int NOT NULL AUTO_INCREMENT,
        `nama_file` varchar(255) NOT NULL,
        `tipe` varchar(20) NOT NULL,
        `caption` varchar(255) DEFAULT NULL,
        `keterangan` varchar(255) DEFAULT NULL,
        `tanggal` date DEFAULT NULL,
        `jam_mulai` time DEFAULT NULL,
        `jam_selesai` time DEFAULT NULL,
        `status` varchar(20) NOT NULL DEFAULT 'aktif',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_galeri_app_status_tipe` (`status`, `tipe`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE `galeri_media` (
        `id` int NOT NULL AUTO_INCREMENT,
        `nama_file` varchar(255) NOT NULL,
        `tipe` varchar(20) NOT NULL,
        `caption` varchar(255) DEFAULT NULL,
        `keterangan` varchar(255) DEFAULT NULL,
        `tanggal` date DEFAULT NULL,
        `jam_mulai` time DEFAULT NULL,
        `jam_selesai` time DEFAULT NULL,
        `status` varchar(20) NOT NULL DEFAULT 'aktif',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_galeri_media_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE `app_timer_iqomah` (
        `id` int NOT NULL,
        `subuh` int NOT NULL DEFAULT 12,
        `dzuhur` int NOT NULL DEFAULT 10,
        `ashar` int NOT NULL DEFAULT 10,
        `maghrib` int NOT NULL DEFAULT 7,
        `isya` int NOT NULL DEFAULT 10,
        `sound_file` varchar(255) DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE `pengaturan` (
        `id` int NOT NULL AUTO_INCREMENT,
        `nama_setting` varchar(50) NOT NULL,
        `nilai_setting` text NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_pengaturan_nama_setting` (`nama_setting`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE `app_pengaturan` (
        `id` int NOT NULL,
        `running_text` text NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE `app_hari_besar` (
        `id` int NOT NULL AUTO_INCREMENT,
        `nama_event` varchar(150) NOT NULL,
        `tanggal_masehi` date NOT NULL,
        `tahun_hijriah` varchar(30) NOT NULL DEFAULT '',
        PRIMARY KEY (`id`),
        KEY `idx_app_hari_besar_tanggal` (`tanggal_masehi`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

foreach ($definitions as $definition) {
    if (!mysqli_query($koneksi, $definition)) {
        fwrite(STDERR, 'Failed to create table: ' . mysqli_error($koneksi) . PHP_EOL);
        exit(1);
    }
}

$seed_password = bin2hex(random_bytes(24));
$password_hash = password_hash($seed_password, PASSWORD_DEFAULT);
$seed_date = date('Y-m-d', strtotime('next Friday'));
$default_running_text = 'Selamat Datang di Musholla Al-Muhajirin Menara Samawa Jakarta Timur.';

if (!mysqli_begin_transaction($koneksi)) {
    fwrite(STDERR, 'Could not start transaction for initial data: ' . mysqli_error($koneksi) . PHP_EOL);
    exit(1);
}

$seed_statements = [
    [
        'INSERT INTO `users` (`nama_lengkap`, `username`, `email`, `password`, `role`) VALUES (?, ?, ?, ?, ?)',
        'sssss',
        ['Administrator Musholla', 'superadmin', null, $password_hash, 'Super Admin'],
    ],
    [
        'INSERT INTO `keuangan` (`tanggal`, `jenis`, `keterangan`, `pekan_ke`, `bulan`, `nominal`) VALUES (CURDATE(), ?, ?, ?, ?, ?)',
        'ssisd',
        ['Pemasukan', 'Saldo awal', (int)ceil((int)date('j') / 7), date('F'), 0],
    ],
    [
        'INSERT INTO `jadwal_jumat` (`tanggal`, `khatib`, `imam`, `muadzin`, `status_tayang`) VALUES (?, ?, ?, ?, ?)',
        'sssss',
        [$seed_date, 'Ust. Ahmad', 'Ust. Ahmad Ridwan', 'Bilal Hasby', 'Y'],
    ],
    [
        'INSERT INTO `app_timer_iqomah` (`id`, `subuh`, `dzuhur`, `ashar`, `maghrib`, `isya`) VALUES (1, 12, 10, 10, 7, 10)',
        '',
        [],
    ],
    [
        'INSERT INTO `pengaturan` (`nama_setting`, `nilai_setting`) VALUES (?, ?)',
        'ss',
        ['running_text', $default_running_text],
    ],
    [
        'INSERT INTO `app_pengaturan` (`id`, `running_text`) VALUES (1, ?)',
        's',
        [$default_running_text],
    ],
];

foreach ($seed_statements as [$sql, $types, $values]) {
    $statement = mysqli_prepare($koneksi, $sql);
    if (!$statement) {
        mysqli_rollback($koneksi);
        fwrite(STDERR, 'Could not prepare seed data: ' . mysqli_error($koneksi) . PHP_EOL);
        exit(1);
    }

    if ($types !== '') {
        $bind_arguments = [$statement, $types];
        foreach ($values as &$value) {
            $bind_arguments[] = &$value;
        }
        unset($value);
        $bound = call_user_func_array('mysqli_stmt_bind_param', $bind_arguments);
        if (!$bound) {
            mysqli_stmt_close($statement);
            mysqli_rollback($koneksi);
            fwrite(STDERR, 'Could not bind seed data: ' . mysqli_error($koneksi) . PHP_EOL);
            exit(1);
        }
    }

    if (!mysqli_stmt_execute($statement)) {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        mysqli_rollback($koneksi);
        fwrite(STDERR, 'Could not insert seed data: ' . $error . PHP_EOL);
        exit(1);
    }
    mysqli_stmt_close($statement);
}

if (!mysqli_commit($koneksi)) {
    mysqli_rollback($koneksi);
    fwrite(STDERR, 'Could not commit seed data: ' . mysqli_error($koneksi) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Database db_musholla reset completed.\n");
fwrite(STDOUT, "Seed admin username: superadmin\n");
fwrite(STDOUT, "Seed admin password (shown once): {$seed_password}\n");
fwrite(STDOUT, "Seed cash row: Rp 0.00\n");
fwrite(STDOUT, "Active Friday schedule date: {$seed_date}\n");
