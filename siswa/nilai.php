<?php
// siswa/nilai.php
session_start();
require_once '../config/koneksi.php';

// Proteksi Halaman: Cek Sesi & Role Siswa
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'siswa') {
    header("Location: ../login.php");
    exit();
}

$id_user = $_SESSION['id_user'];

// Ambil Data Siswa
$stmt_siswa = mysqli_prepare($koneksi, "
    SELECT s.id_siswa, s.nisn, s.nama_lengkap, k.nama_kelas 
    FROM siswa s 
    LEFT JOIN kelas k ON s.id_kelas = k.id_kelas 
    WHERE s.id_user = ?
");
mysqli_stmt_bind_param($stmt_siswa, "i", $id_user);
mysqli_stmt_execute($stmt_siswa);
$data_siswa = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_siswa));

$id_siswa = $data_siswa['id_siswa'] ?? 0;

// Ambil Nilai Akademik Siswa
$stmt_nilai = mysqli_prepare($koneksi, "
    SELECT m.nama_mapel, n.nilai_tugas, n.nilai_uts, n.nilai_uas, n.nilai_akhir 
    FROM nilai n 
    JOIN mapel m ON n.id_mapel = m.id_mapel 
    WHERE n.id_siswa = ? 
    ORDER BY m.nama_mapel ASC
");
mysqli_stmt_bind_param($stmt_nilai, "i", $id_siswa);
mysqli_stmt_execute($stmt_nilai);
$query_nilai = mysqli_stmt_get_result($stmt_nilai);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nilai & Rapor - Portal Siswa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
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
                    <li class="nav-item"><a class="nav-link active" href="nilai.php">Nilai & Rapor</a></li>
                </ul>
                <a href="../logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-journal-bookmark me-2 text-success"></i>Laporan Nilai Akademik</h4>
                <p class="text-muted small mb-0">Transkrip nilai hasil belajar siswa: <strong><?= htmlspecialchars($data_siswa['nama_lengkap'] ?? ''); ?></strong> (Kelas: <?= htmlspecialchars($data_siswa['nama_kelas'] ?? '-'); ?>)</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="width: 50px;">No</th>
                                <th>Mata Pelajaran</th>
                                <th style="width: 130px;">Tugas (30%)</th>
                                <th style="width: 130px;">UTS (30%)</th>
                                <th style="width: 130px;">UAS (40%)</th>
                                <th style="width: 130px;" class="text-center">Nilai Akhir</th>
                                <th style="width: 100px;" class="text-center">Predikat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($query_nilai && mysqli_num_rows($query_nilai) > 0): ?>
                                <?php $no = 1; while ($n = mysqli_fetch_assoc($query_nilai)): ?>
                                    <?php 
                                        $na = $n['nilai_akhir'];
                                        if ($na >= 85) { $predikat = 'A'; $badge = 'bg-success'; }
                                        elseif ($na >= 75) { $predikat = 'B'; $badge = 'bg-primary'; }
                                        elseif ($na >= 60) { $predikat = 'C'; $badge = 'bg-warning text-dark'; }
                                        else { $predikat = 'D'; $badge = 'bg-danger'; }
                                    ?>
                                    <tr>
                                        <td class="ps-3"><?= $no++; ?></td>
                                        <td><strong><?= htmlspecialchars($n['nama_mapel']); ?></strong></td>
                                        <td><?= number_format($n['nilai_tugas'], 2); ?></td>
                                        <td><?= number_format($n['nilai_uts'], 2); ?></td>
                                        <td><?= number_format($n['nilai_uas'], 2); ?></td>
                                        <td class="text-center fw-bold text-success">
                                            <?= number_format($n['nilai_akhir'], 2); ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge <?= $badge; ?> fs-6"><?= $predikat; ?></span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">Belum ada data nilai yang diinput oleh guru.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>