<?php
// admin/tambah_siswa.php
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

// PROSES TAMBAH SISWA MANUAL
if (isset($_POST['simpan_siswa'])) {
    $nisn          = trim($_POST['nisn']);
    $nama_lengkap  = trim($_POST['nama_lengkap']);
    $jenis_kelamin = $_POST['jenis_kelamin'];
    $id_kelas      = intval($_POST['id_kelas']);
    $username      = trim($_POST['username']);
    $password      = trim($_POST['password']);

    if (!empty($nisn) && !empty($nama_lengkap) && !empty($id_kelas) && !empty($username)) {
        // 1. Cek apakah NISN atau Username sudah terdaftar
        $stmt_check = mysqli_prepare($koneksi, "SELECT id_user FROM users WHERE username = ?");
        mysqli_stmt_bind_param($stmt_check, "s", $username);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);

        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            $_SESSION['pesan'] = "Username <strong>" . htmlspecialchars($username) . "</strong> sudah digunakan!";
            $_SESSION['tipe_pesan'] = "danger";
            mysqli_stmt_close($stmt_check);
        } else {
            mysqli_stmt_close($stmt_check);

            // Gunakan Transaksi Database agar pembuatan User & Siswa selalu sinkron
            mysqli_begin_transaction($koneksi);

            try {
                // Hash Password jika menggunakan enkripsi (password_hash) atau plain jika disesuaikan
                $password_db = password_hash($password, PASSWORD_DEFAULT);
                $role = 'siswa';

                // Insert ke tabel users
                $stmt_user = mysqli_prepare($koneksi, "INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmt_user, "sss", $username, $password_db, $role);
                mysqli_stmt_execute($stmt_user);
                $id_user_baru = mysqli_insert_id($koneksi);
                mysqli_stmt_close($stmt_user);

                // Insert ke tabel siswa
                $stmt_siswa = mysqli_prepare($koneksi, "INSERT INTO siswa (id_user, nisn, nama_lengkap, jenis_kelamin, id_kelas) VALUES (?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt_siswa, "isssi", $id_user_baru, $nisn, $nama_lengkap, $jenis_kelamin, $id_kelas);
                mysqli_stmt_execute($stmt_siswa);
                mysqli_stmt_close($stmt_siswa);

                // Commit transaksi jika sukses
                mysqli_commit($koneksi);

                $_SESSION['pesan'] = "Berhasil menambahkan siswa <strong>" . htmlspecialchars($nama_lengkap) . "</strong> beserta akun loginnnya!";
                $_SESSION['tipe_pesan'] = "success";
            } catch (Exception $e) {
                // Rollback jika ada error
                mysqli_rollback($koneksi);
                $_SESSION['pesan'] = "Gagal menyimpan data siswa: " . $e->getMessage();
                $_SESSION['tipe_pesan'] = "danger";
            }
        }
    }

    header("Location: tambah_siswa.php");
    exit();
}

// Ambil daftar kelas untuk dropdown select
$query_kelas = mysqli_query($koneksi, "SELECT id_kelas, nama_kelas FROM kelas ORDER BY tingkat ASC, nama_kelas ASC");

