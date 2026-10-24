<?php
// guru/rekap_absensi.php
session_start();
require_once '../config/koneksi.php';

// Proteksi Halaman: Cek Sesi & Role Guru
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'guru') {
    header("Location: ../login.php");
    exit();
}

$id_user = $_SESSION['id_user'];

// ==========================================================
// AMBIL ID GURU
// ==========================================================
$stmt_guru = mysqli_prepare(
    $koneksi,
    "SELECT id_guru FROM guru WHERE id_user = ?"
);
mysqli_stmt_bind_param($stmt_guru, "i", $id_user);
mysqli_stmt_execute($stmt_guru);
$guru = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_guru));
$id_guru = $guru['id_guru'] ?? 0;
mysqli_stmt_close($stmt_guru);

// ==========================================================
// AMBIL DAFTAR KELAS & MAPEL YANG DIAJAR GURU
// ==========================================================
$stmt_jadwal = mysqli_prepare(
    $koneksi,
    "SELECT DISTINCT
        k.id_kelas,
        k.nama_kelas,
        m.id_mapel,
        m.nama_mapel
     FROM jadwal j
     JOIN kelas k ON j.id_kelas = k.id_kelas
     JOIN mapel m ON j.id_mapel = m.id_mapel
     WHERE j.id_guru = ?
     ORDER BY k.nama_kelas ASC, m.nama_mapel ASC"
);
mysqli_stmt_bind_param($stmt_jadwal, "i", $id_guru);
mysqli_stmt_execute($stmt_jadwal);
$list_filter = mysqli_fetch_all(mysqli_stmt_get_result($stmt_jadwal), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_jadwal);

// ==========================================================
// FILTER
// ==========================================================
$selected_filter = $_GET['filter_jadwal'] ?? '';
$semester = $_GET['semester'] ?? 'Ganjil';
$tahun_ajaran = $_GET['tahun_ajaran'] ?? date('Y') . '/' . (date('Y') + 1);

$info_kbm = '';
$list_tanggal = [];
$siswas = [];
$matrix_absensi = [];

