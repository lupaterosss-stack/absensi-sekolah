<?php
// guru/detail_kelas.php
session_start();
require_once '../config/koneksi.php';

// Proteksi Halaman: Cek Sesi & Role Guru
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'guru') {
    header("Location: ../login.php");
    exit();
}

$id_user = $_SESSION['id_user'];

// Ambil id_guru
$stmt_guru = mysqli_prepare($koneksi, "SELECT id_guru FROM guru WHERE id_user = ?");
mysqli_stmt_bind_param($stmt_guru, "i", $id_user);
mysqli_stmt_execute($stmt_guru);
$guru = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_guru));
$id_guru = $guru['id_guru'] ?? 0;
mysqli_stmt_close($stmt_guru);

// Tangkap ID Kelas & ID Mapel dari URL
$id_kelas = intval($_GET['id_kelas'] ?? 0);
$id_mapel = intval($_GET['id_mapel'] ?? 0);

if ($id_kelas <= 0) {
    header("Location: index.php");
    exit();
}

// Ambil Informasi Kelas
$stmt_kelas = mysqli_prepare($koneksi, "SELECT nama_kelas FROM kelas WHERE id_kelas = ?");
mysqli_stmt_bind_param($stmt_kelas, "i", $id_kelas);
mysqli_stmt_execute($stmt_kelas);
$data_kelas = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_kelas));
$nama_kelas = $data_kelas['nama_kelas'] ?? 'Kelas Tidak Dikenal';
mysqli_stmt_close($stmt_kelas);

