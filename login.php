<?php
// login.php
session_start();
require_once 'config/koneksi.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        // Query disesuaikan dengan tabel 'users' Anda
        $stmt = mysqli_prepare($koneksi, "SELECT id_user, username, password, role FROM users WHERE username = ?");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {
            if (password_verify($password,$row['password']) || $password ===$row['password']) {
                $_SESSION['id_user'] =$row['id_user'];
                $_SESSION['username'] =$row['username'];
                $_SESSION['role'] =$row['role'];

                if ($row['role'] === 'admin') {
                    header("Location: admin/index.php");
                } elseif ($row['role'] === 'guru') { header("Location: guru/index.php"); } elseif ($row['role'] === 'siswa') {
                    header("Location: siswa/index.php");
                } else {
                    header("Location: index.php");
                }
                exit();
            } else {
                $error = "Password yang Anda masukkan salah!";
            }
        } else {
            $error = "Username tidak ditemukan!";
        }
        mysqli_stmt_close($stmt);
    } else {
        $error = "Mohon isi username dan password terlebih dahulu!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SMA NEGERI 5 WAKATOBI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body, html {
            height: 100%;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-container {
            min-height: 100vh;
            height: 100vh;
        }
        /* Sisi Kiri: Background & Slideshow */
        .login-bg-side {
            position: relative;
            color: white;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100vh;
            background-color: #000;
            background-size: cover !important;
            background-position: center center !important;
            background-repeat: no-repeat !important;
            transition: background-image 1s ease-in-out;
        }
        /* Efek bayangan teks agar tetap terbaca jelas di atas foto terang tanpa kotak hitam */
        .login-bg-side h1, 
        .login-bg-side p, 
        .login-bg-side a, 
        .login-bg-side span, 
        .login-bg-side div {
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.8), 0 1px 3px rgba(0, 0, 0, 0.9);
        }

        /* Sisi Kanan: Form Login Interaktif */
        .login-form-side {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            height: 100vh;
        }
        .form-card {
            background: #ffffff;
            padding: 40px 30px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            width: 100%;
            max-width: 420px;
            transition: transform 0.3s ease;
        }
        .form-card:hover {
            transform: translateY(-3px);
        }
        .input-group {
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            box-shadow: none;
            border-color: #0d6efd;
        }
        .input-group:focus-within {
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
            border-color: #0d6efd;
        }
        .form-control, .input-group-text {
            border-color: #dee2e6;
            padding: 12px 15px;
        }
        .btn-primary-custom {
            background-color: #0d6efd;
            border-color: #0d6efd;
            padding: 12px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .btn-primary-custom:hover {
            background-color: #0b5ed7;
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
        }
        .icon-box {
            width: 65px;
            height: 65px;
            font-size: 28px;
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
        }
    </style>
</head>
<body>

    <div class="container-fluid login-container p-0">
        <div class="row g-0 h-100">
            
            <!-- SISI KIRI: BACKGROUND & SLIDESHOW -->
            <div class="col-lg-7 d-none d-lg-flex login-bg-side" id="loginBg">
                <div>
                    <a href="#" class="text-white text-decoration-none fw-bold fs-5">
                        <i class="bi bi-mortarboard-fill me-2"></i>SMA NEGERI 5 WAKATOBI
                    </a>
                </div>
                <div class="my-auto py-5">
                    <span class="badge bg-primary mb-3 px-3 py-2 rounded-pill fw-normal">Portal Akademik Terpadu</span>
                    <h1 class="display-5 fw-bold mb-3">Selamat Datang di Portal Presensi & Nilai</h1>
                    <p class="lead text-light opacity-85 mb-0">
                        Sistem informasi pengelolaan kegiatan belajar mengajar, pemantauan kehadiran harian, serta rekapitulasi akademik SMA Negeri 5 Wakatobi secara akurat dan transparan.
                    </p>
                </div>
                <div>
                    <p class="small text-white-50 mb-0">&copy; <?= date('Y'); ?> Oleh Mahasiswa PKL Institut Teknologi dan Bisnis Muhammadiyah Wakatobi.</p>
                </div>
            </div>

            <!-- SISI KANAN: FORM LOGIN -->
            <div class="col-lg-5 login-form-side">
                <div class="form-card">
                    
                    <div class="text-center mb-4">
                        <div class="icon-box mb-3">
                            <i class="bi bi-shield-lock-fill"></i>
                        </div>
                        <h3 class="fw-bold text-dark mb-1">Silahkan Masuk</h3>
                        <p class="text-muted small">Portal Akademik SMA Negeri 5 Wakatobi</p>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show py-2 small rounded-3" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($error); ?>
                            <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label small fw-bold text-secondary">Username</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white text-muted"><i class="bi bi-person"></i></span>
                                <input type="text" name="username" id="username" class="form-control" placeholder="Masukkan username" required autofocus>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label small fw-bold text-secondary">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white text-muted"><i class="bi bi-key"></i></span>
                                <input type="password" name="password" id="password" class="form-control" placeholder="Masukkan password" required>
                                <button class="btn btn-outline-secondary bg-white border text-muted" type="button" id="togglePassword" title="Tampilkan/Sembunyikan Password">
                                    <i class="bi bi-eye" id="eyeIcon"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-primary-custom shadow-sm">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Masuk ke Portal
                            </button>
                        </div>
                    </form>

                    <!-- Opsi Tautan Pendaftaran Akun -->
                    <div class="text-center mt-3">
                        <p class="text-muted small mb-0">
                            Belum punya akun? <a href="register.php" class="text-primary text-decoration-none fw-bold">Daftar di sini</a>
                        </p>
                    </div>

                    <div class="text-center mt-3">
                        <p class="text-muted small mb-0" style="font-size: 11px;">Kendala login? Hubungi Administrator sekolah.</p>
                    </div>

                </div>
            </div>

        </div>
    </div>

    <!-- SCRIPT SLIDESHOW & LIHAT PASSWORD -->
    <script>
        const images = [
            "assets/img/sekolah.jpeg",
            "assets/img/halim.jpg",
            "assets/img/images.jpeg"
        ];
        
        let currentIndex = 0;
        const bgElement = document.getElementById('loginBg');

        if(images.length > 0) {
            bgElement.style.backgroundImage = `url('${images[0]}')`;
        }

        function changeBackground() {
            currentIndex = (currentIndex + 1) % images.length;
            bgElement.style.backgroundImage = `url('${images[currentIndex]}')`;
        }

        if(images.length > 1) {
            setInterval(changeBackground, 4000);
        }

        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');
        const eyeIcon = document.querySelector('#eyeIcon');

        togglePassword.addEventListener('click', function () {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            
            if (type === 'text') {
                eyeIcon.classList.remove('bi-eye');
                eyeIcon.classList.add('bi-eye-slash');
            } else {
                eyeIcon.classList.remove('bi-eye-slash');
                eyeIcon.classList.add('bi-eye');
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>