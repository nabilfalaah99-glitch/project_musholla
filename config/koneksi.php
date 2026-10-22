<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = "127.0.0.1";
$user = "root";
$pass = "";
$db   = "db_musholla";

function musholla_reconnect_database()
{
    global $host, $user, $pass, $db;

    foreach ([$host, 'localhost'] as $database_host) {
        $connection = mysqli_init();
        mysqli_options($connection, MYSQLI_OPT_CONNECT_TIMEOUT, 5);
        mysqli_options($connection, MYSQLI_OPT_READ_TIMEOUT, 5);

        try {
            if ($connection->real_connect($database_host, $user, $pass, $db)) {
                return $connection;
            }
        } catch (mysqli_sql_exception $exception) {
            error_log('Database reconnect failed: ' . $exception->getMessage());
        }
    }

    return false;
}

// Batasi waktu tunggu agar request tidak menggantung jika MySQL tidak merespons.
$connection = mysqli_init();
mysqli_options($connection, MYSQLI_OPT_CONNECT_TIMEOUT, 5);
mysqli_options($connection, MYSQLI_OPT_READ_TIMEOUT, 5);
try {
    $koneksi = $connection->real_connect($host, $user, $pass, $db) ? $connection : false;
} catch (mysqli_sql_exception $exception) {
    error_log('Database connection to configured host failed: ' . $exception->getMessage());
    $koneksi = false;
}

if (!$koneksi) {
    $koneksi = musholla_reconnect_database();
}

// Jika koneksi tetap gagal, tampilkan pesan ramah tanpa bikin browser crash
if (!$koneksi) {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Database connection failed. Check that MySQL is running and responsive.\n");
        exit(1);
    }

    echo "
    <div style='font-family: Arial, sans-serif; background-color: #0f172a; color: #ffffff; height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0;'>
        <div style='background-color: #1e293b; border: 2px solid #ef4444; padding: 30px; border-radius: 16px; text-align: center; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.5);'>
            <h2 style='color: #f87171; margin-top: 0;'>Database Belum Terhubung</h2>
            <p style='color:#cbd5e1; font-size:14px; line-height:1.5;'>Koneksi ke MySQL gagal. Periksa status MySQL di XAMPP dan konfigurasi host, username, password, serta nama database.</p>
            <p style='color:#94a3b8; font-size:12px;'>Setelah MySQL berjalan dan konfigurasi benar, muat ulang halaman ini.</p>
        </div>
    </div>";
    exit();
}
?>