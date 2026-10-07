<?php
// admin/approval.php
session_start();
require_once '../config/koneksi.php';

// Proteksi Halaman Admin
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$pesan = "";
$tipe_pesan = "";

// PROSES APPROVAL AKUN (AKTIFKAN / TOLAK)
if (isset($_POST['aksi_approval_akun'])) {
    $id_user     = intval($_POST['id_user']);
    $status_baru = $_POST['status_akun']; // 'Aktif' atau 'Ditolak'

    if ($id_user > 0 && in_array($status_baru, ['Aktif', 'Ditolak'])) {
        $stmt_upd = mysqli_prepare($koneksi, "UPDATE users SET status_akun = ? WHERE id_user = ?");
        mysqli_stmt_bind_param($stmt_upd, "si", $status_baru, $id_user);

        if (mysqli_stmt_execute($stmt_upd)) {
            $pesan = "Status akun berhasil diperbarui menjadi: <strong>$status_baru</strong>.";
            $tipe_pesan = "success";
        } else {
            $pesan = "Gagal memperbarui status akun.";
            $tipe_pesan = "danger";
        }
    }
}

// AMBIL DATA AKUN PENDING (Hanya untuk role guru & siswa, admin dikecualikan)
$query_akun = "
    SELECT id_user, username, role, status_akun, created_at 
    FROM users 
    WHERE status_akun = 'Pending' AND role != 'admin' 
    ORDER BY created_at DESC
";
$result_akun = mysqli_query($koneksi, $query_akun);

if (!$result_akun) {
    die("Query Error: " . mysqli_error($koneksi));
}

$list_akun = mysqli_fetch_all($result_akun, MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Persetujuan Akun Baru - Admin</title>
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
                    <li class="nav-item"><a class="nav-link active" href="approval.php">Approval Akun</a></li>
                </ul>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-person-check me-2 text-primary"></i>Persetujuan Akun Pendaftaran</h4>
                <p class="text-muted small mb-0">Verifikasi dan aktifkan akun Guru atau Siswa baru sebelum diberikan akses ke sistem.</p>
            </div>
        </div>

        <?php if (!empty($pesan)): ?>
            <div class="alert alert-<?= $tipe_pesan; ?> alert-dismissible fade show shadow-sm" role="alert">
                <?= $pesan; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Tabel Akun Pending -->
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width: 50px;">No</th>
                                <th>Username</th>
                                <th>Role / Hak Akses</th>
                                <th>Tanggal Daftar</th>
                                <th>Status</th>
                                <th class="text-center" style="width: 200px;">Aksi Persetujuan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($list_akun) > 0): ?>
                                <?php $no = 1; foreach ($list_akun as $row): ?>
                                    <tr>
                                        <td class="text-center"><?= $no++; ?></td>
                                        <td class="fw-bold"><?= htmlspecialchars($row['username']); ?></td>
                                        <td>
                                            <span class="badge bg-<?= ($row['role'] === 'guru') ? 'info text-dark' : 'secondary'; ?>">
                                                <?= ucfirst(htmlspecialchars($row['role'])); ?>
                                            </span>
                                        </td>
                                        <td><small><?= date('d-m-Y H:i', strtotime($row['created_at'])); ?></small></td>
                                        <td><span class="badge bg-warning text-dark">Pending</span></td>
                                        <td class="text-center">
                                            <form action="approval.php" method="POST" class="d-inline">
                                                <input type="hidden" name="id_user" value="<?= $row['id_user']; ?>">
                                                <button type="submit" name="aksi_approval_akun" value="1" onclick="this.form.status_akun.value='Aktif'" class="btn btn-sm btn-success me-1">
                                                    <i class="bi bi-check-lg me-1"></i>Setujui
                                                </button>
                                                <button type="submit" name="aksi_approval_akun" value="1" onclick="this.form.status_akun.value='Ditolak'" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-x-lg me-1"></i>Tolak
                                                </button>
                                                <input type="hidden" name="status_akun" value="">
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="bi bi-check-circle text-success fs-4 d-block mb-2"></i>
                                        Tidak ada akun baru yang menunggu persetujuan (Pending).
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>