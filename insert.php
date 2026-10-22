<?php
require_once __DIR__ . '/config/koneksi.php';

$sql = "INSERT INTO galeri (file, nama_file, tipe, caption, keterangan, status) VALUES 
('Pengukuhan anggota DKM Almuhajirin.jpg', 'Pengukuhan anggota DKM Almuhajirin.jpg', 'gambar', 'Pengukuhan anggota DKM Al-Muhajirin', 'Pengukuhan anggota DKM Al-Muhajirin', 'aktif'),
('Rapat Kerja DKM.jpg', 'Rapat Kerja DKM.jpg', 'gambar', 'Rapat Kerja DKM', 'Rapat Kerja DKM', 'aktif'),
('Tamasya jamaah musholla Al-muhajirin.jpg', 'Tamasya jamaah musholla Al-muhajirin.jpg', 'gambar', 'Tamasya Jamaah Musholla Al-Muhajirin', 'Tamasya Jamaah Musholla Al-Muhajirin', 'aktif'),
('Dokumentasi Rapat Kerja Dkm.jpg', 'Dokumentasi Rapat Kerja Dkm.jpg', 'gambar', 'Dokumentasi Rapat Kerja DKM', 'Dokumentasi Rapat Kerja DKM', 'aktif')";

if (mysqli_query($koneksi, $sql)) {
    echo "<h2 style='color: green;'>BERJAYA! 4 foto telah dimasukkan ke dalam database db_musholla.</h2>";
} else {
    echo "Ralat: " . mysqli_error($koneksi);
}
?>