<?php
// guru/ekspor_absensi_csv.php
session_start();
require_once '../config/koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'guru') {
    exit("Akses ditolak.");
}

$id_user      = $_SESSION['id_user'];
$id_jadwal    = $_GET['id_jadwal'] ?? '';
$semester     = $_GET['semester'] ?? 'Ganjil';
$tahun_ajaran = $_GET['tahun_ajaran'] ?? date('Y') . '/' . (date('Y') + 1);

// Ambil id_guru
$stmt_guru = mysqli_prepare($koneksi, "SELECT id_guru FROM guru WHERE id_user = ?");
mysqli_stmt_bind_param($stmt_guru, "i", $id_user);
mysqli_stmt_execute($stmt_guru);
$guru = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_guru));
$id_guru = $guru['id_guru'] ?? 0;
mysqli_stmt_close($stmt_guru);

// Jika ID Jadwal belum dipilih, tampilkan form pemilihan lengkap dengan pilihan semester & tahun ajaran
if (empty($id_jadwal)) {
    $stmt_list = mysqli_prepare($koneksi, "
        SELECT j.id_jadwal, k.nama_kelas, m.nama_mapel, j.hari, j.jam_mulai, j.jam_selesai 
        FROM jadwal j 
        JOIN kelas k ON j.id_kelas = k.id_kelas 
        JOIN mapel m ON j.id_mapel = m.id_mapel 
        WHERE j.id_guru = ? 
        ORDER BY k.nama_kelas ASC, m.nama_mapel ASC
    ");
    mysqli_stmt_bind_param($stmt_list, "i", $id_guru);
    mysqli_stmt_execute($stmt_list);
    $list_jadwal = mysqli_fetch_all(mysqli_stmt_get_result($stmt_list), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_list);
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Ekspor Matriks Absensi - Portal Guru</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    </head>
    <body class="bg-light">
        <div class="container my-5" style="max-width: 650px;">
            <div class="card shadow border-0">
                <div class="card-header bg-success text-white py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Ekspor Matriks Absensi ke Excel</h5>
                </div>
                <div class="card-body p-4">
                    <form action="ekspor_absensi_csv.php" method="GET">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Pilih Jadwal (Kelas & Mapel)</label>
                            <select name="id_jadwal" class="form-select" required>
                                <option value="">-- Pilih Jadwal Mengajar --</option>
                                <?php foreach ($list_jadwal as $j): ?>
                                    <option value="<?= $j['id_jadwal']; ?>">
                                        Kelas <?= htmlspecialchars($j['nama_kelas']); ?> — <?= htmlspecialchars($j['nama_mapel']); ?> (<?= $j['hari']; ?>, <?= date('H:i', strtotime($j['jam_mulai'])); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Semester</label>
                                <select name="semester" class="form-select">
                                    <option value="Ganjil">Semester Ganjil (Juli - Des)</option>
                                    <option value="Genap">Semester Genap (Jan - Jun)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Tahun Ajaran</label>
                                <input type="text" name="tahun_ajaran" class="form-control" value="<?= date('Y') . '/' . (date('Y') + 1); ?>" placeholder="2025/2026" required>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Batal</a>
                            <button type="submit" class="btn btn-success fw-bold"><i class="bi bi-file-earmark-excel me-1"></i> Unduh File Excel Matriks</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// ==========================================================
// PROSES EKSPOR MATRIKS BERDASARKAN JADWAL & SEMESTER
// ==========================================================

// 1. Ambil Detail Jadwal, Kelas, & Mapel
$stmt_det = mysqli_prepare($koneksi, "
    SELECT j.id_kelas, j.id_mapel, k.nama_kelas, m.nama_mapel
    FROM jadwal j
    LEFT JOIN kelas k ON j.id_kelas = k.id_kelas
    LEFT JOIN mapel m ON j.id_mapel = m.id_mapel
    WHERE j.id_jadwal = ?
");
mysqli_stmt_bind_param($stmt_det, "i", $id_jadwal);
mysqli_stmt_execute($stmt_det);
$detail = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_det));
mysqli_stmt_close($stmt_det);

$id_kelas   = $detail['id_kelas'] ?? 0;
$id_mapel   = $detail['id_mapel'] ?? 0;
$nama_kelas = $detail['nama_kelas'] ?? 'Kelas';
$nama_mapel = $detail['nama_mapel'] ?? 'Mapel';

// 2. Tentukan Rentang Bulan Semester
$bulan_semester = ($semester == 'Ganjil') ? ['07', '08', '09', '10', '11', '12'] : ['01', '02', '03', '04', '05', '06'];
$tahun_parts = explode('/', $tahun_ajaran);
$tahun_dasar = intval($tahun_parts[0]);
$tahun_genap = isset($tahun_parts[1]) ? intval($tahun_parts[1]) : ($tahun_dasar + 1);

$tgl_kondisi = [];
foreach ($bulan_semester as $bln) {
    $thn_target = ($semester == 'Genap' && intval($bln) <= 6) ? $tahun_genap : $tahun_dasar;
    $tgl_kondisi[] = "a.tanggal LIKE '{$thn_target}-{$bln}%'";
}
$where_semester = "(" . implode(' OR ', $tgl_kondisi) . ")";

// 3. Ambil Daftar Tanggal Pertemuan dalam Semester tersebut untuk Mapel ini
$list_tanggal = [];
$stmt_tgl = mysqli_prepare($koneksi, "
    SELECT DISTINCT a.tanggal 
    FROM absensi a 
    JOIN jadwal j ON a.id_jadwal = j.id_jadwal 
    WHERE j.id_kelas = ? AND j.id_mapel = ? AND $where_semester 
    ORDER BY a.tanggal ASC
");
mysqli_stmt_bind_param($stmt_tgl, "ii", $id_kelas, $id_mapel);
mysqli_stmt_execute($stmt_tgl);
$res_tgl = mysqli_stmt_get_result($stmt_tgl);
while ($row = mysqli_fetch_assoc($res_tgl)) {
    $list_tanggal[] = $row['tanggal'];
}
mysqli_stmt_close($stmt_tgl);

// 4. Ambil Daftar Siswa di Kelas
$stmt_siswa = mysqli_prepare($koneksi, "SELECT id_siswa, nisn, nama_lengkap FROM siswa WHERE id_kelas = ? ORDER BY nama_lengkap ASC");
mysqli_stmt_bind_param($stmt_siswa, "i", $id_kelas);
mysqli_stmt_execute($stmt_siswa);
$siswas = mysqli_fetch_all(mysqli_stmt_get_result($stmt_siswa), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_siswa);

// 5. Ambil Data Absensi Detail untuk Dimasukkan ke Matriks
$matrix_absensi = [];
$stmt_abs = mysqli_prepare($koneksi, "
    SELECT a.id_siswa, a.tanggal, a.status 
    FROM absensi a 
    JOIN jadwal j ON a.id_jadwal = j.id_jadwal 
    WHERE j.id_kelas = ? AND j.id_mapel = ? AND $where_semester
");
mysqli_stmt_bind_param($stmt_abs, "ii", $id_kelas, $id_mapel);
mysqli_stmt_execute($stmt_abs);
$res_abs = mysqli_stmt_get_result($stmt_abs);
while ($row = mysqli_fetch_assoc($res_abs)) {
    $matrix_absensi[$row['id_siswa']][$row['tanggal']] = $row['status'];
}
mysqli_stmt_close($stmt_abs);

// ==========================================================
// OUTPUT KE FILE EXCEL (.xls)
// ==========================================================
$filename = "Matriks_Absensi_" . str_replace(' ', '_', $nama_kelas) . "_" . str_replace(' ', '_', $nama_mapel) . "_Sem_{$semester}.xls";
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        table { border-collapse: collapse; width: 100%; font-family: sans-serif; white-space: nowrap; }
        th, td { border: 1px solid #000000; padding: 5px 8px; font-size: 10pt; text-align: center; }
        th { background-color: #d9edf7; font-weight: bold; }
        .text-start { text-align: left; }
        .title { font-size: 13pt; font-weight: bold; margin-bottom: 5px; font-family: sans-serif; }
        .st-Hadir { background-color: #d1e7dd; color: #0f5132; font-weight: bold; }
        .st-Sakit { background-color: #fff3cd; color: #664d03; font-weight: bold; }
        .st-Izin  { background-color: #cff4fc; color: #055160; font-weight: bold; }
        .st-Alpa  { background-color: #f8d7da; color: #842029; font-weight: bold; }
    </style>
</head>
<body>
    <div class="title">MATRIKS REKAPITULASI ABSENSI SEMESTER</div>
    <table>
        <tr>
            <td style="border:none; text-align:left; font-weight:bold;" width="100">Kelas</td>
            <td style="border:none; text-align:left;" colspan="10">: <?= htmlspecialchars($nama_kelas); ?></td>
        </tr>
        <tr>
            <td style="border:none; text-align:left; font-weight:bold;">Mata Pelajaran</td>
            <td style="border:none; text-align:left;" colspan="10">: <?= htmlspecialchars($nama_mapel); ?></td>
        </tr>
        <tr>
            <td style="border:none; text-align:left; font-weight:bold;">Semester / TA</td>
            <td style="border:none; text-align:left;" colspan="10">: <?= htmlspecialchars($semester); ?> (TA: <?= htmlspecialchars($tahun_ajaran); ?>)</td>
        </tr>
    </table>
    <br>
    <table>
        <thead>
            <tr>
                <th rowspan="2" style="width: 35px;">No</th>
                <th rowspan="2" style="width: 110px;">NISN</th>
                <th rowspan="2" style="width: 200px;" class="text-start">Nama Siswa</th>
                <?php if (!empty($list_tanggal)): ?>
                    <th colspan="<?= count($list_tanggal); ?>">Tanggal Pertemuan Harian</th>
                <?php else: ?>
                    <th>Tanggal Pertemuan</th>
                <?php endif; ?>
                <th colspan="5" style="width: 150px;">Total Rekap</th>
            </tr>
            <tr>
                <?php if (!empty($list_tanggal)): ?>
                    <?php foreach ($list_tanggal as $tgl): ?>
                        <th style="min-width: 45px;"><?= date('d/m', strtotime($tgl)); ?></th>
                    <?php endforeach; ?>
                <?php else: ?>
                    <th>Tidak ada pertemuan</th>
                <?php endif; ?>
                <th>H</th>
                <th>S</th>
                <th>I</th>
                <th>A</th>
                <th>Tot</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($siswas)): ?>
                <?php $no = 1; foreach ($siswas as $s): ?>
                    <?php 
                        $id_s = $s['id_siswa'];
                        $cnt_h = 0; $cnt_s = 0; $cnt_i = 0; $cnt_a = 0;
                    ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td style="mso-number-format:'\@';"><?= htmlspecialchars($s['nisn']); ?></td>
                        <td class="text-start"><?= htmlspecialchars($s['nama_lengkap']); ?></td>

                        <?php if (!empty($list_tanggal)): ?>
                            <?php foreach ($list_tanggal as $tgl): ?>
                                <?php 
                                    $st = $matrix_absensi[$id_s][$tgl] ?? '-';
                                    $code = '-';
                                    $class_css = '';
                                    if ($st === 'Hadir') { $code = 'H'; $cnt_h++; $class_css = 'st-Hadir'; }
                                    elseif ($st === 'Sakit') { $code = 'S'; $cnt_s++; $class_css = 'st-Sakit'; }
                                    elseif ($st === 'Izin')  { $code = 'I'; $cnt_i++; $class_css = 'st-Izin'; }
                                    elseif ($st === 'Alpa')  { $code = 'A'; $cnt_a++; $class_css = 'st-Alpa'; }
                                ?>
                                <td class="<?= $class_css; ?>"><?= $code; ?></td>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <td>-</td>
                        <?php endif; ?>

                        <td style="font-weight:bold; color:#0f5132;"><?= $cnt_h; ?></td>
                        <td style="font-weight:bold; color:#664d03;"><?= $cnt_s; ?></td>
                        <td style="font-weight:bold; color:#055160;"><?= $cnt_i; ?></td>
                        <td style="font-weight:bold; color:#842029;"><?= $cnt_a; ?></td>
                        <td style="font-weight:bold;"><?= ($cnt_h + $cnt_s + $cnt_i + $cnt_a); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="100%">Tidak ada data siswa.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>