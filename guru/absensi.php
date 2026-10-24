<?php
// guru/absensi.php
session_start();
require_once '../config/koneksi.php';

// =====================================================
// PROTEKSI HALAMAN: CEK SESI & ROLE GURU
// =====================================================
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'guru') {
    header("Location: ../login.php");
    exit();
}

$id_user = $_SESSION['id_user'];

// =====================================================
// 1. AMBIL ID GURU & NAMA GURU BERDASARKAN ID USER LOGIN
// =====================================================
$stmt_guru = mysqli_prepare(
    $koneksi,
    "SELECT id_guru, nama_guru FROM guru WHERE id_user = ?"
);

mysqli_stmt_bind_param($stmt_guru, "i", $id_user);
mysqli_stmt_execute($stmt_guru);

$res_guru = mysqli_stmt_get_result($stmt_guru);
$guru = mysqli_fetch_assoc($res_guru);

$id_guru = $guru['id_guru'] ?? 0;

mysqli_stmt_close($stmt_guru);

// =====================================================
// PARAMETER FILTER DARI FORM / URL
// =====================================================
$id_jadwal = $_GET['id_jadwal'] ?? $_POST['id_jadwal'] ?? '';
$tanggal   = $_GET['tanggal'] ?? $_POST['tanggal'] ?? date('Y-m-d');

$pesan_sukses = '';
$pesan_error  = '';

// =====================================================
// 2. PROSES SIMPAN / UPDATE ABSENSI SISWA
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_absensi'])) {

    if (
        !empty($id_jadwal) &&
        !empty($tanggal) &&
        isset($_POST['status_absensi'])
    ) {

        $status_list = $_POST['status_absensi'];
        $keterangan_list = $_POST['keterangan'] ?? [];
        $waktu_sekarang = date('H:i:s');

        // -------------------------------------------------
        // VALIDASI KETERSEDIAAN JADWAL
        // -------------------------------------------------
        $stmt_valid = mysqli_prepare(
            $koneksi,
            "SELECT id_jadwal FROM jadwal WHERE id_jadwal = ?"
        );

        mysqli_stmt_bind_param(
            $stmt_valid,
            "i",
            $id_jadwal
        );

        mysqli_stmt_execute($stmt_valid);

        $res_valid = mysqli_stmt_get_result($stmt_valid);
        $cek_jadwal_ada = mysqli_fetch_assoc($res_valid);

        mysqli_stmt_close($stmt_valid);

        if (!$cek_jadwal_ada) {

            $pesan_error = "Gagal menyimpan: ID Jadwal tidak ditemukan pada tabel jadwal.";

        } else {

            mysqli_begin_transaction($koneksi);

            try {

                // -------------------------------------------------
                // PROSES SETIAP SISWA
                // -------------------------------------------------
                foreach ($status_list as $id_siswa => $status) {

                    $ket = $keterangan_list[$id_siswa] ?? '';

                    // -------------------------------------------------
                    // CEK APAKAH ABSENSI SUDAH ADA
                    // -------------------------------------------------
                    $stmt_cek = mysqli_prepare(
                        $koneksi,
                        "SELECT id_absensi
                         FROM absensi
                         WHERE id_jadwal = ?
                         AND id_siswa = ?
                         AND tanggal = ?"
                    );

                    mysqli_stmt_bind_param(
                        $stmt_cek,
                        "iis",
                        $id_jadwal,
                        $id_siswa,
                        $tanggal
                    );

                    mysqli_stmt_execute($stmt_cek);

                    $res_cek = mysqli_stmt_get_result($stmt_cek);
                    $existing = mysqli_fetch_assoc($res_cek);

                    mysqli_stmt_close($stmt_cek);

                    // -------------------------------------------------
                    // JIKA SUDAH ADA → UPDATE
                    // -------------------------------------------------
                    if ($existing) {

                        $stmt_upd = mysqli_prepare(
                            $koneksi,
                            "UPDATE absensi
                             SET id_jadwal = ?,
                                 status = ?,
                                 keterangan = ?,
                                 waktu_scan = ?
                             WHERE id_absensi = ?"
                        );

                        mysqli_stmt_bind_param(
                            $stmt_upd,
                            "isssi",
                            $id_jadwal,
                            $status,
                            $ket,
                            $waktu_sekarang,
                            $existing['id_absensi']
                        );

                        mysqli_stmt_execute($stmt_upd);

                        mysqli_stmt_close($stmt_upd);

                    // -------------------------------------------------
                    // JIKA BELUM ADA → INSERT
                    // -------------------------------------------------
                    } else {

                        $stmt_ins = mysqli_prepare(
                            $koneksi,
                            "INSERT INTO absensi
                            (
                                id_jadwal,
                                id_siswa,
                                tanggal,
                                waktu_scan,
                                status,
                                keterangan
                            )
                            VALUES (?, ?, ?, ?, ?, ?)"
                        );

                        mysqli_stmt_bind_param(
                            $stmt_ins,
                            "iissss",
                            $id_jadwal,
                            $id_siswa,
                            $tanggal,
                            $waktu_sekarang,
                            $status,
                            $ket
                        );

                        mysqli_stmt_execute($stmt_ins);

                        mysqli_stmt_close($stmt_ins);
                    }
                }

                // -------------------------------------------------
                // CATAT LOG
                // -------------------------------------------------
                if (function_exists('catatLog')) {
                    catatLog(
                        $koneksi,
                        $id_user,
                        'guru',
                        "Menginput absensi jadwal ID: $id_jadwal tanggal $tanggal"
                    );
                }

                // -------------------------------------------------
                // COMMIT TRANSAKSI
                // -------------------------------------------------
                mysqli_commit($koneksi);

                $pesan_sukses = "Data absensi berhasil disimpan!";

            } catch (Exception $e) {

                mysqli_rollback($koneksi);

                $pesan_error = "Gagal menyimpan absensi: " . $e->getMessage();
            }
        }

    } else {

        $pesan_error = "Harap pilih jadwal mengajar dan tanggal terlebih dahulu.";
    }
}

