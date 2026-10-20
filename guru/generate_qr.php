<?php
// guru/generate_qr.php
session_start();
require_once '../config/koneksi.php';

// Proteksi Halaman: Cek Sesi & Role Guru
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'guru') {
    header("Location: ../login.php");
    exit();
}

$id_user = $_SESSION['id_user'];

// Ambil id_guru
$stmt_guru = mysqli_prepare($koneksi, "SELECT id_guru FROM guru WHERE id_user = ?");
mysqli_stmt_bind_param($stmt_guru, "i", $id_user);
mysqli_stmt_execute($stmt_guru);
$guru = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_guru));
$id_guru = $guru['id_guru'] ?? 0;
mysqli_stmt_close($stmt_guru);

// Ambil daftar jadwal guru untuk pilihan form QR
$stmt_jadwal = mysqli_prepare($koneksi, "SELECT j.*, k.nama_kelas, m.nama_mapel 
                                       FROM jadwal j 
                                       JOIN kelas k ON j.id_kelas = k.id_kelas 
                                       JOIN mapel m ON j.id_mapel = m.id_mapel 
                                       WHERE j.id_guru = ? 
                                       ORDER BY k.nama_kelas ASC");
mysqli_stmt_bind_param($stmt_jadwal, "i", $id_guru);
mysqli_stmt_execute($stmt_jadwal);
$list_jadwal = mysqli_fetch_all(mysqli_stmt_get_result($stmt_jadwal), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_jadwal);

// Tangkap parameter jika form disubmit
$selected_jadwal = $_GET['id_jadwal'] ?? '';
$qr_data = '';
$info_kelas = '';
$info_mapel = '';

if (!empty($selected_jadwal)) {
    foreach ($list_jadwal as $j) {
        if ($j['id_jadwal'] == $selected_jadwal) {
            $info_kelas = $j['nama_kelas'];
            $info_mapel = $j['nama_mapel'];
            // Buat token atau string data unik untuk QR code (misal: ID Jadwal + Tanggal hari ini)
            $tanggal_hari_ini = date('Y-m-d');
            $data_array = [
                        'type'      => 'absensi',
                        'id_jadwal' => $j['id_jadwal'],
                        'id_kelas'  => $j['id_kelas'],
                        'id_mapel'  => $j['id_mapel'],
                        'tanggal'   => $tanggal_hari_ini
                            ];
            $qr_data = json_encode($data_array);
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat QR Code Absensi - Portal Guru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">

    <!-- Navbar Portal Guru -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-journal-check me-2"></i>Portal Guru</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navGuru">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navGuru">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
                    
                    <!-- Dropdown Menu Absensi -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" id="navbarDropdownAbsensi" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-check2-square me-1"></i> Absensi
                        </a>
                        <ul class="dropdown-menu shadow border-0" aria-labelledby="navbarDropdownAbsensi">
                            <li><a class="dropdown-item py-2" href="absensi.php"><i class="bi bi-pencil-square me-2 text-primary"></i> Input Absensi Manual</a></li>
                            <li><a class="dropdown-item py-2 active" href="generate_qr.php"><i class="bi bi-qr-code-scan me-2 text-success"></i> Buat QR Code Absensi</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item py-2" href="rekap_absensi.php"><i class="bi bi-file-earmark-text me-2 text-warning"></i> Rekap Absensi</a></li>
                            <li><a class="dropdown-item py-2" href="ekspor_absensi_csv.php"><i class="bi bi-file-earmark-spreadsheet me-2 text-info"></i> Ekspor ke CSV</a></li>
                        </ul>
                    </li>

                    <li class="nav-item"><a class="nav-link" href="nilai.php">Input Nilai</a></li>
                </ul>
                <div class="d-flex align-items-center">
                    <a href="../logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <!-- Breadcrumb / Header -->
        <div class="d-flex align-items-center mb-4">
            <a href="index.php" class="btn btn-outline-secondary btn-sm me-3"><i class="bi bi-arrow-left"></i> Kembali</a>
            <h4 class="fw-bold mb-0"><i class="bi bi-qr-code-scan text-success me-2"></i>Generate QR Code Absensi</h4>
        </div>

        <div class="row">
            <!-- Kolom Form Pilih Sesi Mengajar -->
            <div class="col-md-5 mb-4">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 fw-bold">
                        Pilih Sesi Mengajar
                    </div>
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="mb-3">
                                <label for="id_jadwal" class="form-label small fw-bold">Jadwal / Kelas & Mapel</label>
                                <select name="id_jadwal" id="id_jadwal" class="form-select" required>
                                    <option value="">-- Pilih Jadwal Mengajar --</option>
                                    <?php foreach ($list_jadwal as $j): ?>
                                        <option value="<?= $j['id_jadwal']; ?>" <?= ($selected_jadwal == $j['id_jadwal']) ? 'selected' : ''; ?>>
                                            [<?= $j['hari']; ?>] <?= $j['nama_kelas']; ?> - <?= $j['nama_mapel']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-success w-100"><i class="bi bi-qr-code me-1"></i> Generate QR Code</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Kolom Tampilan QR Code -->
            <div class="col-md-7">
                <div class="card shadow-sm border-0 text-center">
                    <div class="card-header bg-white py-3 fw-bold text-start">
                        Tampilan QR Code Sesi Aktif
                    </div>
                    <div class="card-body p-5">
                        <?php if (!empty($qr_data)): ?>
                            <h5 class="text-primary fw-bold mb-1"><?= htmlspecialchars($info_mapel); ?></h5>
                            <p class="text-muted small mb-4">Kelas: <strong><?= htmlspecialchars($info_kelas); ?></strong> | Tanggal: <?= date('d-m-Y'); ?></p>
                            
                            <!-- Menampilkan QR Code menggunakan API publik (GoQR.me) -->
                            <div class="bg-white p-3 d-inline-block border rounded shadow-sm mb-3">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=<?= urlencode($qr_data); ?>" alt="QR Code Absensi" class="img-fluid">
                            </div>

                            <p class="text-muted small">Minta siswa memindai QR Code di atas menggunakan perangkat mereka untuk mencatat kehadiran.</p>
                            
                            <div>
                                <button onclick="window.print();" class="btn btn-outline-primary btn-sm"><i class="bi bi-printer me-1"></i> Cetak / Print QR</button>
                            </div>
                        <?php else: ?>
                            <div class="py-5 text-muted">
                                <i class="bi bi-qr-code fs-1 d-block mb-3 text-secondary opacity-50"></i>
                                <p class="mb-0">Silakan pilih jadwal mengajar di sebelah kiri lalu klik tombol <strong>Generate QR Code</strong>.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>