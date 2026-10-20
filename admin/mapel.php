<?php
// admin/mapel.php
session_start();
require_once '../config/koneksi.php';

// Proteksi Halaman: Hanya Admin
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$pesan = "";
$tipe_pesan = "";

// 1. PROSES TAMBAH MAPEL
if (isset($_POST['tambah'])) {
    $nama_mapel = trim($_POST['nama_mapel']);

    if (!empty($nama_mapel)) {
        // Cek apakah nama mapel sudah ada
        $stmt_cek = mysqli_prepare($koneksi, "SELECT id_mapel FROM mapel WHERE nama_mapel = ?");
        mysqli_stmt_bind_param($stmt_cek, "s", $nama_mapel);
        mysqli_stmt_execute($stmt_cek);
        $res_cek = mysqli_stmt_get_result($stmt_cek);

        if (mysqli_num_rows($res_cek) > 0) {
            $pesan = "Mata pelajaran dengan nama tersebut sudah ada!";
            $tipe_pesan = "warning";
        } else {
            // Generate kode_mapel otomatis (Contoh: Matematika -> MAT-582)
            $clean_name = preg_replace('/[^a-zA-Z]/', '', $nama_mapel);
            $prefix = strtoupper(substr($clean_name, 0, 3));
            if (strlen($prefix) < 3) {
                $prefix = str_pad($prefix, 3, 'X');
            }
            $kode_mapel = $prefix . '-' . rand(100, 999);

            // Insert Data Baru dengan kode_mapel yang digenerate otomatis
            $stmt_ins = mysqli_prepare($koneksi, "INSERT INTO mapel (kode_mapel, nama_mapel) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt_ins, "ss", $kode_mapel, $nama_mapel);
            
            if (mysqli_stmt_execute($stmt_ins)) {
                $pesan = "Mata pelajaran berhasil ditambahkan!";
                $tipe_pesan = "success";
            } else {
                $pesan = "Gagal menambahkan mata pelajaran.";
                $tipe_pesan = "danger";
            }
            mysqli_stmt_close($stmt_ins);
        }
        mysqli_stmt_close($stmt_cek);
    } else {
        $pesan = "Nama mata pelajaran tidak boleh kosong!";
        $tipe_pesan = "danger";
    }
}

// 2. PROSES EDIT MAPEL
if (isset($_POST['edit'])) {
    $id_mapel = intval($_POST['id_mapel']);
    $nama_mapel = trim($_POST['nama_mapel']);

    if ($id_mapel > 0 && !empty($nama_mapel)) {
        $stmt_upd = mysqli_prepare($koneksi, "UPDATE mapel SET nama_mapel = ? WHERE id_mapel = ?");
        mysqli_stmt_bind_param($stmt_upd, "si", $nama_mapel, $id_mapel);
        if (mysqli_stmt_execute($stmt_upd)) {
            $pesan = "Data mata pelajaran berhasil diperbarui!";
            $tipe_pesan = "success";
        } else {
            $pesan = "Gagal memperbarui data mata pelajaran.";
            $tipe_pesan = "danger";
        }
        mysqli_stmt_close($stmt_upd);
    }
}

// 3. PROSES HAPUS MAPEL
if (isset($_GET['hapus'])) {
    $id_hapus = intval($_GET['hapus']);
    if ($id_hapus > 0) {
        $stmt_del = mysqli_prepare($koneksi, "DELETE FROM mapel WHERE id_mapel = ?");
        mysqli_stmt_bind_param($stmt_del, "i", $id_hapus);
        if (mysqli_stmt_execute($stmt_del)) {
            $pesan = "Mata pelajaran berhasil dihapus!";
            $tipe_pesan = "success";
        } else {
            $pesan = "Gagal menghapus mata pelajaran. Data mungkin masih terikat dengan jadwal atau nilai.";
            $tipe_pesan = "danger";
        }
        mysqli_stmt_close($stmt_del);
    }
}

// AMBIL SEMUA DATA MAPEL
$query_mapel = mysqli_query($koneksi, "SELECT * FROM mapel ORDER BY nama_mapel ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Mata Pelajaran - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">

    <!-- Navbar Admin -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-shield-lock me-2"></i>Panel Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navAdmin">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navAdmin">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="siswa.php">Siswa</a></li>
                    <li class="nav-item"><a class="nav-link" href="guru.php">Guru</a></li>
                    <li class="nav-item"><a class="nav-link" href="kelas.php">Kelas</a></li>
                    <li class="nav-item"><a class="nav-link active" href="mapel.php">Mapel</a></li>
                    <li class="nav-item"><a class="nav-link" href="jadwal.php">Jadwal</a></li>
                </ul>
                <a href="../logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-book me-2 text-primary"></i>Kelola Data Mata Pelajaran</h4>
                <p class="text-muted small mb-0">Daftar mata pelajaran yang diajarkan di sekolah.</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
                <i class="bi bi-plus-lg me-1"></i> Tambah Mapel
            </button>
        </div>

        <?php if (!empty($pesan)): ?>
            <div class="alert alert-<?= $tipe_pesan; ?> alert-dismissible fade show shadow-sm" role="alert">
                <?= $pesan; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Tabel Data Mapel -->
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="width: 60px;">No</th>
                                <th style="width: 150px;">Kode Mapel</th>
                                <th>Nama Mata Pelajaran</th>
                                <th class="text-center" style="width: 150px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($query_mapel && mysqli_num_rows($query_mapel) > 0): ?>
                                <?php $no = 1; while ($m = mysqli_fetch_assoc($query_mapel)): ?>
                                    <tr>
                                        <td class="ps-3"><?= $no++; ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($m['kode_mapel'] ?? '-'); ?></span></td>
                                        <td class="fw-bold text-dark"><?= htmlspecialchars($m['nama_mapel']); ?></td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-outline-warning me-1" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modalEdit<?= $m['id_mapel']; ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <a href="mapel.php?hapus=<?= $m['id_mapel']; ?>" 
                                               class="btn btn-sm btn-outline-danger" 
                                               onclick="return confirm('Yakin ingin menghapus mata pelajaran ini?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>

                                    <!-- Modal Edit Mapel -->
                                    <div class="modal fade" id="modalEdit<?= $m['id_mapel']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form action="mapel.php" method="POST">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title fw-bold">Edit Mata Pelajaran</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="id_mapel" value="<?= $m['id_mapel']; ?>">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Nama Mata Pelajaran</label>
                                                            <input type="text" name="nama_mapel" class="form-control" value="<?= htmlspecialchars($m['nama_mapel']); ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" name="edit" class="btn btn-primary">Simpan Perubahan</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">Belum ada data mata pelajaran.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Tambah Mapel -->
    <div class="modal fade" id="modalTambah" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="mapel.php" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Tambah Mata Pelajaran</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nama Mata Pelajaran</label>
                            <input type="text" name="nama_mapel" class="form-control" placeholder="Contoh: Matematika, Bahasa Indonesia" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="tambah" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>