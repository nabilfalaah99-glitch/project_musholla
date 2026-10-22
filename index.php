<?php
require_once __DIR__ . '/config/koneksi.php';

require_once __DIR__ . '/config/alarm_audio.php';
function display_db_query(&$connection, $sql)
{
    for ($attempt = 0; $attempt < 2; $attempt++) {
        if (!($connection instanceof mysqli)) {
            $connection = musholla_reconnect_database();
        }

        if (!($connection instanceof mysqli)) {
            return false;
        }

        try {
            return mysqli_query($connection, $sql);
        } catch (mysqli_sql_exception $exception) {
            error_log('Display database query failed: ' . $exception->getMessage());
            if (!in_array((int)$exception->getCode(), [2006, 2013, 2055], true) || $attempt === 1) {
                return false;
            }
            $connection = musholla_reconnect_database();
        }
    }

    return false;
}

try {
    $current_alarm_audio = current_alarm_audio($koneksi, __DIR__ . '/assets/audio/');
} catch (Throwable $exception) {
    error_log('Could not load display alarm audio: ' . $exception->getMessage());
    $current_alarm_audio = 'alarm.mp3';
}
$alarm_audio_src = 'assets/audio/' . rawurlencode($current_alarm_audio) . '?v=' . time();

// ====== AMBIL RINGKASAN TOTAL KAS SESUAI TABEL ADMIN ======
$tot_masuk = 0.0;
$tot_keluar = 0.0;
$kas_totals_query = display_db_query(
    $koneksi,
    "SELECT
        COALESCE(SUM(CASE WHEN jenis = 'Pemasukan' THEN nominal ELSE 0 END), 0) AS total_pemasukan,
        COALESCE(SUM(CASE WHEN jenis = 'Pengeluaran' THEN nominal ELSE 0 END), 0) AS total_pengeluaran
     FROM keuangan
     LIMIT 1"
);
if ($kas_totals_query) {
    try {
        $kas_totals = mysqli_fetch_assoc($kas_totals_query);
        if ($kas_totals) {
            $tot_masuk = (float)$kas_totals['total_pemasukan'];
            $tot_keluar = (float)$kas_totals['total_pengeluaran'];
        }
    } catch (mysqli_sql_exception $exception) {
        error_log('Display kas totals result failed: ' . $exception->getMessage());
    }
}
$saldo_akhir = $tot_masuk - $tot_keluar;

$transaksi_kas_terbaru = [];
$transaksi_kas_gagal = false;
$query_transaksi_kas = display_db_query(
    $koneksi,
    "SELECT * FROM keuangan ORDER BY tanggal DESC, id DESC LIMIT 3"
);
if ($query_transaksi_kas) {
    try {
        while ($transaksi = mysqli_fetch_assoc($query_transaksi_kas)) {
            $transaksi_kas_terbaru[] = $transaksi;
        }
    } catch (mysqli_sql_exception $exception) {
        error_log('Display latest kas transactions result failed: ' . $exception->getMessage());
        $transaksi_kas_gagal = true;
    }
} else {
    $transaksi_kas_gagal = true;
}

// ====== AMBIL HARI BESAR TERDEKAT ======
$hari_besar = null;
$query_hari_besar = display_db_query(
    $koneksi,
    "SELECT *, DATEDIFF(tanggal_masehi, CURDATE()) AS sisa_hari
     FROM app_hari_besar
     WHERE tanggal_masehi >= CURDATE()
     ORDER BY tanggal_masehi ASC
     LIMIT 1"
);
$hari_besar_gagal = !$query_hari_besar;
if ($query_hari_besar && mysqli_num_rows($query_hari_besar) > 0) {
    $hari_besar = mysqli_fetch_assoc($query_hari_besar);
}

// ====== FORMAT WAKTU INDONESIA ======
$bulan_indo = ['January'=>'Januari', 'February'=>'Februari', 'March'=>'Maret', 'April'=>'April', 'May'=>'Mei', 'June'=>'Juni', 'July'=>'Juli', 'August'=>'Agustus', 'September'=>'September', 'October'=>'Oktober', 'November'=>'November', 'December'=>'Desember'];$hari_indo = ['Sunday'=>'Minggu', 'Monday'=>'Senin', 'Tuesday'=>'Selasa', 'Wednesday'=>'Rabu', 'Thursday'=>'Kamis', 'Friday'=>'Jumat', 'Saturday'=>'Sabtu'];

$bulan_sekarang = $bulan_indo[date('F')];
$tanggal_hijriah = '8 Jumadil Awwal 1448 H';

// ====== GALERI GAMBAR AKTIF BESERTA CAPTION ======
$gambar_galeri = [];
$query_galeri = display_db_query(
    $koneksi,
    "SELECT file, nama_file, tipe, caption, keterangan, tanggal, tanggal_acara, jam_mulai, jam_selesai
     FROM galeri WHERE status='aktif' ORDER BY id DESC LIMIT 20"
);
$galeri_gagal = !$query_galeri;
if ($query_galeri) {
    while ($row_galeri = mysqli_fetch_assoc($query_galeri)) {
        $nama_file_galeri = basename((string)($row_galeri['file'] ?? $row_galeri['nama_file'] ?? ''));
        if ($nama_file_galeri === '') {
            continue;
        }
        $path_galeri = 'assets/img/' . $nama_file_galeri;
        $ekstensi_galeri = strtolower(pathinfo($nama_file_galeri, PATHINFO_EXTENSION));
        if (
            in_array($ekstensi_galeri, ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'webm', 'ogg'], true)
            && is_file($path_galeri)
        ) {
            $gambar_galeri[] = [
                'path' => $path_galeri,
                'type' => in_array($ekstensi_galeri, ['mp4', 'webm', 'ogg'], true) ? 'video' : 'gambar',
                'date' => $row_galeri['tanggal_acara'] ?? $row_galeri['tanggal'] ?? '',
                'start' => $row_galeri['jam_mulai'] ?? '',
                'end' => $row_galeri['jam_selesai'] ?? '',
                'caption' => trim((string)($row_galeri['caption'] ?? ''))
                    ?: trim((string)($row_galeri['keterangan'] ?? ''))
                    ?: pathinfo($nama_file_galeri, PATHINFO_FILENAME),
            ];
        }
    }
}

