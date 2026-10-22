<?php
require_once __DIR__ . '/../config/koneksi.php';

// Display TV reads kas transactions directly from the database, so sending only refreshes the page state.
if (isset($_POST['kirim_tv'])) {
    header('Location: kas.php?status=sent');
    exit;
}

// ====== PROSES SIMPAN TRANSAKSI ======
if (isset($_POST['simpan_baris'])) {
    $fields = ['tanggal', 'jenis', 'keterangan', 'pekan_ke', 'bulan', 'nominal'];
    $rows = [];
    $save_failed = false;
    $inserted_count = 0;

    foreach ($fields as $field) {
        if (!isset($_POST[$field]) || !is_array($_POST[$field])) {
            $save_failed = true;
            break;
        }
    }

    if (!$save_failed) {
        $count = count($_POST['tanggal']);
        foreach ($fields as $field) {
            if (count($_POST[$field]) !== $count) {
                $save_failed = true;
                break;
            }
        }
    }

    if (!$save_failed) {
        for ($i = 0; $i < $count; $i++) {
            $raw_description = $_POST['keterangan'][$i];
            $raw_amount = $_POST['nominal'][$i];
            if (!is_string($raw_description) || !is_scalar($raw_amount)) {
                $save_failed = true;
                break;
            }

            $description = trim($raw_description);
            $amount = preg_replace('/\D/', '', (string)$raw_amount);
            if ($description === '' && $amount === '') {
                continue;
            }

            $date = $_POST['tanggal'][$i];
            $type = $_POST['jenis'][$i];
            $week = $_POST['pekan_ke'][$i];
            $month = $_POST['bulan'][$i];
            if (
                !is_string($date)
                || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
                || !checkdate((int)substr($date, 5, 2), (int)substr($date, 8, 2), (int)substr($date, 0, 4))
                || !is_string($type)
                || !in_array(strtolower($type), ['pemasukan', 'pengeluaran'], true)
                || $description === ''
                || $amount === ''
                || ltrim($amount, '0') === ''
                || (!is_string($week) && $week !== null)
                || ($week !== null && $week !== '' && (!ctype_digit($week) || (int)$week < 1 || (int)$week > 5))
                || (!is_string($month) && $month !== null)
            ) {
                $save_failed = true;
                break;
            }

            $rows[] = [
                $date,
                strtolower($type) === 'pemasukan' ? 'Pemasukan' : 'Pengeluaran',
                $amount,
                $description,
                $week === '' || $week === null ? null : (int)$week,
                $month === '' ? null : trim($month),
            ];
        }
    }

    if (!$save_failed && count($rows) > 0) {
        if (!mysqli_begin_transaction($koneksi)) {
            $save_failed = true;
            error_log('Could not start kas transaction: ' . mysqli_error($koneksi));
        } else {
            $statement = mysqli_prepare(
                $koneksi,
                'INSERT INTO keuangan (jenis, tanggal, nominal, keterangan, pekan_ke, bulan) VALUES (?, ?, ?, ?, ?, ?)'
            );
            if (!$statement) {
                $save_failed = true;
                error_log('Prepare kas transaction insert failed: ' . mysqli_error($koneksi));
            } else {
                foreach ($rows as $row) {
                    [$date, $type, $amount, $description, $week, $month] = $row;
                    if (
                        !mysqli_stmt_bind_param($statement, 'ssssss', $type, $date, $amount, $description, $week, $month)
                        || !mysqli_stmt_execute($statement)
                    ) {
                        $save_failed = true;
                        error_log('Kas transaction insert failed: ' . mysqli_stmt_error($statement));
                        break;
                    }
                    $inserted_count++;
                }
                mysqli_stmt_close($statement);
            }

            if ($save_failed || !mysqli_commit($koneksi)) {
                mysqli_rollback($koneksi);
                $save_failed = true;
                $inserted_count = 0;
                error_log('Kas transaction save failed; changes rolled back.');
            }
        }
    } elseif (!$save_failed) {
        $save_failed = true;
    }

    if ($save_failed) {
        header('Location: kas.php?status=save_failed');
    } else {
        header('Location: kas.php?status=success&count=' . $inserted_count);
    }
    exit;
}

