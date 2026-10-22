<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This database optimization script can only be run from the command line.\n");
}

require_once __DIR__ . '/config/koneksi.php';

if (!$koneksi) {
    fwrite(STDERR, "Database connection is unavailable.\n");
    exit(1);
}

$database_result = mysqli_query($koneksi, 'SELECT DATABASE() AS database_name LIMIT 1');
if (!$database_result) {
    fwrite(STDERR, 'Could not verify database: ' . mysqli_error($koneksi) . PHP_EOL);
    exit(1);
}

$database = mysqli_fetch_assoc($database_result);
if (!$database || $database['database_name'] !== 'db_musholla') {
    fwrite(STDERR, "Refusing to execute SQL on a database other than db_musholla.\n");
    exit(1);
}

$sql_path = __DIR__ . '/optimasi_index.sql';
$sql = file_get_contents($sql_path);
if ($sql === false || trim($sql) === '') {
    fwrite(STDERR, "Could not read optimasi_index.sql or the file is empty.\n");
    exit(1);
}

if (!mysqli_multi_query($koneksi, $sql)) {
    fwrite(STDERR, 'Optimization SQL failed: ' . mysqli_error($koneksi) . PHP_EOL);
    exit(1);
}

do {
    $result = mysqli_store_result($koneksi);
    if ($result) {
        while ($row = mysqli_fetch_row($result)) {
            if (isset($row[0]) && str_contains((string)$row[0], 'sudah tersedia')) {
                fwrite(STDOUT, $row[0] . PHP_EOL);
            }
        }
        mysqli_free_result($result);
    } elseif (mysqli_field_count($koneksi) > 0) {
        fwrite(STDERR, 'Could not read SQL result: ' . mysqli_error($koneksi) . PHP_EOL);
        exit(1);
    }

    if (!mysqli_more_results($koneksi)) {
        break;
    }
    if (!mysqli_next_result($koneksi)) {
        fwrite(STDERR, 'Optimization SQL failed: ' . mysqli_error($koneksi) . PHP_EOL);
        exit(1);
    }
} while (true);

$expected_indexes = [
    'keuangan' => ['jenis', 'pekan_ke', 'bulan'],
    'jadwal_jumat' => ['status_tayang', 'tanggal'],
];
$verification = mysqli_prepare(
    $koneksi,
    'SELECT COUNT(*) AS index_count
     FROM information_schema.statistics
     WHERE table_schema = DATABASE()
       AND table_name = ?
       AND column_name = ?
       AND seq_in_index = 1
     LIMIT 1'
);
if (!$verification) {
    fwrite(STDERR, 'Could not prepare index verification: ' . mysqli_error($koneksi) . PHP_EOL);
    exit(1);
}

foreach ($expected_indexes as $table => $columns) {
    foreach ($columns as $column) {
        if (
            !mysqli_stmt_bind_param($verification, 'ss', $table, $column)
            || !mysqli_stmt_execute($verification)
        ) {
            fwrite(STDERR, "Could not verify {$table}.{$column}: " . mysqli_stmt_error($verification) . PHP_EOL);
            mysqli_stmt_close($verification);
            exit(1);
        }

        $result = mysqli_stmt_get_result($verification);
        $row = $result ? mysqli_fetch_assoc($result) : false;
        if (!$row || (int)$row['index_count'] < 1) {
            fwrite(STDERR, "Index verification failed for {$table}.{$column}.\n");
            mysqli_stmt_close($verification);
            exit(1);
        }
        fwrite(STDOUT, "Verified index on {$table}.{$column}.\n");
    }
}

mysqli_stmt_close($verification);
fwrite(STDOUT, "Database indexes are installed and verified.\n");
