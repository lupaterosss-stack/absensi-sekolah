<?php
// admin/log_aktivitas.php
session_start();
require_once '../config/koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Ambil data log beserta informasi user jika ada tabel users
$query_log = mysqli_query($koneksi, "
    SELECT l.*, u.username 
    FROM activity_logs l 
    LEFT JOIN users u ON l.id_user = u.id_user 
    ORDER BY l.waktu DESC
");
$list_log = mysqli_fetch_all($query_log, MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Log Aktivitas Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">

    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-clock-history me-2 text-primary"></i>Log Aktivitas Sistem</h4>
                <p class="text-muted small mb-0">Riwayat aksi penting yang dilakukan oleh admin atau pengguna.</p>
            </div>
            <a href="index.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali ke Dashboard</a>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body bg-white rounded">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th style="width: 150px;">Waktu</th>
                                <th style="width: 150px;">User / Role</th>
                                <th>Aksi / Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($list_log) > 0): ?>
                                <?php $no = 1; foreach ($list_log as $log): ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><small class="text-muted"><?= $log['waktu']; ?></small></td>
                                        <td>
                                            <span class="fw-bold text-dark"><?= htmlspecialchars($log['username'] ?? 'User ID: '.$log['id_user']); ?></span><br>
                                            <span class="badge bg-secondary text-uppercase" style="font-size: 10px;"><?= $log['role']; ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($log['aksi']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">Belum ada aktivitas tercatat.</td>
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