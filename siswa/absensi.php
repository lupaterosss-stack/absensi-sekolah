<?php
// siswa/absensi.php
session_start();
require_once '../config/koneksi.php';

// Proteksi Halaman: Cek Sesi & Role Siswa
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'siswa') {
    header("Location: ../login.php");
    exit();
}

$id_user = $_SESSION['id_user'];

// Ambil Data Siswa
$stmt_siswa = mysqli_prepare($koneksi, "SELECT id_siswa, nisn, nama_lengkap FROM siswa WHERE id_user = ?");
mysqli_stmt_bind_param($stmt_siswa, "i", $id_user);
mysqli_stmt_execute($stmt_siswa);
$data_siswa = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_siswa));

$id_siswa = $data_siswa['id_siswa'] ?? 0;

// Ambil Riwayat Absensi Siswa
$stmt_absensi = mysqli_prepare($koneksi, "
    SELECT a.tanggal, a.status, m.nama_mapel 
    FROM absensi a 
    JOIN mapel m ON a.id_mapel = m.id_mapel 
    WHERE a.id_siswa = ? 
    ORDER BY a.tanggal DESC
");
mysqli_stmt_bind_param($stmt_absensi, "i", $id_siswa);
mysqli_stmt_execute($stmt_absensi);
$query_absensi = mysqli_stmt_get_result($stmt_absensi);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Absensi - Portal Siswa</title>
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
                    <li class="nav-item"><a class="nav-link active" href="absensi.php">Rekap Absensi</a></li>
                    <li class="nav-item"><a class="nav-link" href="nilai.php">Nilai & Rapor</a></li>
                </ul>
                <a href="../logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-calendar-check me-2 text-success"></i>Rekap Kehadiran Siswa</h4>
                <p class="text-muted small mb-0">Riwayat presensi harian untuk siswa: <strong><?= htmlspecialchars($data_siswa['nama_lengkap'] ?? ''); ?></strong></p>
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
                                <th>Tanggal</th>
                                <th>Mata Pelajaran</th>
                                <th class="text-center">Status Kehadiran</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($query_absensi && mysqli_num_rows($query_absensi) > 0): ?>
                                <?php $no = 1; while ($a = mysqli_fetch_assoc($query_absensi)): ?>
                                    <tr>
                                        <td class="ps-3"><?= $no++; ?></td>
                                        <td><?= date('d-m-Y', strtotime($a['tanggal'])); ?></td>
                                        <td><strong><?= htmlspecialchars($a['nama_mapel']); ?></strong></td>
                                        <td class="text-center">
                                            <?php
                                            $badge_class = 'bg-secondary';
                                            if ($a['status'] == 'Hadir') $badge_class = 'bg-success';
                                            elseif ($a['status'] == 'Izin') $badge_class = 'bg-info text-dark';
                                            elseif ($a['status'] == 'Sakit') $badge_class = 'bg-warning text-dark';
                                            elseif ($a['status'] == 'Alpa') $badge_class = 'bg-danger';
                                            ?>
                                            <span class="badge <?= $badge_class; ?> fs-6"><?= htmlspecialchars($a['status']); ?></span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">Belum ada catatan data absensi.</td>
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
