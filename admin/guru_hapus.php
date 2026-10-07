<?php
// admin/guru_hapus.php
session_start();
require_once '../config/koneksi.php';

// Proteksi Admin
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id_guru = intval($_GET['id']);

    if ($id_guru > 0) {
        // 1. Hapus jadwal terkait guru ini terlebih dahulu agar tidak memicu error constraint
        $stmt_jadwal = mysqli_prepare($koneksi, "DELETE FROM jadwal WHERE id_guru = ?");
        mysqli_stmt_bind_param($stmt_jadwal, "i", $id_guru);
        mysqli_stmt_execute($stmt_jadwal);

        // 2. Hapus data guru dari tabel guru
        $stmt_guru = mysqli_prepare($koneksi, "DELETE FROM guru WHERE id_guru = ?");
        mysqli_stmt_bind_param($stmt_guru, "i", $id_guru);

        if (mysqli_stmt_execute($stmt_guru)) {
            header("Location: guru.php?pesan=terhapus");
            exit();
        } else {
            echo "Gagal menghapus data guru: " . mysqli_error($koneksi);
            exit();
        }
    }
}

header("Location: guru.php");
exit();