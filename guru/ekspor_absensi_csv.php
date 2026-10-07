<?php
// guru/ekspor_absensi_csv.php
session_start();
require_once '../config/koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'guru') {
    exit("Akses ditolak.");
}

$id_jadwal = $_GET['id_jadwal'] ?? '';
$bulan     = $_GET['bulan'] ?? date('m');
$tahun     = $_GET['tahun'] ?? date('Y');

if (empty($id_jadwal)) {
    exit("Jadwal tidak dipilih.");
}

// 1. Ambil Detail Kelas & Mapel
$stmt_det = mysqli_prepare($koneksi, "
    SELECT k.nama_kelas, m.nama_mapel
    FROM jadwal j
    LEFT JOIN kelas k ON j.id_kelas = k.id_kelas
    LEFT JOIN mapel m ON j.id_mapel = m.id_mapel
    WHERE j.id_jadwal = ?
");
mysqli_stmt_bind_param($stmt_det, "i", $id_jadwal);
mysqli_stmt_execute($stmt_det);
$detail = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_det));
mysqli_stmt_close($stmt_det);

$nama_kelas = $detail['nama_kelas'] ?? 'Kelas';
$nama_mapel = $detail['nama_mapel'] ?? 'Mapel';

// 2. Set Header Response HTTP untuk Download CSV
$filename = "Rekap_Absensi_" . str_replace(' ', '_', $nama_kelas) . "_" . $bulan . "_" . $tahun . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Tambahkan BOM (Byte Order Mark) agar Excel membaca UTF-8/Karakter khusus dengan benar
fputs($output, "\xEF\xBB\xBF");

// 3. Tulis Baris Judul & Header Tabel
fputcsv($output, ["REKAPITULASI ABSENSI SISWA"]);
fputcsv($output, ["Kelas:", $nama_kelas]);
fputcsv($output, ["Mata Pelajaran:", $nama_mapel]);
fputcsv($output, ["Periode:", $bulan . "/" . $tahun]);
fputcsv($output, []); // Baris kosong
fputcsv($output, ['No', 'NISN', 'Nama Siswa', 'Hadir', 'Izin', 'Sakit', 'Alpa', 'Total Pertemuan']);

// 4. Query & Tulis Data Absensi
$query_rekap = "
    SELECT 
        s.nisn, 
        s.nama_lengkap,
        COUNT(CASE WHEN a.status = 'Hadir' THEN 1 END) AS total_hadir,
        COUNT(CASE WHEN a.status = 'Izin' THEN 1 END) AS total_izin,
        COUNT(CASE WHEN a.status = 'Sakit' THEN 1 END) AS total_sakit,
        COUNT(CASE WHEN a.status = 'Alpa' THEN 1 END) AS total_alpa,
        COUNT(a.id_absensi) AS total_pertemuan
    FROM siswa s
    LEFT JOIN absensi a ON s.id_siswa = a.id_siswa 
        AND a.id_jadwal = ? 
        AND MONTH(a.tanggal) = ? 
        AND YEAR(a.tanggal) = ?
    WHERE s.id_kelas = (SELECT id_kelas FROM jadwal WHERE id_jadwal = ?)
    GROUP BY s.id_siswa
    ORDER BY s.nama_lengkap ASC
";

$stmt_r = mysqli_prepare($koneksi, $query_rekap);
mysqli_stmt_bind_param($stmt_r, "iiii", $id_jadwal, $bulan, $tahun, $id_jadwal);
mysqli_stmt_execute($stmt_r);
$result = mysqli_stmt_get_result($stmt_r);

$no = 1;
while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($output, [
        $no++,
        "'" . $row['nisn'], // Tanda petik tunggal agar NISN tidak berubah format scientific
        $row['nama_lengkap'],
        $row['total_hadir'],
        $row['total_izin'],
        $row['total_sakit'],
        $row['total_alpa'],
        $row['total_pertemuan']
    ]);
}

mysqli_stmt_close($stmt_r);
fclose($output);
exit();