<?php
// config/koneksi.php

$host     = "localhost";
$user     = "root";
$password = "";
$database = "db_absensi_sekolah"; // Sesuaikan dengan nama database Anda

$koneksi = mysqli_connect($host, $user, $password, $database);

// Cek koneksi database
if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Set timezone agar waktu log sesuai (WIB / zona waktu lokal)
date_default_timezone_set('Asia/Jakarta');

/**
 * Fungsi Global untuk Mencatat Log Aktivitas Sistem
 * 
 * @param mysqli $koneksi Koneksi database aktif
 * @param int $id_user ID pengguna yang sedang login
 * @param string $role Peran pengguna (admin, guru, dll)
 * @param string $aksi Deskripsi atau keterangan aksi yang dilakukan
 */
function catatLog($koneksi, $id_user, $role, $aksi) {
    $stmt = mysqli_prepare($koneksi, "INSERT INTO activity_logs (id_user, role, aksi) VALUES (?, ?, ?)");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "iss", $id_user, $role, $aksi);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}
?>