// Ambil daftar mapel yang diajar guru di kelas ini
$stmt_mapel_list = mysqli_prepare($koneksi, "
    SELECT DISTINCT m.id_mapel, m.nama_mapel 
    FROM jadwal j 
    JOIN mapel m ON j.id_mapel = m.id_mapel 
    WHERE j.id_guru = ? AND j.id_kelas = ?
    ORDER BY m.nama_mapel ASC
");
mysqli_stmt_bind_param($stmt_mapel_list, "ii", $id_guru, $id_kelas);
mysqli_stmt_execute($stmt_mapel_list);
$list_mapel = mysqli_fetch_all(mysqli_stmt_get_result($stmt_mapel_list), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_mapel_list);

if ($id_mapel <= 0 && !empty($list_mapel)) {
    $id_mapel = $list_mapel[0]['id_mapel'];
}

// 1. Ambil Semua Daftar Tanggal Pertemuan yang Pernah Diabsen (Header Kolom Matriks)
$list_tanggal = [];
if ($id_kelas > 0 && $id_mapel > 0) {
    $stmt_tgl = mysqli_prepare($koneksi, "
        SELECT DISTINCT a.tanggal, j.id_jadwal 
        FROM absensi a 
        JOIN jadwal j ON a.id_jadwal = j.id_jadwal 
        WHERE j.id_guru = ? AND j.id_kelas = ? AND j.id_mapel = ? 
        ORDER BY a.tanggal ASC
    ");
    mysqli_stmt_bind_param($stmt_tgl, "iii", $id_guru, $id_kelas, $id_mapel);
    mysqli_stmt_execute($stmt_tgl);
    $list_tanggal = mysqli_fetch_all(mysqli_stmt_get_result($stmt_tgl), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_tgl);
}

// 2. Ambil Daftar Siswa dan Seluruh Records Absensi Mereka (Memetakan Data Matriks)
$siswa_list = [];
$matrix_absensi = []; // Map: [id_siswa][tanggal] = status

if ($id_kelas > 0) {
    // Ambil daftar siswa
    $stmt_s = mysqli_prepare($koneksi, "SELECT id_siswa, nisn, nama_lengkap FROM siswa WHERE id_kelas = ? ORDER BY nama_lengkap ASC");
    mysqli_stmt_bind_param($stmt_s, "i", $id_kelas);
    mysqli_stmt_execute($stmt_s);
    $siswa_list = mysqli_fetch_all(mysqli_stmt_get_result($stmt_s), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_s);

    // Ambil data absensi detail untuk diplot ke matriks
    if ($id_mapel > 0) {
        $stmt_m = mysqli_prepare($koneksi, "
            SELECT a.id_siswa, a.tanggal, a.status 
            FROM absensi a 
            JOIN jadwal j ON a.id_jadwal = j.id_jadwal 
            WHERE j.id_guru = ? AND j.id_kelas = ? AND j.id_mapel = ?
        ");
        mysqli_stmt_bind_param($stmt_m, "iii", $id_guru, $id_kelas, $id_mapel);
        mysqli_stmt_execute($stmt_m);
        $res_m = mysqli_stmt_get_result($stmt_m);
        while ($row = mysqli_fetch_assoc($res_m)) {
            $matrix_absensi[$row['id_siswa']][$row['tanggal']] = $row['status'];
        }
        mysqli_stmt_close($stmt_m);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matriks Presensi Siswa - Portal Guru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            text-decoration: none;
            transition: transform 0.15s ease-in-out;
        }
        .cell-status:hover {
            transform: scale(1.2);
        }
        .st-Hadir { background-color: #d1e7dd; color: #0f5132; }
        .st-Sakit { background-color: #fff3cd; color: #664d03; }
        .st-Izin  { background-color: #cff4fc; color: #055160; }
        .st-Alpa  { background-color: #f8d7da; color: #842029; }
        .st-Empty { background-color: #e9ecef; color: #6c757d; }
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
                    <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active" href="absensi.php">Absensi</a></li>
                    <li class="nav-item"><a class="nav-link" href="nilai.php">Input Nilai</a></li>
                </ul>
                <div class="d-flex align-items-center">
                    <a href="../logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-4 my-4">
        <!-- Header & Filter Mapel -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <a href="index.php" class="btn btn-outline-secondary btn-sm me-3"><i class="bi bi-arrow-left"></i> Kembali</a>
                <div>
                    <h4 class="fw-bold mb-0"><i class="bi bi-grid-3x3-gap text-primary me-2"></i>Matriks Presensi Harian: <?= htmlspecialchars($nama_kelas); ?></h4>
                    <p class="text-muted small mb-0">Rincian status absensi siswa per tanggal pertemuan. Klik pada bulatan status untuk mengedit tanggal tersebut.</p>
                </div>
            </div>

            <?php if (count($list_mapel) > 1): ?>
                <form method="GET" action="detail_kelas.php" class="d-flex align-items-center">
                    <input type="hidden" name="id_kelas" value="<?= $id_kelas; ?>">
                    <select name="id_mapel" class="form-select form-select-sm me-2" onchange="this.form.submit()">
                        <?php foreach ($list_mapel as $m): ?>
                            <option value="<?= $m['id_mapel']; ?>" <?= ($id_mapel == $m['id_mapel']) ? 'selected' : ''; ?>>
                                Mapel: <?= htmlspecialchars($m['nama_mapel']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php endif; ?>
        </div>

        <!-- Legend / Petunjuk Kode -->
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body py-2 d-flex align-items-center gap-4 text-center small">
                <span class="fw-bold text-muted me-2"><i class="bi bi-info-circle me-1"></i> Keterangan Status:</span>
                <div><span class="cell-status st-Hadir me-1">H</span> Hadir</div>
                <div><span class="cell-status st-Sakit me-1">S</span> Sakit</div>
                <div><span class="cell-status st-Izin me-1">I</span> Izin</div>
                <div><span class="cell-status st-Alpa me-1">A</span> Alpa</div>
                <div><span class="cell-status st-Empty me-1">-</span> Belum Diabsen</div>
            </div>
        </div>

        <!-- TABEL MATRIKS PRESENSI HARIAN -->
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-matrix align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px;" rowspan="2">No</th>
                                <th style="width: 220px;" class="text-start" rowspan="2">Nama Lengkap Siswa</th>
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
                                            <a href="absensi.php?id_jadwal=<?= $tgl['id_jadwal']; ?>&tanggal=<?= $tgl['tanggal']; ?>" class="text-decoration-none text-dark d-block" title="Klik untuk edit presensi tanggal ini">
                                                <?= date('d/m', strtotime($tgl['tanggal'])); ?>
                                                <i class="bi bi-pencil-square d-block text-primary" style="font-size: 10px;"></i>
                                            </a>
                                        </th>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <th class="text-muted small fw-normal py-3">Belum ada tanggal pertemuan</th>
                                <?php endif; ?>
                                <th style="width: 40px;" class="text-success" title="Hadir">H</th>
                                <th style="width: 40px;" class="text-warning" title="Sakit">S</th>
                                <th style="width: 40px;" class="text-info" title="Izin">I</th>
                                <th style="width: 40px;" class="text-danger" title="Alpa">A</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($siswa_list)): ?>
                                <?php $no = 1; foreach ($siswa_list as $s): ?>
                                    <?php 
                                        $id_s = $s['id_siswa'];
                                        $cnt_h = 0; $cnt_s = 0; $cnt_i = 0; $cnt_a = 0;
                                    ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td class="text-start"><strong><?= htmlspecialchars($s['nama_lengkap']); ?></strong></td>
                                        
                                        <!-- Render Sel Matriks Tanggal -->
                                        <?php if (!empty($list_tanggal)): ?>
                                            <?php foreach ($list_tanggal as $tgl): ?>
                                                <?php 
                                                    $t = $tgl['tanggal'];
                                                    $st = $matrix_absensi[$id_s][$t] ?? '-';

                                                    $code = '-';
                                                    if ($st === 'Hadir') { $code = 'H'; $cnt_h++; }
                                                    elseif ($st === 'Sakit') { $code = 'S'; $cnt_s++; }
                                                    elseif ($st === 'Izin')  { $code = 'I'; $cnt_i++; }
                                                    elseif ($st === 'Alpa')  { $code = 'A'; $cnt_a++; }
                                                ?>
                                                <td>
                                                    <a href="absensi.php?id_jadwal=<?= $tgl['id_jadwal']; ?>&tanggal=<?= $tgl['tanggal']; ?>" 
                                                       class="cell-status st-<?= ($st !== '-' ? $st : 'Empty'); ?>" 
                                                       title="Edit <?= htmlspecialchars($s['nama_lengkap']); ?> (<?= date('d-m-Y', strtotime($t)); ?>)">
                                                        <?= $code; ?>
                                                    </a>
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
                                    <td colspan="100%" class="text-center py-4 text-muted">Belum ada data siswa terdaftar di kelas ini.</td>
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