<?php
require_once __DIR__ . '/../config/koneksi.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin DKM - Musholla Al-Muhajirin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Poppins', sans-serif; }</style>
</head>
<body class="bg-slate-950 text-white min-h-screen p-6">

    <div class="max-w-5xl mx-auto">
        <!-- HEADER DASHBOARD (TANPA TOMBOL RUANG IT) -->
        <div class="flex justify-between items-center mb-8 pb-4 border-b border-slate-800">
            <div>
                <h1 class="text-2xl font-bold text-emerald-400 flex items-center gap-2">
                    <i class="fa-solid fa-gauge-high"></i> Panel Utama Admin DKM
                </h1>
                <p class="text-xs text-slate-400 mt-1">Selamat datang di pusat pengoperasian Display Musholla Al-Muhajirin.</p>
            </div>
            <div>
                <a href="logout.php" class="bg-rose-950 hover:bg-rose-900 text-rose-300 text-xs px-4 py-2 rounded-xl border border-rose-800 font-medium transition flex items-center gap-2">
                    <i class="fa-solid fa-right-from-bracket"></i> Keluar
                </a>
            </div>
        </div>

        <!-- MENU UTAMA DASHBOARD SUPER ADMIN -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <!-- CARD 1: GALERI MEDIA & SLIDE -->
            <a href="kegiatan.php" class="bg-slate-900 border border-slate-800 hover:border-emerald-500/50 p-6 rounded-2xl shadow-xl hover:scale-[1.02] transition group flex items-start gap-4">
                <div class="w-14 h-14 bg-emerald-950 border border-emerald-500/30 rounded-2xl flex items-center justify-center text-emerald-400 text-2xl group-hover:bg-emerald-600 group-hover:text-white transition flex-shrink-0">
                    <i class="fa-solid fa-images"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white mb-1 group-hover:text-emerald-400 transition">Galeri & Slide Media</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">Kelola foto kegiatan jamaah dan video acara berjadwal untuk tampilan layar TV utama.</p>
                </div>
            </a>

            <!-- CARD 2: PENGATURAN IQOMAH -->
            <a href="iqomah.php" class="bg-slate-900 border border-slate-800 hover:border-amber-500/50 p-6 rounded-2xl shadow-xl hover:scale-[1.02] transition group flex items-start gap-4">
                <div class="w-14 h-14 bg-amber-950 border border-amber-500/30 rounded-2xl flex items-center justify-center text-amber-400 text-2xl group-hover:bg-amber-600 group-hover:text-white transition flex-shrink-0">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white mb-1 group-hover:text-amber-400 transition">Pengaturan Iqomah</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">Atur durasi jeda hitung mundur waktu Iqomah untuk masing-masing waktu sholat 5 waktu.</p>
                </div>
            </a>

        </div>
    </div>

</body>
</html>