<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This maintenance script can only be run from the command line.');
}

require_once __DIR__ . '/config/koneksi.php';
mysqli_report(MYSQLI_REPORT_OFF);

if (!mysqli_query($koneksi, 'DROP TABLE IF EXISTS `jadwal_jumat`')) {
    fwrite(STDERR, 'Failed to drop jadwal_jumat: ' . mysqli_error($koneksi) . PHP_EOL);
    exit(1);
}

$database_result = mysqli_query($koneksi, 'SELECT DATABASE() AS database_name, @@datadir AS data_directory LIMIT 1');
$database = $database_result ? mysqli_fetch_assoc($database_result) : false;
$data_directory = $database ? realpath($database['data_directory']) : false;
if (
    !$database
    || $database['database_name'] !== 'db_musholla'
    || !$data_directory
) {
    fwrite(STDERR, 'Could not safely resolve db_musholla data directory.' . PHP_EOL);
    exit(1);
}

$database_directory = realpath($data_directory . DIRECTORY_SEPARATOR . $database['database_name']);
if (
    !$database_directory
    || dirname($database_directory) !== $data_directory
    || basename($database_directory) !== 'db_musholla'
) {
    fwrite(STDERR, 'Could not safely resolve db_musholla schema directory.' . PHP_EOL);
    exit(1);
}

$table_check = mysqli_query($koneksi, "SHOW TABLES LIKE 'jadwal_jumat'");
if (!$table_check || mysqli_num_rows($table_check) > 0) {
    fwrite(STDERR, 'Refusing to remove tablespace while jadwal_jumat is still registered.' . PHP_EOL);
    exit(1);
}

$tablespace_file = $database_directory . DIRECTORY_SEPARATOR . 'jadwal_jumat.ibd';
if (is_file($tablespace_file) && !unlink($tablespace_file)) {
    fwrite(STDERR, 'Could not remove orphaned tablespace: ' . $tablespace_file . PHP_EOL);
    exit(1);
}

$create_query = "CREATE TABLE `jadwal_jumat` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!mysqli_query($koneksi, $create_query)) {
    fwrite(STDERR, 'Failed to create jadwal_jumat: ' . mysqli_error($koneksi) . PHP_EOL);
    exit(1);
}

$columns_result = mysqli_query($koneksi, 'SHOW COLUMNS FROM `jadwal_jumat`');
$expected_columns = ['id', 'tanggal', 'khatib', 'imam', 'muadzin', 'status_tayang', 'created_at'];
$actual_columns = [];
if ($columns_result) {
    while ($column = mysqli_fetch_assoc($columns_result)) {
        $actual_columns[] = $column['Field'];
    }
}

if ($actual_columns !== $expected_columns) {
    fwrite(STDERR, 'Table verification failed for jadwal_jumat.' . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, 'Table jadwal_jumat was recreated and verified successfully.' . PHP_EOL);
