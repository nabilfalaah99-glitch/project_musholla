<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This maintenance script can only be run from the command line.');
}

require_once __DIR__ . '/config/koneksi.php';
mysqli_report(MYSQLI_REPORT_OFF);

$drop_query = 'DROP TABLE IF EXISTS `keuangan`, `keuangan_pekanan`';
if (!mysqli_query($koneksi, $drop_query)) {
    fwrite(STDERR, 'Failed to drop cash tables: ' . mysqli_error($koneksi) . PHP_EOL);
    exit(1);
}

$database_result = mysqli_query($koneksi, 'SELECT DATABASE() AS database_name, @@datadir AS data_directory LIMIT 1');
$database = $database_result ? mysqli_fetch_assoc($database_result) : false;
$data_directory = $database ? realpath($database['data_directory']) : false;
if (
    !$database
    || !preg_match('/\A[A-Za-z0-9_]+\z/', $database['database_name'])
    || !$data_directory
) {
    fwrite(STDERR, 'Could not safely resolve the active database directory.' . PHP_EOL);
    exit(1);
}

$database_directory = realpath($data_directory . DIRECTORY_SEPARATOR . $database['database_name']);
if (
    !$database_directory
    || dirname($database_directory) !== $data_directory
    || basename($database_directory) !== $database['database_name']
) {
    fwrite(STDERR, 'Could not safely resolve the active database schema directory.' . PHP_EOL);
    exit(1);
}

$tablespace_files = [];
foreach (['keuangan', 'keuangan_pekanan'] as $table_name) {
    $table_check = mysqli_query($koneksi, "SHOW TABLES LIKE '{$table_name}'");
    if (!$table_check || mysqli_num_rows($table_check) > 0) {
        fwrite(STDERR, "Refusing to remove a tablespace while table {$table_name} is still registered." . PHP_EOL);
        exit(1);
    }

    $tablespace_file = $database_directory . DIRECTORY_SEPARATOR . $table_name . '.ibd';
    if (is_file($tablespace_file)) {
        $tablespace_files[] = $tablespace_file;
    }
}

foreach ($tablespace_files as $tablespace_file) {
    if (!unlink($tablespace_file)) {
        fwrite(STDERR, 'Could not remove orphaned cash tablespace: ' . $tablespace_file . PHP_EOL);
        exit(1);
    }
}

$table_definitions = [
    'keuangan' => "CREATE TABLE `keuangan` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `tanggal` date NOT NULL,
        `jenis` enum('Pemasukan','Pengeluaran') NOT NULL,
        `keterangan` varchar(255) NOT NULL,
        `pekan_ke` tinyint(3) unsigned DEFAULT NULL,
        `bulan` varchar(30) DEFAULT NULL,
        `nominal` decimal(15,2) NOT NULL DEFAULT 0.00,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_keuangan_tanggal` (`tanggal`),
        KEY `idx_keuangan_jenis` (`jenis`),
        KEY `idx_keuangan_pekan_ke` (`pekan_ke`),
        KEY `idx_keuangan_bulan` (`bulan`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'keuangan_pekanan' => "CREATE TABLE `keuangan_pekanan` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `tanggal` date NOT NULL,
        `jenis` enum('Pemasukan','Pengeluaran') NOT NULL,
        `keterangan` varchar(255) NOT NULL,
        `pekan_ke` tinyint(3) unsigned DEFAULT NULL,
        `bulan` varchar(30) DEFAULT NULL,
        `nominal` decimal(15,2) NOT NULL DEFAULT 0.00,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_keuangan_pekanan_tanggal` (`tanggal`),
        KEY `idx_keuangan_pekanan_jenis` (`jenis`),
        KEY `idx_keuangan_pekanan_pekan_ke` (`pekan_ke`),
        KEY `idx_keuangan_pekanan_bulan` (`bulan`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

foreach ($table_definitions as $table_name => $create_query) {
    if (!mysqli_query($koneksi, $create_query)) {
        fwrite(STDERR, "Failed to create {$table_name}: " . mysqli_error($koneksi) . PHP_EOL);
        exit(1);
    }
}

foreach (array_keys($table_definitions) as $table_name) {
    $columns_result = mysqli_query($koneksi, "SHOW COLUMNS FROM `{$table_name}`");
    if (!$columns_result || mysqli_num_rows($columns_result) !== 8) {
        fwrite(STDERR, "Table verification failed for {$table_name}: " . mysqli_error($koneksi) . PHP_EOL);
        exit(1);
    }
}

fwrite(STDOUT, "Cash tables keuangan and keuangan_pekanan were recreated and verified successfully." . PHP_EOL);
