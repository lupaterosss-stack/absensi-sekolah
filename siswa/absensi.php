<?php
// siswa/absensi.php
session_start();
require_once '../config/koneksi.php';

// =====================================================
// PROTEKSI HALAMAN: CEK SESI & ROLE SISWA
// =====================================================
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'siswa') {
    header("Location: ../login.php");
    exit();
}

$id_user = $_SESSION['id_user'];

// =====================================================
// 1. AMBIL DATA SISWA BERDASARKAN ID USER LOGIN
// =====================================================
$stmt_siswa = mysqli_prepare(
    $koneksi,
    "SELECT s.id_siswa, s.nisn, s.nama_lengkap, s.id_kelas, k.nama_kelas 
     FROM siswa s 
     LEFT JOIN kelas k ON s.id_kelas = k.id_kelas 
     WHERE s.id_user = ?"
);
mysqli_stmt_bind_param($stmt_siswa, "i", $id_user);
mysqli_stmt_execute($stmt_siswa);
$res_siswa = mysqli_stmt_get_result($stmt_siswa);
$siswa = mysqli_fetch_assoc($res_siswa);
mysqli_stmt_close($stmt_siswa);

$id_siswa = $siswa['id_siswa'] ?? 0;
$id_kelas = $siswa['id_kelas'] ?? 0;

// =====================================================
// 2. AMBIL DAFTAR MATA PELAJARAN YANG DIAMBIL KELAS INI
// =====================================================
$stmt_mapel = mysqli_prepare(
    $koneksi,
    "SELECT DISTINCT m.id_mapel, m.nama_mapel 
     FROM jadwal j 
     JOIN mapel m ON j.id_mapel = m.id_mapel 
     WHERE j.id_kelas = ? 
     ORDER BY m.nama_mapel ASC"
);
mysqli_stmt_bind_param($stmt_mapel, "i", $id_kelas);
mysqli_stmt_execute($stmt_mapel);
$list_mapel = mysqli_fetch_all(mysqli_stmt_get_result($stmt_mapel), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_mapel);

// =====================================================
// 3. AMBIL SELURUH TANGGAL PERTEMUAN UNTUK KELAS INI
// =====================================================
$stmt_tgl = mysqli_prepare(
    $koneksi,
    "SELECT DISTINCT a.tanggal, j.id_mapel 
     FROM absensi a 
     JOIN jadwal j ON a.id_jadwal = j.id_jadwal 
     WHERE j.id_kelas = ? 
     ORDER BY a.tanggal ASC"
);
mysqli_stmt_bind_param($stmt_tgl, "i", $id_kelas);
mysqli_stmt_execute($stmt_tgl);
$res_tgl = mysqli_stmt_get_result($stmt_tgl);
$all_tanggal = [];
while ($row = mysqli_fetch_assoc($res_tgl)) {
    // Kumpulkan tanggal unik secara global atau per mapel
    $all_tanggal[$row['tanggal']] = true;
}
mysqli_stmt_close($stmt_tgl);

// Urutkan tanggal dari yang terlama ke terbaru
$list_tanggal = array_keys($all_tanggal);
sort($list_tanggal);

