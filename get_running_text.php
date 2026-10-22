<?php
require_once __DIR__ . '/config/koneksi.php';

$query = false;
try {
    $query = mysqli_query($koneksi, "SELECT running_text FROM app_pengaturan WHERE id=1 LIMIT 1");
} catch (mysqli_sql_exception $exception) {
    error_log('Could not load running text: ' . $exception->getMessage());
}

if ($query && ($data = mysqli_fetch_assoc($query)) && !empty($data['running_text'])) {
    echo $data['running_text'];
} elseif ($query) {
    echo "Selamat Datang di Musholla Al-Muhajirin. Mari luruskan dan rapatkan shaf.";
} else {
    http_response_code(500);
    echo "Gagal mengambil running text dari database.";
}
?>