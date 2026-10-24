<?php
// admin/detail_kelas.php
session_start();
require_once '../config/koneksi.php';

// Proteksi Halaman: Cek Sesi & Role Admin
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$pesan = "";
$tipe_pesan = "";

// Ambil pesan dari session jika ada (Hasil dari Redirect PRG)
if (isset($_SESSION['pesan'])) {
    $pesan = $_SESSION['pesan'];
    $tipe_pesan = $_SESSION['tipe_pesan'];
    unset($_SESSION['pesan'], $_SESSION['tipe_pesan']);
}

// 1. Validasi ID Kelas dari URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: kelas.php");
    exit();
}

$id_kelas = intval($_GET['id']);

// 2. Ambil Informasi Kelas
$stmt_kelas = mysqli_prepare($koneksi, "SELECT * FROM kelas WHERE id_kelas = ?");
mysqli_stmt_bind_param($stmt_kelas, "i", $id_kelas);
mysqli_stmt_execute($stmt_kelas);
$res_kelas = mysqli_stmt_get_result($stmt_kelas);
$kelas = mysqli_fetch_assoc($res_kelas);
mysqli_stmt_close($stmt_kelas);

if (!$kelas) {
    header("Location: kelas.php");
    exit();
}

// 3. PROSES MEMASUKKAN SISWA YANG SUDAH TERDAFTAR KE KELAS INI
if (isset($_POST['tambah_siswa_kelas'])) {
    $id_siswa = intval($_POST['id_siswa'] ?? 0);

    if ($id_siswa > 0) {
        $nama_kelas_str = $kelas['nama_kelas'];
        
        // Update id_kelas dan nama kelas string pada tabel siswa
        $stmt_update = mysqli_prepare($koneksi, "UPDATE siswa SET id_kelas = ?, kelas = ? WHERE id_siswa = ?");
        mysqli_stmt_bind_param($stmt_update, "isi", $id_kelas, $nama_kelas_str, $id_siswa);

        if (mysqli_stmt_execute($stmt_update)) {
            $_SESSION['pesan'] = "Berhasil memasukkan siswa ke kelas <strong>" . htmlspecialchars($kelas['nama_kelas']) . "</strong>!";
            $_SESSION['tipe_pesan'] = "success";
        } else {
            $_SESSION['pesan'] = "Gagal memasukkan siswa ke kelas: " . mysqli_error($koneksi);
            $_SESSION['tipe_pesan'] = "danger";
        }
        mysqli_stmt_close($stmt_update);
    } else {
        $_SESSION['pesan'] = "Silakan pilih siswa terlebih dahulu.";
        $_SESSION['tipe_pesan'] = "warning";
    }

    header("Location: detail_kelas.php?id=" . $id_kelas);
    exit();
}

// 4. Ambil Daftar Siswa yang sudah ada di kelas ini
$query_siswa = "SELECT * FROM siswa WHERE id_kelas = ? ORDER BY nama_lengkap ASC";
$stmt_siswa = mysqli_prepare($koneksi, $query_siswa);

$daftar_siswa = false;
if ($stmt_siswa) {
    mysqli_stmt_bind_param($stmt_siswa, "i", $id_kelas);
    mysqli_stmt_execute($stmt_siswa);
    $daftar_siswa = mysqli_stmt_get_result($stmt_siswa);
}

$total_siswa = $daftar_siswa ? mysqli_num_rows($daftar_siswa) : 0;