// =====================================================
// 4. AMBIL DATA ABSENSI SISWA INI (MAP: [id_mapel][tanggal] = status)
// =====================================================
$matrix_absensi = [];
if ($id_siswa > 0) {
    $stmt_abs = mysqli_prepare(
        $koneksi,
        "SELECT a.tanggal, j.id_mapel, a.status, a.keterangan 
         FROM absensi a 
         JOIN jadwal j ON a.id_jadwal = j.id_jadwal 
         WHERE a.id_siswa = ?"
    );
    mysqli_stmt_bind_param($stmt_abs, "i", $id_siswa);
    mysqli_stmt_execute($stmt_abs);
    $res_abs = mysqli_stmt_get_result($stmt_abs);
    while ($row = mysqli_fetch_assoc($res_abs)) {
        $matrix_absensi[$row['id_mapel']][$row['tanggal']] = [
            'status' => $row['status'],
            'keterangan' => $row['keterangan']
        ];
    }
    mysqli_stmt_close($stmt_abs);
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matriks Rekap Absensi - Portal Siswa</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <style>
        .table-matrix th, .table-matrix td {
            text-align: center;
            vertical-align: middle;
            font-size: 13px;
        }
        .cell-status {
            display: inline-block;
            width: 28px;
            height: 28px;
            line-height: 28px;
            border-radius: 50%;
            font-weight: bold;
        }
        .st-Hadir { background-color: #d1e7dd; color: #0f5132; }
        .st-Sakit { background-color: #fff3cd; color: #664d03; }
        .st-Izin  { background-color: #cff4fc; color: #055160; }
        .st-Alpa  { background-color: #f8d7da; color: #842029; }
        .st-Empty { background-color: #e9ecef; color: #6c757d; }
    </style>
</head>

<body class="bg-light">

<!-- =====================================================
     NAVBAR
===================================================== -->
<nav class="navbar navbar-expand-lg navbar-dark bg-success shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">
            <i class="bi bi-mortarboard-fill me-2"></i>Portal Siswa
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navSiswa">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navSiswa">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="scan_absensi.php">Scan QR Absen</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="absensi.php">Rekap Absensi</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="nilai.php">Nilai & Transkrip</a>
                </li>
            </ul>

            <div class="d-flex align-items-center">
                <span class="text-white me-3">
                    Halo, <strong><?= htmlspecialchars($siswa['nama_lengkap'] ?? 'Siswa'); ?></strong>
                </span>

                <a href="../logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i>Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- =====================================================
     CONTENT
===================================================== -->
<div class="container-fluid px-4 my-4">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                <i class="bi bi-grid-3x3-gap text-success me-2"></i>Matriks Rekap Kehadiran
            </h4>
            <p class="text-muted small mb-0">
                Kelas: <strong><?= htmlspecialchars($siswa['nama_kelas'] ?? '-'); ?></strong> | NISN: <code><?= htmlspecialchars($siswa['nisn'] ?? '-'); ?></code>
            </p>
        </div>

        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <!-- PETUNJUK STATUS -->
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body py-2 d-flex align-items-center gap-4 text-center small">
            <span class="fw-bold text-muted me-2"><i class="bi bi-info-circle me-1"></i> Keterangan:</span>
            <div><span class="cell-status st-Hadir me-1">H</span> Hadir</div>
            <div><span class="cell-status st-Sakit me-1">S</span> Sakit</div>
            <div><span class="cell-status st-Izin me-1">I</span> Izin</div>
            <div><span class="cell-status st-Alpa me-1">A</span> Alpa</div>
            <div><span class="cell-status st-Empty me-1">-</span> Tidak ada jadwal / Belum diabsen</div>
        </div>
    </div>

    <!-- TABEL MATRIKS ABSENSI SISWA -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-matrix align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;" rowspan="2">No</th>
                            <th style="width: 250px;" class="text-start" rowspan="2">Mata Pelajaran</th>
                            <?php if (!empty($list_tanggal)): ?>
                                <th colspan="<?= count($list_tanggal); ?>">Tanggal Pertemuan</th>
                            <?php else: ?>
                                <th>Tanggal Pertemuan</th>
                            <?php endif; ?>
                            <th colspan="4" style="width: 160px;">Total Rekap</th>
                        </tr>
                        <tr>
                            <?php if (!empty($list_tanggal)): ?>
                                <?php foreach ($list_tanggal as $tgl): ?>
                                    <th style="min-width: 55px;" class="px-1">
                                        <?= date('d/m', strtotime($tgl)); ?>
                                    </th>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <th class="text-muted small fw-normal py-3">Belum ada riwayat pertemuan</th>
                            <?php endif; ?>
                            <th style="width: 40px;" class="text-success" title="Hadir">H</th>
                            <th style="width: 40px;" class="text-warning" title="Sakit">S</th>
                            <th style="width: 40px;" class="text-info" title="Izin">I</th>
                            <th style="width: 40px;" class="text-danger" title="Alpa">A</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($list_mapel)): ?>
                            <?php $no = 1; foreach ($list_mapel as $m): ?>
                                <?php 
                                    $id_m = $m['id_mapel'];
                                    $cnt_h = 0; $cnt_s = 0; $cnt_i = 0; $cnt_a = 0;
                                ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td class="text-start"><strong><?= htmlspecialchars($m['nama_mapel']); ?></strong></td>

                                    <!-- Render Kolom Tanggal -->
                                    <?php if (!empty($list_tanggal)): ?>
                                        <?php foreach ($list_tanggal as $tgl): ?>
                                            <?php 
                                                $data_absen = $matrix_absensi[$id_m][$tgl] ?? null;
                                                $st = $data_absen['status'] ?? '-';
                                                $ket = $data_absen['keterangan'] ?? '';

                                                $code = '-';
                                                if ($st === 'Hadir') { $code = 'H'; $cnt_h++; }
                                                elseif ($st === 'Sakit') { $code = 'S'; $cnt_s++; }
                                                elseif ($st === 'Izin')  { $code = 'I'; $cnt_i++; }
                                                elseif ($st === 'Alpa')  { $code = 'A'; $cnt_a++; }

                                                $tooltip = date('d-m-Y', strtotime($tgl)) . ' : ' . $st . ($ket ? " ($ket)" : '');
                                            ?>
                                            <td>
                                                <?php if ($st !== '-'): ?>
                                                    <span class="cell-status st-<?= $st; ?>" title="<?= htmlspecialchars($tooltip); ?>">
                                                        <?= $code; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <td class="text-muted small">-</td>
                                    <?php endif; ?>

                                    <!-- Total Rekap Kanan -->
                                    <td class="fw-bold text-success bg-light"><?= $cnt_h; ?></td>
                                    <td class="fw-bold text-warning bg-light"><?= $cnt_s; ?></td>
                                    <td class="fw-bold text-info bg-light"><?= $cnt_i; ?></td>
                                    <td class="fw-bold text-danger bg-light"><?= $cnt_a; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="100%" class="text-center py-4 text-muted">Belum ada mata pelajaran terdaftar untuk kelas ini.</td>
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