<?php
// siswa/scan_absensi.php
session_start();
require_once '../config/koneksi.php';

// Proteksi Halaman: Cek Sesi & Role Siswa
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'siswa') {
    header("Location: ../login.php");
    exit();
}

$id_user = $_SESSION['id_user'];

// Ambil ID Siswa & Kelas
$stmt_siswa = mysqli_prepare($koneksi, "SELECT id_siswa, id_kelas, nama_lengkap FROM siswa WHERE id_user = ?");
mysqli_stmt_bind_param($stmt_siswa, "i", $id_user);
mysqli_stmt_execute($stmt_siswa);
$data_siswa = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_siswa));

$id_siswa = $data_siswa['id_siswa'] ?? 0;
$id_kelas = $data_siswa['id_kelas'] ?? 0;

$pesan = "";
$tipe_pesan = "";

// Cek struktur kolom di tabel absensi untuk mencegah error
$has_id_mapel = false;
$check_col = mysqli_query($koneksi, "SHOW COLUMNS FROM absensi LIKE 'id_mapel'");
if ($check_col && mysqli_num_rows($check_col) > 0) {
    $has_id_mapel = true;
}

// Proses Absensi Saat QR Code Terbaca
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_code'])) {
    $qr_data = trim($_POST['qr_code']); 
    $id_mapel = 0;

    // Pengecekan 1: Format "MAPEL-{id_mapel}"
    if (strpos($qr_data, 'MAPEL-') === 0) {
        $id_mapel = intval(str_replace('MAPEL-', '', $qr_data));
    } 
    // Pengecekan 2: Format JSON
    else {
        $json = json_decode($qr_data, true);
        if ($json && isset($json['id_mapel'])) {
            $id_mapel = intval($json['id_mapel']);
        }
    }

    if ($id_mapel > 0 && $id_siswa > 0) {
        $tanggal_hari_ini = date('Y-m-d');

        if ($has_id_mapel) {
            // Jika tabel absensi memiliki kolom id_mapel
            $stmt_cek = mysqli_prepare($koneksi, "SELECT id_absensi FROM absensi WHERE id_siswa = ? AND id_mapel = ? AND tanggal = ?");
            mysqli_stmt_bind_param($stmt_cek, "iis", $id_siswa, $id_mapel, $tanggal_hari_ini);
            mysqli_stmt_execute($stmt_cek);
            $res_cek = mysqli_stmt_get_result($stmt_cek);

            if (mysqli_num_rows($res_cek) > 0) {
                $pesan = "Anda sudah melakukan absensi untuk mata pelajaran ini hari ini!";
                $tipe_pesan = "warning";
            } else {
                $stmt_ins = mysqli_prepare($koneksi, "INSERT INTO absensi (id_siswa, id_kelas, id_mapel, tanggal, status) VALUES (?, ?, ?, ?, 'Hadir')");
                mysqli_stmt_bind_param($stmt_ins, "iiis", $id_siswa, $id_kelas, $id_mapel, $tanggal_hari_ini);
                
                if (mysqli_stmt_execute($stmt_ins)) {
                    $pesan = "Berhasil! Absensi kehadiran Anda telah dicatat.";
                    $tipe_pesan = "success";
                } else {
                    $pesan = "Gagal mencatat absensi. Silakan coba lagi.";
                    $tipe_pesan = "danger";
                }
            }
        } else {
            // Jika tabel absensi terhubung via id_jadwal
            // Cari id_jadwal berdasarkan id_kelas dan id_mapel
            $stmt_jadwal = mysqli_prepare($koneksi, "SELECT id_jadwal FROM jadwal WHERE id_kelas = ? AND id_mapel = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt_jadwal, "ii", $id_kelas, $id_mapel);
            mysqli_stmt_execute($stmt_jadwal);
            $res_j = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_jadwal));
            $id_jadwal = $res_j['id_jadwal'] ?? 0;

            if ($id_jadwal > 0) {
                $stmt_cek = mysqli_prepare($koneksi, "SELECT id_absensi FROM absensi WHERE id_siswa = ? AND id_jadwal = ? AND tanggal = ?");
                mysqli_stmt_bind_param($stmt_cek, "iis", $id_siswa, $id_jadwal, $tanggal_hari_ini);
                mysqli_stmt_execute($stmt_cek);
                $res_cek = mysqli_stmt_get_result($stmt_cek);

                if (mysqli_num_rows($res_cek) > 0) {
                    $pesan = "Anda sudah melakukan absensi untuk mata pelajaran ini hari ini!";
                    $tipe_pesan = "warning";
                } else {
                    $stmt_ins = mysqli_prepare($koneksi, "INSERT INTO absensi (id_siswa, id_jadwal, tanggal, status) VALUES (?, ?, ?, 'Hadir')");
                    mysqli_stmt_bind_param($stmt_ins, "iis", $id_siswa, $id_jadwal, $tanggal_hari_ini);
                    
                    if (mysqli_stmt_execute($stmt_ins)) {
                        $pesan = "Berhasil! Absensi kehadiran Anda telah dicatat.";
                        $tipe_pesan = "success";
                    } else {
                        $pesan = "Gagal mencatat absensi. Silakan coba lagi.";
                        $tipe_pesan = "danger";
                    }
                }
            } else {
                $pesan = "Jadwal pelajaran tidak ditemukan untuk kelas Anda.";
                $tipe_pesan = "danger";
            }
        }
    } else {
        $pesan = "QR Code tidak valid!";
        $tipe_pesan = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan Absensi QR Code - Portal Siswa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
</head>
<body class="bg-light">

    <!-- Navbar Portal Siswa -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-success shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-person-badge me-2"></i>Portal Siswa</a>
            <div class="collapse navbar-collapse" id="navSiswa">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="absensi.php">Rekap Absensi</a></li>
                    <li class="nav-item"><a class="nav-link active" href="scan_absensi.php">Scan QR Absen</a></li>
                    <li class="nav-item"><a class="nav-link" href="nilai.php">Nilai & Rapor</a></li>
                </ul>
                <a href="../logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-qr-code-scan me-2 text-success"></i>Scan QR Code Absensi</h4>
                <p class="text-muted small mb-0">Arahkan kamera HP / Laptop Anda ke QR Code yang disediakan oleh Guru.</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
        </div>

        <?php if ($pesan != ""): ?>
            <div class="alert alert-<?= $tipe_pesan; ?> alert-dismissible fade show shadow-sm mb-4" role="alert">
                <?= $pesan; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-sm border-0 text-center">
                    <div class="card-header bg-white py-3 fw-bold">
                        <i class="bi bi-camera me-2 text-success"></i>Kamera Pemindai QR
                    </div>
                    <div class="card-body p-3">
                        <div id="reader" style="width: 100%; min-height: 300px;" class="rounded border"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Tersembunyi untuk Mengirim Hasil Scan -->
    <form id="formAbsen" action="scan_absensi.php" method="POST" style="display: none;">
        <input type="text" name="qr_code" id="inputQrCode">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function onScanSuccess(decodedText, decodedResult) {
            html5QrcodeScanner.clear();
            document.getElementById('inputQrCode').value = decodedText;
            document.getElementById('formAbsen').submit();
        }

        function onScanFailure(error) {
            // Mengabaikan error pemindaian per-frame
        }

        let html5QrcodeScanner = new Html5QrcodeScanner(
            "reader", 
            { fps: 10, qrbox: { width: 250, height: 250 } },
            false
        );
        html5QrcodeScanner.render(onScanSuccess, onScanFailure);
    </script>
</body>
</html>