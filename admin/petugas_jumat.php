<?php
require_once __DIR__ . '/../config/koneksi.php';

// ====== PROSES SIMPAN / TAMBAH ======
if (isset($_POST['simpan'])) {
    $tanggal = mysqli_real_escape_string($koneksi, $_POST['tanggal']);
    $khatib  = mysqli_real_escape_string($koneksi, $_POST['khatib']);
    $imam    = mysqli_real_escape_string($koneksi, $_POST['imam']);
    $muadzin = mysqli_real_escape_string($koneksi, $_POST['muadzin']);

    $save_result = mysqli_query($koneksi, "INSERT INTO jadwal_jumat (tanggal, khatib, imam, muadzin) VALUES ('$tanggal', '$khatib', '$imam', '$muadzin')");
    header('Location: petugas_jumat.php?status=' . ($save_result ? 'sukses' : 'gagal'));
    exit;
}

// ====== PROSES UPDATE / EDIT ======
if (isset($_POST['update'])) {
    $id      = (int)$_POST['id_edit'];
    $tanggal = mysqli_real_escape_string($koneksi, $_POST['tanggal']);
    $khatib  = mysqli_real_escape_string($koneksi, $_POST['khatib']);
    $imam    = mysqli_real_escape_string($koneksi, $_POST['imam']);
    $muadzin = mysqli_real_escape_string($koneksi, $_POST['muadzin']);

    $update_result = mysqli_query($koneksi, "UPDATE jadwal_jumat SET tanggal='$tanggal', khatib='$khatib', imam='$imam', muadzin='$muadzin' WHERE id=$id");
    header('Location: petugas_jumat.php?status=' . ($update_result ? 'diupdate' : 'gagal'));
    exit;
}

// ====== PROSES HAPUS ======
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $delete_result = mysqli_query($koneksi, "DELETE FROM jadwal_jumat WHERE id=$id");
    header('Location: petugas_jumat.php?status=' . ($delete_result ? 'dihapus' : 'gagal'));
    exit;
}

// ====== PROSES SET TAYANG KE TV ======
if (isset($_GET['set_tayang'])) {
    $id = (int)$_GET['set_tayang'];
    $show_result = mysqli_begin_transaction($koneksi);
    if ($show_result) {
        $show_result = mysqli_query($koneksi, "UPDATE jadwal_jumat SET status_tayang=0 WHERE status_tayang=1");
        if ($show_result) {
            $show_result = mysqli_query($koneksi, "UPDATE jadwal_jumat SET status_tayang=1 WHERE id=$id");
        }
        if ($show_result) {
            $show_result = mysqli_commit($koneksi);
        }
        if (!$show_result) {
            mysqli_rollback($koneksi);
        }
    }
    header('Location: petugas_jumat.php?status=' . ($show_result ? 'tayang' : 'gagal'));
    exit;
}

// ====== AMBIL DATA UNTUK FORM EDIT ======
$data_edit = null;
if (isset($_GET['edit'])) {
    $id_edit = (int)$_GET['edit'];
    $q_edit = mysqli_query($koneksi, "SELECT * FROM jadwal_jumat WHERE id=$id_edit LIMIT 1");
    $data_edit = $q_edit ? mysqli_fetch_assoc($q_edit) : null;
}

