<?php
// guru/index.php
session_start();
require_once '../config/koneksi.php';

// Proteksi Halaman: Cek Sesi & Role Guru
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'guru') {
    header("Location: ../login.php");
    exit();
}

$id_user = $_SESSION['id_user'];

// 1. Ambil data profil guru berdasarkan id_user
$stmt_guru = mysqli_prepare($koneksi, "SELECT * FROM guru WHERE id_user = ?");
mysqli_stmt_bind_param($stmt_guru, "i", $id_user);
mysqli_stmt_execute($stmt_guru);
$result_guru = mysqli_stmt_get_result($stmt_guru);
$guru = mysqli_fetch_assoc($result_guru);
$id_guru = $guru['id_guru'] ?? 0;
mysqli_stmt_close($stmt_guru);

// 2. Daftar Kelas yang Diajar (Distinct)
$stmt_kelas = mysqli_prepare($koneksi, "SELECT DISTINCT k.id_kelas, k.nama_kelas 
                                       FROM jadwal j 
                                       JOIN kelas k ON j.id_kelas = k.id_kelas 
                                       WHERE j.id_guru = ? ORDER BY k.nama_kelas ASC");
mysqli_stmt_bind_param($stmt_kelas, "i", $id_guru);
mysqli_stmt_execute($stmt_kelas);
$result_kelas_list = mysqli_stmt_get_result($stmt_kelas);
$list_kelas = mysqli_fetch_all($result_kelas_list, MYSQLI_ASSOC);
$total_kelas = count($list_kelas);
mysqli_stmt_close($stmt_kelas);

// 3. Daftar Mapel yang Diampu (Distinct)
$stmt_mapel = mysqli_prepare($koneksi, "SELECT DISTINCT m.id_mapel, m.nama_mapel 
                                       FROM jadwal j 
                                       JOIN mapel m ON j.id_mapel = m.id_mapel 
                                       WHERE j.id_guru = ? ORDER BY m.nama_mapel ASC");
mysqli_stmt_bind_param($stmt_mapel, "i", $id_guru);
mysqli_stmt_execute($stmt_mapel);
$result_mapel_list = mysqli_stmt_get_result($stmt_mapel);
$list_mapel = mysqli_fetch_all($result_mapel_list, MYSQLI_ASSOC);
$total_mapel = count($list_mapel);
mysqli_stmt_close($stmt_mapel);

// 4. Daftar Jadwal Mengajar Guru
$stmt_jadwal = mysqli_prepare($koneksi, "SELECT j.*, k.nama_kelas, m.nama_mapel 
                                       FROM jadwal j 
                                       JOIN kelas k ON j.id_kelas = k.id_kelas 
                                       JOIN mapel m ON j.id_mapel = m.id_mapel 
                                       WHERE j.id_guru = ? 
                                       ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu')");
mysqli_stmt_bind_param($stmt_jadwal, "i", $id_guru);
mysqli_stmt_execute($stmt_jadwal);
$query_jadwal = mysqli_stmt_get_result($stmt_jadwal);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Guru - Portal Guru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .card-clickable {
            cursor: pointer;
            transition: transform 0.2s ease, shadow 0.2s ease;
        }
        .card-clickable:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
        }
    </style>
</head>
<body class="bg-light">

    <!-- Navbar Portal Guru -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-journal-check me-2"></i>Portal Guru</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navGuru">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navGuru">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link active" href="index.php">Dashboard</a></li>
                    
                    <!-- Dropdown Menu Absensi -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownAbsensi" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-check2-square me-1"></i> Absensi
                        </a>
                        <ul class="dropdown-menu shadow border-0" aria-labelledby="navbarDropdownAbsensi">
                            <li><a class="dropdown-item py-2" href="absensi.php"><i class="bi bi-pencil-square me-2 text-primary"></i> Input Absensi Manual</a></li>
                            <li><a class="dropdown-item py-2" href="generate_qr.php"><i class="bi bi-qr-code-scan me-2 text-success"></i> Buat QR Code Absensi</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item py-2" href="rekap_absensi.php"><i class="bi bi-file-earmark-text me-2 text-warning"></i> Rekap Absensi</a></li>
                            <li><a class="dropdown-item py-2" href="ekspor_absensi_csv.php"><i class="bi bi-file-earmark-spreadsheet me-2 text-info"></i> Ekspor ke CSV</a></li>
                        </ul>
                    </li>

                    <li class="nav-item"><a class="nav-link" href="nilai.php">Input Nilai</a></li>
                </ul>
                <div class="d-flex align-items-center">
                    <span class="text-white me-3">Halo, <strong><?= htmlspecialchars($guru['nama_guru'] ?? $_SESSION['username'] ?? 'Guru'); ?></strong></span>
                    <a href="../logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <!-- Banner Selamat Datang -->
        <div class="p-4 mb-4 bg-white rounded-3 shadow-sm border-start border-4 border-primary">
            <h4 class="fw-bold mb-1">Selamat Datang, <?= htmlspecialchars($guru['nama_guru'] ?? 'Bapak/Ibu Guru'); ?>!</h4>
            <p class="text-muted mb-0 small">NIP: <?= htmlspecialchars($guru['nip'] ?? '-'); ?> | Kelola kehadiran siswa dan penginputan nilai secara terintegrasi.</p>
        </div>

        <!-- Metric Cards (Klik untuk membuka modal) -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card shadow-sm border-0 bg-white card-clickable" data-bs-toggle="modal" data-bs-target="#modalKelas">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-3 me-3">
                                <i class="bi bi-building fs-3"></i>
                            </div>
                            <div>
                                <span class="text-muted small">Total Kelas Diajar</span>
                                <h3 class="fw-bold mb-0"><?= $total_kelas; ?></h3>
                            </div>
                        </div>
                        <i class="bi bi-chevron-right text-muted fs-5"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm border-0 bg-white card-clickable" data-bs-toggle="modal" data-bs-target="#modalMapel">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <div class="p-3 bg-success bg-opacity-10 text-success rounded-3 me-3">
                                <i class="bi bi-book fs-3"></i>
                            </div>
                            <div>
                                <span class="text-muted small">Mata Pelajaran Diampu</span>
                                <h3 class="fw-bold mb-0"><?= $total_mapel; ?></h3>
                            </div>
                        </div>
                        <i class="bi bi-chevron-right text-muted fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Schedule Table -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 fw-bold border-bottom">
                <i class="bi bi-calendar-week me-1 text-primary"></i> Jadwal Mengajar Anda
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Hari</th>
                                <th>Mata Pelajaran</th>
                                <th>Kelas</th>
                                <th>Jam Mengajar</th>
                                <th class="text-center">Aksi Cepat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($query_jadwal && mysqli_num_rows($query_jadwal) > 0): ?>
                                <?php while ($j = mysqli_fetch_assoc($query_jadwal)): ?>
                                    <?php 
                                        $jam_mulai = isset($j['jam_mulai']) ? date('H:i', strtotime($j['jam_mulai'])) : '07:30';
                                        $jam_selesai = isset($j['jam_selesai']) ? date('H:i', strtotime($j['jam_selesai'])) : '09:00';
                                    ?>
                                    <tr>
                                        <td class="ps-3"><span class="badge bg-secondary"><?= htmlspecialchars($j['hari']); ?></span></td>
                                        <td><strong><?= htmlspecialchars($j['nama_mapel']); ?></strong></td>
                                        <td>
                                            <!-- Klik badge kelas menuju detail siswa & nilai -->
                                            <a href="detail_kelas.php?id_kelas=<?= $j['id_kelas']; ?>" class="badge bg-info text-dark text-decoration-none p-2">
                                                <i class="bi bi-eye me-1"></i><?= htmlspecialchars($j['nama_kelas']); ?>
                                            </a>
                                        </td>
                                        <td><small class="text-muted"><?= $jam_mulai; ?> - <?= $jam_selesai; ?></small></td>
                                        <td class="text-center">
                                            <a href="absensi.php?id_kelas=<?= $j['id_kelas']; ?>&id_mapel=<?= $j['id_mapel']; ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-check2-square me-1"></i> Absen
                                            </a>
                                            <a href="nilai.php?id_kelas=<?= $j['id_kelas']; ?>&id_mapel=<?= $j['id_mapel']; ?>" class="btn btn-sm btn-outline-success ms-1">
                                                <i class="bi bi-pencil-square me-1"></i> Nilai
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">Belum ada jadwal mengajar yang ditugaskan.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Daftar Kelas -->
    <div class="modal fade" id="modalKelas" tabindex="-1" aria-labelledby="modalKelasLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="modalKelasLabel"><i class="bi bi-building me-2"></i>Daftar Kelas Diajar</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if (!empty($list_kelas)): ?>
                            <?php foreach ($list_kelas as $k): ?>
                                <a href="detail_kelas.php?id_kelas=<?= $k['id_kelas']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                                    <div>
                                        <strong class="d-block text-dark"><?= htmlspecialchars($k['nama_kelas']); ?></strong>
                                        <small class="text-muted">Klik untuk melihat detail siswa, absensi & nilai</small>
                                    </div>
                                    <span class="btn btn-sm btn-outline-primary rounded-pill"><i class="bi bi-arrow-right"></i></span>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-4 text-center text-muted">Belum ada kelas yang diampu.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Daftar Mapel -->
    <div class="modal fade" id="modalMapel" tabindex="-1" aria-labelledby="modalMapelLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold" id="modalMapelLabel"><i class="bi bi-book me-2"></i>Mata Pelajaran Diampu</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php if (!empty($list_mapel)): ?>
                            <?php foreach ($list_mapel as $m): ?>
                                <li class="list-group-item py-3 d-flex align-items-center">
                                    <i class="bi bi-journal-bookmark text-success me-3 fs-5"></i>
                                    <strong class="text-dark"><?= htmlspecialchars($m['nama_mapel']); ?></strong>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-4 text-center text-muted">Belum ada mata pelajaran yang diampu.</div>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>