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

// Ambil ID Guru
$stmt_guru = mysqli_prepare($koneksi, "SELECT id_guru FROM guru WHERE id_user = ?");
mysqli_stmt_bind_param($stmt_guru, "i", $id_user);
mysqli_stmt_execute($stmt_guru);
$guru = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_guru));
$id_guru = $guru['id_guru'] ?? 0;

$pesan = "";
$tipe_pesan = "";

if (isset($_SESSION['pesan'])) {
    $pesan = $_SESSION['pesan'];
    $tipe_pesan = $_SESSION['tipe_pesan'];
    unset($_SESSION['pesan'], $_SESSION['tipe_pesan']);
}

// Parameter Filter Selection
$selected_kelas = isset($_GET['id_kelas']) ? intval($_GET['id_kelas']) : 0;
$selected_mapel = isset($_GET['id_mapel']) ? intval($_GET['id_mapel']) : 0;

// SIMPAN / UPDATE NILAI MASAL
if (isset($_POST['simpan_nilai'])) {
    $id_kelas_post = intval($_POST['id_kelas']);
    $id_mapel_post = intval($_POST['id_mapel']);
    $nilai_tugas   = $_POST['nilai_tugas'] ?? [];
    $nilai_uts     = $_POST['nilai_uts'] ?? [];
    $nilai_uas     = $_POST['nilai_uas'] ?? [];

    if ($id_kelas_post > 0 && $id_mapel_post > 0) {
        mysqli_begin_transaction($koneksi);

        try {
            foreach ($nilai_tugas as $id_siswa => $tugas) {
                $id_siswa = intval($id_siswa);
                $val_tugas = floatval($tugas);
                $val_uts   = floatval($nilai_uts[$id_siswa] ?? 0);
                $val_uas   = floatval($nilai_uas[$id_siswa] ?? 0);

                // Hitung Nilai Akhir (Bobot Contoh: 30% Tugas, 30% UTS, 40% UAS)
                $nilai_akhir = ($val_tugas * 0.3) + ($val_uts * 0.3) + ($val_uas * 0.4);

                // Cek apakah data nilai sudah ada
                $stmt_chk = mysqli_prepare($koneksi, "SELECT id_nilai FROM nilai WHERE id_siswa = ? AND id_mapel = ?");
                mysqli_stmt_bind_param($stmt_chk, "ii", $id_siswa, $id_mapel_post);
                mysqli_stmt_execute($stmt_chk);
                $res_chk = mysqli_stmt_get_result($stmt_chk);

                if (mysqli_num_rows($res_chk) > 0) {
                    // Update
                    $stmt_upd = mysqli_prepare($koneksi, "UPDATE nilai SET nilai_tugas = ?, nilai_uts = ?, nilai_uas = ?, nilai_akhir = ? WHERE id_siswa = ? AND id_mapel = ?");
                    mysqli_stmt_bind_param($stmt_upd, "ddddii", $val_tugas, $val_uts, $val_uas, $nilai_akhir, $id_siswa, $id_mapel_post);
                    mysqli_stmt_execute($stmt_upd);
                    mysqli_stmt_close($stmt_upd);
                } else {
                    // Insert Baru
                    $stmt_ins = mysqli_prepare($koneksi, "INSERT INTO nilai (id_siswa, id_kelas, id_mapel, nilai_tugas, nilai_uts, nilai_uas, nilai_akhir) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt_ins, "iiidddd", $id_siswa, $id_kelas_post, $id_mapel_post, $val_tugas, $val_uts, $val_uas, $nilai_akhir);
                    mysqli_stmt_execute($stmt_ins);
                    mysqli_stmt_close($stmt_ins);
                }
                mysqli_stmt_close($stmt_chk);
            }

            mysqli_commit($koneksi);
            $_SESSION['pesan'] = "Berhasil menyimpan data nilai siswa!";
            $_SESSION['tipe_pesan'] = "success";
        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            $_SESSION['pesan'] = "Gagal menyimpan nilai: " . $e->getMessage();
            $_SESSION['tipe_pesan'] = "danger";
        }
    }

    header("Location: nilai.php?id_kelas=$id_kelas_post&id_mapel=$id_mapel_post");
    exit();
}

// Ambil Daftar Kelas & Mapel yang Diampu Guru
$stmt_list_kelas = mysqli_prepare($koneksi, "SELECT DISTINCT k.id_kelas, k.nama_kelas 
                                              FROM jadwal_pelajaran j 
                                              JOIN kelas k ON j.id_kelas = k.id_kelas 
                                              WHERE j.id_guru = ? ORDER BY k.nama_kelas ASC");
mysqli_stmt_bind_param($stmt_list_kelas, "i", $id_guru);
mysqli_stmt_execute($stmt_list_kelas);
$query_list_kelas = mysqli_stmt_get_result($stmt_list_kelas);

$stmt_list_mapel = mysqli_prepare($koneksi, "SELECT DISTINCT m.id_mapel, m.nama_mapel 
                                              FROM jadwal_pelajaran j 
                                              JOIN mapel m ON j.id_mapel = m.id_mapel 
                                              WHERE j.id_guru = ? ORDER BY m.nama_mapel ASC");
mysqli_stmt_bind_param($stmt_list_mapel, "i", $id_guru);
mysqli_stmt_execute($stmt_list_mapel);
$query_list_mapel = mysqli_stmt_get_result($stmt_list_mapel);

// Ambil List Siswa dan Nilai jika Kelas dan Mapel telah dipilih
$list_siswa = [];
if ($selected_kelas > 0 && $selected_mapel > 0) {
    $stmt_siswa = mysqli_prepare($koneksi, "SELECT s.id_siswa, s.nisn, s.nama_lengkap, n.nilai_tugas, n.nilai_uts, n.nilai_uas, n.nilai_akhir 
                                            FROM siswa s 
                                            LEFT JOIN nilai n ON s.id_siswa = n.id_siswa AND n.id_mapel = ? 
                                            WHERE s.id_kelas = ? 
                                            ORDER BY s.nama_lengkap ASC");
    mysqli_stmt_bind_param($stmt_siswa, "ii", $selected_mapel, $selected_kelas);
    mysqli_stmt_execute($stmt_siswa);
    $list_siswa = mysqli_stmt_get_result($stmt_siswa);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Nilai - Portal Guru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">

    <!-- Navbar Portal Guru -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-journal-check me-2"></i>Portal Guru</a>
            <div class="collapse navbar-collapse" id="navGuru">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="absensi.php">Input Absensi</a></li>
                    <li class="nav-item"><a class="nav-link active" href="nilai.php">Input Nilai</a></li>
                </ul>
                <a href="../logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-pencil-square me-2 text-primary"></i>Penginputan Nilai Siswa</h4>
                <p class="text-muted small mb-0">Pilih kelas dan mata pelajaran untuk menginput/mengedit nilai tugas, UTS, dan UAS.</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
        </div>

        <?php if ($pesan != ""): ?>
            <div class="alert alert-<?= $tipe_pesan; ?> alert-dismissible fade show shadow-sm" role="alert">
                <?= $pesan; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Form Filter / Selector -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <form action="nilai.php" method="GET" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Pilih Kelas</label>
                        <select name="id_kelas" class="form-select" required>
                            <option value="" disabled <?= $selected_kelas == 0 ? 'selected' : ''; ?>>-- Pilih Kelas --</option>
                            <?php while ($k = mysqli_fetch_assoc($query_list_kelas)): ?>
                                <option value="<?= $k['id_kelas']; ?>" <?= $selected_kelas == $k['id_kelas'] ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($k['nama_kelas']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Pilih Mata Pelajaran</label>
                        <select name="id_mapel" class="form-select" required>
                            <option value="" disabled <?= $selected_mapel == 0 ? 'selected' : ''; ?>>-- Pilih Mapel --</option>
                            <?php while ($m = mysqli_fetch_assoc($query_list_mapel)): ?>
                                <option value="<?= $m['id_mapel']; ?>" <?= $selected_mapel == $m['id_mapel'] ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($m['nama_mapel']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> Tampilkan</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabel Lembar Nilai -->
        <?php if ($selected_kelas > 0 && $selected_mapel > 0): ?>
            <div class="card shadow-sm border-0">
                <form action="nilai.php" method="POST">
                    <input type="hidden" name="id_kelas" value="<?= $selected_kelas; ?>">
                    <input type="hidden" name="id_mapel" value="<?= $selected_mapel; ?>">

                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                        <span class="fw-bold"><i class="bi bi-calculator me-1 text-primary"></i> Form Nilai Akademik</span>
                        <button type="submit" name="simpan_nilai" class="btn btn-success btn-sm fw-bold">
                            <i class="bi bi-save me-1"></i> Simpan Nilai
                        </button>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3" style="width: 50px;">No</th>
                                        <th>NISN</th>
                                        <th>Nama Lengkap Siswa</th>
                                        <th style="width: 130px;">Tugas (30%)</th>
                                        <th style="width: 130px;">UTS (30%)</th>
                                        <th style="width: 130px;">UAS (40%)</th>
                                        <th style="width: 130px;" class="text-center">Nilai Akhir</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($list_siswa && mysqli_num_rows($list_siswa) > 0): ?>
                                        <?php $no = 1; while ($s = mysqli_fetch_assoc($list_siswa)): ?>
                                            <tr>
                                                <td class="ps-3"><?= $no++; ?></td>
                                                <td><code><?= htmlspecialchars($s['nisn']); ?></code></td>
                                                <td><strong class="text-dark"><?= htmlspecialchars($s['nama_lengkap']); ?></strong></td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm" name="nilai_tugas[<?= $s['id_siswa']; ?>]" value="<?= htmlspecialchars($s['nilai_tugas'] ?? '0'); ?>" required>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm" name="nilai_uts[<?= $s['id_siswa']; ?>]" value="<?= htmlspecialchars($s['nilai_uts'] ?? '0'); ?>" required>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm" name="nilai_uas[<?= $s['id_siswa']; ?>]" value="<?= htmlspecialchars($s['nilai_uas'] ?? '0'); ?>" required>
                                                </td>
                                                <td class="text-center fw-bold text-primary">
                                                    <?= number_format($s['nilai_akhir'] ?? 0, 2); ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">Belum ada data siswa di kelas ini.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="alert alert-info border-0 shadow-sm text-center py-4">
                <i class="bi bi-info-circle fs-3 d-block mb-2"></i>
                Silakan pilih <strong>Kelas</strong> dan <strong>Mata Pelajaran</strong> di atas untuk menampilkan lembar nilai siswa.
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>