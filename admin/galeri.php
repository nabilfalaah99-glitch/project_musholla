<?php
// Pastikan file koneksi database sudah di-include
require_once __DIR__ . '/../config/koneksi.php';

$pesan = '';

// PROSES UPLOAD MEDIA
if (isset($_POST['simpan_media'])) {
    $file_media = $_FILES['file_media'] ?? null;
    $keterangan = trim($_POST['keterangan'] ?? '');
    $jenis_kegiatan = $_POST['jenis_kegiatan'] ?? '';
    $ekstensi_diperbolehkan = ['jpg', 'jpeg', 'png', 'mp4'];
    $ukuran_maksimal = 50 * 1024 * 1024;

    if (!$file_media || !isset($file_media['error']) || $file_media['error'] !== UPLOAD_ERR_OK) {
        $pesan = '<div class="bg-rose-500/20 border border-rose-500 text-rose-400 px-4 py-3 rounded mb-4">Gagal menerima file upload. Periksa ukuran file dan coba lagi.</div>';
    } elseif ($keterangan === '') {
        $pesan = '<div class="bg-amber-500/20 border border-amber-500 text-amber-400 px-4 py-3 rounded mb-4">Keterangan wajib diisi.</div>';
    } elseif (!in_array($jenis_kegiatan, ['umum', 'rutin'], true)) {
        $pesan = '<div class="bg-amber-500/20 border border-amber-500 text-amber-400 px-4 py-3 rounded mb-4">Jenis penayangan tidak valid.</div>';
    } elseif ($file_media['size'] > $ukuran_maksimal) {
        $pesan = '<div class="bg-amber-500/20 border border-amber-500 text-amber-400 px-4 py-3 rounded mb-4">Ukuran file terlalu besar! Maksimal 50MB.</div>';
    } else {
        $ekstensi = strtolower(pathinfo($file_media['name'], PATHINFO_EXTENSION));

        if (!in_array($ekstensi, $ekstensi_diperbolehkan, true)) {
            $pesan = '<div class="bg-amber-500/20 border border-amber-500 text-amber-400 px-4 py-3 rounded mb-4">Ekstensi file tidak diperbolehkan! Hanya JPG, PNG, atau MP4.</div>';
        } else {
            $tipe_media = $ekstensi === 'mp4' ? 'video' : 'gambar';
            $nama_file_baru = bin2hex(random_bytes(8)) . '.' . $ekstensi;
            $path = '../assets/img/' . $nama_file_baru;

            $tanggal = $jenis_kegiatan === 'rutin' && !empty($_POST['tanggal']) ? $_POST['tanggal'] : null;
            $jam_mulai = $jenis_kegiatan === 'rutin' && !empty($_POST['jam_mulai']) ? $_POST['jam_mulai'] . ':00' : null;
            $jam_selesai = $jenis_kegiatan === 'rutin' && !empty($_POST['jam_selesai']) ? $_POST['jam_selesai'] . ':00' : null;

            if (!move_uploaded_file($file_media['tmp_name'], $path)) {
                $pesan = '<div class="bg-rose-500/20 border border-rose-500 text-rose-400 px-4 py-3 rounded mb-4">Gagal mengupload file ke folder tujuan.</div>';
            } else {
                $query = mysqli_prepare(
                    $koneksi,
                    "INSERT INTO galeri (file, nama_file, tipe, caption, keterangan, tanggal, tanggal_acara, jam_mulai, jam_selesai, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'aktif')"
                );

                if ($query) {
                    $berhasil_simpan = mysqli_stmt_bind_param(
                        $query,
                        'sssssssss',
                        $nama_file_baru,
                        $nama_file_baru,
                        $tipe_media,
                        $keterangan,
                        $keterangan,
                        $tanggal,
                        $tanggal,
                        $jam_mulai,
                        $jam_selesai
                    )
                        && mysqli_stmt_execute($query);
                    $error_database = mysqli_stmt_error($query);
                    mysqli_stmt_close($query);

                    if ($berhasil_simpan) {
                        $pesan = '<div class="bg-emerald-500/20 border border-emerald-500 text-emerald-400 px-4 py-3 rounded mb-4">Media berhasil diupload dan disimpan!</div>';
                    } else {
                        unlink($path);
                        $pesan = '<div class="bg-rose-500/20 border border-rose-500 text-rose-400 px-4 py-3 rounded mb-4">Gagal menyimpan ke database: ' . htmlspecialchars($error_database, ENT_QUOTES, 'UTF-8') . '</div>';
                    }
                } else {
                    unlink($path);
                    $pesan = '<div class="bg-rose-500/20 border border-rose-500 text-rose-400 px-4 py-3 rounded mb-4">Gagal menyiapkan penyimpanan database: ' . htmlspecialchars(mysqli_error($koneksi), ENT_QUOTES, 'UTF-8') . '</div>';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Galeri - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Poppins', sans-serif; }</style>
</head>
<body class="bg-slate-950 text-slate-200 p-6">

    <div class="max-w-4xl mx-auto">
        <!-- Header Halaman -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-emerald-400"><i class="fa-solid fa-images mr-2"></i> Kelola Galeri & Video</h1>
                <p class="text-slate-400 text-sm mt-1">Upload dokumentasi kegiatan atau atur jadwal penayangan poster/video.</p>
            </div>
            <a href="index.php" class="bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 rounded-lg border border-slate-700 transition-colors text-sm">
                <i class="fa-solid fa-arrow-left mr-1"></i> Kembali ke Dashboard
            </a>
        </div>

        <?= $pesan; ?>

        <!-- Form Upload -->
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 shadow-xl">
            <form action="" method="POST" enctype="multipart/form-data">
                
                <div class="mb-5">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Pilih File (JPG/PNG/MP4)</label>
                    <input type="file" name="file_media" required class="w-full text-slate-300 bg-slate-950 border border-slate-700 rounded-lg p-2 focus:outline-none focus:border-emerald-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-emerald-500/20 file:text-emerald-400 hover:file:bg-emerald-500/30">
                </div>

                <div class="mb-5">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Keterangan / Caption Text</label>
                    <input type="text" name="keterangan" required placeholder="Cth: Dokumentasi Pengajian Ahad Pagi..." class="w-full bg-slate-950 border border-slate-700 rounded-lg p-3 text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                </div>

                <div class="mb-5">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Jenis Penayangan</label>
                    <select name="jenis_kegiatan" id="jenisKegiatan" onchange="toggleJadwal()" class="w-full bg-slate-950 border border-slate-700 rounded-lg p-3 text-white focus:outline-none focus:border-emerald-500">
                        <option value="umum">Tayang Selalu (Kegiatan Umum / Default)</option>
                        <option value="rutin">Tayang Terjadwal (Kajian Rutin / Acara Khusus)</option>
                    </select>
                </div>

                <!-- Bagian Jadwal (Awalnya Disembunyikan) -->
                <div id="formJadwal" class="hidden bg-slate-950 p-4 rounded-lg border border-slate-800 mb-5">
                    <p class="text-xs text-amber-400 mb-4"><i class="fa-solid fa-circle-info"></i> Media hanya akan muncul di layar TV pada rentang waktu di bawah ini.</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Tanggal Tayang</label>
                            <input type="date" name="tanggal" class="w-full bg-slate-900 border border-slate-700 rounded-md p-2 text-white focus:border-emerald-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Jam Mulai</label>
                            <input type="time" name="jam_mulai" class="w-full bg-slate-900 border border-slate-700 rounded-md p-2 text-white focus:border-emerald-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Jam Selesai</label>
                            <input type="time" name="jam_selesai" class="w-full bg-slate-900 border border-slate-700 rounded-md p-2 text-white focus:border-emerald-500">
                        </div>
                    </div>
                </div>

                <button type="submit" name="simpan_media" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-3 px-4 rounded-lg transition-colors">
                    <i class="fa-solid fa-upload mr-2"></i> Upload & Simpan
                </button>
            </form>
        </div>
    </div>

    <!-- Script untuk menyembunyikan/menampilkan form jadwal -->
    <script>
        function toggleJadwal() {
            var jenis = document.getElementById("jenisKegiatan").value;
            var formJadwal = document.getElementById("formJadwal");
            
            if (jenis === "rutin") {
                formJadwal.classList.remove("hidden");
            } else {
                formJadwal.classList.add("hidden");
                // Reset value ketika disembunyikan
                document.querySelector('input[name="tanggal"]').value = '';
                document.querySelector('input[name="jam_mulai"]').value = '';
                document.querySelector('input[name="jam_selesai"]').value = '';
            }
        }
    </script>
</body>
</html>