// =====================================================
// 3. AMBIL DAFTAR JADWAL MENGAJAR GURU
// =====================================================
$query_jadwal = "
    SELECT
        j.id_jadwal,
        k.nama_kelas,
        m.nama_mapel,
        j.hari,
        j.jam_mulai,
        j.jam_selesai
    FROM jadwal j
    LEFT JOIN kelas k
        ON j.id_kelas = k.id_kelas
    LEFT JOIN mapel m
        ON j.id_mapel = m.id_mapel
    WHERE j.id_guru = ?
       OR j.id_guru = ?
    ORDER BY
        FIELD(
            j.hari,
            'Senin',
            'Selasa',
            'Rabu',
            'Kamis',
            'Jumat',
            'Sabtu'
        ),
        j.jam_mulai ASC
";

$stmt_jadwal = mysqli_prepare(
    $koneksi,
    $query_jadwal
);

mysqli_stmt_bind_param(
    $stmt_jadwal,
    "ii",
    $id_guru,
    $id_user
);

mysqli_stmt_execute($stmt_jadwal);

$res_jadwal = mysqli_stmt_get_result($stmt_jadwal);

$list_jadwal = mysqli_fetch_all(
    $res_jadwal,
    MYSQLI_ASSOC
);

mysqli_stmt_close($stmt_jadwal);

// =====================================================
// 4. JIKA JADWAL DIPILIH, AMBIL DETAIL JADWAL & SISWA
// =====================================================
$list_siswa = [];
$detail_jadwal = null;

