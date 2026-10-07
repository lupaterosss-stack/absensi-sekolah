<?php
// admin/ubah_password.php
session_start();
require_once '../config/koneksi.php';

// Proteksi Halaman Admin
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$id_user = $_SESSION['id_user'];
$pesan = "";
$tipe_pesan = "";

// PROSES UBAH PASSWORD
if (isset($_POST['ubah_password'])) {
    $password_lama = $_POST['password_lama'];
    $password_baru = $_POST['password_baru'];
    $konfirmasi_password = $_POST['konfirmasi_password'];

    // Validasi kesesuaian password baru
    if ($password_baru !== $konfirmasi_password) {
        $pesan = "Password baru dan konfirmasi password tidak cocok!";
        $tipe_pesan = "danger";
    } else {
        // Ambil data user dari database
        $query = mysqli_query($koneksi, "SELECT password FROM users WHERE id_user = '$id_user'");
        $user  = mysqli_fetch_assoc($query);

        // Verifikasi password lama
        if ($user && password_verify($password_lama, $user['password'])) {
            // Hash password baru
            $password_baru_hash = password_hash($password_baru, PASSWORD_DEFAULT);

            // Update ke database
            $stmt_update = mysqli_prepare($koneksi, "UPDATE users SET password = ? WHERE id_user = ?");
            mysqli_stmt_bind_param($stmt_update, "si", $password_baru_hash, $id_user);

            if (mysqli_stmt_execute($stmt_update)) {
                $pesan = "Password berhasil diperbarui!";
                $tipe_pesan = "success";
            } else {
                $pesan = "Gagal memperbarui password: " . mysqli_error($koneksi);
                $tipe_pesan = "danger";
            }
        } else {
            $pesan = "Password lama yang Anda masukkan salah!";
            $tipe_pesan = "danger";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ubah Password Admin - Absensi Sekolah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">

    <!-- Navbar Admin -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-shield-lock me-2"></i>Panel Admin</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="guru.php">Data Guru</a></li>
                    <li class="nav-item"><a class="nav-link" href="siswa.php">Data Siswa</a></li>
                    <li class="nav-item"><a class="nav-link" href="kelas.php">Data Kelas</a></li>
                    <li class="nav-item"><a class="nav-link" href="jadwal.php">Jadwal</a></li>
                    <li class="nav-item"><a class="nav-link" href="approval.php">Approval Akun</a></li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($_SESSION['username']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item active" href="ubah_password.php"><i class="bi bi-key me-2"></i>Ubah Password</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <h4 class="fw-bold mb-3"><i class="bi bi-key me-2 text-primary"></i>Ubah Password Admin</h4>
                        <p class="text-muted small mb-4">Pastikan Anda menggunakan password yang kuat untuk menjaga keamanan sistem.</p>

                        <?php if (!empty($pesan)): ?>
                            <div class="alert alert-<?= $tipe_pesan; ?> alert-dismissible fade show" role="alert">
                                <?= $pesan; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form action="ubah_password.php" method="POST">
                            <div class="mb-3">
                                <label class="form-label">Password Lama</label>
                                <input type="password" name="password_lama" class="form-control" placeholder="Masukkan password saat ini" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password Baru</label>
                                <input type="password" name="password_baru" class="form-control" placeholder="Masukkan password baru" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Konfirmasi Password Baru</label>
                                <input type="password" name="konfirmasi_password" class="form-control" placeholder="Ulangi password baru" required>
                            </div>
                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-secondary">Kembali</a>
                                <button type="submit" name="ubah_password" class="btn btn-primary fw-bold">Simpan Password Baru</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>