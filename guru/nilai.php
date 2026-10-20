<?php
// guru/nilai.php
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

// Ambil daftar kelas dan mapel yang diajar guru
$stmt_jadwal = mysqli_prepare($koneksi, "SELECT DISTINCT k.id_kelas, k.nama_kelas, m.id_mapel, m.nama_mapel 
                                       FROM jadwal j 
                                       JOIN kelas k ON j.id_kelas = k.id_kelas 
                                       JOIN mapel m ON j.id_mapel = m.id_mapel 
                                       WHERE j.id_guru = ? 
                                       ORDER BY k.nama_kelas ASC, m.nama_mapel ASC");
mysqli_stmt_bind_param($stmt_jadwal, "i", $id_guru);
mysqli_stmt_execute($stmt_jadwal);
$list_filter = mysqli_fetch_all(mysqli_stmt_get_result($stmt_jadwal), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_jadwal);

// Tangkap ID Kelas dan ID Mapel dari GET atau POST
$id_kelas = intval($_POST['id_kelas'] ?? ($_GET['id_kelas'] ?? 0));
$id_mapel = intval($_POST['id_mapel'] ?? ($_GET['id_mapel'] ?? 0));

$pesan = "";
$tipe_pesan = "";

// Proses Simpan / Update Nilai Siswa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_nilai'])) {
    $nilai_tugas_arr = $_POST['nilai_tugas'] ?? [];
    $nilai_uts_arr   = $_POST['nilai_uts'] ?? [];
    $nilai_uas_arr   = $_POST['nilai_uas'] ?? [];

    $berhasil = true;

    if ($id_mapel > 0 && $id_kelas > 0) {
        foreach ($nilai_tugas_arr as $id_siswa => $n_tugas) {
            $id_siswa = intval($id_siswa);
            $tugas    = floatval($n_tugas);
            $uts      = floatval($nilai_uts_arr[$id_siswa] ?? 0);
            $uas      = floatval($nilai_uas_arr[$id_siswa] ?? 0);

            // Hitung Nilai Akhir (30% Tugas + 30% UTS + 40% UAS)
            $nilai_akhir = ($tugas * 0.3) + ($uts * 0.3) + ($uas * 0.4);

            // Cek apakah data nilai siswa untuk mapel ini sudah ada
            $stmt_cek = mysqli_prepare($koneksi, "SELECT id_nilai FROM nilai WHERE id_siswa = ? AND id_mapel = ?");
            mysqli_stmt_bind_param($stmt_cek, "ii", $id_siswa, $id_mapel);
            mysqli_stmt_execute($stmt_cek);
            $res_cek = mysqli_stmt_get_result($stmt_cek);

            if (mysqli_num_rows($res_cek) > 0) {
                // UPDATE data nilai
                $stmt_upd = mysqli_prepare($koneksi, "UPDATE nilai SET nilai_tugas = ?, nilai_uts = ?, nilai_uas = ?, nilai_akhir = ? WHERE id_siswa = ? AND id_mapel = ?");
                mysqli_stmt_bind_param($stmt_upd, "ddddii", $tugas, $uts, $uas, $nilai_akhir, $id_siswa, $id_mapel);
                if (!mysqli_stmt_execute($stmt_upd)) {
                    $berhasil = false;
                }
                mysqli_stmt_close($stmt_upd);
            } else {
                // INSERT data nilai baru
                $stmt_ins = mysqli_prepare($koneksi, "INSERT INTO nilai (id_siswa, id_mapel, nilai_tugas, nilai_uts, nilai_uas, nilai_akhir) VALUES (?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt_ins, "iidddd", $id_siswa, $id_mapel, $tugas, $uts, $uas, $nilai_akhir);
                if (!mysqli_stmt_execute($stmt_ins)) {
                    $berhasil = false;
                }
                mysqli_stmt_close($stmt_ins);
            }
            mysqli_stmt_close($stmt_cek);
        }

        if ($berhasil) {
            $pesan = "Data nilai siswa berhasil disimpan!";
            $tipe_pesan = "success";
        } else {
            $pesan = "Gagal menyimpan beberapa nilai siswa. Silakan periksa kembali.";
            $tipe_pesan = "danger";
        }
    } else {
        $pesan = "Pilih kelas dan mata pelajaran terlebih dahulu.";
        $tipe_pesan = "warning";
    }
}

// Ambil Data Siswa & Nilai jika Kelas dan Mapel dipilih
$siswa_list = [];
$info_kelas = "";
$info_mapel = "";

if ($id_kelas > 0 && $id_mapel > 0) {
    // Ambil Info Nama Kelas & Mapel
    $stmt_info = mysqli_prepare($koneksi, "SELECT k.nama_kelas, m.nama_mapel FROM kelas k, mapel m WHERE k.id_kelas = ? AND m.id_mapel = ?");
    mysqli_stmt_bind_param($stmt_info, "ii", $id_kelas, $id_mapel);
    mysqli_stmt_execute($stmt_info);
    $res_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_info));
    $info_kelas = $res_info['nama_kelas'] ?? '';
    $info_mapel = $res_info['nama_mapel'] ?? '';
    mysqli_stmt_close($stmt_info);

    // Ambil Daftar Siswa beserta Nilai yang sudah tersimpan (jika ada)
    $query_siswa = "SELECT s.id_siswa, s.nisn, s.nama_lengkap, 
                           n.nilai_tugas, n.nilai_uts, n.nilai_uas, n.nilai_akhir
                    FROM siswa s 
                    LEFT JOIN nilai n ON s.id_siswa = n.id_siswa AND n.id_mapel = ?
                    WHERE s.id_kelas = ? 
                    ORDER BY s.nama_lengkap ASC";
    $stmt_s = mysqli_prepare($koneksi, $query_siswa);
    mysqli_stmt_bind_param($stmt_s, "ii", $id_mapel, $id_kelas);
    mysqli_stmt_execute($stmt_s);
    $siswa_list = mysqli_fetch_all(mysqli_stmt_get_result($stmt_s), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_s);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Nilai Siswa - Portal Guru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
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
                    
                    <!-- Dropdown Menu Absensi -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownAbsensi" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-check2-square me-1"></i> Absensi
                        </a>
                        <ul class="dropdown-menu shadow border-0" aria-labelledby="navbarDropdownAbsensi">
                            <li><a class="dropdown-item py-2" href="absensi.php"><i class="bi bi-pencil-square me-2 text-primary"></i> Input Absensi Manual</a></li>
                            <li><a class="dropdown-item py-2" href="generate_qr.php"><i class="bi bi-qr-code-scan me-2 text-success"></i> Buat QR Code Absensi</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item py-2" href="rekap_absensi.php"><i class="bi bi-file-earmark-text me-2 text-warning"></i> Rekap Absensi Semester</a></li>
                            <li><a class="dropdown-item py-2" href="ekspor_absensi_csv.php"><i class="bi bi-file-earmark-spreadsheet me-2 text-info"></i> Ekspor ke CSV</a></li>
                        </ul>
                    </li>

                    <li class="nav-item"><a class="nav-link active" href="nilai.php">Input Nilai</a></li>
                </ul>
                <div class="d-flex align-items-center">
                    <a href="../logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <!-- Header -->
        <div class="d-flex align-items-center mb-4">
            <a href="index.php" class="btn btn-outline-secondary btn-sm me-3"><i class="bi bi-arrow-left"></i> Kembali</a>
            <h4 class="fw-bold mb-0"><i class="bi bi-pencil-square text-success me-2"></i>Input & Kelola Nilai Siswa</h4>
        </div>

        <?php if (!empty($pesan)): ?>
            <div class="alert alert-<?= $tipe_pesan; ?> alert-dismissible fade show shadow-sm mb-4" role="alert">
                <?= $pesan; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Form Filter Kelas & Mapel -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <form method="GET" action="nilai.php" class="row g-3 align-items-end">
                    <div class="col-md-9">
                        <label for="filter_opt" class="form-label small fw-bold">Pilih Kelas & Mata Pelajaran</label>
                        <select id="filter_opt" class="form-select" onchange="splitValue(this.value)" required>
                            <option value="">-- Pilih Kelas & Mapel --</option>
                            <?php foreach ($list_filter as $f): ?>
                                <?php $val = $f['id_kelas'] . '|' . $f['id_mapel']; ?>
                                <option value="<?= $val; ?>" <?= ($id_kelas == $f['id_kelas'] && $id_mapel == $f['id_mapel']) ? 'selected' : ''; ?>>
                                    Kelas: <?= $f['nama_kelas']; ?> — Mapel: <?= $f['nama_mapel']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="id_kelas" id="id_kelas" value="<?= $id_kelas ? $id_kelas : ''; ?>">
                        <input type="hidden" name="id_mapel" id="id_mapel" value="<?= $id_mapel ? $id_mapel : ''; ?>">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="bi bi-search me-1"></i> Tampilkan Siswa</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabel Input Nilai -->
        <?php if ($id_kelas > 0 && $id_mapel > 0): ?>
            <form method="POST" action="nilai.php?id_kelas=<?= $id_kelas; ?>&id_mapel=<?= $id_mapel; ?>">
                <input type="hidden" name="id_kelas" value="<?= $id_kelas; ?>">
                <input type="hidden" name="id_mapel" value="<?= $id_mapel; ?>">

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-1">Daftar Nilai Siswa</h5>
                            <p class="text-muted small mb-0">Kelas: <strong><?= htmlspecialchars($info_kelas); ?></strong> | Mapel: <strong><?= htmlspecialchars($info_mapel); ?></strong></p>
                        </div>
                        <button type="submit" name="simpan_nilai" class="btn btn-success fw-bold"><i class="bi bi-save me-1"></i> Simpan Semua Nilai</button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3" width="5%">No</th>
                                        <th width="15%">NISN</th>
                                        <th width="35%">Nama Siswa</th>
                                        <th width="12%">Nilai Tugas</th>
                                        <th width="12%">Nilai UTS</th>
                                        <th width="12%">Nilai UAS</th>
                                        <th width="9%" class="text-center pe-3">Nilai Akhir</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($siswa_list)): ?>
                                        <?php $no = 1; foreach ($siswa_list as $s): ?>
                                            <?php 
                                                $id_s = $s['id_siswa'];
                                                $n_tugas = $s['nilai_tugas'] ?? '';
                                                $n_uts   = $s['nilai_uts'] ?? '';
                                                $n_uas   = $s['nilai_uas'] ?? '';
                                                $n_akhir = $s['nilai_akhir'] ?? '-';
                                            ?>
                                            <tr>
                                                <td class="ps-3"><?= $no++; ?></td>
                                                <td><?= htmlspecialchars($s['nisn'] ?? '-'); ?></td>
                                                <td><strong><?= htmlspecialchars($s['nama_lengkap']); ?></strong></td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" max="100" name="nilai_tugas[<?= $id_s; ?>]" class="form-control form-control-sm" value="<?= $n_tugas; ?>" placeholder="0-100">
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" max="100" name="nilai_uts[<?= $id_s; ?>]" class="form-control form-control-sm" value="<?= $n_uts; ?>" placeholder="0-100">
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" max="100" name="nilai_uas[<?= $id_s; ?>]" class="form-control form-control-sm" value="<?= $n_uas; ?>" placeholder="0-100">
                                                </td>
                                                <td class="text-center pe-3 fw-bold text-primary">
                                                    <?= is_numeric($n_akhir) ? number_format($n_akhir, 2) : $n_akhir; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">Belum ada siswa di kelas ini.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php if (!empty($siswa_list)): ?>
                        <div class="card-footer bg-white py-3 text-end">
                            <button type="submit" name="simpan_nilai" class="btn btn-success fw-bold"><i class="bi bi-save me-1"></i> Simpan Semua Nilai</button>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        <?php else: ?>
            <div class="card shadow-sm border-0 text-center py-5">
                <div class="card-body text-muted">
                    <i class="bi bi-file-earmark-spreadsheet fs-1 d-block mb-3 text-secondary opacity-50"></i>
                    <p class="mb-0">Silakan pilih kelas dan mata pelajaran pada form di atas, lalu klik <strong>Tampilkan Siswa</strong>.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function splitValue(val) {
            if (val) {
                const parts = val.split('|');
                document.getElementById('id_kelas').value = parts[0];
                document.getElementById('id_mapel').value = parts[1];
            } else {
                document.getElementById('id_kelas').value = '';
                document.getElementById('id_mapel').value = '';
            }
        }
    </script>
</body>
</html>