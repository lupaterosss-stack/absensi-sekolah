<?php
// admin/kelas.php
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

// 1. TAMBAH DATA KELAS OTOMATIS
if (isset($_POST['tambah_kelas'])) {
    $tingkat = trim($_POST['tingkat']);
    $jurusan = strtoupper(trim($_POST['jurusan']));

    if (!empty($tingkat)) {
        // Cari kelas terakhir untuk tingkat & jurusan yang sama agar penamaan A, B, C tidak bentrok
        $stmt_check = mysqli_prepare($koneksi, "SELECT nama_kelas FROM kelas WHERE tingkat = ? AND jurusan = ? ORDER BY id_kelas DESC LIMIT 1");
        mysqli_stmt_bind_param($stmt_check, "ss", $tingkat, $jurusan);
        mysqli_stmt_execute($stmt_check);
        $res_check = mysqli_stmt_get_result($stmt_check);

        if ($row = mysqli_fetch_assoc($res_check)) {
            // Ekstrak huruf dari nama kelas terakhir (contoh: "10 B IPA" -> ambil huruf "B")
            preg_match('/^\d+\s+([A-Z])/', $row['nama_kelas'], $matches);
            $last_char = isset($matches[1]) ? $matches[1] : 'A';
            $huruf_sub = chr(ord($last_char) + 1); // Huruf berikutnya (A -> B, B -> C)
        } else {
            $huruf_sub = 'A'; // Jika belum ada, mulai dari A
        }
        mysqli_stmt_close($stmt_check);

        // Susun nama kelas
        $nama_kelas = !empty($jurusan) ? "$tingkat $huruf_sub $jurusan" : "$tingkat $huruf_sub";

        // Query Insert dengan Prepared Statement
        $stmt_ins = mysqli_prepare($koneksi, "INSERT INTO kelas (tingkat, nama_kelas, jurusan) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt_ins, "sss", $tingkat, $nama_kelas, $jurusan);

        if (mysqli_stmt_execute($stmt_ins)) {
            $_SESSION['pesan'] = "Berhasil menambahkan kelas <strong>" . htmlspecialchars($nama_kelas) . "</strong> (Paralel <strong>$huruf_sub</strong>)!";
            $_SESSION['tipe_pesan'] = "success";
        } else {
            $_SESSION['pesan'] = "Gagal menambahkan data: " . mysqli_error($koneksi);
            $_SESSION['tipe_pesan'] = "danger";
        }
        mysqli_stmt_close($stmt_ins);
    }

    header("Location: kelas.php");
    exit();
}

// 2. HAPUS DATA KELAS (Menggunakan POST untuk Keamanan)
if (isset($_POST['hapus_kelas'])) {
    $id_kelas = intval($_POST['id_kelas']);
    $stmt_del = mysqli_prepare($koneksi, "DELETE FROM kelas WHERE id_kelas = ?");
    mysqli_stmt_bind_param($stmt_del, "i", $id_kelas);

    if (mysqli_stmt_execute($stmt_del)) {
        $_SESSION['pesan'] = "Data kelas berhasil dihapus!";
        $_SESSION['tipe_pesan'] = "warning";
    } else {
        $_SESSION['pesan'] = "Gagal menghapus data kelas.";
        $_SESSION['tipe_pesan'] = "danger";
    }
    mysqli_stmt_close($stmt_del);

    header("Location: kelas.php");
    exit();
}

// 3. LOGIKA FILTER & PENCARIAN
$where_clauses = [];
$params = [];
$types = "";

$filter_tingkat = isset($_GET['filter_tingkat']) ? trim($_GET['filter_tingkat']) : '';
$filter_jurusan = isset($_GET['filter_jurusan']) ? trim($_GET['filter_jurusan']) : '';
$keyword        = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

if ($filter_tingkat != '') {
    $where_clauses[] = "tingkat = ?";
    $params[] = $filter_tingkat;
    $types .= "s";
}
if ($filter_jurusan != '') {
    $where_clauses[] = "jurusan LIKE ?";
    $params[] = "%$filter_jurusan%";
    $types .= "s";
}
if ($keyword != '') {
    $where_clauses[] = "nama_kelas LIKE ?";
    $params[] = "%$keyword%";
    $types .= "s";
}

$sql_where = "";
if (count($where_clauses) > 0) {
    $sql_where = " WHERE " . implode(" AND ", $where_clauses);
}

// Query Filtered Data
$query_str = "SELECT * FROM kelas $sql_where ORDER BY tingkat ASC, nama_kelas ASC";
$stmt_kelas = mysqli_prepare($koneksi, $query_str);

if (!empty($params)) {
    mysqli_stmt_bind_param($stmt_kelas, $types, ...$params);
}
mysqli_stmt_execute($stmt_kelas);
$query_kelas = mysqli_stmt_get_result($stmt_kelas);

// 4. STATISTIK KELAS
$total_semua = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM kelas"))['total'] ?? 0;
$total_k10   = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM kelas WHERE tingkat='10'"))['total'] ?? 0;
$total_k11   = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM kelas WHERE tingkat='11'"))['total'] ?? 0;
$total_k12   = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM kelas WHERE tingkat='12'"))['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Data Kelas - Admin</title>
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
        <!-- Header & Top Action -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-journal-bookmark me-2 text-primary"></i>Kelola Data Kelas</h4>
                <p class="text-muted small mb-0">Atur tingkat, jurusan, dan manajemen sub-kelas secara otomatis.</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Kembali ke Dashboard</a>
        </div>

        <?php if ($pesan != ""): ?>
            <div class="alert alert-<?= $tipe_pesan; ?> alert-dismissible fade show shadow-sm" role="alert">
                <?= $pesan; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm bg-primary text-white">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="small text-white-50">Total Kelas</span>
                            <h3 class="fw-bold mb-0"><?= $total_semua; ?></h3>
                        </div>
                        <i class="bi bi-door-open fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm bg-info text-white">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="small text-white-50">Kelas 10</span>
                            <h3 class="fw-bold mb-0"><?= $total_k10; ?></h3>
                        </div>
                        <i class="bi bi-1-circle fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm bg-warning text-white">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="small text-white-50">Kelas 11</span>
                            <h3 class="fw-bold mb-0"><?= $total_k11; ?></h3>
                        </div>
                        <i class="bi bi-2-circle fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm bg-success text-white">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="small text-white-50">Kelas 12</span>
                            <h3 class="fw-bold mb-0"><?= $total_k12; ?></h3>
                        </div>
                        <i class="bi bi-3-circle fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Form Tambah Kelas -->
            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3 fw-bold border-bottom">
                        <i class="bi bi-plus-circle me-1 text-primary"></i> Tambah Kelas Baru
                    </div>
                    <div class="card-body">
                        <form action="kelas.php" method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Tingkat Kelas <span class="text-danger">*</span></label>
                                <select name="tingkat" class="form-select" required>
                                    <option value="" selected disabled>-- Pilih Tingkat --</option>
                                    <option value="10">Kelas 10</option>
                                    <option value="11">Kelas 11</option>
                                    <option value="12">Kelas 12</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Jurusan / Peminatan</label>
                                <input type="text" name="jurusan" class="form-control" placeholder="Contoh: IPA / IPS / TKJ">
                                <div class="form-text">Kosongkan jika tidak ada jurusan.</div>
                            </div>

                            <div class="alert alert-light border small py-2 mb-3 text-secondary">
                                <i class="bi bi-info-circle text-primary me-1"></i> 
                                <strong>Sistem Otomatis:</strong> Sub-kelas (A, B, C...) akan ditambahkan otomatis secara berurutan.
                            </div>

                            <button type="submit" name="tambah_kelas" class="btn btn-primary w-100 fw-bold">
                                <i class="bi bi-save me-1"></i> Simpan Kelas Baru
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Area Data & Filter Kelas -->
            <div class="col-lg-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 border-bottom">
                        <div class="fw-bold mb-2"><i class="bi bi-funnel me-1 text-primary"></i> Filter & Pencarian Data Kelas</div>
                        
                        <form action="kelas.php" method="GET" class="row g-2">
                            <div class="col-md-3 col-6">
                                <select name="filter_tingkat" class="form-select form-select-sm">
                                    <option value="">Semua Tingkat</option>
                                    <option value="10" <?= $filter_tingkat == '10' ? 'selected' : ''; ?>>Kelas 10</option>
                                    <option value="11" <?= $filter_tingkat == '11' ? 'selected' : ''; ?>>Kelas 11</option>
                                    <option value="12" <?= $filter_tingkat == '12' ? 'selected' : ''; ?>>Kelas 12</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-6">
                                <input type="text" name="filter_jurusan" class="form-control form-control-sm" placeholder="Jurusan (ex: IPA)" value="<?= htmlspecialchars($filter_jurusan); ?>">
                            </div>
                            <div class="col-md-4 col-8">
                                <input type="text" name="keyword" class="form-control form-control-sm" placeholder="Cari nama kelas..." value="<?= htmlspecialchars($keyword); ?>">
                            </div>
                            <div class="col-md-2 col-4 d-flex gap-1">
                                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i></button>
                                <?php if ($filter_tingkat != '' || $filter_jurusan != '' || $keyword != ''): ?>
                                    <a href="kelas.php" class="btn btn-outline-secondary btn-sm" title="Reset Filter"><i class="bi bi-x-lg"></i></a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3" style="width: 50px;">No</th>
                                        <th>Tingkat</th>
                                        <th>Nama Kelas Ringkas</th>
                                        <th>Jurusan / Peminatan</th>
                                        <th class="text-center" style="width: 140px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($query_kelas && mysqli_num_rows($query_kelas) > 0): ?>
                                        <?php $no = 1; while ($row = mysqli_fetch_assoc($query_kelas)): ?>
                                            <?php
                                                $badge_color = 'bg-info';
                                                if ($row['tingkat'] == '11') $badge_color = 'bg-warning text-dark';
                                                if ($row['tingkat'] == '12') $badge_color = 'bg-success';
                                            ?>
                                            <tr>
                                                <td class="ps-3"><?= $no++; ?></td>
                                                <td>
                                                    <span class="badge <?= $badge_color; ?> rounded-pill">
                                                        Kelas <?= htmlspecialchars($row['tingkat']); ?>
                                                    </span>
                                                </td>
                                                <td><strong class="text-dark fs-6"><?= htmlspecialchars($row['nama_kelas']); ?></strong></td>
                                                <td>
                                                    <?= !empty($row['jurusan']) 
                                                        ? '<span class="badge bg-light text-dark border">' . htmlspecialchars(strtoupper($row['jurusan'])) . '</span>' 
                                                        : '<span class="text-muted small">-</span>'; 
                                                    ?>
                                                </td>
                                                <td class="text-center">
                                                    <a href="detail_kelas.php?id=<?= $row['id_kelas']; ?>" class="btn btn-info btn-sm text-white me-1" title="Lihat Detail Kelas">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <!-- Tombol Hapus Menggunakan Form POST -->
                                                    <form action="kelas.php" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus kelas <?= htmlspecialchars($row['nama_kelas']); ?>?')">
                                                        <input type="hidden" name="id_kelas" value="<?= $row['id_kelas']; ?>">
                                                        <button type="submit" name="hapus_kelas" class="btn btn-outline-danger btn-sm" title="Hapus Kelas">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-5 text-muted">
                                                <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                                                Data kelas tidak ditemukan.
                                            </td>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>