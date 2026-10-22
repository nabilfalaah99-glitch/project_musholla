<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Installer hanya dapat dijalankan dari CLI untuk mencegah pemasangan akun default melalui web.\n");
}

require_once __DIR__ . '/config/koneksi.php';

if (!isset($koneksi) || !($koneksi instanceof mysqli)) {
    fwrite(STDERR, "Koneksi database tidak valid.\n");
    exit(1);
}

$database_result = mysqli_query($koneksi, 'SELECT DATABASE() AS database_name LIMIT 1');
if (!$database_result) {
    fwrite(STDERR, 'Gagal memeriksa database: ' . mysqli_error($koneksi) . PHP_EOL);
    exit(1);
}

$database = mysqli_fetch_assoc($database_result);
if (!$database || $database['database_name'] !== 'db_musholla') {
    fwrite(STDERR, "Installer hanya diizinkan untuk database db_musholla.\n");
    exit(1);
}

$table_definitions = [
    "CREATE TABLE IF NOT EXISTS `users` (
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
    "CREATE TABLE IF NOT EXISTS `keuangan` (
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
    "CREATE TABLE IF NOT EXISTS `keuangan_pekanan` (
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
    "CREATE TABLE IF NOT EXISTS `jadwal_jumat` (
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
    "CREATE TABLE IF NOT EXISTS `galeri` (
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
    "CREATE TABLE IF NOT EXISTS `galeri_app` (
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
    "CREATE TABLE IF NOT EXISTS `galeri_media` (
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
        fwrite(STDERR, 'Gagal membuat tabel: ' . mysqli_error($koneksi) . PHP_EOL);
        exit(1);
    }
}

$default_running_text = 'Selamat Datang di Musholla Al-Muhajirin Menara Samawa Jakarta Timur.';
$seed_date = date('Y-m-d', strtotime('next Friday'));
$admin_password_hash = password_hash('admin123', PASSWORD_DEFAULT);

$seed_operations = [
    [
        'SELECT `id` FROM `users` LIMIT 1',
        'INSERT INTO `users` (`nama_lengkap`, `username`, `email`, `password`, `role`) VALUES (?, ?, NULL, ?, ?)',
        'ssss',
        ['Administrator Musholla', 'admin', $admin_password_hash, 'admin'],
    ],
    [
        'SELECT `id` FROM `keuangan` LIMIT 1',
        'INSERT INTO `keuangan` (`tanggal`, `jenis`, `keterangan`, `pekan_ke`, `bulan`, `nominal`) VALUES (CURDATE(), ?, ?, ?, ?, 0)',
        'ssis',
        ['Pemasukan', 'Saldo awal', (int)ceil((int)date('j') / 7), date('F')],
    ],
    [
        'SELECT `id` FROM `jadwal_jumat` LIMIT 1',
        'INSERT INTO `jadwal_jumat` (`tanggal`, `khatib`, `imam`, `muadzin`, `status_tayang`) VALUES (?, ?, ?, ?, ?)',
        'sssss',
        [$seed_date, 'Ust. Ahmad', 'Ust. Ahmad Ridwan', 'Bilal Hasby', 'Y'],
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
];

foreach ($seed_operations as [$check_sql, $insert_sql, $types, $values]) {
    $check_result = mysqli_query($koneksi, $check_sql);
    if (!$check_result) {
        fwrite(STDERR, 'Gagal memeriksa seed: ' . mysqli_error($koneksi) . PHP_EOL);
        exit(1);
    }
    if (mysqli_num_rows($check_result) > 0) {
        continue;
    }

    $statement = mysqli_prepare($koneksi, $insert_sql);
    if (!$statement) {
        fwrite(STDERR, 'Gagal menyiapkan seed: ' . mysqli_error($koneksi) . PHP_EOL);
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
            $error = mysqli_stmt_error($statement);
            mysqli_stmt_close($statement);
            fwrite(STDERR, 'Gagal mengikat data seed: ' . $error . PHP_EOL);
            exit(1);
        }
    }

    if (!mysqli_stmt_execute($statement)) {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        fwrite(STDERR, 'Gagal memasukkan seed: ' . $error . PHP_EOL);
        exit(1);
    }
    mysqli_stmt_close($statement);
}

fwrite(STDOUT, "\033[32mINSTALL BERHASIL\033[0m\n");
fwrite(STDOUT, "Login awal: admin / admin123. Segera ganti password setelah login.\n");