if (!empty($id_jadwal)) {

    $stmt_det = mysqli_prepare(
        $koneksi,
        "
        SELECT
            j.id_kelas,
            k.nama_kelas,
            m.nama_mapel,
            j.hari,
            j.jam_mulai,
            j.jam_selesai
        FROM jadwal j
        LEFT JOIN kelas k
            ON j.id_kelas = k.id_kelas
        LEFT JOIN mapel m
            ON j.id_mapel = m.id_mapel
        WHERE j.id_jadwal = ?
          AND (
              j.id_guru = ?
              OR j.id_guru = ?
          )
        "
    );

    mysqli_stmt_bind_param(
        $stmt_det,
        "iii",
        $id_jadwal,
        $id_guru,
        $id_user
    );

    mysqli_stmt_execute($stmt_det);

    $res_det = mysqli_stmt_get_result($stmt_det);

    $detail_jadwal = mysqli_fetch_assoc($res_det);

    mysqli_stmt_close($stmt_det);

    if ($detail_jadwal) {

        $id_kelas = $detail_jadwal['id_kelas'];

        $query_siswa = "
            SELECT
                s.id_siswa,
                s.nisn,
                s.nama_lengkap,
                a.status AS status_absen,
                a.keterangan AS ket_absen,
                a.waktu_scan
            FROM siswa s
            LEFT JOIN absensi a
                ON s.id_siswa = a.id_siswa
                AND a.id_jadwal = ?
                AND a.tanggal = ?
            WHERE s.id_kelas = ?
            ORDER BY s.nama_lengkap ASC
        ";

        $stmt_siswa = mysqli_prepare(
            $koneksi,
            $query_siswa
        );

        mysqli_stmt_bind_param(
            $stmt_siswa,
            "isi",
            $id_jadwal,
            $tanggal,
            $id_kelas
        );

        mysqli_stmt_execute($stmt_siswa);

        $res_siswa = mysqli_stmt_get_result($stmt_siswa);

        $list_siswa = mysqli_fetch_all(
            $res_siswa,
            MYSQLI_ASSOC
        );

        mysqli_stmt_close($stmt_siswa);
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Absensi - Portal Guru</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        /* CSS untuk styling Select Dropdown dengan highlight status */
        .select-status {
            font-weight: 600;
            border-radius: 6px;
            transition: all 0.2s ease-in-out;
            cursor: pointer;
        }
        .status-hadir {
            background-color: #d1e7dd !important;
            color: #0f5132 !important;
            border-color: #badbcc !important;
        }
        .status-izin {
            background-color: #cff4fc !important;
            color: #055160 !important;
            border-color: #b6effb !important;
        }
        .status-sakit {
            background-color: #fff3cd !important;
            color: #664d03 !important;
            border-color: #ffecb5 !important;
        }
        .status-alpa {
            background-color: #f8d7da !important;
            color: #842029 !important;
            border-color: #f5c2c7 !important;
        }
    </style>
</head>

<body class="bg-light">

<!-- =====================================================
     NAVBAR
===================================================== -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">
            <i class="bi bi-journal-check me-2"></i>Portal Guru
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navGuru">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navGuru">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="absensi.php">Input Absensi</a>
                </li>
            </ul>

            <div class="d-flex align-items-center">
                <span class="text-white me-3">
                    Halo, <strong><?= htmlspecialchars($guru['nama_guru'] ?? $_SESSION['username'] ?? 'Guru'); ?></strong>
                </span>

                <a href="../logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i>Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- =====================================================
     CONTENT
===================================================== -->
<div class="container my-4">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1">
                <i class="bi bi-check2-square text-primary me-2"></i>Input Presensi Siswa
            </h4>
            <p class="text-muted small mb-0">Kelola presensi kehadiran siswa sesuai jadwal pelajaran.</p>
        </div>

        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <!-- ALERT SUCCESS -->
    <?php if ($pesan_sukses): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($pesan_sukses); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- ALERT ERROR -->
    <?php if ($pesan_error): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($pesan_error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- FORM FILTER JADWAL & TANGGAL -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="absensi.php" class="row g-3 align-items-end">

                <!-- JADWAL -->
                <div class="col-md-7">
                    <label class="form-label fw-bold small">
                        Pilih Jadwal Mengajar (Kelas - Mapel)
                    </label>

                    <select name="id_jadwal" class="form-select" required>
                        <option value="">-- Pilih Jadwal --</option>
                        <?php foreach ($list_jadwal as $j): ?>
                            <?php
                            $jam_m = date('H:i', strtotime($j['jam_mulai']));
                            $jam_s = date('H:i', strtotime($j['jam_selesai']));
                            ?>
                            <option value="<?= $j['id_jadwal']; ?>" <?= ($id_jadwal == $j['id_jadwal']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars(
                                    ($j['nama_kelas'] ?? 'Kelas N/A') . " - " . 
                                    ($j['nama_mapel'] ?? 'Mapel N/A') . " (" . 
                                    $j['hari'] . " " . $jam_m . "-" . $jam_s . ")"
                                ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- TANGGAL -->
                <div class="col-md-3">
                    <label class="form-label fw-bold small">Tanggal Presensi</label>
                    <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($tanggal); ?>" required>
                </div>

                <!-- BUTTON -->
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100 fw-bold">
                        <i class="bi bi-search me-1"></i> Tampilkan
                    </button>
                </div>

            </form>
        </div>
    </div>

    <!-- FORM PRESENSI SISWA -->
    <?php if (!empty($id_jadwal) && $detail_jadwal): ?>
        <form method="POST" action="absensi.php">
            <input type="hidden" name="id_jadwal" value="<?= htmlspecialchars($id_jadwal); ?>">
            <input type="hidden" name="tanggal" value="<?= htmlspecialchars($tanggal); ?>">

            <div class="card shadow-sm border-0">
                <!-- CARD HEADER -->
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fw-bold text-primary fs-6 me-2">
                            <i class="bi bi-people me-1"></i>Kelas: <?= htmlspecialchars($detail_jadwal['nama_kelas'] ?? 'N/A'); ?>
                            <span class="mx-1">|</span>Mapel: <?= htmlspecialchars($detail_jadwal['nama_mapel'] ?? 'N/A'); ?>
                        </span>
                        <span class="badge bg-secondary"><?= date('d-m-Y', strtotime($tanggal)); ?></span>
                    </div>

                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setAllHadir()">
                        <i class="bi bi-check-all me-1"></i> Set Semua Hadir
                    </button>
                </div>

                <!-- CARD BODY -->
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width: 50px;">No</th>
                                    <th style="width: 140px;">NISN</th>
                                    <th>Nama Siswa</th>
                                    <th style="width: 180px;" class="text-center">Status Kehadiran</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($list_siswa)): ?>
                                    <?php $no = 1; foreach ($list_siswa as $s): ?>
                                        <?php 
                                            $st = $s['status_absen'] ?? 'Hadir'; 
                                            // Tentukan class warna awal berdasarkan data
                                            $class_warna = 'status-hadir';
                                            if ($st === 'Izin') $class_warna = 'status-izin';
                                            elseif ($st === 'Sakit') $class_warna = 'status-sakit';
                                            elseif ($st === 'Alpa') $class_warna = 'status-alpa';
                                        ?>
                                        <tr>
                                            <!-- NOMOR -->
                                            <td class="ps-3"><?= $no++; ?></td>

                                            <!-- NISN -->
                                            <td><code><?= htmlspecialchars($s['nisn']); ?></code></td>

                                            <!-- NAMA -->
                                            <td><strong><?= htmlspecialchars($s['nama_lengkap']); ?></strong></td>

                                            <!-- STATUS SELECT (ONLY HIGHLIGHT SELECTED) -->
                                            <td class="text-center">
                                                <select name="status_absensi[<?= $s['id_siswa']; ?>]" 
                                                        class="form-select form-select-sm text-center select-status <?= $class_warna; ?>" 
                                                        onchange="updateStatusColor(this)">
                                                    <option value="Hadir" <?= ($st === 'Hadir') ? 'selected' : ''; ?>>Hadir</option>
                                                    <option value="Izin" <?= ($st === 'Izin') ? 'selected' : ''; ?>>Izin</option>
                                                    <option value="Sakit" <?= ($st === 'Sakit') ? 'selected' : ''; ?>>Sakit</option>
                                                    <option value="Alpa" <?= ($st === 'Alpa') ? 'selected' : ''; ?>>Alpa</option>
                                                </select>
                                            </td>

                                            <!-- KETERANGAN -->
                                            <td>
                                                <input type="text" name="keterangan[<?= $s['id_siswa']; ?>]" class="form-control form-control-sm" placeholder="Opsional..." value="<?= htmlspecialchars($s['ket_absen'] ?? ''); ?>">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">Belum ada siswa yang terdaftar pada kelas ini.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- CARD FOOTER -->
                <?php if (!empty($list_siswa)): ?>
                    <div class="card-footer bg-white py-3 text-end">
                        <button type="submit" name="simpan_absensi" class="btn btn-success fw-bold px-4">
                            <i class="bi bi-save me-1"></i> Simpan Absensi
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    <?php endif; ?>

</div>

<!-- JavaScript untuk mengganti warna sesuai pilihan status yang dipilih -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function updateStatusColor(selectElement) {
        // Hapus kelas warna terdahulu
        selectElement.classList.remove('status-hadir', 'status-izin', 'status-sakit', 'status-alpa');
        
        // Tambahkan kelas warna baru sesuai opsi terpilih
        const val = selectElement.value;
        if (val === 'Hadir') {
            selectElement.classList.add('status-hadir');
        } else if (val === 'Izin') {
            selectElement.classList.add('status-izin');
        } else if (val === 'Sakit') {
            selectElement.classList.add('status-sakit');
        } else if (val === 'Alpa') {
            selectElement.classList.add('status-alpa');
        }
    }

    function setAllHadir() {
        const selects = document.querySelectorAll('.select-status');
        selects.forEach(select => {
            select.value = 'Hadir';
            updateStatusColor(select);
        });
    }
</script>
</body>
</html>