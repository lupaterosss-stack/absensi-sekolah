<?php
// register.php
require_once 'config/koneksi.php';

$pesan = "";
$tipe_pesan = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username     = mysqli_real_escape_string($koneksi, trim($_POST['username']));
    $nama_lengkap = mysqli_real_escape_string($koneksi, trim($_POST['nama_lengkap']));
    $password     = $_POST['password'];
    $role         = $_POST['role'];
    
    // Hash password demi keamanan
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Cek apakah username sudah terdaftar
    $cek_user = mysqli_query($koneksi, "SELECT id_user FROM users WHERE username = '$username'");
    if (mysqli_num_rows($cek_user) > 0) {
        $pesan = "Username sudah terdaftar dalam sistem!";
        $tipe_pesan = "danger";
    } else {
        // 1. Simpan ke tabel users (status_akun = 'pending')
        $query_user = "INSERT INTO users (username, password, role, status_akun) 
                       VALUES ('$username', '$password_hash', '$role', 'pending')";
        
        if (mysqli_query($koneksi, $query_user)) {
            $id_user_baru = mysqli_insert_id($koneksi);

            // 2. Simpan ke tabel relasi (guru atau siswa)
            if ($role == 'siswa') {
                $query_detail = "INSERT INTO siswa (id_user, nisn, nama_lengkap, jenis_kelamin) 
                                 VALUES ('$id_user_baru', '$username', '$nama_lengkap' , 'L')";
            } else if ($role == 'guru') {
                $query_detail = "INSERT INTO guru (id_user, nip, nama_guru) 
                                 VALUES ('$id_user_baru', '$username', '$nama_lengkap')";
            }
            
            mysqli_query($koneksi, $query_detail);

            $pesan = "Pendaftaran berhasil! Akun Anda sedang menunggu persetujuan (approval) dari Admin.";
            $tipe_pesan = "success";
        } else {
            $pesan = "Gagal mendaftar: " . mysqli_error($koneksi);
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
    <title>Registrasi Akun - Absensi Sekolah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center vh-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <h3 class="card-title text-center fw-bold mb-3">Daftar Akun Baru</h3>
                        <p class="text-muted text-center small mb-4">Sistem Absensi & Nilai Sekolah</p>

                        <?php if ($pesan != ""): ?>
                            <div class="alert alert-<?= $tipe_pesan; ?> alert-dismissible fade show" role="alert">
                                <?= $pesan; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form action="register.php" method="POST">
                            <div class="mb-3">
                                <label class="form-label">Daftar Sebagai</label>
                                <select name="role" class="form-select" required>
                                    <option value="siswa">Siswa</option>
                                    <option value="guru">Guru</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Username (Gunakan NISN untuk Siswa / NIP untuk Guru)</label>
                                <input type="text" name="username" class="form-control" placeholder="Contoh: 0051234567" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nama Lengkap</label>
                                <input type="text" name="nama_lengkap" class="form-control" placeholder="Masukkan nama lengkap" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" placeholder="Buat password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 fw-bold">Daftar Sekarang</button>
                        </form>
                        <hr>
                        <p class="text-center small mb-0">Sudah punya akun? <a href="login.php" class="text-decoration-none">Login di sini</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>