<?php
// admin/import_siswa.php
session_start();
require_once '../config/koneksi.php';

// Proteksi Halaman: Cek Sesi & Role Admin
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$pesan = "";
$tipe_pesan = "";

if (isset($_SESSION['pesan'])) {
    $pesan = $_SESSION['pesan'];
    $tipe_pesan = $_SESSION['tipe_pesan'];
    unset($_SESSION['pesan'], $_SESSION['tipe_pesan']);
}

// PROSES IMPORT SISWA DARI FILE CSV
if (isset($_POST['import_csv'])) {
    if (isset($_FILES['file_csv']) && $_FILES['file_csv']['error'] == 0) {
        $file_name = $_FILES['file_csv']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['file_csv']['name'], PATHINFO_EXTENSION));

        if ($ext === 'csv') {
            $handle = fopen($file_name, "r");
            $berhasil = 0;
            $gagal = 0;
            $baris = 0;

            // Transaksi Database
            mysqli_begin_transaction($koneksi);

            try {
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $baris++;
                    // Dilewati jika baris pertama adalah Header
                    if ($baris == 1 && strtolower($data[0]) == 'nisn') {
                        continue;
                    }

                    $nisn          = trim($data[0] ?? '');
                    $nama_lengkap  = trim($data[1] ?? '');
                    $jenis_kelamin = strtoupper(trim($data[2] ?? 'L'));
                    $id_kelas      = intval($data[3] ?? 0);

                    if (!empty($nisn) && !empty($nama_lengkap) && $id_kelas > 0) {
                        // Cek ketersediaan username (NISN)
                        $stmt_chk = mysqli_prepare($koneksi, "SELECT id_user FROM users WHERE username = ?");
                        mysqli_stmt_bind_param($stmt_chk, "s", $nisn);
                        mysqli_stmt_execute($stmt_chk);
                        mysqli_stmt_store_result($stmt_chk);

                        if (mysqli_stmt_num_rows($stmt_chk) == 0) {
                            mysqli_stmt_close($stmt_chk);

                            // Insert Akun Users
                            $password_db = password_hash($nisn, PASSWORD_DEFAULT);
                            $role = 'siswa';
                            $stmt_u = mysqli_prepare($koneksi, "INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                            mysqli_stmt_bind_param($stmt_u, "sss", $nisn, $password_db, $role);
                            mysqli_stmt_execute($stmt_u);
                            $id_user_baru = mysqli_insert_id($koneksi);
                            mysqli_stmt_close($stmt_u);

                            // Insert Data Siswa
                            $stmt_s = mysqli_prepare($koneksi, "INSERT INTO siswa (id_user, nisn, nama_lengkap, jenis_kelamin, id_kelas) VALUES (?, ?, ?, ?, ?)");
                            mysqli_stmt_bind_param($stmt_s, "isssi", $id_user_baru, $nisn, $nama_lengkap, $jenis_kelamin, $id_kelas);
                            mysqli_stmt_execute($stmt_s);
                            mysqli_stmt_close($stmt_s);

                            $berhasil++;
                        } else {
                            mysqli_stmt_close($stmt_chk);
                            $gagal++;
                        }
                    }
                }
                fclose($handle);
                mysqli_commit($koneksi);

                $_SESSION['pesan'] = "Import selesai! <strong>$berhasil data siswa berhasil ditambahkan</strong> ($gagal dilewati/duplikat).";
                $_SESSION['tipe_pesan'] = "success";
            } catch (Exception $e) {
                mysqli_rollback($koneksi);
                $_SESSION['pesan'] = "Terjadi kesalahan saat memproses file: " . $e->getMessage();
                $_SESSION['tipe_pesan'] = "danger";
            }
        } else {
            $_SESSION['pesan'] = "Format file tidak valid! Gunakan format file .CSV";
            $_SESSION['tipe_pesan'] = "danger";
        }
    } else {
        $_SESSION['pesan'] = "Silakan pilih file CSV terlebih dahulu.";
        $_SESSION['tipe_pesan'] = "warning";
    }

    header("Location: import_siswa.php");
    exit();
}

// Ambil daftar kelas untuk referensi ID Kelas
$query_kelas = mysqli_query($koneksi, "SELECT id_kelas, nama_kelas FROM kelas ORDER BY tingkat ASC, nama_kelas ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Data Siswa - Admin</title>
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Import Data Siswa Massal</h4>
                <p class="text-muted small mb-0">Unggah file format .CSV untuk memasukkan puluhan data siswa sekaligus.</p>
            </div>
            <div>
                <a href="tambah_siswa.php" class="btn btn-outline-primary btn-sm me-1"><i class="bi bi-person-plus me-1"></i> Input Manual</a>
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
            <!-- Form Upload -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3 fw-bold border-bottom">
                        <i class="bi bi-upload me-1 text-success"></i> Form Unggah File
                    </div>
                    <div class="card-body">
                        <form action="import_siswa.php" method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Pilih File (.CSV) <span class="text-danger">*</span></label>
                                <input type="file" name="file_csv" class="form-control" accept=".csv" required>
                                <div class="form-text">Pastikan file bertipe <code>.csv</code> dan disesuaikan dengan contoh format.</div>
                            </div>

                            <button type="submit" name="import_csv" class="btn btn-success w-100 fw-bold py-2 mt-3">
                                <i class="bi bi-cloud-upload me-1"></i> Proses & Import Siswa
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Panduan Format CSV & Referensi ID Kelas -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 fw-bold border-bottom">
                        <i class="bi bi-info-circle me-1 text-info"></i> Panduan Format & Kode Kelas
                    </div>
                    <div class="card-body">
                        <h6>Format Kolom CSV:</h6>
                        <pre class="bg-dark text-white p-2 rounded small">nisn,nama_lengkap,jenis_kelamin,id_kelas
005123451,Ahmad Subagja,L,1
005123452,Siti Aminah,P,2</pre>

                        <h6 class="mt-3">Daftar ID Kelas Sekolah:</h6>
                        <div class="table-responsive" style="max-height: 180px; overflow-y: auto;">
                            <table class="table table-sm table-bordered mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID Kelas</th>
                                        <th>Nama Kelas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($query_kelas && mysqli_num_rows($query_kelas) > 0): ?>
                                        <?php while ($k = mysqli_fetch_assoc($query_kelas)): ?>
                                            <tr>
                                                <td><code><?= $k['id_kelas']; ?></code></td>
                                                <td><?= htmlspecialchars($k['nama_kelas']); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="2" class="text-center text-muted">Belum ada kelas.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>