if (!empty($selected_filter)) {
    $parts = explode('|', $selected_filter);

    if (count($parts) == 2) {
        $id_kelas_filter = intval($parts[0]);
        $id_mapel_filter = intval($parts[1]);

        // ==================================================
        // AMBIL INFO KELAS & MAPEL
        // ==================================================
        $stmt_info = mysqli_prepare(
            $koneksi,
            "SELECT k.nama_kelas, m.nama_mapel
             FROM kelas k
             CROSS JOIN mapel m
             WHERE k.id_kelas = ? AND m.id_mapel = ?"
        );
        mysqli_stmt_bind_param($stmt_info, "ii", $id_kelas_filter, $id_mapel_filter);
        mysqli_stmt_execute($stmt_info);
        $res_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_info));
        if ($res_info) {
            $info_kbm = "Kelas: <strong>" . htmlspecialchars($res_info['nama_kelas']) . "</strong> | Mapel: <strong>" . htmlspecialchars($res_info['nama_mapel']) . "</strong>";
        }
        mysqli_stmt_close($stmt_info);

        // ==================================================
        // TENTUKAN RENTANG BULAN & TAHUN BERDASARKAN SEMESTER
        // ==================================================
        $bulan_awal = ($semester == 'Ganjil') ? ['07', '08', '09', '10', '11', '12'] : ['01', '02', '03', '04', '05', '06'];
        $tahun_parts = explode('/', $tahun_ajaran);
        $tahun_dasar = intval($tahun_parts[0]);
        $tahun_genap = isset($tahun_parts[1]) ? intval($tahun_parts[1]) : ($tahun_dasar + 1);

        // ==================================================
        // AMBIL SEMUA TANGGAL PERTEMUAN DALAM SEMESTER TERSEBUT
        // ==================================================
        $tgl_kondisi = [];
        foreach ($bulan_awal as $bln) {
            $thn_target = ($semester == 'Genap' && intval($bln) <= 6) ? $tahun_genap : $tahun_dasar;
            $tgl_kondisi[] = "a.tanggal LIKE '{$thn_target}-{$bln}%'";
        }
        $where_bulan = "(" . implode(' OR ', $tgl_kondisi) . ")";

        $query_tgl = "
            SELECT DISTINCT a.tanggal, j.id_jadwal 
            FROM absensi a 
            JOIN jadwal j ON a.id_jadwal = j.id_jadwal 
            WHERE j.id_kelas = ? AND j.id_mapel = ? AND $where_bulan 
            ORDER BY a.tanggal ASC
        ";
        $stmt_tgl = mysqli_prepare($koneksi, $query_tgl);
        mysqli_stmt_bind_param($stmt_tgl, "ii", $id_kelas_filter, $id_mapel_filter);
        mysqli_stmt_execute($stmt_tgl);
        $res_tgl = mysqli_stmt_get_result($stmt_tgl);
        while ($row = mysqli_fetch_assoc($res_tgl)) {
            $list_tanggal[] = $row;
        }
        mysqli_stmt_close($stmt_tgl);

        // ==================================================
        // AMBIL DAFTAR SISWA
        // ==================================================
        $stmt_siswa = mysqli_prepare(
            $koneksi,
            "SELECT id_siswa, nisn, nama_lengkap FROM siswa WHERE id_kelas = ? ORDER BY nama_lengkap ASC"
        );
        mysqli_stmt_bind_param($stmt_siswa, "i", $id_kelas_filter);
        mysqli_stmt_execute($stmt_siswa);
        $siswas = mysqli_fetch_all(mysqli_stmt_get_result($stmt_siswa), MYSQLI_ASSOC);
        mysqli_stmt_close($stmt_siswa);

        // ==================================================
        // AMBIL DETAIL ABSENSI UNTUK DIISI KE MATRIKS
        // ==================================================
        $query_abs = "
            SELECT a.id_siswa, a.tanggal, a.status 
            FROM absensi a 
            JOIN jadwal j ON a.id_jadwal = j.id_jadwal 
            WHERE j.id_kelas = ? AND j.id_mapel = ? AND $where_bulan
        ";
        $stmt_abs = mysqli_prepare($koneksi, $query_abs);
        mysqli_stmt_bind_param($stmt_abs, "ii", $id_kelas_filter, $id_mapel_filter);
        mysqli_stmt_execute($stmt_abs);
        $res_abs = mysqli_stmt_get_result($stmt_abs);
        while ($row = mysqli_fetch_assoc($res_abs)) {
            $matrix_absensi[$row['id_siswa']][$row['tanggal']] = $row['status'];
        }
        mysqli_stmt_close($stmt_abs);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matriks Rekap Absensi Semester - Portal Guru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .table-matrix th, .table-matrix td {
            text-align: center;
            vertical-align: middle;
            font-size: 12px;
        }
        .cell-status {
            display: inline-block;
            width: 24px;
            height: 24px;
            line-height: 24px;
            border-radius: 50%;
            font-weight: bold;
            text-decoration: none;
        }
        .st-Hadir { background-color: #d1e7dd !important; color: #0f5132 !important; }
        .st-Sakit { background-color: #fff3cd !important; color: #664d03 !important; }
        .st-Izin  { background-color: #cff4fc !important; color: #055160 !important; }
        .st-Alpa  { background-color: #f8d7da !important; color: #842029 !important; }
        .st-Empty { background-color: #e9ecef !important; color: #6c757d !important; }

        /* PENGATURAN KHUSUS CETAK / PRINT */
        @media print {
            body {
                background-color: white !important;
                color: black !important;
                font-size: 11px;
            }
            .navbar, .card:first-of-type, .btn, .no-print, .bi-pencil-square {
                display: none !important;
            }
            .card {
                border: none !important;
                box-shadow: none !important;
            }
            .table-matrix th, .table-matrix td {
                padding: 4px !important;
                font-size: 10px !important;
            }
            /* Memaksa browser mencetak warna latar belakang status */
            .cell-status, .st-Hadir, .st-Sakit, .st-Izin, .st-Alpa, .st-Empty {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body class="bg-light">

    <!-- NAVBAR PORTAL GURU -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-journal-check me-2"></i>Portal Guru
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navGuru">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navGuru">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" id="navbarDropdownAbsensi" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-check2-square me-1"></i>Absensi
                        </a>
                        <ul class="dropdown-menu shadow border-0" aria-labelledby="navbarDropdownAbsensi">
                            <li><a class="dropdown-item py-2" href="absensi.php"><i class="bi bi-pencil-square me-2 text-primary"></i>Input Absensi Manual</a></li>
                            <li><a class="dropdown-item py-2" href="generate_qr.php"><i class="bi bi-qr-code-scan me-2 text-success"></i>Buat QR Code Absensi</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item py-2 active" href="rekap_absensi.php"><i class="bi bi-file-earmark-text me-2 text-warning"></i>Rekap Absensi Semester</a></li>
                            <li><a class="dropdown-item py-2" href="ekspor_absensi_csv.php"><i class="bi bi-file-earmark-spreadsheet me-2 text-info"></i>Ekspor ke CSV</a></li>
                        </ul>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="nilai.php">Input Nilai</a></li>
                </ul>
                <div class="d-flex align-items-center">
                    <a href="../logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-4 my-4">
        <!-- HEADER -->
        <div class="d-flex align-items-center mb-4 no-print">
            <a href="index.php" class="btn btn-outline-secondary btn-sm me-3">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
            <h4 class="fw-bold mb-0">
                <i class="bi bi-file-earmark-text text-warning me-2"></i>Matriks Rekap Absensi Semester
            </h4>
        </div>

        <!-- FILTER FORM -->
        <div class="card shadow-sm border-0 mb-4 no-print">
            <div class="card-body">
                <form method="GET" action="" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label for="filter_jadwal" class="form-label small fw-bold">Pilih Kelas & Mata Pelajaran</label>
                        <select name="filter_jadwal" id="filter_jadwal" class="form-select" required>
                            <option value="">-- Pilih Kelas & Mapel --</option>
                            <?php foreach ($list_filter as $f): ?>
                                <?php $val_opt = $f['id_kelas'] . '|' . $f['id_mapel']; ?>
                                <option value="<?= htmlspecialchars($val_opt); ?>" <?= ($selected_filter == $val_opt) ? 'selected' : ''; ?>>
                                    Kelas: <?= htmlspecialchars($f['nama_kelas']); ?> — Mapel: <?= htmlspecialchars($f['nama_mapel']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="semester" class="form-label small fw-bold">Semester</label>
                        <select name="semester" id="semester" class="form-select">
                            <option value="Ganjil" <?= ($semester == 'Ganjil') ? 'selected' : ''; ?>>Semester Ganjil</option>
                            <option value="Genap" <?= ($semester == 'Genap') ? 'selected' : ''; ?>>Semester Genap</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="tahun_ajaran" class="form-label small fw-bold">Tahun Ajaran</label>
                        <input type="text" name="tahun_ajaran" id="tahun_ajaran" class="form-control" value="<?= htmlspecialchars($tahun_ajaran); ?>" placeholder="2025/2026" required>
                    </div>

                    <div class="col-md-2">
                        <button type="submit" class="btn btn-warning w-100 fw-bold">
                            <i class="bi bi-search me-1"></i> Tampilkan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- HASIL REKAP MATRIKS -->
        <?php if (!empty($selected_filter)): ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-1">Laporan Matriks Kehadiran Harian</h5>
                        <p class="text-muted small mb-0">
                            <?= $info_kbm; ?> | Semester: <strong><?= htmlspecialchars($semester); ?></strong> (TA: <?= htmlspecialchars($tahun_ajaran); ?>)
                        </p>
                    </div>
                    <button onclick="window.print();" class="btn btn-outline-secondary btn-sm no-print">
                        <i class="bi bi-printer me-1"></i> Cetak Matriks Rekap
                    </button>
                </div>

                <!-- KETERANGAN KODE -->
                <div class="card-body bg-light py-2 border-top border-bottom d-flex align-items-center gap-4 text-center small no-print">
                    <span class="fw-bold text-muted me-2"><i class="bi bi-info-circle me-1"></i> Kode Status:</span>
                    <div><span class="cell-status st-Hadir me-1">H</span> Hadir</div>
                    <div><span class="cell-status st-Sakit me-1">S</span> Sakit</div>
                    <div><span class="cell-status st-Izin me-1">I</span> Izin</div>
                    <div><span class="cell-status st-Alpa me-1">A</span> Alpa</div>
                    <div><span class="cell-status st-Empty me-1">-</span> Kosong</div>
                    <span class="text-primary ms-auto"><i class="bi bi-pencil-square"></i> Klik tanggal di header untuk koreksi absensi</span>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-matrix align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 35px;" rowspan="2">No</th>
                                    <th style="width: 110px;" rowspan="2">NISN</th>
                                    <th style="width: 200px;" class="text-start" rowspan="2">Nama Lengkap Siswa</th>
                                    <?php if (!empty($list_tanggal)): ?>
                                        <th colspan="<?= count($list_tanggal); ?>">Tanggal Pertemuan Harian</th>
                                    <?php else: ?>
                                        <th>Tanggal Pertemuan</th>
                                    <?php endif; ?>
                                    <th colspan="5" style="width: 160px;">Total Rekap</th>
                                </tr>
                                <tr>
                                    <?php if (!empty($list_tanggal)): ?>
                                        <?php foreach ($list_tanggal as $tgl): ?>
                                            <th style="min-width: 45px;" class="px-1">
                                                <a href="absensi.php?id_jadwal=<?= $tgl['id_jadwal']; ?>&tanggal=<?= $tgl['tanggal']; ?>" class="text-decoration-none text-dark d-block no-print" title="Edit absensi tanggal <?= $tgl['tanggal']; ?>">
                                                    <?= date('d/m', strtotime($tgl['tanggal'])); ?>
                                                    <i class="bi bi-pencil-square d-block text-primary" style="font-size: 9px;"></i>
                                                </a>
                                                <!-- Teks statis khusus saat dicetak agar tidak ada ikon edit -->
                                                <span class="d-none d-print-block"><?= date('d/m', strtotime($tgl['tanggal'])); ?></span>
                                            </th>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <th class="text-muted small fw-normal py-3">Tidak ada jadwal/pertemuan pada semester ini</th>
                                    <?php endif; ?>
                                    <th style="width: 30px;" class="text-success" title="Hadir">H</th>
                                    <th style="width: 30px;" class="text-warning" title="Sakit">S</th>
                                    <th style="width: 30px;" class="text-info" title="Izin">I</th>
                                    <th style="width: 30px;" class="text-danger" title="Alpa">A</th>
                                    <th style="width: 35px;" class="bg-light" title="Total Pertemuan">Tot</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($siswas)): ?>
                                    <?php $no = 1; foreach ($siswas as $s): ?>
                                        <?php 
                                            $id_s = $s['id_siswa'];
                                            $cnt_h = 0; $cnt_s = 0; $cnt_i = 0; $cnt_a = 0;
                                        ?>
                                        <tr>
                                            <td><?= $no++; ?></td>
                                            <td><code><?= htmlspecialchars($s['nisn']); ?></code></td>
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
                                                        <span class="cell-status st-<?= ($st !== '-' ? $st : 'Empty'); ?>">
                                                            <?= $code; ?>
                                                        </span>
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
                                            <td class="fw-bold bg-light"><?= ($cnt_h + $cnt_s + $cnt_i + $cnt_a); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="100%" class="text-center py-4 text-muted">Tidak ada data siswa untuk kelas ini.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card shadow-sm border-0 text-center py-5 no-print">
                <div class="card-body text-muted">
                    <i class="bi bi-file-earmark-bar-graph fs-1 d-block mb-3 text-secondary opacity-50"></i>
                    <p class="mb-0">Silakan pilih kelas, mata pelajaran, dan semester pada form di atas, lalu klik <strong>Tampilkan</strong>.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>