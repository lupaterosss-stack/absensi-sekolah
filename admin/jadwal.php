<?php
// admin/jadwal.php
session_start();
require_once '../config/koneksi.php';

// Proteksi Halaman: Hanya Admin
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$pesan = "";
$tipe_pesan = "";

$daftar_hari = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

// Ambil Data Referensi
$query_kelas = mysqli_query($koneksi, "SELECT * FROM kelas ORDER BY nama_kelas ASC");
$query_mapel = mysqli_query($koneksi, "SELECT * FROM mapel ORDER BY nama_mapel ASC");
$query_guru  = mysqli_query($koneksi, "SELECT * FROM guru ORDER BY nama_guru ASC");

$list_kelas = mysqli_fetch_all($query_kelas, MYSQLI_ASSOC);
$list_mapel = mysqli_fetch_all($query_mapel, MYSQLI_ASSOC);
$list_guru  = mysqli_fetch_all($query_guru, MYSQLI_ASSOC);

// Filter Kelas Aktif
$id_kelas_filter = isset($_GET['kelas']) ? intval($_GET['kelas']) : ($list_kelas[0]['id_kelas'] ?? 0);

// 1. PROSES TAMBAH JADWAL MASSAL
if (isset($_POST['tambah_massal'])) {
    $id_kelas = intval($_POST['id_kelas']);
    $hari_arr = $_POST['hari'] ?? [];
    $mapel_arr = $_POST['id_mapel'] ?? [];
    $guru_arr = $_POST['id_guru'] ?? [];
    $mulai_arr = $_POST['jam_mulai'] ?? [];
    $selesai_arr = $_POST['jam_selesai'] ?? [];

    $berhasil = 0;
    $gagal_bentrok = 0;
    $detail_error = [];

    if ($id_kelas > 0 && is_array($hari_arr)) {
        for ($i = 0; $i < count($hari_arr); $i++) {
            $hari        = trim($hari_arr[$i]);
            $id_mapel    = intval($mapel_arr[$i] ?? 0);
            $id_guru     = intval($guru_arr[$i] ?? 0);
            $jam_mulai   = trim($mulai_arr[$i] ?? '');
            $jam_selesai = trim($selesai_arr[$i] ?? '');

            if (empty($hari) || $id_mapel <= 0 || $id_guru <= 0 || empty($jam_mulai) || empty($jam_selesai)) {
                continue;
            }

            // Cek Bentrok Guru
            $stmt_cek_guru = mysqli_prepare($koneksi, "
                SELECT j.*, k.nama_kelas 
                FROM jadwal j 
                JOIN kelas k ON j.id_kelas = k.id_kelas 
                WHERE j.id_guru = ? AND j.hari = ? 
                AND ((j.jam_mulai < ? AND j.jam_selesai > ?))
            ");
            mysqli_stmt_bind_param($stmt_cek_guru, "isss", $id_guru, $hari, $jam_selesai, $jam_mulai);
            mysqli_stmt_execute($stmt_cek_guru);
            $res_guru = mysqli_stmt_get_result($stmt_cek_guru);

            // Cek Bentrok Kelas
            $stmt_cek_kelas = mysqli_prepare($koneksi, "
                SELECT * FROM jadwal 
                WHERE id_kelas = ? AND hari = ? 
                AND ((jam_mulai < ? AND jam_selesai > ?))
            ");
            mysqli_stmt_bind_param($stmt_cek_kelas, "isss", $id_kelas, $hari, $jam_selesai, $jam_mulai);
            mysqli_stmt_execute($stmt_cek_kelas);
            $res_kelas = mysqli_stmt_get_result($stmt_cek_kelas);

            if (mysqli_num_rows($res_guru) > 0) {
                $gagal_bentrok++;
                $data_bentrok = mysqli_fetch_assoc($res_guru);
                $detail_error[] = "Guru sudah mengajar di kelas " . htmlspecialchars($data_bentrok['nama_kelas']) . " ($hari $jam_mulai-$jam_selesai)";
            } elseif (mysqli_num_rows($res_kelas) > 0) {
                $gagal_bentrok++;
                $detail_error[] = "Kelas sudah terisi jadwal lain ($hari $jam_mulai-$jam_selesai)";
            } else {
                $stmt_ins = mysqli_prepare($koneksi, "INSERT INTO jadwal (id_kelas, id_mapel, id_guru, hari, jam_mulai, jam_selesai) VALUES (?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt_ins, "iiisss", $id_kelas, $id_mapel, $id_guru, $hari, $jam_mulai, $jam_selesai);
                if (mysqli_stmt_execute($stmt_ins)) {
                    $berhasil++;
                }
            }
        }

        if ($berhasil > 0 && $gagal_bentrok == 0) {
            $pesan = "Berhasil menambahkan $berhasil jadwal pelajaran baru!";
            $tipe_pesan = "success";
        } elseif ($berhasil > 0 && $gagal_bentrok > 0) {
            $pesan = "Berhasil menyimpan $berhasil jadwal. Namun ada $gagal_bentrok jadwal gagal karena bentrok:<br>• " . implode("<br>• ", $detail_error);
            $tipe_pesan = "warning";
        } else {
            $pesan = "Gagal menyimpan jadwal karena bentrok:<br>• " . implode("<br>• ", $detail_error);
            $tipe_pesan = "danger";
        }
    }
}

// 2. PROSES HAPUS JADWAL
if (isset($_GET['hapus'])) {
    $id_hapus = intval($_GET['hapus']);
    if ($id_hapus > 0) {
        $stmt_del = mysqli_prepare($koneksi, "DELETE FROM jadwal WHERE id_jadwal = ?");
        mysqli_stmt_bind_param($stmt_del, "i", $id_hapus);
        mysqli_stmt_execute($stmt_del);
        $pesan = "Jadwal berhasil dihapus.";
        $tipe_pesan = "success";
    }
}

// AMBIL DATA JADWAL UNTUK KELAS YANG DIPILIH
$jadwal_matriks = [];
if ($id_kelas_filter > 0) {
    $stmt_j = mysqli_prepare($koneksi, "
        SELECT j.id_jadwal, j.hari, j.jam_mulai, j.jam_selesai, m.nama_mapel, g.nama_guru 
        FROM jadwal j 
        JOIN mapel m ON j.id_mapel = m.id_mapel 
        JOIN guru g ON j.id_guru = g.id_guru 
        WHERE j.id_kelas = ? 
        ORDER BY j.jam_mulai ASC
    ");
    mysqli_stmt_bind_param($stmt_j, "i", $id_kelas_filter);
    mysqli_stmt_execute($stmt_j);
    $res_j = mysqli_stmt_get_result($stmt_j);

    while ($row = mysqli_fetch_assoc($res_j)) {
        $jadwal_matriks[$row['hari']][] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input & Edit Jadwal - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-shield-lock me-2"></i>Panel Admin</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active" href="jadwal.php">Input Jadwal</a></li>
                    <li class="nav-item"><a class="nav-link" href="jadwal_master.php">Master All-in-One</a></li>
                </ul>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-calendar-grid me-2 text-primary"></i>Kelola Jadwal Pelajaran</h4>
                <p class="text-muted small mb-0">Input jadwal per kelas atau lihat rekap master sekolah.</p>
            </div>
            <div>
                <a href="jadwal_master.php" class="btn btn-outline-dark me-2">
                    <i class="bi bi-table me-1"></i> Rekap Master (Cetak)
                </a>
                <button class="btn btn-primary fw-semibold" data-bs-toggle="modal" data-bs-target="#modalTambahMassal">
                    <i class="bi bi-plus-square me-1"></i> Input Massal (5 Baris)
                </button>
            </div>
        </div>

        <?php if (!empty($pesan)): ?>
            <div class="alert alert-<?= $tipe_pesan; ?> alert-dismissible fade show shadow-sm" role="alert">
                <?= $pesan; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body bg-white rounded">
                <form action="jadwal.php" method="GET" class="row align-items-center g-2">
                    <div class="col-auto">
                        <label class="fw-bold text-secondary mb-0"><i class="bi bi-funnel me-1"></i>Tampilkan Kelas:</label>
                    </div>
                    <div class="col-md-4">
                        <select name="kelas" class="form-select fw-bold text-primary" onchange="this.form.submit()">
                            <?php foreach ($list_kelas as $k): ?>
                                <option value="<?= $k['id_kelas']; ?>" <?= ($k['id_kelas'] == $id_kelas_filter) ? 'selected' : ''; ?>>
                                    Kelas <?= htmlspecialchars($k['nama_kelas']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3">
            <?php foreach ($daftar_hari as $hari): ?>
                <div class="col-md-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-primary text-white fw-bold d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-calendar-day me-2"></i><?= $hari; ?></span>
                            <span class="badge bg-light text-dark">
                                <?= isset($jadwal_matriks[$hari]) ? count($jadwal_matriks[$hari]) : 0; ?> Mapel
                            </span>
                        </div>
                        <div class="card-body p-2 bg-light">
                            <?php if (isset($jadwal_matriks[$hari]) && count($jadwal_matriks[$hari]) > 0): ?>
                                <?php foreach ($jadwal_matriks[$hari] as $item): ?>
                                    <div class="card mb-2 border-0 shadow-sm">
                                        <div class="card-body p-2">
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <span class="badge bg-secondary">
                                                    <i class="bi bi-clock me-1"></i>
                                                    <?= date('H:i', strtotime($item['jam_mulai'])); ?> - <?= date('H:i', strtotime($item['jam_selesai'])); ?>
                                                </span>
                                                <a href="jadwal.php?kelas=<?= $id_kelas_filter; ?>&hapus=<?= $item['id_jadwal']; ?>" 
                                                   class="text-danger small" 
                                                   title="Hapus Jadwal"
                                                   onclick="return confirm('Hapus jadwal ini?')">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
                                            <div class="fw-bold text-dark mb-1"><?= htmlspecialchars($item['nama_mapel']); ?></div>
                                            <div class="small text-muted">
                                                <i class="bi bi-person me-1"></i><?= htmlspecialchars($item['nama_guru']); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center text-muted py-4 small">Tidak ada jadwal</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Modal Input Jadwal Massal -->
    <div class="modal fade" id="modalTambahMassal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <form action="jadwal.php?kelas=<?= $id_kelas_filter; ?>" method="POST">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold"><i class="bi bi-plus-square me-2"></i>Input Jadwal Massal</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        
                        <div class="mb-3 row align-items-center">
                            <label class="col-md-2 col-form-label fw-bold">Pilih Kelas Target:</label>
                            <div class="col-md-4">
                                <select name="id_kelas" class="form-select fw-bold" required>
                                    <?php foreach ($list_kelas as $k): ?>
                                        <option value="<?= $k['id_kelas']; ?>" <?= ($k['id_kelas'] == $id_kelas_filter) ? 'selected' : ''; ?>>
                                            Kelas <?= htmlspecialchars($k['nama_kelas']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 140px;">Hari</th>
                                        <th>Mata Pelajaran</th>
                                        <th>Guru Pengampu</th>
                                        <th style="width: 130px;">Jam Mulai</th>
                                        <th style="width: 130px;">Jam Selesai</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php for ($i = 0; $i < 5; $i++): ?>
                                        <tr>
                                            <td>
                                                <select name="hari[]" class="form-select form-select-sm">
                                                    <option value="">-- Hari --</option>
                                                    <?php foreach ($daftar_hari as $h): ?>
                                                        <option value="<?= $h; ?>"><?= $h; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <select name="id_mapel[]" class="form-select form-select-sm">
                                                    <option value="">-- Pilih Mapel --</option>
                                                    <?php foreach ($list_mapel as $m): ?>
                                                        <option value="<?= $m['id_mapel']; ?>"><?= htmlspecialchars($m['nama_mapel']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <select name="id_guru[]" class="form-select form-select-sm">
                                                    <option value="">-- Pilih Guru --</option>
                                                    <?php foreach ($list_guru as $g): ?>
                                                        <option value="<?= $g['id_guru']; ?>"><?= htmlspecialchars($g['nama_guru']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="time" name="jam_mulai[]" class="form-control form-control-sm">
                                            </td>
                                            <td>
                                                <input type="time" name="jam_selesai[]" class="form-control form-control-sm">
                                            </td>
                                        </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="tambah_massal" class="btn btn-primary fw-semibold">Simpan Semua Jadwal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>