// ====== PROSES HAPUS TRANSAKSI ======
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $delete_succeeded = false;

    if ($id > 0) {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                if (!($koneksi instanceof mysqli) || !mysqli_ping($koneksi)) {
                    $koneksi = musholla_reconnect_database();
                }
            } catch (mysqli_sql_exception $exception) {
                error_log('Kas database ping failed before delete: ' . $exception->getMessage());
                $koneksi = musholla_reconnect_database();
            }

            if (!($koneksi instanceof mysqli)) {
                break;
            }

            try {
                $delete_result = mysqli_query($koneksi, "DELETE FROM keuangan WHERE id=$id");
                $delete_succeeded = (bool)$delete_result;
                break;
            } catch (mysqli_sql_exception $exception) {
                error_log('Kas transaction delete failed: ' . $exception->getMessage());
                if (!in_array((int)$exception->getCode(), [2006, 2013, 2055], true) || $attempt === 1) {
                    break;
                }

                $koneksi = musholla_reconnect_database();
            }
        }
    }

    header('Location: kas.php?status=' . ($delete_succeeded ? 'deleted' : 'delete_failed'));
    exit;
}

// ====== HITUNG REKAPITULASI KAS TOTAL ======
$totals_query = mysqli_query(
    $koneksi,
    "SELECT
        COALESCE(SUM(CASE WHEN jenis = 'Pemasukan' THEN nominal ELSE 0 END), 0) AS total_pemasukan,
        COALESCE(SUM(CASE WHEN jenis = 'Pengeluaran' THEN nominal ELSE 0 END), 0) AS total_pengeluaran
     FROM keuangan
     LIMIT 1"
);
$totals = $totals_query ? mysqli_fetch_assoc($totals_query) : false;
$kas_load_error = !$totals;
$tot_masuk = $totals ? (float)$totals['total_pemasukan'] : 0;
$tot_keluar = $totals ? (float)$totals['total_pengeluaran'] : 0;
$saldo_akhir = $tot_masuk - $tot_keluar;

