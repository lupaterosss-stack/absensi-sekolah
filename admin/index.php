<?php
// admin/index.php
session_start();
require_once '../config/koneksi.php';

// Proteksi Halaman: Hanya Admin
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Inisialisasi Pesan Alert Notifikasi
$status_pesan = '';
$pesan = '';

// Proses Reset Password Pengguna (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $id_user_target = intval($_POST['id_user']);
    $password_baru = trim($_POST['password_baru']);
    $konfirmasi_password = trim($_POST['konfirmasi_password']);

    if (empty($password_baru) || empty($konfirmasi_password)) {
        $status_pesan = 'danger';
        $pesan = 'Password baru dan konfirmasi password tidak boleh kosong!';
    } elseif ($password_baru !== $konfirmasi_password) {
        $status_pesan = 'danger';
        $pesan = 'Password baru dan konfirmasi password tidak cocok!';
    } else {
        // Hash password menggunakan BCRYPT (Ganti ke md5($password_baru) jika sistem Anda masih menggunakan MD5)
        $password_hash = password_hash($password_baru, PASSWORD_BCRYPT);
        
        // Prepared Statement untuk keamanan dari SQL Injection
        $stmt = mysqli_prepare($koneksi, "UPDATE users SET password = ? WHERE id_user = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "si", $password_hash, $id_user_target);
            if (mysqli_stmt_execute($stmt)) {
                $status_pesan = 'success';
                $pesan = 'Password pengguna berhasil diperbarui!';
            } else {
                $status_pesan = 'danger';
                $pesan = 'Gagal memperbarui password pengguna.';
            }
            mysqli_stmt_close($stmt);
        } else {
            $status_pesan = 'danger';
            $pesan = 'Terjadi kesalahan sistem pada query database.';
        }
    }
}

// Proses Ubah Status Akun (Aktif / Nonaktif) jika ada permintaan GET
if (isset($_GET['ubah_status']) && isset($_GET['id'])) {
    $id_user_target = intval($_GET['id']);
    $status_baru = ($_GET['ubah_status'] === 'Aktif') ? 'Aktif' : 'Nonaktif';
    
    // Mencegah admin menonaktifkan akun sendiri
    if ($id_user_target != $_SESSION['id_user']) {
        mysqli_query($koneksi, "UPDATE users SET status_akun = '$status_baru' WHERE id_user = $id_user_target");
    }
    header("Location: index.php");
    exit();
}

// Hitung Ringkasan Data (Menghitung langsung dari tabel master)
$res_kelas = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM kelas");
$total_kelas = ($res_kelas) ? (mysqli_fetch_assoc($res_kelas)['total'] ?? 0) : 0;

$res_guru = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM guru");
$total_guru = ($res_guru) ? (mysqli_fetch_assoc($res_guru)['total'] ?? 0) : 0;

$res_siswa = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM siswa");
$total_siswa = ($res_siswa) ? (mysqli_fetch_assoc($res_siswa)['total'] ?? 0) : 0;

$res_mapel = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM mapel");
$total_mapel = ($res_mapel) ? (mysqli_fetch_assoc($res_mapel)['total'] ?? 0) : 0;

// Ambil Keyword Pencarian jika ada
$keyword = isset($_GET['cari']) ? trim($_GET['cari']) : '';
$keyword_escaped = mysqli_real_escape_string($koneksi, $keyword);

