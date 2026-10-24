<?php
// guru/pengaturan_bobot.php (Contoh Proses Simpan/Update Bobot)
session_start();
require_once '../config/koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'guru') {
    header("Location: ../login.php");
    exit();
}

$id_user = $_SESSION['id_user'];
// Ambil id_guru
$stmt_g = mysqli_prepare($koneksi, "SELECT id_guru FROM guru WHERE id_user = ?");
mysqli_stmt_bind_param($stmt_g, "i", $id_user);
mysqli_stmt_execute($stmt_g);
$guru = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_g));
$id_guru = $guru['id_guru'] ?? 0;

$pesan = "";
$tipe_pesan = "";

// Proses Simpan / Update Bobot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_bobot'])) {
    $bobot_tugas = intval($_POST['bobot_tugas']);
    $bobot_uts   = intval($_POST['bobot_uts']);
    $bobot_uas   = intval($_POST['bobot_uas']);

    // Validasi total harus 100%
    if (($bobot_tugas + $bobot_uts + $bobot_uas) !== 100) {
        $pesan = "Gagal! Total keseluruhan bobot harus tepat 100% (Saat ini: " . ($bobot_tugas + $bobot_uts + $bobot_uas) . "%)";
        $tipe_pesan = "danger";
    } else {
        // Cek apakah guru sudah punya pengaturan bobot sebelumnya
        $stmt_cek = mysqli_prepare($koneksi, "SELECT id_bobot FROM pengaturan_bobot WHERE id_guru = ?");
        mysqli_stmt_bind_param($stmt_cek, "i", $id_guru);
        mysqli_stmt_execute($stmt_cek);
        $res_cek = mysqli_stmt_get_result($stmt_cek);

        if (mysqli_num_rows($res_cek) > 0) {
            // Update
            $stmt_upd = mysqli_prepare($koneksi, "UPDATE pengaturan_bobot SET bobot_tugas = ?, bobot_uts = ?, bobot_uas = ? WHERE id_guru = ?");
            mysqli_stmt_bind_param($stmt_upd, "iiii", $bobot_tugas, $bobot_uts, $bobot_uas, $id_guru);
            mysqli_stmt_execute($stmt_upd);
            mysqli_stmt_close($stmt_upd);
        } else {
            // Insert baru
            $stmt_ins = mysqli_prepare($koneksi, "INSERT INTO pengaturan_bobot (id_guru, bobot_tugas, bobot_uts, bobot_uas) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt_ins, "iiii", $id_guru, $bobot_tugas, $bobot_uts, $bobot_uas);
            mysqli_stmt_execute($stmt_ins);
            mysqli_stmt_close($stmt_ins);
        }
        $pesan = "Pengaturan bobot nilai berhasil disimpan!";
        $tipe_pesan = "success";
    }
}

// Ambil data bobot saat ini
$stmt_b = mysqli_prepare($koneksi, "SELECT * FROM pengaturan_bobot WHERE id_guru = ?");
mysqli_stmt_bind_param($stmt_b, "i", $id_guru);
mysqli_stmt_execute($stmt_b);
$current_bobot = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_b)) ?? ['bobot_tugas' => 30, 'bobot_uts' => 30, 'bobot_uas' => 40];
?>