if (!$galeri_gagal && !$gambar_galeri) {
    $files_galeri = glob('assets/img/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE) ?: [];
    foreach ($files_galeri as $file_galeri) {
        $nama_file_galeri = strtolower(basename($file_galeri));
        if (!in_array($nama_file_galeri, ['logo.png', 'bg-sholat.png', 'bg_sholat.png'], true)) {
            $gambar_galeri[] = [
                'path' => $file_galeri,
                'caption' => pathinfo(basename($file_galeri), PATHINFO_FILENAME),
            ];
        }
    }
}

// ====== AMBIL PETUGAS JUMAT YANG TAYANG ======
$query_jumat = display_db_query($koneksi, "SELECT * FROM jadwal_jumat WHERE status_tayang=1 ORDER BY tanggal DESC, id DESC LIMIT 1");
$jumat = ($query_jumat && mysqli_num_rows($query_jumat) > 0) ? mysqli_fetch_assoc($query_jumat) : null;
$khatib  = $jumat['khatib'] ?? 'Ustdz Ahmad, M.Ag';
$imam    = $jumat['imam'] ?? 'Ust. Ahmad Ridwan, S.Ag';
$muadzin = $jumat['muadzin'] ?? 'Bilal Hasby';

// ====== RUNNING TEXT DINAMIS ======
$running_text = 'Selamat Datang di Musholla Al-Muhajirin Menara Samawa Jakarta Timur.';
$query_setting = display_db_query($koneksi, "SELECT running_text FROM app_pengaturan WHERE id=1 LIMIT 1");
if ($query_setting && mysqli_num_rows($query_setting) > 0) {
    $row_setting = mysqli_fetch_assoc($query_setting);
    if (!empty($row_setting['running_text'])) {
        $running_text =$row_setting['running_text'];
    }
}

// ====== DURASI IQOMAH ======
$iqomah_db = ['SUBUH' => 12, 'DZUHUR' => 10, 'ASHAR' => 10, 'MAGHRIB' => 7, 'ISYA' => 10];
$q_iq = display_db_query($koneksi, "SELECT subuh, dzuhur, ashar, maghrib, isya FROM app_timer_iqomah WHERE id=1 LIMIT 1");
if ($q_iq && ($row_iq = mysqli_fetch_assoc($q_iq))) {
    $iqomah_db = [
        'SUBUH' => (int)$row_iq['subuh'],
        'DZUHUR' => (int)$row_iq['dzuhur'],
        'ASHAR' => (int)$row_iq['ashar'],
        'MAGHRIB' => (int)$row_iq['maghrib'],
        'ISYA' => (int)$row_iq['isya'],
    ];
}

// ====== JADWAL SHOLAT OTOMATIS ======
$file_jadwal = 'jadwal_sholat.json';
$tanggal_hari_ini = date('Y-m-d');
$jadwal_sholat = [];
$jadwal_default = [
    'Imsak' => '04:16',
    'Fajr' => '04:26',
    'Sunrise' => '05:27',
    'Dhuhr' => '11:43',
    'Asr' => '14:50',
    'Maghrib' => '17:51',
    'Isha' => '19:00',
];

if (is_file($file_jadwal) && is_readable($file_jadwal)) {
    $file_jadwal_content = file_get_contents($file_jadwal);
    $data_jadwal = is_string($file_jadwal_content)
        ? json_decode($file_jadwal_content, true)
        : null;
    if (
        is_array($data_jadwal)
        && ($data_jadwal['tanggal'] ?? null) === $tanggal_hari_ini
        && is_array($data_jadwal['jadwal'] ?? null)
    ) {
        $jadwal_sholat = $data_jadwal['jadwal'];
    }
}

if (empty($jadwal_sholat)) {
    $url = 'https://api.aladhan.com/v1/timingsByCity?city=Jakarta&country=Indonesia&method=20';
    $context = stream_context_create(['http' => ['timeout' => 3]]);
    $response = @file_get_contents($url, false, $context);
    $api_data = is_string($response) ? json_decode($response, true) : null;
    $api_timings = $api_data['data']['timings'] ?? null;
    if (is_array($api_timings)) {
        $jadwal_sholat = $api_timings;
        $encoded_schedule = json_encode(
            ['tanggal' => $tanggal_hari_ini, 'jadwal' => $jadwal_sholat],
            JSON_UNESCAPED_UNICODE
        );
        if (is_string($encoded_schedule) && file_put_contents($file_jadwal, $encoded_schedule, LOCK_EX) === false) {
            error_log('Could not write prayer schedule cache: ' . $file_jadwal);
        }
    } else {
        error_log('Prayer schedule API unavailable or returned invalid data.');
    }
}

$required_prayer_times = ['Imsak', 'Fajr', 'Sunrise', 'Dhuhr', 'Asr', 'Maghrib', 'Isha'];
foreach ($required_prayer_times as $prayer_time_name) {
    $prayer_time = $jadwal_sholat[$prayer_time_name] ?? null;
    if (!is_string($prayer_time) || !preg_match('/\A\d{1,2}:\d{2}/', $prayer_time, $matches)) {
        $jadwal_sholat[$prayer_time_name] = $jadwal_default[$prayer_time_name];
    } else {
        $jadwal_sholat[$prayer_time_name] = $matches[0];
    }
}

$nama_dzuhur = (date('w') == 5) ? 'JUMAT' : 'DZUHUR';

$waktu = [
    'IMSAK' => $jadwal_sholat['Imsak'],
    'SUBUH' => $jadwal_sholat['Fajr'],
    'SYURUQ' => $jadwal_sholat['Sunrise'],
    $nama_dzuhur => $jadwal_sholat['Dhuhr'],
    'ASHAR' => $jadwal_sholat['Asr'],
    'MAGHRIB' => $jadwal_sholat['Maghrib'],
    'ISYA' => $jadwal_sholat['Isha']
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MUSHOLLA AL-MUHAJIRIN - Digital Display System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        };
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&family=Orbitron:wght@500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', sans-serif; user-select: none; }
        .font-orbitron { font-family: 'Orbitron', sans-serif; }
        @keyframes marqueeAnimation { 0% { transform: translateX(100%); } 100% { transform: translateX(-100%); } }
        .animate-marquee-text { display: inline-block; white-space: nowrap; animation: marqueeAnimation 32s linear infinite; }
        .glow-emerald { box-shadow: 0 0 20px rgba(16, 185, 129, 0.35); }
        .glow-amber { box-shadow: 0 0 20px rgba(245, 158, 11, 0.35); }
        .text-glow-emerald { text-shadow: 0 0 10px rgba(52, 211, 153, 0.5); }
        .text-glow-amber { text-shadow: 0 0 10px rgba(245, 158, 11, 0.5); }
        .fade-in { animation: fadeIn 0.5s ease-in-out forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.98); } to { opacity: 1; transform: scale(1); } }
        .bg-overlay-sholat { background: linear-gradient(rgba(5, 11, 20, 0.85), rgba(5, 11, 20, 0.85)), url('assets/img/bg-sholat.png') no-repeat center center / cover !important; }

        html, body { width: 100%; height: 100%; }
        body {
            height: 100vh;
            height: 100dvh;
            min-height: 0;
            display: grid;
            grid-template-rows: auto minmax(0, 1fr) auto auto;
            gap: clamp(6px, 1vh, 12px);
            padding: clamp(6px, 1vw, 14px);
            overflow: hidden;
        }
        body > header { min-width: 0; padding: clamp(8px, 1vw, 14px); }
        body > header img { height: clamp(34px, 7vh, 56px); }
        body > header h1 { font-size: clamp(14px, 1.6vw, 24px); }
        body > header p { margin-top: clamp(2px, .4vh, 6px); font-size: clamp(9px, .9vw, 14px); }
        body > header > div { min-width: 0; }
        body > header > div:last-child { gap: clamp(8px, 1.5vw, 24px); }
        body > header > div:last-child > div:first-child { padding: clamp(5px, .7vw, 10px); }
        body > main {
            width: 100%;
            min-height: 0;
            height: 100%;
            margin: 0;
            gap: clamp(6px, 1vw, 14px);
        }
        body > main > section {
            min-width: 0;
            min-height: 0;
            flex: 2 1 0%;
        }
        body > main > aside {
            min-width: 0;
            min-height: 0;
            height: 100%;
            flex: 1 1 0%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: clamp(6px, .7vw, 10px);
            overflow-x: hidden;
            overflow-y: auto;
            overscroll-behavior: contain;
        }
        body > main > aside > div:first-child {
            flex: 0 1 auto;
            min-height: 0;
            width: 100%;
            gap: clamp(2px, .35vh, 5px);
        }
        body > main > aside > div:first-child > div:first-child {
            margin-bottom: 2px;
            padding: 3px 8px;
        }
        #nextPrayerName {
            margin-bottom: 2px;
            line-height: 1.15;
            font-size: clamp(20px, 2.1vw, 30px);
        }
        #mainCountdown {
            margin-bottom: 2px;
            padding: clamp(5px, .7vh, 9px) clamp(8px, 1vw, 14px);
            font-size: clamp(24px, 2.5vw, 36px);
            line-height: 1.1;
        }
        #countdownStatus {
            margin-top: 2px;
            line-height: 1.25;
            font-size: clamp(10px, .85vw, 13px);
        }
        #loopOverlay {
            position: relative;
            flex: 0 1 clamp(190px, 27vh, 230px);
            min-height: 190px;
            max-height: 230px;
            display: block;
            line-height: 1.45;
            padding: clamp(7px, .65vw, 10px);
            margin-top: clamp(6px, .8vh, 10px);
            overflow: hidden;
        }
        @keyframes infoSlide {
            0%, 46% { opacity: 1; visibility: visible; transform: translateY(0); }
            50%, 96% { opacity: 0; visibility: hidden; transform: translateY(-6px); }
            100% { opacity: 1; visibility: visible; transform: translateY(0); }
        }
        #loopOverlay #cardTitle {
            position: relative;
            z-index: 2;
            margin-bottom: 5px;
            padding-bottom: 5px;
            font-size: clamp(12px, .95vw, 15px);
            line-height: 1.2;
        }
        #cardKeuangan,
        #cardJumat {
            position: absolute;
            inset: 39px 10px 8px;
            width: auto;
            min-height: 0;
            overflow: hidden;
            animation: infoSlide 16s ease-in-out infinite;
            will-change: opacity, transform;
        }
        #cardJumat { animation-delay: -8s; }
        #cardKeuangan {
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            gap: clamp(4px, .55vh, 7px);
            font-size: clamp(9px, .72vw, 12px);
        }
        #cardKeuangan #cardTitle {
            font-size: clamp(12px, 1vw, 16px);
            line-height: 1.25;
            gap: 4px;
            margin: 0;
            padding: 0;
        }
        #cardKeuangan .finance-summary-heading {
            font-size: clamp(10px, .78vw, 13px);
            line-height: 1.2;
        }
        #cardKeuangan .finance-transactions {
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        #cardKeuangan .finance-transaction-header,
        #cardKeuangan .finance-transaction {
            display: grid;
            grid-template-columns: minmax(44px, .85fr) minmax(0, 1.5fr) minmax(0, 1.2fr);
            align-items: center;
            gap: clamp(3px, .35vw, 6px);
            min-width: 0;
        }
        #cardKeuangan .finance-transaction-header {
            padding: 2px clamp(4px, .4vw, 6px);
            color: #94a3b8;
            font-size: clamp(8px, .58vw, 10px);
            font-weight: 700;
            line-height: 1.1;
        }
        #cardKeuangan .finance-transaction {
            padding: clamp(2px, .28vh, 4px) clamp(4px, .4vw, 6px);
            border-bottom: 1px solid rgba(148, 163, 184, .25);
            line-height: 1.15;
        }
        #cardKeuangan .finance-transaction-description {
            min-width: 0;
            overflow: hidden;
            color: #e2e8f0;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: clamp(8px, .62vw, 10px);
        }
        #cardKeuangan .finance-transaction-date { color: #cbd5e1; white-space: nowrap; font-size: clamp(8px, .58vw, 10px); }
        #cardKeuangan .finance-transaction-amount {
            min-width: 0;
            overflow: hidden;
            font-size: clamp(8px, .62vw, 10px);
            font-weight: 700;
            white-space: nowrap;
            text-align: right;
        }
        #cardKeuangan .finance-empty {
            padding: 6px 4px;
            color: #94a3b8;
            font-size: clamp(9px, .7vw, 12px);
            line-height: 1.25;
            text-align: center;
        }
        #cardKeuangan .finance-totals {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: clamp(3px, .35vw, 6px);
            margin-top: clamp(3px, .45vh, 6px);
        }
        #cardKeuangan .finance-total-card {
            min-width: 0;
            padding: clamp(4px, .5vh, 7px) clamp(3px, .3vw, 5px);
            border: 1px solid rgba(148, 163, 184, .3);
            border-radius: 8px;
            background: rgba(15, 23, 42, .78);
            line-height: 1.1;
            text-align: center;
        }
        #cardKeuangan .finance-total-label {
            display: block;
            margin-bottom: 3px;
            color: #cbd5e1;
            font-size: clamp(7px, .52vw, 9px);
            font-weight: 800;
            text-transform: uppercase;
        }
        #cardKeuangan .finance-total-value {
            display: block;
            overflow: hidden;
            font-size: clamp(8px, .65vw, 11px);
            font-weight: 900;
            text-overflow: clip;
            white-space: nowrap;
        }
        #cardKeuangan .finance-total-card.balance {
            border-color: rgba(245, 158, 11, .55);
            background: rgba(120, 53, 15, .28);
        }
        #cardKeuangan .finance-total-card.balance .finance-total-label,
        #cardKeuangan .finance-total-card.balance .finance-total-value {
            color: #fbbf24;
        }
        #cardJumat {
            border-top: 1px solid rgba(148, 163, 184, .35);
            padding-top: clamp(5px, .6vh, 8px);
            font-size: clamp(9px, .75vw, 12px);
        }
        #cardJumat .friday-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 4px;
            margin-bottom: 4px;
            font-size: clamp(11px, .9vw, 14px);
            line-height: 1.25;
        }
        #cardJumat > div:not(.friday-title) {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 6px;
            min-height: 2.4em;
            line-height: 1.4;
            padding: clamp(5px, .65vh, 8px) 0;
        }
        #cardJumat > div:not(.friday-title) > span:last-child {
            min-width: 0;
            text-align: right;
            overflow-wrap: anywhere;
        }
        #mediaContainer, #mediaContainer .media-item { min-width: 0; min-height: 0; }
        #mediaContainer .media-item { position: relative; }
        #mediaContainer .media-item img,
        #mediaContainer .media-item video {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        body > footer { min-width: 0; gap: clamp(4px, .7vw, 10px); margin: 0; }
        body > footer > div { min-width: 0; padding: clamp(4px, .6vw, 10px); }
        body > footer .font-orbitron { font-size: clamp(12px, 1.4vw, 20px); }
        body > div.bg-emerald-950 {
            min-width: 0;
            min-height: 0;
            padding-top: clamp(3px, .4vh, 7px);
            padding-bottom: clamp(3px, .4vh, 7px);
        }
        @media (max-height: 650px) {
            body > header > div:first-child > div:last-child p { display: none; }
            body > main > aside { padding: 5px; }
            body > main > aside > div:first-child { gap: 2px; }
            #nextPrayerName { font-size: clamp(18px, 2vw, 24px); }
            #mainCountdown { padding-top: 4px; padding-bottom: 4px; font-size: clamp(22px, 2.2vw, 30px); }
            body > main > aside #loopOverlay {
                flex-basis: clamp(175px, 29vh, 205px);
                min-height: 175px;
                max-height: 205px;
                padding: 7px;
                margin-top: 5px;
            }
            #cardKeuangan,
            #cardJumat { inset: 36px 8px 6px; }
            #cardKeuangan { font-size: 10px; }
            #cardKeuangan #cardTitle { font-size: 13px; }
            #cardKeuangan .finance-transaction { padding-top: 2px; padding-bottom: 2px; }
            #cardKeuangan .finance-totals { margin-top: 3px; }
            #cardJumat > div:not(.friday-title) { min-height: 2em; padding-top: 3px; padding-bottom: 3px; }
            body > footer > div { padding: 4px; }
        }
        @media (max-width: 700px) {
            body > header > div:first-child > div:last-child p,
            body > header > div:last-child > div:first-child { display: none; }
            body > main { gap: 5px; }
            body > main > aside { flex-basis: 36%; padding: 5px; }
        }
    </style>
