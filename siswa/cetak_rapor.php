<?php
// siswa/cetak_rapor.php
session_start();
require_once '../config/koneksi.php';

// Proteksi Halaman: Cek Sesi (Siswa, Guru, atau Admin)
if (!isset($_SESSION['id_user'])) {
    header("Location: ../login.php");
    exit();
}

$id_user = $_SESSION['id_user'];
$role = $_SESSION['role'];

// Jika login sebagai siswa, ambil id_siswa berdasarkan id_user
if ($role === 'siswa') {
    $stmt_siswa = mysqli_prepare($koneksi, "SELECT id_siswa FROM siswa WHERE id_user = ?");
    mysqli_stmt_bind_param($stmt_siswa, "i", $id_user);
    mysqli_stmt_execute($stmt_siswa);
    $res_siswa = mysqli_stmt_get_result($stmt_siswa);
    $data_siswa_user = mysqli_fetch_assoc($res_siswa);
    $id_siswa = $data_siswa_user['id_siswa'] ?? 0;
} else {
    // Jika guru/admin mencetak milik siswa tertentu via parameter URL ?id_siswa=X
    $id_siswa = isset($_GET['id_siswa']) ? intval($_GET['id_siswa']) : 0;
}

if ($id_siswa == 0) {
    echo "Data siswa tidak ditemukan.";
    exit();
}

// Ambil Data Detail Siswa & Kelas
$sql_detail = "
    SELECT s.nis, s.nama_lengkap, s.jenis_kelamin, k.nama_kelas, k.tingkat 
    FROM siswa s 
    LEFT JOIN kelas k ON s.id_kelas = k.id_kelas 
    WHERE s.id_siswa = ?
";
$stmt_detail = mysqli_prepare($koneksi, $sql_detail);
mysqli_stmt_bind_param($stmt_detail, "i", $id_siswa);
mysqli_stmt_execute($stmt_detail);
$siswa = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_detail));

if (!$siswa) {
    echo "Siswa tidak ditemukan.";
    exit();
}

// Ambil Data Nilai Mata Pelajaran Siswa
$sql_nilai = "
    SELECT m.nama_mapel, n.nilai_tugas, n.nilai_uts, n.nilai_uas, n.nilai_akhir, n.predikat 
    FROM nilai n 
    JOIN mapel m ON n.id_mapel = m.id_mapel 
    WHERE n.id_siswa = ? 
    ORDER BY m.nama_mapel ASC
";
$stmt_nilai = mysqli_prepare($koneksi, $sql_nilai);
mysqli_stmt_bind_param($stmt_nilai, "i", $id_siswa);
mysqli_stmt_execute($stmt_nilai);
$query_nilai = mysqli_stmt_get_result($stmt_nilai);

// Ambil Rekap Absensi Siswa
$sql_absensi = "
    SELECT status, COUNT(*) as jumlah 
    FROM absensi 
    WHERE id_siswa = ? 
    GROUP BY status
";
$stmt_absen = mysqli_prepare($koneksi, $sql_absensi);
mysqli_stmt_bind_param($stmt_absen, "i", $id_siswa);
mysqli_stmt_execute($stmt_absen);
$res_absen = mysqli_stmt_get_result($stmt_absen);

