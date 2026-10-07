<?php
// login.php
session_start();
require_once 'config/koneksi.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($koneksi, trim($_POST['username']));
    $password = $_POST['password'];

    // Query cek user berdasarkan username
    $query = mysqli_query($koneksi, "SELECT * FROM users WHERE username = '$username'");

    if ($query && mysqli_num_rows($query) === 1) {
        $row = mysqli_fetch_assoc($query);

        // 1. Verifikasi Password menggunakan password_verify() (Sinkron dengan register.php)
        if (password_verify($password, $row['password'])) {
            
            // 2. Cek Status Akun (Harus 'Aktif')
            if (strtolower($row['status_akun']) !== 'aktif') {
                $error = "Akun Anda belum disetujui oleh Admin atau berstatus ditolak.";
            } else {
                // Set Session
                $_SESSION['id_user']  = $row['id_user'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['role']     = $row['role'];

                // Redirection berdasarkan Role
                if ($row['role'] == 'admin') {
                    header("Location: admin/index.php");
                    exit();
                } elseif ($row['role'] == 'guru') {
                    header("Location: guru/index.php");
                    exit();
                } elseif ($row['role'] == 'siswa') {
                    header("Location: siswa/index.php");
                    exit();
                }
            }
        } else {
            $error = "Password yang Anda masukkan salah!";
        }
    } else {
        $error = "Username tidak ditemukan!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login System - Absensi Sekolah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-primary-subtle d-flex align-items-center vh-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <div class="card shadow border-0">
                    <div class="card-body p-4">
                        <h3 class="card-title text-center fw-bold mb-1">Masuk Sistem</h3>
                        <p class="text-muted text-center small mb-4">Absensi & Nilai Sekolah</p>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger py-2 small" role="alert">
                                <?= $error; ?>
                            </div>
                        <?php endif; ?>

                        <form action="login.php" method="POST">
                            <div class="mb-3">
                                <label class="form-label">Username / NISN / NIP</label>
                                <input type="text" name="username" class="form-control" placeholder="Masukkan username" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" placeholder="Masukkan password" required>
                            </div>
                            <button type="submit" name="login" class="btn btn-primary w-100 fw-bold">Login</button>
                        </form>
                        <hr>
                        <p class="text-center small mb-0">Belum punya akun? <a href="register.php" class="text-decoration-none">Daftar Akun</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>