// ====== AMBIL DAFTAR TRANSAKSI ======
$query_kas = mysqli_query($koneksi, "SELECT * FROM keuangan ORDER BY tanggal DESC, id DESC LIMIT 100");
$kas_load_error = $kas_load_error || !$query_kas;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kas Musholla - Admin DKM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #050b14; color: #ffffff; }
        .table-grid th, .table-grid td { border: 1px solid #1e293b; }
    </style>
</head>
<body class="p-6 min-h-screen flex flex-col justify-between">

    <div class="max-w-7xl mx-auto w-full">
        <!-- HEADER -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <a href="index.php" class="text-xs text-emerald-400 font-semibold uppercase tracking-wider flex items-center gap-2 mb-1 hover:underline">
                    <i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard
                </a>
                <h1 class="text-2xl font-black text-white uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-wallet text-amber-400"></i> Pencatatan Kas Musholla
                </h1>
            </div>
            <a href="../index.php" target="_blank" class="bg-rose-600/80 hover:bg-rose-600 text-white text-xs font-bold py-2.5 px-4 rounded-xl transition-all shadow-lg flex items-center gap-2">
                <i class="fa-solid fa-desktop"></i> Lihat Display TV
            </a>
        </div>

        <!-- NOTIFIKASI -->
        <?php if (isset($_GET['status']) && $_GET['status'] == 'sent'): ?>
            <div class="mb-6 bg-emerald-950/80 border border-emerald-500/50 text-emerald-300 p-4 rounded-xl text-xs font-semibold flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-base"></i> Data kas tersimpan sudah disinkronkan ke Display TV.
            </div>
        <?php elseif (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
            <div class="mb-6 bg-emerald-950/80 border border-emerald-500/50 text-emerald-300 p-4 rounded-xl text-xs font-semibold flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-base"></i> Transaksi kas berhasil disimpan!
            </div>
        <?php elseif (isset($_GET['status']) && $_GET['status'] == 'deleted'): ?>
            <div class="mb-6 bg-rose-950/80 border border-rose-500/50 text-rose-300 p-4 rounded-xl text-xs font-semibold flex items-center gap-2">
                <i class="fa-solid fa-trash-can text-base"></i> Transaksi berhasil dihapus!
            </div>
        <?php elseif (isset($_GET['status']) && $_GET['status'] == 'save_failed'): ?>
            <div class="mb-6 bg-rose-950/80 border border-rose-500/50 text-rose-300 p-4 rounded-xl text-xs font-semibold flex items-center gap-2">
                <i class="fa-solid fa-circle-xmark text-base"></i> Transaksi gagal disimpan. Pastikan semua data transaksi valid dan tabel keuangan tersedia; tidak ada transaksi yang disimpan.
            </div>
        <?php elseif (isset($_GET['status']) && $_GET['status'] == 'delete_failed'): ?>
            <div class="mb-6 bg-rose-950/80 border border-rose-500/50 text-rose-300 p-4 rounded-xl text-xs font-semibold flex items-center gap-2">
                <i class="fa-solid fa-circle-xmark text-base"></i> Gagal menghapus transaksi. Periksa koneksi dan tabel keuangan.
            </div>
        <?php elseif ($kas_load_error): ?>
            <div class="mb-6 bg-rose-950/80 border border-rose-500/50 text-rose-300 p-4 rounded-xl text-xs font-semibold flex items-center gap-2">
                <i class="fa-solid fa-circle-xmark text-base"></i> Gagal membaca rekap atau riwayat kas dari tabel keuangan. Periksa koneksi database dan struktur tabel.
            </div>
        <?php endif; ?>

        <!-- REKAP CARD -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-slate-900/80 p-4 rounded-xl border border-emerald-500/30">
                <span class="text-xs text-emerald-400 font-bold uppercase tracking-wider block">Total Pemasukan</span>
                <span class="text-2xl font-black text-emerald-300 mt-1 block"><?= $totals ? 'Rp ' . number_format($tot_masuk, 0, ',', '.') : 'Tidak tersedia'; ?></span>
            </div>
            <div class="bg-slate-900/80 p-4 rounded-xl border border-rose-500/30">
                <span class="text-xs text-rose-400 font-bold uppercase tracking-wider block">Total Pengeluaran</span>
                <span class="text-2xl font-black text-rose-400 mt-1 block"><?= $totals ? 'Rp ' . number_format($tot_keluar, 0, ',', '.') : 'Tidak tersedia'; ?></span>
            </div>
            <div class="bg-slate-900/80 p-4 rounded-xl border border-amber-500/40 bg-gradient-to-br from-emerald-950/40 to-slate-900">
                <span class="text-xs text-amber-400 font-bold uppercase tracking-wider block">Saldo Akhir Kas</span>
                <span class="text-2xl font-black text-amber-300 mt-1 block"><?= $totals ? 'Rp ' . number_format($saldo_akhir, 0, ',', '.') : 'Tidak tersedia'; ?></span>
            </div>
        </div>

        <!-- FORM UTAMA -->
        <div class="bg-slate-900/90 p-5 rounded-2xl border border-slate-800 shadow-xl">
                <div class="flex justify-between items-center mb-4 pb-2 border-b border-slate-800">
                    <h2 class="text-sm font-bold text-emerald-400 uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-table"></i> INPUT & RIWAYAT TRANSAKSI KAS
                    </h2>
                    <span class="text-xs text-slate-400">REALTIME</span>
                </div>

                <form action="kas.php" method="POST">
                <div class="overflow-x-auto mb-4">
                    <table class="w-full text-left text-xs border-collapse table-grid">
                        <thead>
                            <tr class="bg-slate-950 text-slate-300 font-bold uppercase">
                                <th class="p-3 w-36">Tanggal</th>
                                <th class="p-3 w-36">Jenis</th>
                                <th class="p-3">Keterangan</th>
                                <th class="p-3 w-24">Pekan Ke-</th>
                                <th class="p-3 w-32">Bulan Apa</th>
                                <th class="p-3 w-40 text-right">Nominal (Rp)</th>
                                <th class="p-3 w-24 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="bodyTabelInput">
                            <!-- BARIS INPUT PERTAMA -->
                            <tr class="bg-emerald-950/40 border-b-2 border-emerald-500/50 baris-input">
                                <td class="p-2">
                                    <input type="date" name="tanggal[]" value="<?= date('Y-m-d'); ?>" class="w-full bg-slate-950 border border-slate-700 rounded p-1.5 text-white focus:border-emerald-500 outline-none">
                                </td>
                                <td class="p-2">
                                    <select name="jenis[]" class="w-full bg-slate-950 border border-slate-700 rounded p-1.5 text-white focus:border-emerald-500 outline-none">
                                        <option value="Pemasukan">Pemasukan (+)</option>
                                        <option value="Pengeluaran" selected>Pengeluaran (-)</option>
                                    </select>
                                </td>
                                <td class="p-2">
                                    <input type="text" name="keterangan[]" placeholder="Ketik Keterangan / Keperluan..." class="w-full bg-slate-950 border border-slate-700 rounded p-1.5 text-white focus:border-emerald-500 outline-none">
                                </td>
                                <td class="p-2">
                                    <input type="number" name="pekan_ke[]" min="1" max="5" placeholder="Ke-1" class="w-full bg-slate-950 border border-slate-700 rounded p-1.5 text-white focus:border-emerald-500 outline-none">
                                </td>
                                <td class="p-2">
                                    <input type="text" name="bulan[]" placeholder="Oktober" class="w-full bg-slate-950 border border-slate-700 rounded p-1.5 text-white focus:border-emerald-500 outline-none">
                                </td>
                                <td class="p-2">
                                    <input type="text" name="nominal[]" placeholder="0" class="w-full bg-slate-950 border border-slate-700 rounded p-1.5 text-white text-right focus:border-emerald-500 outline-none">
                                </td>
                                <td class="p-2 text-center">
                                    <button type="submit" name="simpan_baris" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-1.5 px-3 rounded transition-all flex items-center justify-center gap-1 w-full shadow">
                                        <i class="fa-solid fa-save"></i> Simpan
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                        <tbody id="bodyTabelRiwayat">
                            <!-- RIWAYAT TRANSAKSI DARI DATABASE -->
                            <?php if ($query_kas && mysqli_num_rows($query_kas) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($query_kas)): ?>
                                    <tr class="hover:bg-slate-800/50 transition-all">
                                        <td class="p-3 text-slate-300"><?= date('d/m/Y', strtotime($row['tanggal'])); ?></td>
                                        <td class="p-3 font-bold">
                                            <?php if ($row['jenis'] == 'Pemasukan'): ?>
                                                <span class="text-emerald-400 bg-emerald-950 px-2 py-0.5 rounded border border-emerald-500/30">Pemasukan</span>
                                            <?php elseif ($query_kas): ?>
                                                <span class="text-rose-400 bg-rose-950 px-2 py-0.5 rounded border border-rose-500/30">Pengeluaran</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3 text-slate-200"><?= htmlspecialchars($row['keterangan']); ?></td>
                                        <td class="p-3 text-slate-400 text-center"><?= $row['pekan_ke'] ? 'Pekan ' . $row['pekan_ke'] : '-'; ?></td>
                                        <td class="p-3 text-slate-400"><?= htmlspecialchars($row['bulan'] ?? '-'); ?></td>
                                        <td class="p-3 text-right font-bold <?= $row['jenis'] == 'Pemasukan' ? 'text-emerald-400' : 'text-rose-400'; ?>">
                                            Rp <?= number_format($row['nominal'], 0, ',', '.'); ?>
                                        </td>
                                        <td class="p-3 text-center">
                                            <a href="kas.php?hapus=<?= $row['id']; ?>" onclick="return confirm('Hapus baris transaksi ini?')" class="text-rose-400 hover:text-rose-300 p-1 inline-block">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="p-6 text-center text-slate-500 italic">Belum ada riwayat transaksi kas di database. Isi baris di atas lalu klik Simpan / Kirim ke Display TV.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                </form>

                <!-- TOMBOL KIRIM KIRI BAWAH -->
                <div class="flex justify-start pt-2">
                    <form action="kas.php" method="POST">
                        <button type="submit" name="kirim_tv" value="1" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-2.5 px-6 rounded-xl transition-all shadow-lg flex items-center gap-2 text-xs">
                            <i class="fa-solid fa-paper-plane"></i> Kirim ke Display TV
                        </button>
                    </form>
                </div>
        </div>
    </div>

</body>
</html>