// Ambil daftar siswa yang baru diinput untuk tabel preview ringkas
$query_siswa_terbaru = mysqli_query($koneksi, "SELECT s.*, k.nama_kelas, u.username 
                                                FROM siswa s 
                                                LEFT JOIN kelas k ON s.id_kelas = k.id_kelas 
                                                LEFT JOIN users u ON s.id_user = u.id_user 
                                                ORDER BY s.id_siswa DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Siswa Manual - Admin</title>
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
        <!-- Header & Navigation -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-person-plus me-2 text-primary"></i>Tambah Data Siswa Baru</h4>
                <p class="text-muted small mb-0">Input data siswa beserta otomatisasi pembuatan akun pengguna.</p>
            </div>
            <div>
                <a href="import_siswa.php" class="btn btn-outline-success btn-sm me-1"><i class="bi bi-file-earmark-excel me-1"></i> Import via Excel</a>
                <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Dashboard</a>
            </div>
        </div>

        <?php if ($pesan != ""): ?>
            <div class="alert alert-<?= $tipe_pesan; ?> alert-dismissible fade show shadow-sm" role="alert">
                <?= $pesan; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Form Input Siswa -->
            <div class="col-lg-7 mb-4">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 fw-bold border-bottom">
                        <i class="bi bi-journal-text me-1 text-primary"></i> Form Registrasi Siswa
                    </div>
                    <div class="card-body">
                        <form action="tambah_siswa.php" method="POST">
                            <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-person me-1"></i> Informasi Profil Siswa</h6>
                            
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">NISN <span class="text-danger">*</span></label>
                                    <input type="text" name="nisn" id="nisn_field" class="form-control" placeholder="Contoh: 0051234567" required onkeyup="syncCredentials()">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Kelas <span class="text-danger">*</span></label>
                                    <select name="id_kelas" class="form-select" required>
                                        <option value="" selected disabled>-- Pilih Kelas --</option>
                                        <?php while ($k = mysqli_fetch_assoc($query_kelas)): ?>
                                            <option value="<?= $k['id_kelas']; ?>"><?= htmlspecialchars($k['nama_kelas']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-7">
                                    <label class="form-label fw-semibold">Nama Lengkap Siswa <span class="text-danger">*</span></label>
                                    <input type="text" name="nama_lengkap" class="form-control" placeholder="Masukkan nama lengkap" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label fw-semibold">Jenis Kelamin <span class="text-danger">*</span></label>
                                    <select name="jenis_kelamin" class="form-select" required>
                                        <option value="L">Laki-laki (L)</option>
                                        <option value="P">Perempuan (P)</option>
                                    </select>
                                </div>
                            </div>

                            <hr class="my-4">

                            <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-shield-lock me-1"></i> Akun Login Sistem</h6>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Username Login <span class="text-danger">*</span></label>
                                    <input type="text" name="username" id="username_field" class="form-control" placeholder="Otomatis dari NISN" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                                    <input type="password" name="password" id="password_field" class="form-control" placeholder="Otomatis dari NISN" required>
                                </div>
                            </div>

                            <div class="alert alert-light border small text-muted py-2 mb-3">
                                <i class="bi bi-info-circle text-primary me-1"></i> Username dan Password secara bawaan disamakan dengan NISN siswa agar mudah diingat.
                            </div>

                            <button type="submit" name="simpan_siswa" class="btn btn-primary w-100 fw-bold">
                                <i class="bi bi-save me-1"></i> Simpan Data & Buat Akun
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Tabel Siswa Baru Diinput -->
            <div class="col-lg-5">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <div class="fw-bold"><i class="bi bi-clock-history me-1 text-primary"></i> 5 Siswa Terakhir Input</div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Siswa</th>
                                        <th>Kelas</th>
                                        <th>Username</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($query_siswa_terbaru && mysqli_num_rows($query_siswa_terbaru) > 0): ?>
                                        <?php while ($s = mysqli_fetch_assoc($query_siswa_terbaru)): ?>
                                            <tr>
                                                <td class="ps-3">
                                                    <strong class="d-block text-dark small"><?= htmlspecialchars($s['nama_lengkap']); ?></strong>
                                                    <span class="text-muted extra-small">NISN: <?= htmlspecialchars($s['nisn']); ?></span>
                                                </td>
                                                <td><span class="badge bg-secondary"><?= htmlspecialchars($s['nama_kelas'] ?? '-'); ?></span></td>
                                                <td><code><?= htmlspecialchars($s['username']); ?></code></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center py-4 text-muted small">Belum ada siswa yang ditambahkan.</td>
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

    <script>
        // Otomatis mengisi Username & Password sesuai input NISN
        function syncCredentials() {
            var nisn = document.getElementById('nisn_field').value;
            document.getElementById('username_field').value = nisn;
            document.getElementById('password_field').value = nisn;
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>