// Query Daftar Pengguna dengan Filter Pencarian (Search)
if ($keyword !== '') {
    $query_users = mysqli_query($koneksi, "
        SELECT u.id_user, u.username, u.role, u.status_akun, 
               COALESCE(g.nama_guru, s.nama_lengkap, u.username) as nama_lengkap
        FROM users u
        LEFT JOIN guru g ON u.id_user = g.id_user
        LEFT JOIN siswa s ON u.id_user = s.id_user
        WHERE u.username LIKE '%$keyword_escaped%' 
           OR g.nama_guru LIKE '%$keyword_escaped%' 
           OR s.nama_lengkap LIKE '%$keyword_escaped%'
        ORDER BY u.id_user DESC
    ");
} else {
    $query_users = mysqli_query($koneksi, "
        SELECT u.id_user, u.username, u.role, u.status_akun, 
               COALESCE(g.nama_guru, s.nama_lengkap, u.username) as nama_lengkap
        FROM users u
        LEFT JOIN guru g ON u.id_user = g.id_user
        LEFT JOIN siswa s ON u.id_user = s.id_user
        ORDER BY u.id_user DESC
    ");
}

// Menyimpan data users ke array agar bisa digunakan kembali untuk pembuatan Modal Reset Password
$users_list = [];
if ($query_users && mysqli_num_rows($query_users) > 0) {
    while ($row = mysqli_fetch_assoc($query_users)) {
        $users_list[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Panel Kontrol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">

    <!-- Navbar Admin Utama -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-shield-lock me-2"></i>Panel Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navAdmin">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navAdmin">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link active" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="siswa.php">Siswa</a></li>
                    <li class="nav-item"><a class="nav-link" href="guru.php">Guru</a></li>
                    <li class="nav-item"><a class="nav-link" href="kelas.php">Kelas</a></li>
                    <li class="nav-item"><a class="nav-link" href="mapel.php">Mapel</a></li>
                    <li class="nav-item"><a class="nav-link" href="jadwal.php">Jadwal</a></li>
                    <li class="nav-item"><a class="nav-link" href="approval.php">Approval Izin</a></li>
                </ul>

                <!-- Dropdown Profil -->
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <span class="small me-1">Halo, <strong><?= htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></strong></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li><a class="dropdown-item" href="ubah_password.php"><i class="bi bi-key me-2"></i>Ubah Password</a></li>
                            <li><a class="dropdown-item" href="log_aktivitas.php"><i class="bi bi-clock-history me-2"></i>Log Aktivitas</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        
        <!-- Header -->
        <div class="mb-4">
            <h3 class="fw-bold mb-1">Selamat Datang di Panel Kontrol Admin</h3>
            <p class="text-muted">Kelola master data kelas, siswa, guru, mata pelajaran, serta akses akun pengguna.</p>
        </div>

        <!-- Alert Notifikasi Reset Password -->
        <?php if (!empty($pesan)): ?>
            <div class="alert alert-<?= $status_pesan; ?> alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-<?= ($status_pesan === 'success') ? 'check-circle-fill' : 'exclamation-triangle-fill'; ?> me-2"></i>
                <?= htmlspecialchars($pesan); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white shadow-sm border-0">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <p class="mb-1 small opacity-75">Total Kelas</p>
                            <h2 class="fw-bold mb-0"><?= $total_kelas; ?></h2>
                        </div>
                        <i class="bi bi-journal-bookmark fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white shadow-sm border-0">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <p class="mb-1 small opacity-75">Total Guru</p>
                            <h2 class="fw-bold mb-0"><?= $total_guru; ?></h2>
                        </div>
                        <i class="bi bi-person-badge fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white shadow-sm border-0">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <p class="mb-1 small opacity-75">Total Siswa</p>
                            <h2 class="fw-bold mb-0"><?= $total_siswa; ?></h2>
                        </div>
                        <i class="bi bi-people fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark shadow-sm border-0">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <p class="mb-1 small opacity-75">Total Mapel</p>
                            <h2 class="fw-bold mb-0"><?= $total_mapel; ?></h2>
                        </div>
                        <i class="bi bi-book fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tombol Menu Pintas (Shortcut) -->
        <div class="row g-3 mb-4">
            <div class="col-md-2 col-6">
                <a href="kelas.php" class="btn btn-outline-primary w-100 py-3 text-center fw-semibold shadow-sm">
                    <i class="bi bi-door-open d-block fs-3 mb-1"></i> Kelola Kelas
                </a>
            </div>
            <div class="col-md-2 col-6">
                <a href="guru.php" class="btn btn-outline-info w-100 py-3 text-center fw-semibold shadow-sm">
                    <i class="bi bi-person-badge d-block fs-3 mb-1"></i> Kelola Guru
                </a>
            </div>
            <div class="col-md-2 col-6">
                <a href="siswa.php" class="btn btn-outline-success w-100 py-3 text-center fw-semibold shadow-sm">
                    <i class="bi bi-person-plus d-block fs-3 mb-1"></i> Kelola Siswa
                </a>
            </div>
            <div class="col-md-2 col-6">
                <a href="mapel.php" class="btn btn-outline-secondary w-100 py-3 text-center fw-semibold shadow-sm">
                    <i class="bi bi-book d-block fs-3 mb-1"></i> Mata Pelajaran
                </a>
            </div>
            <div class="col-md-2 col-6">
                <a href="jadwal.php" class="btn btn-outline-dark w-100 py-3 text-center fw-semibold shadow-sm">
                    <i class="bi bi-calendar-week d-block fs-3 mb-1"></i> Jadwal Pelajaran
                </a>
            </div>
            <div class="col-md-2 col-6">
                <a href="approval.php" class="btn btn-outline-warning w-100 py-3 text-center fw-semibold shadow-sm">
                    <i class="bi bi-check2-square d-block fs-3 mb-1"></i> Approval Izin
                </a>
            </div>
        </div>

        <!-- Tabel Daftar Pengguna Sistem & Fitur Pencarian (Search) -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-shield-check me-2 text-primary"></i>Daftar Pengguna Sistem & Status Akun</h6>
                
                <!-- Form Search -->
                <form action="index.php" method="GET" class="d-flex align-items-center gap-2">
                    <div class="input-group input-group-sm">
                        <input type="text" name="cari" class="form-control" placeholder="Cari username / nama..." value="<?= htmlspecialchars($keyword); ?>">
                        <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button>
                        <?php if ($keyword !== ''): ?>
                            <a href="index.php" class="btn btn-outline-secondary" title="Reset Pencarian"><i class="bi bi-x-lg"></i></a>
                        <?php endif; ?>
                    </div>
                    <span class="badge bg-light text-dark border ms-md-2">Total: <?= count($users_list); ?> Akun</span>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="width: 60px;">No</th>
                                <th>ID User</th>
                                <th>Username</th>
                                <th>Nama Lengkap</th>
                                <th class="text-center">Role</th>
                                <th class="text-center">Status Akun</th>
                                <th class="text-center" style="width: 260px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($users_list)): ?>
                                <?php $no = 1; foreach ($users_list as $u): ?>
                                    <tr>
                                        <td class="ps-3"><?= $no++; ?></td>
                                        <td class="text-danger fw-bold">#<?= $u['id_user']; ?></td>
                                        <td class="fw-bold"><?= htmlspecialchars($u['username']); ?></td>
                                        <td><?= htmlspecialchars($u['nama_lengkap']); ?></td>
                                        <td class="text-center">
                                            <?php 
                                                $role_badge = 'bg-secondary';
                                                if ($u['role'] === 'admin') $role_badge = 'bg-danger';
                                                elseif ($u['role'] === 'guru') $role_badge = 'bg-info';
                                                elseif ($u['role'] === 'siswa') $role_badge = 'bg-success';
                                            ?>
                                            <span class="badge <?= $role_badge; ?> text-uppercase"><?= htmlspecialchars($u['role']); ?></span>
                                        </td>
                                        <td class="text-center">
                                            <?php 
                                                $status = $u['status_akun'] ?? 'Aktif';
                                                $status_badge = ($status === 'Aktif') ? 'bg-success' : 'bg-danger';
                                            ?>
                                            <span class="badge <?= $status_badge; ?>"><?= htmlspecialchars($status); ?></span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-1">
                                                <!-- Tombol Reset Password Modal -->
                                                <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modalResetPass<?= $u['id_user']; ?>" title="Reset Password">
                                                    <i class="bi bi-key me-1"></i>Reset Password
                                                </button>

                                                <?php if ($u['id_user'] != $_SESSION['id_user']): ?>
                                                    <?php if ($status === 'Aktif'): ?>
                                                        <a href="index.php?ubah_status=Nonaktif&id=<?= $u['id_user']; ?>&cari=<?= urlencode($keyword); ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Yakin ingin menonaktifkan akun <?= htmlspecialchars($u['username']); ?>?')">
                                                            <i class="bi bi-slash-circle me-1"></i>Nonaktifkan
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="index.php?ubah_status=Aktif&id=<?= $u['id_user']; ?>&cari=<?= urlencode($keyword); ?>" class="btn btn-outline-success btn-sm" onclick="return confirm('Yakin ingin mengaktifkan kembali akun <?= htmlspecialchars($u['username']); ?>?')">
                                                            <i class="bi bi-check-circle me-1"></i>Aktifkan
                                                        </a>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <?php if ($keyword !== ''): ?>
                                            Tidak ditemukan pengguna dengan kata kunci <strong>"<?= htmlspecialchars($keyword); ?>"</strong>. <a href="index.php" class="text-decoration-none">Reset pencarian</a>
                                        <?php else: ?>
                                            Belum ada pengguna terdaftar.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <!-- Modal Reset Password (Dibuat per-User untuk interaksi cepat) -->
    <?php if (!empty($users_list)): ?>
        <?php foreach ($users_list as $u): ?>
            <div class="modal fade" id="modalResetPass<?= $u['id_user']; ?>" tabindex="-1" aria-labelledby="modalLabel<?= $u['id_user']; ?>" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title fw-bold" id="modalLabel<?= $u['id_user']; ?>">
                                <i class="bi bi-key-fill me-2"></i>Reset Password User
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form action="index.php<?= ($keyword !== '') ? '?cari='.urlencode($keyword) : ''; ?>" method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="id_user" value="<?= $u['id_user']; ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label text-muted mb-1">Target Pengguna:</label>
                                    <div class="p-2 bg-light rounded border">
                                        <strong><?= htmlspecialchars($u['nama_lengkap']); ?></strong> 
                                        <span class="text-muted">(@<?= htmlspecialchars($u['username']); ?>)</span>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="password_baru_<?= $u['id_user']; ?>" class="form-label fw-semibold">Password Baru</label>
                                    <input type="password" class="form-control" id="password_baru_<?= $u['id_user']; ?>" name="password_baru" placeholder="Masukkan password baru..." required autocomplete="new-password">
                                </div>

                                <div class="mb-3">
                                    <label for="konfirmasi_password_<?= $u['id_user']; ?>" class="form-label fw-semibold">Konfirmasi Password Baru</label>
                                    <input type="password" class="form-control" id="konfirmasi_password_<?= $u['id_user']; ?>" name="konfirmasi_password" placeholder="Ulangi password baru..." required autocomplete="new-password">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" name="reset_password" class="btn btn-warning fw-semibold">
                                    <i class="bi bi-save me-1"></i>Simpan Password Baru
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>