// ====== AMBIL SEMUA DATA ======
$query_jadwal = mysqli_query($koneksi, "SELECT * FROM jadwal_jumat ORDER BY tanggal DESC, id DESC LIMIT 100");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Petugas Jumat - Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Poppins', sans-serif; background-color: #050b14; color: #ffffff; } </style>
</head>
<body class="p-6 min-h-screen">

    <div class="max-w-7xl mx-auto">
        <!-- HEADER -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <a href="index.php" class="text-xs text-emerald-400 font-semibold uppercase tracking-wider flex items-center gap-2 mb-1 hover:underline">
                    <i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard
                </a>
                <h1 class="text-2xl font-black text-white uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-user-group text-amber-400"></i> Kelola Petugas Jumat
                </h1>
            </div>
            <?php if (isset($_GET['status']) && $_GET['status'] === 'gagal'): ?>
                <div class="mb-5 rounded-xl border border-rose-500/50 bg-rose-950/80 p-4 text-xs font-semibold text-rose-300">
                    Operasi gagal. Periksa koneksi database dan struktur tabel jadwal_jumat.
                </div>
            <?php endif; ?>
            <a href="../index.php" target="_blank" class="bg-rose-600/80 hover:bg-rose-600 text-white text-xs font-bold py-2.5 px-4 rounded-xl transition-all shadow-lg flex items-center gap-2">
                <i class="fa-solid fa-desktop"></i> Lihat TV
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- KOLOM KIRI: FORM INPUT / EDIT -->
            <div class="bg-slate-900/90 p-5 rounded-2xl border border-slate-800 shadow-xl h-fit">
                <h2 class="text-sm font-bold <?= $data_edit ? 'text-amber-400' : 'text-emerald-400' ?> uppercase tracking-wider mb-4 flex items-center gap-2 border-b border-slate-800 pb-2">
                    <i class="fa-solid <?= $data_edit ? 'fa-pen-to-square' : 'fa-plus' ?>"></i> 
                    <?= $data_edit ? 'EDIT PETUGAS JUMAT' : 'TAMBAH PETUGAS JUMAT' ?>
                </h2>

                <form action="petugas_jumat.php" method="POST" class="space-y-4 text-sm">
                    <?php if($data_edit): ?>
                        <input type="hidden" name="id_edit" value="<?= $data_edit['id']; ?>">
                    <?php endif; ?>

                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Tanggal Jumat</label>
                        <input type="date" name="tanggal" value="<?= $data_edit ? $data_edit['tanggal'] : date('Y-m-d'); ?>" required class="w-full bg-slate-950 border border-slate-700 rounded-lg p-2 text-white focus:border-emerald-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1"><i class="fa-solid fa-user-tie text-emerald-400 w-4"></i> Nama Khatib</label>
                        <input type="text" name="khatib" value="<?= $data_edit['khatib'] ?? ''; ?>" placeholder="Ust. Ahmad..." required class="w-full bg-slate-950 border border-slate-700 rounded-lg p-2 text-white focus:border-emerald-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1"><i class="fa-solid fa-user-check text-emerald-400 w-4"></i> Nama Imam</label>
                        <input type="text" name="imam" value="<?= $data_edit['imam'] ?? ''; ?>" placeholder="Ust. Budi..." required class="w-full bg-slate-950 border border-slate-700 rounded-lg p-2 text-white focus:border-emerald-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1"><i class="fa-solid fa-bullhorn text-emerald-400 w-4"></i> Nama Muadzin</label>
                        <input type="text" name="muadzin" value="<?= $data_edit['muadzin'] ?? ''; ?>" placeholder="Bilal..." required class="w-full bg-slate-950 border border-slate-700 rounded-lg p-2 text-white focus:border-emerald-500 outline-none">
                    </div>

                    <div class="pt-2 flex gap-2">
                        <?php if($data_edit): ?>
                            <button type="submit" name="update" class="w-full bg-amber-600 hover:bg-amber-500 text-white font-bold py-2 rounded-lg transition-all shadow">Update Data</button>
                            <a href="petugas_jumat.php" class="w-full bg-slate-700 hover:bg-slate-600 text-center text-white font-bold py-2 rounded-lg transition-all shadow">Batal</a>
                        <?php else: ?>
                            <button type="submit" name="simpan" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-2 rounded-lg transition-all shadow">Simpan Jadwal</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- KOLOM KANAN: TABEL RIWAYAT -->
            <div class="lg:col-span-2 bg-slate-900/90 p-5 rounded-2xl border border-slate-800 shadow-xl">
                <h2 class="text-sm font-bold text-emerald-400 uppercase tracking-wider mb-4 border-b border-slate-800 pb-2">
                    <i class="fa-solid fa-list"></i> DAFTAR JADWAL & STATUS TAYANG
                </h2>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-950 text-slate-300 font-bold uppercase">
                                <th class="p-3 border border-slate-800">Tanggal</th>
                                <th class="p-3 border border-slate-800">Petugas (Khatib/Imam/Muadzin)</th>
                                <th class="p-3 border border-slate-800 text-center w-28">Status TV</th>
                                <th class="p-3 border border-slate-800 text-center w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($query_jadwal && mysqli_num_rows($query_jadwal) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($query_jadwal)): ?>
                                    <tr class="hover:bg-slate-800/50 transition-all border-b border-slate-800">
                                        <td class="p-3 align-top font-bold text-slate-200"><?= date('d M Y', strtotime($row['tanggal'])); ?></td>
                                        <td class="p-3 align-top">
                                            <div class="text-emerald-300"><i class="fa-solid fa-user-tie w-4"></i> <?= htmlspecialchars($row['khatib']); ?> (Khatib)</div>
                                            <div class="text-slate-300"><i class="fa-solid fa-user-check w-4"></i> <?= htmlspecialchars($row['imam']); ?> (Imam)</div>
                                            <div class="text-slate-400"><i class="fa-solid fa-bullhorn w-4"></i> <?= htmlspecialchars($row['muadzin']); ?> (Muadzin)</div>
                                        </td>
                                        <td class="p-3 align-top text-center">
                                            <?php if ((int)$row['status_tayang'] === 1): ?>
                                                <span class="bg-emerald-900 text-emerald-300 px-2 py-1 rounded text-[10px] font-bold border border-emerald-500/50 block mb-2">TAYANG</span>
                                            <?php else: ?>
                                                <a href="petugas_jumat.php?set_tayang=<?= $row['id']; ?>" class="bg-slate-800 hover:bg-emerald-800 text-slate-300 hover:text-emerald-300 px-2 py-1 rounded text-[10px] font-bold border border-slate-600 block mb-2 transition-all">Set Tayang</a>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3 align-top text-center flex justify-center gap-2">
                                            <a href="petugas_jumat.php?edit=<?= $row['id']; ?>" class="bg-amber-900/50 text-amber-400 hover:bg-amber-600 hover:text-white w-7 h-7 rounded flex items-center justify-center transition-all"><i class="fa-solid fa-pen"></i></a>
                                            <a href="petugas_jumat.php?hapus=<?= $row['id']; ?>" onclick="return confirm('Hapus jadwal ini?')" class="bg-rose-900/50 text-rose-400 hover:bg-rose-600 hover:text-white w-7 h-7 rounded flex items-center justify-center transition-all"><i class="fa-solid fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php elseif ($query_jadwal): ?>
                                <tr><td colspan="4" class="p-6 text-center text-slate-500 italic border border-slate-800">Belum ada jadwal Jumat.</td></tr>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="p-6 text-center text-rose-400 border border-slate-800">
                                        Gagal memuat jadwal Jumat: <?= htmlspecialchars(mysqli_error($koneksi), ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</body>
</html>