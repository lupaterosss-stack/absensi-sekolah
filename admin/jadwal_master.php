<?php
// admin/jadwal_master.php
session_start();
require_once '../config/koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$daftar_hari = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

// Waktu Istirahat (Bisa disesuaikan jika perlu)
$jam_istirahat_mulai = '10:00';
$jam_istirahat_selesai = '10:30';

// Ambil Data Referensi
$list_kelas = mysqli_fetch_all(mysqli_query($koneksi, "SELECT * FROM kelas ORDER BY nama_kelas ASC"), MYSQLI_ASSOC);
$list_mapel = mysqli_fetch_all(mysqli_query($koneksi, "SELECT * FROM mapel ORDER BY id_mapel ASC"), MYSQLI_ASSOC);
$list_guru  = mysqli_fetch_all(mysqli_query($koneksi, "SELECT * FROM guru ORDER BY id_guru ASC"), MYSQLI_ASSOC);

// Ambil semua jadwal
$query_jadwal = mysqli_query($koneksi, "
    SELECT j.*, m.kode_mapel, m.nama_mapel, g.id_guru, g.nama_guru
    FROM jadwal j
    JOIN mapel m ON j.id_mapel = m.id_mapel
    JOIN guru g ON j.id_guru = g.id_guru
    ORDER BY j.jam_mulai ASC
");

$jadwal_matrix = [];
$jam_per_hari = [];

while ($row = mysqli_fetch_assoc($query_jadwal)) {
    $hari = $row['hari'];
    $jam = date('H:i', strtotime($row['jam_mulai']));
    
    $jadwal_matrix[$hari][$jam][$row['id_kelas']] = $row;
    
    if (!isset($jam_per_hari[$hari])) {
        $jam_per_hari[$hari] = [];
    }
    if (!in_array($jam, $jam_per_hari[$hari])) {
        $jam_per_hari[$hari][] = $jam;
    }
}

// Pastikan slot jam diurutkan
foreach ($daftar_hari as $h) {
    if (isset($jam_per_hari[$h])) {
        sort($jam_per_hari[$h]);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Master Jadwal Pelajaran</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-size: 12px;
        }
        .table-jadwal th, .table-jadwal td {
            text-align: center;
            vertical-align: middle;
            padding: 4px 6px;
        }
        .bg-istirahat {
            background-color: #ffe3e3 !important;
            color: #dc3545;
            font-weight: bold;
            letter-spacing: 2px;
        }
        .code-badge {
            display: inline-block;
            background-color: #e9ecef;
            padding: 2px 5px;
            border-radius: 4px;
            font-weight: bold;
        }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; padding: 0 !important; }
            .card { border: none !important; box-shadow: none !important; }
            .page-break { page-break-after: always; }
        }
    </style>
</head>
<body class="bg-light p-3">

    <!-- Tombol Navigasi -->
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h4 class="fw-bold mb-0">Master Jadwal Pelajaran All-in-One</h4>
        <div>
            <a href="jadwal.php" class="btn btn-secondary btn-sm">Kembali ke Input Jadwal</a>
            <button onclick="window.print()" class="btn btn-primary btn-sm">Cetak / Print Master</button>
        </div>
    </div>

    <div class="row">
        <!-- Kolom Utama: Jadwal Per Hari -->
        <div class="col-lg-9 col-md-8">
            <div class="bg-white p-3 shadow-sm rounded border mb-3">
                <div class="text-center mb-3">
                    <h5 class="fw-bold mb-0">JADWAL PELAJARAN MINGGUAN</h5>
                    <small class="text-muted">REKAP MASTER SELURUH KELAS</small>
                </div>

                <?php foreach ($daftar_hari as $hari): ?>
                    <?php if (isset($jam_per_hari[$hari]) && count($jam_per_hari[$hari]) > 0): ?>
                        <div class="mb-4">
                            <h6 class="fw-bold text-white bg-dark p-2 rounded-top mb-0">
                                HARI <?= strtoupper($hari); ?>
                            </h6>
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm table-jadwal mb-0" style="font-size: 11px;">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 100px;">WAKTU / JAM</th>
                                            <?php foreach ($list_kelas as $k): ?>
                                                <th>Kelas <?= htmlspecialchars($k['nama_kelas']); ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $istirahat_displayed = false;
                                        foreach ($jam_per_hari[$hari] as $jam): 
                                            // Cek jika saatnya masuk jam istirahat
                                            if (!$istirahat_displayed && $jam >= $jam_istirahat_mulai && $jam < $jam_istirahat_selesai):
                                                $istirahat_displayed = true;
                                        ?>
                                                <tr class="bg-istirahat">
                                                    <td><?= $jam_istirahat_mulai; ?> - <?= $jam_istirahat_selesai; ?></td>
                                                    <td colspan="<?= count($list_kelas); ?>">ISTIRAHAT</td>
                                                </tr>
                                        <?php endif; ?>

                                            <tr>
                                                <td class="fw-bold text-nowrap"><?= $jam; ?> WIB</td>
                                                <?php foreach ($list_kelas as $k): ?>
                                                    <?php 
                                                        $id_k = $k['id_kelas'];
                                                        $cell = $jadwal_matrix[$hari][$jam][$id_k] ?? null;
                                                    ?>
                                                    <td>
                                                        <?php if ($cell): ?>
                                                            <span class="code-badge" data-bs-toggle="tooltip" data-bs-html="true" title="<b>Mapel:</b> <?= htmlspecialchars($cell['nama_mapel']); ?><br><b>Guru:</b> <?= htmlspecialchars($cell['nama_guru']); ?>">
                                                                <?= htmlspecialchars($cell['kode_mapel'] ?? 'M' . $cell['id_mapel']); ?> - G<?= htmlspecialchars($cell['id_guru']); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Kolom Samping: Legenda Keterangan Kode -->
        <div class="col-lg-3 col-md-4">
            <div class="bg-white p-3 shadow-sm rounded border mb-3 sticky-top" style="top: 15px;">
                <h6 class="fw-bold border-bottom pb-2 mb-2 text-primary">LEGENDA KODE</h6>

                <!-- Kode Mapel -->
                <div class="mb-3">
                    <strong class="d-block small text-muted mb-1">MATA PELAJARAN (KODE MAPEL)</strong>
                    <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                        <table class="table table-sm table-striped border mb-0" style="font-size: 11px;">
                            <thead>
                                <tr class="table-light">
                                    <th>Kode</th>
                                    <th>Nama Mapel</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($list_mapel as $m): ?>
                                    <tr>
                                        <td class="fw-bold text-nowrap"><?= htmlspecialchars($m['kode_mapel'] ?? 'M' . $m['id_mapel']); ?></td>
                                        <td><?= htmlspecialchars($m['nama_mapel']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Kode Guru -->
                <div>
                    <strong class="d-block small text-muted mb-1">GURU PENGAMPU (KODE GURU)</strong>
                    <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                        <table class="table table-sm table-striped border mb-0" style="font-size: 11px;">
                            <thead>
                                <tr class="table-light">
                                    <th>Kode</th>
                                    <th>Nama Guru</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($list_guru as $g): ?>
                                    <tr>
                                        <td class="fw-bold text-nowrap">G<?= htmlspecialchars($g['id_guru']); ?></td>
                                        <td><?= htmlspecialchars($g['nama_guru']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Aktifkan Popover / Tooltip Bootstrap saat hover kode
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (el) { return new bootstrap.Tooltip(el); });
    </script>
</body>
</html>