// 5. Ambil Daftar Siswa yang BELUM memiliki kelas (atau bisa dipilih untuk dimasukkan)
$query_siswa_tersedia = mysqli_query($koneksi, "SELECT id_siswa, nisn, nama_lengkap FROM siswa WHERE id_kelas IS NULL OR id_kelas = 0 ORDER BY nama_lengkap ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Kelas <?= htmlspecialchars($kelas['nama_kelas']); ?> - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">

    <!-- Navbar Admin -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-speedometer2 me-2"></i>Admin Dashboard</a>
            <div class="d-flex align-items-center">
                <span class="text-white me-3">Halo, <strong><?= htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></strong></span>
                <a href="../logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <!-- Header & Navigasi -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="bi bi-door-open text-primary me-2"></i>Detail Kelas: <?= htmlspecialchars($kelas['nama_kelas']); ?>
                </h4>
                <p class="text-muted small mb-0">Informasi lengkap tingkat, jurusan, dan daftar siswa terdaftar.</p>
            </div>
            <a href="kelas.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Data Kelas
            </a>
        </div>

        <?php if ($pesan != ""): ?>
            <div class="alert alert-<?= $tipe_pesan; ?> alert-dismissible fade show shadow-sm" role="alert">
                <?= $pesan; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Ringkasan Kelas -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 fw-bold border-bottom">
                        <i class="bi bi-info-circle me-1 text-primary"></i> Informasi Kelas
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td class="text-muted" style="width: 120px;">ID Kelas</td>
                                <td>: <strong>#<?= $kelas['id_kelas']; ?></strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Nama Kelas</td>
                                <td>: <span class="fw-bold text-primary"><?= htmlspecialchars($kelas['nama_kelas']); ?></span></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Tingkat</td>
                                <td>: Kelas <?= htmlspecialchars($kelas['tingkat']); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Jurusan</td>
                                <td>: <?= !empty($kelas['jurusan']) ? htmlspecialchars($kelas['jurusan']) : '-'; ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Total Siswa</td>
                                <td>: <span class="badge bg-success rounded-pill"><?= $total_siswa; ?> Siswa</span></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tabel Daftar Siswa -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                        <span class="fw-bold"><i class="bi bi-people me-1 text-primary"></i> Daftar Siswa di Kelas Ini</span>
                        <div>
                            <button type="button" class="btn btn-primary btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalTambahSiswa">
                                <i class="bi bi-person-plus me-1"></i> Tambah Siswa ke Kelas
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3" style="width: 50px;">No</th>
                                        <th>NISN</th>
                                        <th>Nama Lengkap</th>
                                        <th>Jenis Kelamin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($daftar_siswa && $total_siswa > 0): ?>
                                        <?php $no = 1; while ($siswa = mysqli_fetch_assoc($daftar_siswa)): ?>
                                            <tr>
                                                <td class="ps-3"><?= $no++; ?></td>
                                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($siswa['nisn'] ?? '-'); ?></span></td>
                                                <td class="fw-semibold text-dark"><?= htmlspecialchars($siswa['nama_lengkap']); ?></td>
                                                <td>
                                                    <?php 
                                                        $jk = strtoupper($siswa['jenis_kelamin'] ?? '');
                                                        if ($jk == 'L' || $jk == 'LAKI-LAKI') {
                                                            echo '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">Laki-laki</span>';
                                                        } elseif ($jk == 'P' || $jk == 'PEREMPUAN') {
                                                            echo '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Perempuan</span>';
                                                        } else {
                                                            echo '<span class="text-muted">-</span>';
                                                        }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-5 text-muted">
                                                <i class="bi bi-person-x fs-2 d-block mb-2 text-secondary"></i>
                                                Belum ada siswa yang terdaftar di kelas ini.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL PILIH SISWA YANG SUDAH TERDAFTAR -->
    <div class="modal fade" id="modalTambahSiswa" tabindex="-1" aria-labelledby="modalTambahSiswaLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalTambahSiswaLabel">
                        <i class="bi bi-person-plus text-primary me-2"></i>Tambah Siswa ke Kelas <?= htmlspecialchars($kelas['nama_kelas']); ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="detail_kelas.php?id=<?= $id_kelas; ?>" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Pilih Siswa <span class="text-danger">*</span></label>
                            <select name="id_siswa" class="form-select" required>
                                <option value="" selected disabled>-- Cari atau Pilih Siswa --</option>
                                <?php if ($query_siswa_tersedia && mysqli_num_rows($query_siswa_tersedia) > 0): ?>
                                    <?php while ($st = mysqli_fetch_assoc($query_siswa_tersedia)): ?>
                                        <option value="<?= $st['id_siswa']; ?>">
                                            <?= htmlspecialchars($st['nama_lengkap']); ?> (NISN: <?= htmlspecialchars($st['nisn']); ?>)
                                        </option>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <option value="" disabled>Semua siswa sudah masuk ke dalam kelas.</option>
                                <?php endif; ?>
                            </select>
                            <div class="form-text small text-muted mt-1">Menampilkan siswa yang belum dimasukkan ke kelas mana pun.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="tambah_siswa_kelas" class="btn btn-primary fw-bold">
                            <i class="bi bi-plus-circle me-1"></i> Masukkan ke Kelas
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>