</head>
<body class="bg-slate-50 dark:bg-[#042f2e] text-slate-900 dark:text-white transition-colors duration-500 h-screen flex flex-col justify-between p-3 overflow-hidden relative">

    <div id="overlay-sholat" class="hidden fixed inset-0 z-[9999] bg-overlay-sholat flex-col justify-center items-center text-center">
        <h1 id="teks-status-sholat" class="text-6xl md:text-7xl font-black text-emerald-400 mb-6 uppercase tracking-widest text-glow-emerald px-4">ADZAN BERKUMANDANG</h1>
        <div id="teks-timer-iqomah" class="hidden text-8xl md:text-9xl font-orbitron font-black text-amber-400 glow-amber">00:00</div>
        <div id="khotbah-running-text" class="hidden absolute bottom-16 w-full bg-slate-950/80 border-t-2 border-b-2 border-amber-500 py-3 shadow-2xl backdrop-blur-sm">
            <marquee scrollamount="12" class="text-3xl font-bold text-amber-400 tracking-wider"><?= htmlspecialchars($running_text, ENT_QUOTES, 'UTF-8'); ?></marquee>
        </div>
    </div>

    <div id="overlay-sleep" class="hidden fixed inset-0 z-[10000] bg-black"></div>

    <header class="bg-white dark:bg-gradient-to-r dark:from-emerald-950 dark:via-emerald-900 dark:to-slate-950 p-3.5 rounded-2xl border border-emerald-200 dark:border-b-2 dark:border-emerald-500/80 shadow-2xl flex justify-between items-center backdrop-blur-md transition-colors duration-500">
        <div class="flex items-center gap-4">
            <div class="bg-emerald-900/40 p-1.5 rounded-xl border border-emerald-500/40 shadow-inner">
                <img src="assets/img/logo.png" alt="Logo Musholla" class="h-14 w-auto object-contain drop-shadow-md">
            </div>
            <div>
                <h1 class="text-2xl font-black tracking-wider text-slate-900 dark:text-white uppercase flex items-center gap-2">MUSHOLLA AL-MUHAJIRIN</h1>
                <p class="text-slate-700 dark:text-slate-200 mt-2 text-sm">
                    <i class="fa-solid fa-location-dot text-emerald-600 dark:text-emerald-400"></i> Jl. Haji Naman No.50, Pondok Kelapa, Duren Sawit, Jakarta Timur
                </p>
            </div>
        </div>

        <div class="flex items-center gap-6">
            <div class="bg-emerald-900/70 px-4 py-2 rounded-xl border border-emerald-500/50 text-center shadow-md">
                <span class="text-emerald-300 block text-[10px] font-bold uppercase tracking-widest">
                    <i class="fa-solid fa-calendar-star text-amber-400 mr-1"></i> Hari Besar Terdekat
                </span>
                <span class="bg-amber-500 text-slate-950 px-2.5 py-0.5 rounded-md text-xs font-black inline-block mt-1 tracking-wide shadow">
                    <?php if ($hari_besar): ?>
                        <?= htmlspecialchars($hari_besar['nama_event'], ENT_QUOTES, 'UTF-8'); ?>
                        <?= htmlspecialchars($hari_besar['tahun_hijriah'], ENT_QUOTES, 'UTF-8'); ?>
                        - <?= (int)$hari_besar['sisa_hari']; ?> Hari Lagi
                    <?php elseif ($hari_besar_gagal): ?>
                        Data hari besar gagal dimuat
                    <?php else: ?>
                        Belum ada hari besar terjadwal
                    <?php endif; ?>
                </span>
            </div>
            <div class="text-right pl-4 border-l border-slate-800">
                <div id="clock" class="font-orbitron text-3xl font-black text-emerald-400 text-glow-emerald tracking-wider">00:00:00</div>
                <div id="date" class="text-slate-700 dark:text-slate-100 text-sm mt-1 flex items-center justify-end gap-1.5">
                    <i class="fa-regular fa-clock text-emerald-400"></i> <span id="dateText"><?= $hari_indo[date('l')]; ?>, <?= date('d'); ?> <?= $bulan_sekarang; ?> <?= date('Y'); ?> | <?= htmlspecialchars($tanggal_hijriah, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
        </div>
    </header>

    <main class="flex gap-4 my-3 flex-1 h-[calc(100vh-175px)] z-10">
        <section class="flex-[2] bg-white dark:bg-slate-900 rounded-2xl overflow-hidden border border-slate-200 dark:border-slate-800 relative flex items-center justify-center shadow-2xl backdrop-blur-sm transition-colors duration-500">
            <div id="mediaContainer" class="galeri-slider relative w-full h-full overflow-hidden rounded-2xl bg-black">
                <?php if (!empty($gambar_galeri)): ?>
                    <?php foreach ($gambar_galeri as $index => $gambar): ?>
                        <div class="media-item slide-item <?= $index === 0 ? 'block' : 'hidden'; ?> w-full h-full transition-opacity duration-700"
                             data-caption="<?= htmlspecialchars($gambar['caption'], ENT_QUOTES, 'UTF-8'); ?>"
                             data-tgl="<?= htmlspecialchars((string)$gambar['date'], ENT_QUOTES, 'UTF-8'); ?>"
                             data-mulai="<?= htmlspecialchars((string)$gambar['start'], ENT_QUOTES, 'UTF-8'); ?>"
                             data-selesai="<?= htmlspecialchars((string)$gambar['end'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php if ($gambar['type'] === 'video'): ?>
                                <video src="<?= htmlspecialchars($gambar['path'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full h-full object-contain rounded-2xl" muted playsinline preload="metadata"></video>
                            <?php else: ?>
                                <img src="<?= htmlspecialchars($gambar['path'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full h-full object-cover rounded-2xl" alt="<?= htmlspecialchars($gambar['caption'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center h-full text-slate-500">
                        <i class="fa-solid fa-image text-5xl mb-2"></i>
                        <p class="text-sm"><?= $galeri_gagal ? 'Media galeri gagal dimuat.' : 'Belum ada media galeri.'; ?></p>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($gambar_galeri): ?>
                <div class="absolute bottom-4 left-4 max-w-[85%] bg-slate-950/80 backdrop-blur-md px-4 py-2 rounded-xl border border-emerald-500/40 text-xs text-white flex items-center gap-2.5 shadow-2xl">
                    <i class="fa-solid fa-circle-info text-emerald-400 text-base"></i>
                    <span id="mediaCaptionText" class="font-medium tracking-wide text-emerald-100"><?= htmlspecialchars($gambar_galeri[0]['caption'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>
        </section>

        <aside class="flex-1 bg-white dark:bg-slate-900 rounded-2xl border-2 border-emerald-600/70 p-4 flex flex-col justify-between text-center shadow-2xl glow-emerald relative overflow-hidden backdrop-blur-md transition-colors duration-500">
            <div class="flex flex-col items-center w-full">
                <div class="bg-emerald-950/80 px-3 py-1 rounded-full border border-emerald-500/40 mb-2 inline-block">
                    <h3 class="text-[11px] font-bold text-emerald-300 tracking-widest uppercase flex items-center gap-1.5"><i class="fa-solid fa-hourglass-half text-amber-400"></i> MENUJU WAKTU SHOLAT</h3>
                </div>
                <div id="nextPrayerName" class="text-3xl text-slate-700 dark:text-gray-100 font-semibold tracking-wide uppercase mb-2"><?= $nama_dzuhur; ?></div>
                <div id="mainCountdown" class="font-orbitron text-4xl font-black text-yellow-600 dark:text-yellow-400 bg-amber-50 dark:bg-slate-950/90 py-2.5 px-6 rounded-2xl border-2 border-amber-500/80 tracking-widest mb-2 shadow-xl glow-amber text-glow-amber w-full">-00:00:00</div>
                <p id="countdownStatus" class="text-emerald-700 dark:text-emerald-300 text-sm mt-2 flex items-center gap-1"><i class="fa-solid fa-circle-info"></i> Persiapkan diri untuk sholat berjamaah</p>
            </div>

            <div id="loopOverlay" class="w-full bg-white dark:bg-slate-900 rounded-xl p-3.5 border border-slate-200 dark:border-slate-700/80 text-left transition-all duration-500 shadow-lg mt-3">
                <div id="cardTitle" class="text-slate-800 dark:text-white font-bold text-lg uppercase mb-2 pb-1.5 border-b border-slate-200 dark:border-slate-700/80 flex items-center justify-between">
                    <span class="flex items-center gap-2"><i class="fa-solid fa-circle-info text-amber-400"></i> Informasi Musholla</span>
                    <span class="text-[10px] text-slate-500 dark:text-slate-400 font-normal">Bergantian</span>
                </div>

                <div id="cardKeuangan" class="text-xs fade-in">
                    <div class="finance-summary-heading text-emerald-700 dark:text-emerald-300 font-bold uppercase tracking-wider">
                        <i class="fa-solid fa-receipt text-emerald-500"></i> Transaksi Kas Terbaru
                    </div>
                    <div class="finance-transactions">
                        <div class="finance-transaction-header">
                            <span>Tgl/Hari</span>
                            <span>Keterangan</span>
                            <span class="text-right">Nominal</span>
                        </div>
                        <?php if ($transaksi_kas_terbaru): ?>
                            <?php foreach ($transaksi_kas_terbaru as $transaksi): ?>
                                <?php $is_income = strtolower(trim((string)($transaksi['jenis'] ?? ''))) === 'pemasukan'; ?>
                                <?php
                                    $transaction_timestamp = strtotime((string)($transaksi['tanggal'] ?? ''));
                                    $transaction_day = $transaction_timestamp
                                        ? ($hari_indo[date('l', $transaction_timestamp)] . ' ' . date('d/m', $transaction_timestamp))
                                        : '--';
                                ?>
                                <div class="finance-transaction">
                                    <span class="finance-transaction-date"><?= htmlspecialchars($transaction_day, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="finance-transaction-description" title="<?= htmlspecialchars((string)($transaksi['keterangan'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                        <?= htmlspecialchars((string)($transaksi['keterangan'] ?? 'Transaksi kas'), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                    <span class="finance-transaction-amount <?= $is_income ? 'text-emerald-300' : 'text-rose-300'; ?>">
                                        <?= $is_income ? '+' : '-'; ?> Rp <?= number_format((float)($transaksi['nominal'] ?? 0), 0, ',', '.'); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php elseif ($transaksi_kas_gagal): ?>
                            <div class="finance-empty text-rose-400">Rincian transaksi tidak dapat dimuat.</div>
                        <?php else: ?>
                            <div class="finance-empty">Belum ada transaksi kas.</div>
                        <?php endif; ?>
                    </div>
                    <div class="finance-totals">
                        <div class="finance-total-card">
                            <span class="finance-total-label">Pemasukan</span>
                            <span class="finance-total-value text-emerald-300">Rp <?= number_format($tot_masuk, 0, ',', '.'); ?></span>
                        </div>
                        <div class="finance-total-card">
                            <span class="finance-total-label">Pengeluaran</span>
                            <span class="finance-total-value text-rose-300">Rp <?= number_format($tot_keluar, 0, ',', '.'); ?></span>
                        </div>
                        <div class="finance-total-card balance">
                            <span class="finance-total-label">Saldo Kas</span>
                            <span class="finance-total-value">Rp <?= number_format($saldo_akhir, 0, ',', '.'); ?></span>
                        </div>
                    </div>
                </div>

                <div id="cardJumat" class="space-y-2 text-xs fade-in">
                    <div class="friday-title text-slate-800 dark:text-white font-bold uppercase">
                        <span>Petugas Sholat Jumat</span>
                        <span class="text-slate-500 dark:text-slate-400 font-normal">Pekan Ini</span>
                    </div>
                    <div class="flex justify-between items-center text-slate-700 dark:text-slate-300">
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-user-tie text-emerald-400"></i> Khatib:</span>
                        <span class="text-slate-800 dark:text-white font-bold"><?= htmlspecialchars($khatib); ?></span>
                    </div>
                    <div class="flex justify-between items-center text-slate-700 dark:text-slate-300">
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-user-check text-emerald-400"></i> Imam:</span>
                        <span class="text-slate-800 dark:text-white font-bold"><?= htmlspecialchars($imam); ?></span>
                    </div>
                    <div class="flex justify-between items-center text-slate-700 dark:text-slate-300 border-t border-slate-200 dark:border-slate-700/80 pt-2 mt-1">
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-bullhorn text-amber-400"></i> Muadzin:</span>
                        <span class="text-emerald-700 dark:text-emerald-400 font-bold"><?= htmlspecialchars($muadzin); ?></span>
                    </div>
                </div>
            </div>
        </aside>
    </main>

    <footer class="grid grid-cols-7 gap-2.5 mb-1.5 z-10 relative">
        <?php 
        $icons = ['fa-moon text-slate-500', 'fa-cloud-moon text-emerald-400', 'fa-sun text-amber-500/70', 'fa-sun text-slate-500', 'fa-cloud-sun text-slate-500', 'fa-cloud-moon text-slate-500', 'fa-moon text-slate-500'];$i = 0;
        foreach($waktu as $nama =>$jam): 
        ?>
        <div id="card-<?= strtolower($nama); ?>" class="bg-slate-900/90 p-2.5 rounded-xl text-center border border-slate-800 transition-all">
            <div class="text-[11px] font-bold text-slate-400 mb-1 flex items-center justify-center gap-1">
                <i class="fa-solid <?= $icons[$i]; ?>"></i> <?= $nama; ?>
            </div>
            <div class="font-orbitron text-xl font-bold text-slate-200"><?= $jam; ?></div>
        </div>
        <?php $i++; endforeach; ?>
    </footer>

    <div class="bg-emerald-950 border-t-2 border-emerald-500 py-1.5 overflow-hidden rounded-xl shadow-inner flex items-center z-10 relative">
        <div class="bg-emerald-900 px-3 py-0.5 text-[11px] font-bold text-emerald-200 uppercase tracking-wider rounded-r-lg mr-2 z-10 flex items-center gap-1.5 shadow">
            <i class="fa-solid fa-bullhorn text-amber-400 animate-pulse"></i> INFO
        </div>
        <div id="runningTextDisplay" class="animate-marquee-text text-xs text-emerald-300 font-medium"><?= htmlspecialchars($running_text); ?></div>
    </div>

    <audio id="alarmSound" src="<?= htmlspecialchars($alarm_audio_src, ENT_QUOTES, 'UTF-8'); ?>" preload="auto"></audio>
    <audio id="audioTarhim" src="assets/audio/tarhim.mp3" preload="auto"></audio>

    <script>
        const mediaItems = document.querySelectorAll('.media-item');
        const mediaCaptionText = document.getElementById('mediaCaptionText');
        let mediaIndex = 0;
        let mediaTimeout; 

        const prayerList = [
            { name: 'IMSAK', time: '<?= $waktu["IMSAK"]; ?>', id: 'card-imsak' },
            { name: 'SUBUH', time: '<?= $waktu["SUBUH"]; ?>', id: 'card-subuh', iqomah: <?= $iqomah_db['SUBUH'] ?? 12; ?> },
            { name: 'SYURUQ', time: '<?= $waktu["SYURUQ"]; ?>', id: 'card-syuruq' },
            { name: '<?= $nama_dzuhur; ?>', time: '<?= $waktu[$nama_dzuhur]; ?>', id: 'card-<?= strtolower($nama_dzuhur); ?>', iqomah: <?=$iqomah_db['DZUHUR'] ?? 10; ?> },
            { name: 'ASHAR', time: '<?= $waktu["ASHAR"]; ?>', id: 'card-ashar', iqomah: <?= $iqomah_db['ASHAR'] ?? 10; ?> },
            { name: 'MAGHRIB', time: '<?= $waktu["MAGHRIB"]; ?>', id: 'card-maghrib', iqomah: <?= $iqomah_db['MAGHRIB'] ?? 7; ?> },
            { name: 'ISYA', time: '<?= $waktu["ISYA"]; ?>', id: 'card-isya', iqomah: <?= $iqomah_db['ISYA'] ?? 10; ?> }
        ];

        let flags = { alarmAdzan: false, alarmIqomah: false };
        let tarhimPlayedFor = null;
        const alarmSound = document.getElementById('alarmSound');
        const tarhimAudio = document.getElementById('audioTarhim');
        let pendingAlarmPlayback = false;
        let pendingTarhimPlayback = false;

        function playAlarmSound() {
            if (!alarmSound) return;
            pendingAlarmPlayback = false;
            alarmSound.currentTime = 0;
            alarmSound.play().catch(error => {
                pendingAlarmPlayback = true;
                console.warn('Audio alarm belum dapat diputar:', error);
            });
        }

        function playTarhimSound() {
            if (!tarhimAudio) return;
            tarhimAudio.currentTime = 0;
            tarhimAudio.play().then(() => {
                pendingTarhimPlayback = false;
            }).catch(error => {
                pendingTarhimPlayback = true;
                console.warn('Audio Tarhim belum dapat diputar:', error);
            });
        }

        function unlockAudioElement(audio) {
            if (!audio) return Promise.resolve();
            const wasMuted = audio.muted;
            audio.muted = true;
            return audio.play().then(() => {
                audio.pause();
                audio.currentTime = 0;
                audio.muted = wasMuted;
            }).catch(error => {
                audio.muted = wasMuted;
                throw error;
            });
        }

        function unlockDisplayAudio() {
            const pendingPlayback = [];
            if (pendingAlarmPlayback && alarmSound) {
                pendingAlarmPlayback = false;
                pendingPlayback.push(alarmSound.play().catch(error => {
                    pendingAlarmPlayback = true;
                    throw error;
                }));
            }
            if (pendingTarhimPlayback && tarhimAudio) {
                pendingTarhimPlayback = false;
                pendingPlayback.push(tarhimAudio.play().catch(error => {
                    pendingTarhimPlayback = true;
                    throw error;
                }));
            }

            const unlockPromise = pendingPlayback.length
                ? Promise.all(pendingPlayback)
                : Promise.all([unlockAudioElement(alarmSound), unlockAudioElement(tarhimAudio)]);
            unlockPromise.then(() => {
                document.removeEventListener('click', unlockDisplayAudio);
                document.removeEventListener('keydown', unlockDisplayAudio);
            }).catch(error => {
                console.warn('Browser belum mengizinkan audio otomatis:', error);
            });
        }

        document.addEventListener('click', unlockDisplayAudio);
        document.addEventListener('keydown', unlockDisplayAudio);

        function updateClockAndAutomation() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            document.getElementById('clock').innerText = `${hours}:${minutes}:${seconds}`;

            const gregorianParts = new Intl.DateTimeFormat('id-ID', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            }).formatToParts(now);
            const gregorianPart = type => gregorianParts.find(part => part.type === type).value;
            const gregorianDate = `${gregorianPart('weekday')}, ${gregorianPart('day')} ${gregorianPart('month')} ${gregorianPart('year')}`;
            const hijriParts = new Intl.DateTimeFormat('en-US-u-ca-islamic-umalqura-nu-latn', {
                day: 'numeric',
                month: 'numeric',
                year: 'numeric'
            }).formatToParts(now);
            const hijriPart = type => hijriParts.find(part => part.type === type).value;
            const hijriMonths = [
                'Muharram', 'Safar', 'Rabiul Awwal', 'Rabiul Akhir',
                'Jumadil Awwal', 'Jumadil Akhir', 'Rajab', 'Syaban',
                'Ramadan', 'Syawal', 'Zulkaidah', 'Zulhijah'
            ];
            const hijriMonth = hijriMonths[Number(hijriPart('month')) - 1];
            document.getElementById('dateText').textContent =
                `${gregorianDate} | ${hijriPart('day')} ${hijriMonth} ${hijriPart('year')} H`;

            const currentSeconds = now.getHours() * 3600 + now.getMinutes() * 60 + now.getSeconds();
            let nextPrayer = null, targetSeconds = 0;

            for (let item of prayerList) {
                const [pHours, pMins] = item.time.split(':').map(Number);
                const pSeconds = pHours * 3600 + pMins * 60;
                if (pSeconds > currentSeconds) { nextPrayer = item; targetSeconds = pSeconds; break; }
            }
            if (!nextPrayer) {
                nextPrayer = prayerList[0]; 
                const [pHours, pMins] = nextPrayer.time.split(':').map(Number); 
                targetSeconds = (pHours * 3600 + pMins * 60) + (24 * 3600);
            }

            const imsakPrayer = prayerList.find(item => item.name === 'IMSAK');
            const [imsakHours, imsakMinutes] = imsakPrayer.time.split(':').map(Number);
            const imsakSeconds = imsakHours * 3600 + imsakMinutes * 60;
            let imsakDiffSec = imsakSeconds - currentSeconds;
            if (imsakDiffSec < 0) imsakDiffSec += 24 * 3600;
            const isImsakWindow = imsakDiffSec <= 3600;
            if (isImsakWindow) {
                nextPrayer = imsakPrayer;
                targetSeconds = currentSeconds + imsakDiffSec;
            }

            document.getElementById('nextPrayerName').innerText = nextPrayer.name;
            let diffSec = targetSeconds - currentSeconds;
            let h = String(Math.floor(diffSec / 3600)).padStart(2, '0');
            let m = String(Math.floor((diffSec % 3600) / 60)).padStart(2, '0');
            let s = String(diffSec % 60).padStart(2, '0');
            document.getElementById('mainCountdown').innerText = `-${h}:${m}:${s}`;

            const prayerDate = new Date(now);
            if (targetSeconds >= 24 * 3600) {
                prayerDate.setDate(prayerDate.getDate() + 1);
            }
            const tarhimOccurrence = `${prayerDate.getFullYear()}-${String(prayerDate.getMonth() + 1).padStart(2, '0')}-${String(prayerDate.getDate()).padStart(2, '0')}:${nextPrayer.id}`;
            if (nextPrayer.iqomah !== undefined && diffSec <= 180 && diffSec >= 1 && tarhimPlayedFor !== tarhimOccurrence) {
                tarhimPlayedFor = tarhimOccurrence;
                playTarhimSound();
            }

            prayerList.forEach(p => {
                const card = document.getElementById(p.id);
                if (card) { card.className = p.name === nextPrayer.name ? "bg-gradient-to-br from-emerald-600 via-emerald-700 to-emerald-800 p-2.5 rounded-xl text-center border-2 border-emerald-400 shadow-xl transform -translate-y-1 glow-emerald" : "bg-slate-900/90 p-2.5 rounded-xl text-center border border-slate-800 transition-all"; }
            });

            let overlayAktif = false;
            let stateTerkini = 'NORMAL';
            
            if(document.getElementById('khotbah-running-text')) {
                document.getElementById('khotbah-running-text').classList.add('hidden');
            }

            for (let p of prayerList) {
                if (p.name === 'IMSAK' || p.name === 'SYURUQ') continue; 
                
                let isJumat = (p.name === 'JUMAT');
                let [pHours, pMins] = p.time.split(':').map(Number);
                let dSholat = pHours * 3600 + pMins * 60;
                let dAkhirAdzan = dSholat + 150; 
                let durasiTunggu = isJumat ? 1800 : ((p.iqomah || 10) * 60);
                let dAkhirIqomah = dAkhirAdzan + durasiTunggu;
                let dAkhirSholat = dAkhirIqomah + (15 * 60); 

                if (currentSeconds >= dSholat - 180 && currentSeconds < dSholat) {
                    stateTerkini = 'TARHIM';
                } else if (currentSeconds >= dSholat && currentSeconds < dAkhirAdzan) {
                    stateTerkini = 'ADZAN';
                    overlayAktif = true;
                    document.getElementById('teks-status-sholat').innerText = `ADZAN ${p.name} BERKUMANDANG`;
                    document.getElementById('teks-timer-iqomah').classList.add('hidden');
                    if (tarhimAudio) {
                        tarhimAudio.pause();
                        tarhimAudio.currentTime = 0;
                    }
                    pendingTarhimPlayback = false;
                    if (!flags.alarmAdzan) { playAlarmSound(); flags.alarmAdzan = true; }
                } else if (currentSeconds >= dAkhirAdzan && currentSeconds < dAkhirIqomah) {
                    if (isJumat) {
                        stateTerkini = 'KHOTBAH';
                        overlayAktif = true;
                        document.getElementById('teks-status-sholat').innerText = "DENGARKAN KHOTIB DENGAN FOKUS";
                        document.getElementById('teks-timer-iqomah').classList.add('hidden');
                        document.getElementById('khotbah-running-text').classList.remove('hidden');
                    } else {
                        stateTerkini = 'IQOMAH';
                        overlayAktif = true;
                        document.getElementById('teks-status-sholat').innerText = `MENUJU IQOMAH ${p.name}`;
                        document.getElementById('teks-timer-iqomah').classList.remove('hidden');
                        let sisaIqomah = dAkhirIqomah - currentSeconds;
                        document.getElementById('teks-timer-iqomah').innerText = `${String(Math.floor(sisaIqomah/60)).padStart(2,'0')}:${String(sisaIqomah%60).padStart(2,'0')}`;
                    }
                } else if (currentSeconds >= dAkhirIqomah && currentSeconds < dAkhirSholat) {
                    stateTerkini = 'SHOLAT';
                    overlayAktif = true;
                    document.getElementById('teks-status-sholat').innerText = "SEDANG MENUNAIKAN SHOLAT BERJAMAAH";
                    document.getElementById('teks-timer-iqomah').classList.add('hidden');
                    if (!isJumat && !flags.alarmIqomah) { playAlarmSound(); flags.alarmIqomah = true; }
                }
            }

            if (stateTerkini === 'NORMAL') { flags.alarmAdzan = false; flags.alarmIqomah = false; }

            const countdownStatus = document.getElementById('countdownStatus');
            if (countdownStatus) {
                countdownStatus.innerHTML = isImsakWindow
                    ? '<i class="fa-solid fa-circle-info"></i> Persiapkan diri untuk ibadah sahur &amp; sholat subuh'
                    : '<i class="fa-solid fa-circle-info"></i> Persiapkan diri untuk sholat berjamaah';
            }

            const overlaySholat = document.getElementById('overlay-sholat');
            if (overlaySholat) {
                if (overlayAktif) { overlaySholat.classList.remove('hidden'); overlaySholat.classList.add('flex'); } 
                else { overlaySholat.classList.add('hidden'); overlaySholat.classList.remove('flex'); }
            }

            let dBangun = imsakSeconds - 3600;
            const overlaySleep = document.getElementById('overlay-sleep');
            if (overlaySleep) {
                if (!isImsakWindow && (currentSeconds >= 79200 || currentSeconds < dBangun)) { overlaySleep.classList.remove('hidden'); overlaySleep.classList.add('block'); } 
                else { overlaySleep.classList.add('hidden'); overlaySleep.classList.remove('block'); }
            }
        }

        // --- SISTEM PEMERIKSA JADWAL ACARA KHUSUS ---
        function checkSchedule(item) {
            let tgl = item.getAttribute('data-tgl');
            let start = item.getAttribute('data-mulai');
            let end = item.getAttribute('data-selesai');
            
            if (!tgl || !start || !end || tgl === '' || start === '' || start === '00:00:00' || start === '00:00') {
                return false; 
            }
            
            let now = new Date();
            let todayStr = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0') + '-' + String(now.getDate()).padStart(2,'0');
            
            if (tgl !== todayStr) return false;

            let currentMins = now.getHours() * 60 + now.getMinutes();
            let startMins = (parseInt(start.split(':')[0]) || 0) * 60 + (parseInt(start.split(':')[1]) || 0);
            let endMins = (parseInt(end.split(':')[0]) || 0) * 60 + (parseInt(end.split(':')[1]) || 0);

            return (currentMins >= startMins && currentMins <= endMins);
        }

        // --- LOGIKA SLIDER DENGAN MODE EKSLUSIF ---
        function playNextMedia() {
            if (mediaItems.length === 0) return;
            
            clearTimeout(mediaTimeout);

            mediaItems.forEach(item => { 
                item.classList.add('hidden'); 
                const video = item.querySelector('video');
                if (video) {
                    video.pause();
                    video.onended = null;
                }
            });

            let activeScheduledIndex = -1;
            mediaItems.forEach((item, index) => {
                if (checkSchedule(item)) {
                    activeScheduledIndex = index;
                }
            });

            let currentItem;

            if (activeScheduledIndex !== -1) {
                mediaIndex = activeScheduledIndex;
                currentItem = mediaItems[mediaIndex];
            } else {
                let found = false;
                let attempts = 0;

                while (!found && attempts < mediaItems.length) {
                    currentItem = mediaItems[mediaIndex];
                    let tgl = currentItem.getAttribute('data-tgl');
                    let start = currentItem.getAttribute('data-mulai');
                    
                    if (!tgl || !start || start === '' || start === '00:00:00' || start === '00:00') {
                        found = true;
                    } else {
                        mediaIndex = (mediaIndex + 1) % mediaItems.length;
                        attempts++;
                    }
                }

                if (!found) {
                    mediaTimeout = setTimeout(playNextMedia, 8000);
                    return; 
                }
                mediaIndex = (mediaIndex + 1) % mediaItems.length;
            }

            currentItem.classList.remove('hidden');
            const caption = currentItem.getAttribute('data-caption');
            if (mediaCaptionText && caption) { mediaCaptionText.innerText = caption; }

            const currentVideo = currentItem.querySelector('video');
            if (currentVideo) { 
                currentVideo.load(); 
                currentVideo.play().catch(e => {
                    mediaTimeout = setTimeout(playNextMedia, 8000); 
                }); 

                currentVideo.onended = () => {
                    playNextMedia();
                };
            } else {
                mediaTimeout = setTimeout(playNextMedia, 8000);
            }
        }

        // --- SKRIP CEK RUNNING TEXT REALTIME DARI DATABASE ---
        setInterval(function() {
            fetch('get_running_text.php')
                .then(res => {
                    if (!res.ok) {
                        throw new Error(`Gagal memperbarui running text: HTTP ${res.status}`);
                    }
                    return res.text();
                })
                .then(data => {
                    if (data && data.trim() !== '') {
                        const marquee = document.getElementById('runningTextDisplay');
                        if (marquee && marquee.innerText !== data.trim()) {
                            marquee.innerText = data.trim();
                        }
                    }
                }).catch(error => console.error(error));
        }, 3000);

        // Perbarui data kas secara berkala tanpa memuat ulang seluruh halaman.
        setInterval(function() {
            fetch('index.php')
                .then(res => {
                    if (!res.ok) {
                        throw new Error(`Gagal memperbarui data kas: HTTP ${res.status}`);
                    }
                    return res.text();
                })
                .then(html => {
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const newCard = doc.getElementById('cardKeuangan');
                    const currentCard = document.getElementById('cardKeuangan');

                    if (newCard && currentCard) {
                        currentCard.innerHTML = newCard.innerHTML;
                    }
                })
                .catch(error => console.error(error));
        }, 5000);

        setInterval(updateClockAndAutomation, 1000);

        updateClockAndAutomation(); 
        playNextMedia(); 
    </script>
    <script>
        function updateThemeAutomatically() {
            const hour = new Date().getHours();
            const isNight = (hour < 6 || hour >= 18);
            const htmlElement = document.documentElement;

            if (isNight) {
                htmlElement.classList.add('dark');
            } else {
                htmlElement.classList.remove('dark');
            }
        }

        updateThemeAutomatically();
        setInterval(updateThemeAutomatically, 60000);
    </script>
</body>
</html>