$rekap_absen = [
    'Hadir' => 0,
    'Izin' => 0,
    'Sakit' => 0,
    'Alpha' => 0
];
while ($row_absen = mysqli_fetch_assoc($res_absen)) {
    $st = $row_absen['status'];
    if (isset($rekap_absen[$st])) {
        $rekap_absen[$st] = $row_absen['jumlah'];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Hasil Belajar (Rapor) - <?= htmlspecialchars($siswa['nama_lengkap']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            color: #000;
            background-color: #fff;
        }
        .header-kop {
            border-bottom: 3px double #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .table-bordered th, .table-bordered td {
            border: 1px solid #000 !important;
            vertical-align: middle;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 0;
                margin: 0;
            }
            @page {
                size: A4;
                margin: 1.5cm;
            }
        }
    </style>
</head>
<body onload="window.print()">

    <!-- Tombol Aksi (Tidak Tampil Saat Dicetak) -->
    <div class="container my-3 no-print">
        <div class="d-flex justify-content-between align-items-center bg-light p-3 rounded border">
            <span><i class="bi bi-info-circle me-1"></i> Klik tombol cetak jika dialog cetak tidak otomatis terbuka.</span>
            <div>
                <button onclick="window.print()" class="btn btn-primary btn-sm me-2">
                    <i class="bi bi-printer me-1"></i> Cetak PDF
                </button>
                <a href="nilai.php" class="btn btn-secondary btn-sm">Kembali</a>
            </div>
        </div>
    </div>

    <!-- Lembar Rapor A4 -->
    <div class="container my-4">
        
        <!-- Kop Surat Sekolah -->
        <div class="text-center header-kop">
            <h3 class="fw-bold mb-0" style="letter-spacing: 1px;">SEKOLAH MENENGAH ATAS (SMA) NEGERI 1</h3>
            <p class="mb-0 fs-6">Jl. Pendidikan No. 123, Kota Sekolah, Indonesia</p>
            <p class="mb-0 small text-muted">Email: info@smanegeri1.sch.id | Web: www.smanegeri1.sch.id</p>
        </div>

        <h4 class="text-center fw-bold mb-4 text-uppercase" style="text-decoration: underline;">LAPORAN HASIL BELAJAR (RAPOR)</h4>

        <!-- Identitas Siswa -->
        <table class="table table-borderless mb-4" style="width: 100%;">
            <tr>
                <td style="width: 18%; padding: 3px 0;"><strong>Nama Siswa</strong></td>
                <td style="width: 2%; padding: 3px 0;">:</td>
                <td style="width: 30%; padding: 3px 0;"><?= htmlspecialchars($siswa['nama_lengkap']); ?></td>

                <td style="width: 18%; padding: 3px 0;"><strong>Kelas</strong></td>
                <td style="width: 2%; padding: 3px 0;">:</td>
                <td style="width: 30%; padding: 3px 0;"><?= htmlspecialchars($siswa['nama_kelas'] ?? '-'); ?></td>
            </tr>
            <tr>
                <td style="padding: 3px 0;"><strong>NIS</strong></td>
                <td style="padding: 3px 0;">:</td>
                <td style="padding: 3px 0;"><?= htmlspecialchars($siswa['nis']); ?></td>

                <td style="padding: 3px 0;"><strong>Semester / TA</strong></td>
                <td style="padding: 3px 0;">:</td>
                <td style="padding: 3px 0;">Ganjil / <?= date('Y'); ?></td>
            </tr>
        </table>

        <!-- Tabel Nilai Akademik -->
        <h6 class="fw-bold mb-2">A. Capaian Akademik</h6>
        <table class="table table-bordered align-middle text-center mb-4">
            <thead class="table-light">
                <tr>
                    <th style="width: 5%;">No</th>
                    <th style="width: 35%; text-align: left;">Mata Pelajaran</th>
                    <th style="width: 12%;">Tugas</th>
                    <th style="width: 12%;">UTS</th>
                    <th style="width: 12%;">UAS</th>
                    <th style="width: 12%;">Nilai Akhir</th>
                    <th style="width: 12%;">Predikat</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                $total_nilai = 0;
                $jumlah_mapel = mysqli_num_rows($query_nilai);

                if ($jumlah_mapel > 0):
                    while ($row = mysqli_fetch_assoc($query_nilai)):
                        $total_nilai += $row['nilai_akhir'];
                ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td style="text-align: left;"><?= htmlspecialchars($row['nama_mapel']); ?></td>
                        <td><?= number_format($row['nilai_tugas'], 1); ?></td>
                        <td><?= number_format($row['nilai_uts'], 1); ?></td>
                        <td><?= number_format($row['nilai_uas'], 1); ?></td>
                        <td class="fw-bold"><?= number_format($row['nilai_akhir'], 1); ?></td>
                        <td><span class="badge bg-secondary"><?= $row['predikat']; ?></span></td>
                    </tr>
                <?php 
                    endwhile; 
                    $rata_rata = $jumlah_mapel > 0 ? ($total_nilai / $jumlah_mapel) : 0;
                ?>
                    <tr class="fw-bold table-light">
                        <td colspan="5" style="text-align: right;">Rata-Rata Nilai Akhir</td>
                        <td><?= number_format($rata_rata, 1); ?></td>
                        <td>-</td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-muted">Belum ada data nilai yang diinputkan.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Tabel Rekap Kehadiran -->
        <div class="row mb-5">
            <div class="col-6">
                <h6 class="fw-bold mb-2">B. Kehadiran / Presensi</h6>
                <table class="table table-bordered text-center mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Hadir</th>
                            <th>Sakit</th>
                            <th>Izin</th>
                            <th>Tanpa Keterangan (Alpha)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?= $rekap_absen['Hadir']; ?> hari</td>
                            <td><?= $rekap_absen['Sakit']; ?> hari</td>
                            <td><?= $rekap_absen['Izin']; ?> hari</td>
                            <td><?= $rekap_absen['Alpha']; ?> hari</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tanda Tangan -->
        <div class="row text-center mt-5">
            <div class="col-4">
                <p class="mb-5">Orang Tua / Wali Murid,</p>
                <p class="mt-4 fw-bold">( .................................... )</p>
            </div>
            <div class="col-4"></div>
            <div class="col-4">
                <p class="mb-0">Kota Sekolah, <?= date('d F Y'); ?></p>
                <p class="mb-5">Wali Kelas,</p>
                <p class="mt-4 fw-bold">( .................................... )</p>
            </div>
        </div>

    </div>

</body>
</html>