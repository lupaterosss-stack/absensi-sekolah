<?php
// siswa/index.php
session_start();
require_once '../config/koneksi.php';

// Proteksi Halaman: Cek Sesi & Role Siswa
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'siswa') {
    header("Location: ../login.php");
    exit();
}

$id_user = $_SESSION['id_user'];

// Ambil Detail Data Siswa & Kelas
$stmt_siswa = mysqli_prepare($koneksi, "
    SELECT s.id_siswa, s.nisn, s.nama_lengkap, k.id_kelas, k.nama_kelas 
    FROM siswa s 
    LEFT JOIN kelas k ON s.id_kelas = k.id_kelas 
    WHERE s.id_user = ?
");
mysqli_stmt_bind_param($stmt_siswa, "i", $id_user);
mysqli_stmt_execute($stmt_siswa);
$data_siswa = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_siswa));

$id_siswa = $data_siswa['id_siswa'] ?? 0;
$id_kelas = $data_siswa['id_kelas'] ?? 0;

// Ambil Jadwal Pelajaran Berdasarkan Kelas Siswa
$jadwal_list = [];
if ($id_kelas > 0) {
    $stmt_jadwal = mysqli_prepare($koneksi, "
        SELECT j.hari, j.jam_mulai, j.jam_selesai, m.nama_mapel, g.nama_lengkap AS nama_guru 
        FROM jadwal_pelajaran j 
        JOIN mapel m ON j.id_mapel = m.id_mapel 
        JOIN guru g ON j.id_guru = g.id_guru 
        WHERE j.id_kelas = ? 
        ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'), j.jam_mulai ASC
    ");
    mysqli_stmt_bind_param($stmt_jadwal, "i", $id_kelas);
    mysqli_stmt_execute($stmt_jadwal);
    $jadwal_list = mysqli_stmt_get_result($stmt_jadwal);
}

// Stats Ringkas Kehadiran
$stat_hadir = 0; $stat_izin = 0; $stat_sakit = 0; $stat_alpa = 0;
if ($id_siswa > 0) {
    $query_stat = mysqli_query($koneksi, "
        SELECT status, COUNT(*) AS jumlah 
        FROM absensi 
        WHERE id_siswa = $id_siswa 
        GROUP BY status
    ");
    while ($r = mysqli_fetch_assoc($query_stat)) {
        if ($r['status'] == 'Hadir') $stat_hadir = $r['jumlah'];
        elseif ($r['status'] == 'Izin') $stat_izin = $r['jumlah'];
        elseif ($r['status'] == 'Sakit') $stat_sakit = $r['jumlah'];
        elseif ($r['status'] == 'Alpa') $stat_alpa = $r['jumlah'];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Siswa - Portal Siswa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
<!-- Navbar Portal Siswa -->
<nav class="navbar navbar-expand-lg navbar-dark bg-success shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-person-badge me-2"></i>Portal Siswa</a>
        
        <!-- Tombol Toggler untuk Tampilan Mobile -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navSiswa">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navSiswa">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link active" href="index.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="absensi.php">Rekap Absensi</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="scan_absensi.php">Scan QR Absen</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="nilai.php">Nilai & Rapor</a>
                </li>
            </ul>
            <a href="../logout.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-box-arrow-right me-1"></i>Logout
            </a>
        </div>
    </div>
</nav>
    <div class="container my-4">
        <!-- Banner Selamat Datang -->
        <div class="p-4 mb-4 bg-white rounded-3 shadow-sm border-start border-4 border-success">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h3 class="fw-bold mb-1">Selamat Datang, <?= htmlspecialchars($data_siswa['nama_lengkap'] ?? 'Siswa'); ?>!</h3>
                    <p class="text-muted mb-0">NISN: <code><?= htmlspecialchars($data_siswa['nisn'] ?? '-'); ?></code> | Kelas: <span class="badge bg-success"><?= htmlspecialchars($data_siswa['nama_kelas'] ?? 'Belum Ditentukan'); ?></span></p>
                </div>
                <div class="d-none d-md-block text-end">
                    <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i> <?= date('d M Y'); ?></span>
                </div>
            </div>
        </div>

        <!-- Ringkasan Statistik Presensi -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm bg-success text-white">
                    <div class="card-body text-center py-3">
                        <h6 class="text-uppercase small fw-bold mb-1">Hadir</h6>
                        <h3 class="fw-bold mb-0"><?= $stat_hadir; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm bg-info text-white">
                    <div class="card-body text-center py-3">
                        <h6 class="text-uppercase small fw-bold mb-1">Izin</h6>
                        <h3 class="fw-bold mb-0"><?= $stat_izin; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm bg-warning text-dark">
                    <div class="card-body text-center py-3">
                        <h6 class="text-uppercase small fw-bold mb-1">Sakit</h6>
                        <h3 class="fw-bold mb-0"><?= $stat_sakit; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm bg-danger text-white">
                    <div class="card-body text-center py-3">
                        <h6 class="text-uppercase small fw-bold mb-1">Alpa</h6>
                        <h3 class="fw-bold mb-0"><?= $stat_alpa; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Jadwal Pelajaran -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history me-2 text-success"></i>Jadwal Pelajaran Kelas Anda</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Hari</th>
                                <th>Jam Pelajaran</th>
                                <th>Mata Pelajaran</th>
                                <th>Guru Pengampu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($jadwal_list && mysqli_num_rows($jadwal_list) > 0): ?>
                                <?php while ($j = mysqli_fetch_assoc($jadwal_list)): ?>
                                    <tr>
                                        <td class="ps-3"><span class="badge bg-secondary"><?= htmlspecialchars($j['hari']); ?></span></td>
                                        <td><?= date('H:i', strtotime($j['jam_mulai'])); ?> - <?= date('H:i', strtotime($j['jam_selesai'])); ?></td>
                                        <td class="fw-bold text-dark"><?= htmlspecialchars($j['nama_mapel']); ?></td>
                                        <td><?= htmlspecialchars($j['nama_guru']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">Belum ada jadwal pelajaran untuk kelas ini.</td>
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