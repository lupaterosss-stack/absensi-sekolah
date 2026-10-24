<?php
// guru/nilai.php
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

$pesan = "";
$tipe_pesan = "";

// Tangkap ID Kelas dan ID Mapel dari GET atau POST
$id_kelas = intval($_POST['id_kelas'] ?? ($_GET['id_kelas'] ?? 0));
$id_mapel = intval($_POST['id_mapel'] ?? ($_GET['id_mapel'] ?? 0));

// 1. PROSES SIMPAN / UPDATE PENGATURAN BOBOT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_bobot'])) {
    $bobot_kehadiran = intval($_POST['bobot_kehadiran']);
    $bobot_tugas     = intval($_POST['bobot_tugas']);
    $bobot_uts       = intval($_POST['bobot_uts']);
    $bobot_uas       = intval($_POST['bobot_uas']);

    $total_bobot = $bobot_kehadiran + $bobot_tugas + $bobot_uts + $bobot_uas;
    if ($total_bobot !== 100) {
        $pesan = "Gagal mengubah bobot! Total keseluruhan bobot harus tepat 100% (Saat ini: {$total_bobot}%)";
        $tipe_pesan = "danger";
    } else {
        $stmt_cb = mysqli_prepare($koneksi, "SELECT id_bobot FROM pengaturan_bobot WHERE id_guru = ?");
        mysqli_stmt_bind_param($stmt_cb, "i", $id_guru);
        mysqli_stmt_execute($stmt_cb);
        $res_cb = mysqli_stmt_get_result($stmt_cb);

        if (mysqli_num_rows($res_cb) > 0) {
            $stmt_ub = mysqli_prepare($koneksi, "UPDATE pengaturan_bobot SET bobot_kehadiran = ?, bobot_tugas = ?, bobot_uts = ?, bobot_uas = ? WHERE id_guru = ?");
            mysqli_stmt_bind_param($stmt_ub, "iiiii", $bobot_kehadiran, $bobot_tugas, $bobot_uts, $bobot_uas, $id_guru);
            mysqli_stmt_execute($stmt_ub);
            mysqli_stmt_close($stmt_ub);
        } else {
            $stmt_ib = mysqli_prepare($koneksi, "INSERT INTO pengaturan_bobot (id_guru, bobot_kehadiran, bobot_tugas, bobot_uts, bobot_uas) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt_ib, "iiiii", $id_guru, $bobot_kehadiran, $bobot_tugas, $bobot_uts, $bobot_uas);
            mysqli_stmt_execute($stmt_ib);
            mysqli_stmt_close($stmt_ib);
        }
        $pesan = "Pengaturan bobot nilai berhasil diperbarui!";
        $tipe_pesan = "success";
    }
}

// Ambil bobot aktif milik guru ini
$stmt_b = mysqli_prepare($koneksi, "SELECT bobot_kehadiran, bobot_tugas, bobot_uts, bobot_uas FROM pengaturan_bobot WHERE id_guru = ?");
mysqli_stmt_bind_param($stmt_b, "i", $id_guru);
mysqli_stmt_execute($stmt_b);
$res_b = mysqli_stmt_get_result($stmt_b);
$bobot_data = mysqli_fetch_assoc($res_b) ?? ['bobot_kehadiran' => 10, 'bobot_tugas' => 30, 'bobot_uts' => 30, 'bobot_uas' => 30];
mysqli_stmt_close($stmt_b);

$b_hadir = intval($bobot_data['bobot_kehadiran']);
$b_tugas = intval($bobot_data['bobot_tugas']);
$b_uts   = intval($bobot_data['bobot_uts']);
$b_uas   = intval($bobot_data['bobot_uas']);

// 2. PROSES TAMBAH TUGAS BARU
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_tugas'])) {
    $nama_tugas = trim($_POST['nama_tugas'] ?? '');
    $tanggal_tugas = $_POST['tanggal_tugas'] ?? date('Y-m-d');

    if (!empty($nama_tugas) && $id_kelas > 0 && $id_mapel > 0) {
        $stmt_ins_t = mysqli_prepare($koneksi, "INSERT INTO tugas (id_kelas, id_mapel, id_guru, nama_tugas, tanggal) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt_ins_t, "iiiss", $id_kelas, $id_mapel, $id_guru, $nama_tugas, $tanggal_tugas);
        if (mysqli_stmt_execute($stmt_ins_t)) {
            $pesan = "Tugas baru berhasil ditambahkan! Silakan masukkan nilai untuk tugas tersebut.";
            $tipe_pesan = "success";
        } else {
            $pesan = "Gagal menambahkan tugas baru.";
            $tipe_pesan = "danger";
        }
        mysqli_stmt_close($stmt_ins_t);
    } else {
        $pesan = "Nama tugas tidak boleh kosong.";
        $tipe_pesan = "warning";
    }
}

// 3. PROSES HAPUS TUGAS
if (isset($_GET['hapus_tugas'])) {
    $id_tugas_hapus = intval($_GET['hapus_tugas']);
    // Hapus nilai terkait tugas ini terlebih dahulu, lalu hapus tugasnya
    mysqli_query($koneksi, "DELETE FROM nilai_tugas WHERE id_tugas = $id_tugas_hapus");
    mysqli_query($koneksi, "DELETE FROM tugas WHERE id_tugas = $id_tugas_hapus AND id_guru = $id_guru");
    
    header("Location: nilai.php?id_kelas=$id_kelas&id_mapel=$id_mapel");
    exit();
}

// 4. PROSES SIMPAN / UPDATE NILAI SISWA (UTS, UAS, & SEMUA TUGAS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_nilai'])) {
    $nilai_tugas_input = $_POST['nilai_tugas'] ?? []; // Array [id_tugas][id_siswa] = nilai
    $nilai_uts_arr     = $_POST['nilai_uts'] ?? [];
    $nilai_uas_arr     = $_POST['nilai_uas'] ?? [];

    $berhasil = true;

    if ($id_mapel > 0 && $id_kelas > 0) {
        // A. Simpan nilai tugas per item tugas
        foreach ($nilai_tugas_input as $id_tugas => $siswa_nilai_list) {
            $id_tugas = intval($id_tugas);
            foreach ($siswa_nilai_list as $id_siswa => $nilai_val) {
                $id_siswa = intval($id_siswa);
                $val = ($nilai_val !== '') ? floatval($nilai_val) : null;

                // Cek apakah sudah ada nilai tugas ini
                $stmt_ct = mysqli_prepare($koneksi, "SELECT id_nilai_tugas FROM nilai_tugas WHERE id_tugas = ? AND id_siswa = ?");
                mysqli_stmt_bind_param($stmt_ct, "ii", $id_tugas, $id_siswa);
                mysqli_stmt_execute($stmt_ct);
                $res_ct = mysqli_stmt_get_result($stmt_ct);

                if (mysqli_num_rows($res_ct) > 0) {
                    $stmt_ut = mysqli_prepare($koneksi, "UPDATE nilai_tugas SET nilai = ? WHERE id_tugas = ? AND id_siswa = ?");
                    mysqli_stmt_bind_param($stmt_ut, "dii", $val, $id_tugas, $id_siswa);
                    mysqli_stmt_execute($stmt_ut);
                    mysqli_stmt_close($stmt_ut);
                } else {
                    $stmt_it = mysqli_prepare($koneksi, "INSERT INTO nilai_tugas (id_tugas, id_siswa, nilai) VALUES (?, ?, ?)");
                    mysqli_stmt_bind_param($stmt_it, "iid", $id_tugas, $id_siswa, $val);
                    mysqli_stmt_execute($stmt_it);
                    mysqli_stmt_close($stmt_it);
                }
                mysqli_stmt_close($stmt_ct);
            }
        }

        // B. Hitung rata-rata tugas per siswa untuk dimasukkan ke tabel utama nilai
        // Ambil daftar seluruh siswa di kelas ini
        $stmt_s_list = mysqli_prepare($koneksi, "SELECT id_siswa FROM siswa WHERE id_kelas = ?");
        mysqli_stmt_bind_param($stmt_s_list, "i", $id_kelas);
        mysqli_stmt_execute($stmt_s_list);
        $res_s_list = mysqli_stmt_get_result($stmt_s_list);
        $all_siswa_ids = [];
        while ($rs = mysqli_fetch_assoc($res_s_list)) {
            $all_siswa_ids[] = intval($rs['id_siswa']);
        }
        mysqli_stmt_close($stmt_s_list);

        // Ambil rekap poin kehadiran berbobot
        $kehadiran_persen_map = [];
        $stmt_abs_all = mysqli_prepare($koneksi, "
            SELECT a.id_siswa, 
                   SUM(CASE 
                       WHEN a.status = 'Hadir' THEN 1.0
                       WHEN a.status = 'Sakit' THEN 0.5
                       WHEN a.status = 'Izin'  THEN 0.25
                       ELSE 0.0
                   END) AS poin_kehadiran,
                   COUNT(a.id_absensi) AS total_pertemuan
            FROM absensi a
            JOIN jadwal j ON a.id_jadwal = j.id_jadwal
            WHERE j.id_mapel = ? AND j.id_kelas = ?
            GROUP BY a.id_siswa
        ");
        mysqli_stmt_bind_param($stmt_abs_all, "ii", $id_mapel, $id_kelas);
        mysqli_stmt_execute($stmt_abs_all);
        $res_abs_all = mysqli_stmt_get_result($stmt_abs_all);
        while ($row_abs = mysqli_fetch_assoc($res_abs_all)) {
            $tot  = intval($row_abs['total_pertemuan']);
            $poin = floatval($row_abs['poin_kehadiran']);
            $persen = ($tot > 0) ? ($poin / $tot) * 100 : 100.0;
            $kehadiran_persen_map[$row_abs['id_siswa']] = $persen;
        }
        mysqli_stmt_close($stmt_abs_all);

        // Proses kalkulasi nilai akhir tiap siswa
        foreach ($all_siswa_ids as $id_siswa) {
            // Hitung rata-rata tugas siswa ini pada mapel ini
            $stmt_avg = mysqli_prepare($koneksi, "
                SELECT AVG(nt.nilai) as rata_tugas 
                FROM nilai_tugas nt 
                JOIN tugas t ON nt.id_tugas = t.id_tugas 
                WHERE t.id_mapel = ? AND t.id_kelas = ? AND nt.id_siswa = ? AND nt.nilai IS NOT NULL
            ");
            mysqli_stmt_bind_param($stmt_avg, "iii", $id_mapel, $id_kelas, $id_siswa);
            mysqli_stmt_execute($stmt_avg);
            $res_avg = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_avg));
            $tugas_rata2 = floatval($res_avg['rata_tugas'] ?? 0);
            mysqli_stmt_close($stmt_avg);

            $uts = floatval($nilai_uts_arr[$id_siswa] ?? 0);
            $uas = floatval($nilai_uas_arr[$id_siswa] ?? 0);
            $persen_hadir = $kehadiran_persen_map[$id_siswa] ?? 100.00;

            // Hitung Nilai Akhir Berdasarkan 4 Komponen Bobot
            $nilai_akhir = ($persen_hadir * ($b_hadir / 100)) + 
                           ($tugas_rata2 * ($b_tugas / 100)) + 
                           ($uts * ($b_uts / 100)) + 
                           ($uas * ($b_uas / 100));

            // Simpan ke tabel nilai utama
            $stmt_cek_n = mysqli_prepare($koneksi, "SELECT id_nilai FROM nilai WHERE id_siswa = ? AND id_mapel = ?");
            mysqli_stmt_bind_param($stmt_cek_n, "ii", $id_siswa, $id_mapel);
            mysqli_stmt_execute($stmt_cek_n);
            $res_cek_n = mysqli_stmt_get_result($stmt_cek_n);

            if (mysqli_num_rows($res_cek_n) > 0) {
                $stmt_upd_n = mysqli_prepare($koneksi, "UPDATE nilai SET nilai_tugas = ?, nilai_uts = ?, nilai_uas = ?, nilai_akhir = ? WHERE id_siswa = ? AND id_mapel = ?");
                mysqli_stmt_bind_param($stmt_upd_n, "ddddii", $tugas_rata2, $uts, $uas, $nilai_akhir, $id_siswa, $id_mapel);
                mysqli_stmt_execute($stmt_upd_n);
                mysqli_stmt_close($stmt_upd_n);
            } else {
                $stmt_ins_n = mysqli_prepare($koneksi, "INSERT INTO nilai (id_siswa, id_mapel, nilai_tugas, nilai_uts, nilai_uas, nilai_akhir) VALUES (?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt_ins_n, "iidddd", $id_siswa, $id_mapel, $tugas_rata2, $uts, $uas, $nilai_akhir);
                mysqli_stmt_execute($stmt_ins_n);
                mysqli_stmt_close($stmt_ins_n);
            }
            mysqli_stmt_close($stmt_cek_n);
        }

        if ($berhasil) {
            $pesan = "Semua nilai tugas, UTS, UAS, dan nilai akhir berhasil disimpan & dihitung!";
            $tipe_pesan = "success";
        }
    } else {
        $pesan = "Pilih kelas dan mata pelajaran terlebih dahulu.";
        $tipe_pesan = "warning";
    }
}

// Ambil daftar kelas dan mapel yang diajar guru
$stmt_jadwal = mysqli_prepare($koneksi, "SELECT DISTINCT k.id_kelas, k.nama_kelas, m.id_mapel, m.nama_mapel 
                                       FROM jadwal j 
                                       JOIN kelas k ON j.id_kelas = k.id_kelas 
                                       JOIN mapel m ON j.id_mapel = m.id_mapel 
                                       WHERE j.id_guru = ? 
                                       ORDER BY k.nama_kelas ASC, m.nama_mapel ASC");
mysqli_stmt_bind_param($stmt_jadwal, "i", $id_guru);
mysqli_stmt_execute($stmt_jadwal);
$list_filter = mysqli_fetch_all(mysqli_stmt_get_result($stmt_jadwal), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_jadwal);

// Ambil Data Siswa, Daftar Tugas, dan Nilai jika kelas & mapel dipilih
$siswa_list = [];
$daftar_tugas = [];
$info_kelas = "";
$info_mapel = "";
$kehadiran_info_map = [];

if ($id_kelas > 0 && $id_mapel > 0) {
    $stmt_info = mysqli_prepare($koneksi, "SELECT k.nama_kelas, m.nama_mapel FROM kelas k, mapel m WHERE k.id_kelas = ? AND m.id_mapel = ?");
    mysqli_stmt_bind_param($stmt_info, "ii", $id_kelas, $id_mapel);
    mysqli_stmt_execute($stmt_info);
    $res_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_info));
    $info_kelas = $res_info['nama_kelas'] ?? '';
    $info_mapel = $res_info['nama_mapel'] ?? '';
    mysqli_stmt_close($stmt_info);

    // Ambil daftar tugas yang dibuat guru untuk kelas & mapel ini
    $stmt_dt = mysqli_prepare($koneksi, "SELECT id_tugas, nama_tugas, tanggal FROM tugas WHERE id_kelas = ? AND id_mapel = ? AND id_guru = ? ORDER BY tanggal ASC, id_tugas ASC");
    mysqli_stmt_bind_param($stmt_dt, "iii", $id_kelas, $id_mapel, $id_guru);
    mysqli_stmt_execute($stmt_dt);
    $daftar_tugas = mysqli_fetch_all(mysqli_stmt_get_result($stmt_dt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_dt);

    // Ambil rekap poin kehadiran
    $stmt_abs_inf = mysqli_prepare($koneksi, "
        SELECT a.id_siswa, 
               SUM(CASE 
                   WHEN a.status = 'Hadir' THEN 1.0
                   WHEN a.status = 'Sakit' THEN 0.5
                   WHEN a.status = 'Izin'  THEN 0.25
                   ELSE 0.0
               END) AS poin_kehadiran,
               COUNT(a.id_absensi) AS total_pertemuan
        FROM absensi a
        JOIN jadwal j ON a.id_jadwal = j.id_jadwal
        WHERE j.id_mapel = ? AND j.id_kelas = ?
        GROUP BY a.id_siswa
    ");
    mysqli_stmt_bind_param($stmt_abs_inf, "ii", $id_mapel, $id_kelas);
    mysqli_stmt_execute($stmt_abs_inf);
    $res_abs_inf = mysqli_stmt_get_result($stmt_abs_inf);
    while ($r_abs = mysqli_fetch_assoc($res_abs_inf)) {
        $tot  = intval($r_abs['total_pertemuan']);
        $poin = floatval($r_abs['poin_kehadiran']);
        $persen = ($tot > 0) ? ($poin / $tot) * 100 : 100.0;
        $kehadiran_info_map[$r_abs['id_siswa']] = [
            'persen' => $persen,
            'poin'   => $poin,
            'total'  => $tot
        ];
    }
    mysqli_stmt_close($stmt_abs_inf);

    // Ambil data siswa & nilai utama (UTS, UAS)
    $stmt_s = mysqli_prepare($koneksi, "SELECT s.id_siswa, s.nisn, s.nama_lengkap, n.nilai_uts, n.nilai_uas, n.nilai_akhir FROM siswa s LEFT JOIN nilai n ON s.id_siswa = n.id_siswa AND n.id_mapel = ? WHERE s.id_kelas = ? ORDER BY s.nama_lengkap ASC");
    mysqli_stmt_bind_param($stmt_s, "ii", $id_mapel, $id_kelas);
    mysqli_stmt_execute($stmt_s);
    $siswa_list = mysqli_fetch_all(mysqli_stmt_get_result($stmt_s), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_s);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Nilai Siswa - Portal Guru</title>
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
                    <li class="nav-item"><a class="nav-link active" href="nilai.php">Input Nilai</a></li>
                </ul>
                <div class="d-flex align-items-center">
                    <a href="../logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <!-- Header & Tombol Pengaturan Bobot -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <a href="index.php" class="btn btn-outline-secondary btn-sm me-3"><i class="bi bi-arrow-left"></i> Kembali</a>
                <h4 class="fw-bold mb-0"><i class="bi bi-pencil-square text-success me-2"></i>Input & Kelola Nilai Siswa</h4>
            </div>
            <button class="btn btn-outline-primary btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalBobot">
                <i class="bi bi-sliders me-1"></i> Atur Bobot (Hadir: <?= $b_hadir; ?>%, Tugas: <?= $b_tugas; ?>%, UTS: <?= $b_uts; ?>%, UAS: <?= $b_uas; ?>%)
            </button>
        </div>

        <?php if (!empty($pesan)): ?>
            <div class="alert alert-<?= $tipe_pesan; ?> alert-dismissible fade show shadow-sm mb-4" role="alert">
                <?= $pesan; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Form Filter Kelas & Mapel -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <form method="GET" action="nilai.php" class="row g-3 align-items-end">
                    <div class="col-md-9">
                        <label for="filter_opt" class="form-label small fw-bold">Pilih Kelas & Mata Pelajaran</label>
                        <select id="filter_opt" class="form-select" onchange="splitValue(this.value)" required>
                            <option value="">-- Pilih Kelas & Mapel --</option>
                            <?php foreach ($list_filter as $f): ?>
                                <?php $val = $f['id_kelas'] . '|' . $f['id_mapel']; ?>
                                <option value="<?= $val; ?>" <?= ($id_kelas == $f['id_kelas'] && $id_mapel == $f['id_mapel']) ? 'selected' : ''; ?>>
                                    Kelas: <?= $f['nama_kelas']; ?> — Mapel: <?= $f['nama_mapel']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="id_kelas" id="id_kelas" value="<?= $id_kelas ? $id_kelas : ''; ?>">
                        <input type="hidden" name="id_mapel" id="id_mapel" value="<?= $id_mapel ? $id_mapel : ''; ?>">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="bi bi-search me-1"></i> Tampilkan Siswa</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Bagian Manajemen & Input Nilai -->
        <?php if ($id_kelas > 0 && $id_mapel > 0): ?>
            <!-- Tombol Tambah Tugas Baru -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-journal-text me-2 text-primary"></i>Komponen Tugas Siswa</h5>
                <button class="btn btn-success btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalTambahTugas">
                    <i class="bi bi-plus-circle me-1"></i> Tambah Tugas Baru
                </button>
            </div>

            <form method="POST" action="nilai.php?id_kelas=<?= $id_kelas; ?>&id_mapel=<?= $id_mapel; ?>">
                <input type="hidden" name="id_kelas" value="<?= $id_kelas; ?>">
                <input type="hidden" name="id_mapel" value="<?= $id_mapel; ?>">

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted small mb-0">Kelas: <strong><?= htmlspecialchars($info_kelas); ?></strong> | Mapel: <strong><?= htmlspecialchars($info_mapel); ?></strong></p>
                        </div>
                        <button type="submit" name="simpan_nilai" class="btn btn-success fw-bold"><i class="bi bi-save me-1"></i> Simpan Semua Nilai</button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered align-middle mb-0">
                                <thead class="table-light text-center">
                                    <tr>
                                        <th class="ps-3" width="5%" rowspan="2">No</th>
                                        <th width="12%" rowspan="2">NISN</th>
                                        <th width="25%" rowspan="2" class="text-start">Nama Siswa</th>
                                        <th width="10%" rowspan="2">Kehadiran</th>
                                        
                                        <!-- Kolom Dinamis Daftar Tugas -->
                                        <?php if (!empty($daftar_tugas)): ?>
                                            <th colspan="<?= count($daftar_tugas); ?>">Daftar Tugas (Multi-Tugas)</th>
                                        <?php else: ?>
                                            <th width="15%">Nilai Tugas</th>
                                        <?php endif; ?>

                                        <th width="10%" rowspan="2">UTS</th>
                                        <th width="10%" rowspan="2">UAS</th>
                                        <th width="8%" rowspan="2">Nilai Akhir</th>
                                    </tr>
                                    <tr>
                                        <?php if (!empty($daftar_tugas)): ?>
                                            <?php foreach ($daftar_tugas as $dt): ?>
                                                <th style="min-width: 120px; font-size: 12px;">
                                                    <?= htmlspecialchars($dt['nama_tugas']); ?>
                                                    <br>
                                                    <a href="nilai.php?id_kelas=<?= $id_kelas; ?>&id_mapel=<?= $id_mapel; ?>&hapus_tugas=<?= $dt['id_tugas']; ?>" class="text-danger" onclick="return confirm('Hapus tugas ini beserta nilainya?');" title="Hapus Tugas"><i class="bi bi-trash"></i></a>
                                                </th>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <th class="text-muted small">Belum ada tugas dibuat</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($siswa_list)): ?>
                                        <?php $no = 1; foreach ($siswa_list as $s): ?>
                                            <?php 
                                                $id_s = $s['id_siswa'];
                                                $n_uts = $s['nilai_uts'] ?? '';
                                                $n_uas = $s['nilai_uas'] ?? '';
                                                $n_akhir = $s['nilai_akhir'] ?? '-';
                                                
                                                $info_kehadiran = $kehadiran_info_map[$id_s] ?? ['persen' => 100, 'poin' => 0, 'total' => 0];
                                            ?>
                                            <tr>
                                                <td class="ps-3 text-center"><?= $no++; ?></td>
                                                <td class="text-center"><code><?= htmlspecialchars($s['nisn'] ?? '-'); ?></code></td>
                                                <td><strong><?= htmlspecialchars($s['nama_lengkap']); ?></strong></td>
                                                <td class="text-center">
                                                    <span class="badge bg-info text-dark"><?= number_format($info_kehadiran['persen'], 1); ?>%</span>
                                                </td>

                                                <!-- Input Nilai Berdasarkan Tugas Yang Ada -->
                                                <?php if (!empty($daftar_tugas)): ?>
                                                    <?php foreach ($daftar_tugas as $dt): ?>
                                                        <?php
                                                            // Ambil nilai tugas spesifik siswa ini untuk tugas tersebut
                                                            $stmt_nv = mysqli_prepare($koneksi, "SELECT nilai FROM nilai_tugas WHERE id_tugas = ? AND id_siswa = ?");
                                                            mysqli_stmt_bind_param($stmt_nv, "ii", $dt['id_tugas'], $id_s);
                                                            mysqli_stmt_execute($stmt_nv);
                                                            $res_nv = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_nv));
                                                            $val_tugas_item = $res_nv['nilai'] ?? '';
                                                            mysqli_stmt_close($stmt_nv);
                                                        ?>
                                                        <td class="text-center">
                                                            <input type="number" step="0.01" min="0" max="100" name="nilai_tugas[<?= $dt['id_tugas']; ?>][<?= $id_s; ?>]" class="form-control form-control-sm text-center" value="<?= $val_tugas_item; ?>" placeholder="0-100">
                                                        </td>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <td class="text-center text-muted small">-</td>
                                                <?php endif; ?>

                                                <td>
                                                    <input type="number" step="0.01" min="0" max="100" name="nilai_uts[<?= $id_s; ?>]" class="form-control form-control-sm text-center" value="<?= $n_uts; ?>" placeholder="0-100">
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" max="100" name="nilai_uas[<?= $id_s; ?>]" class="form-control form-control-sm text-center" value="<?= $n_uas; ?>" placeholder="0-100">
                                                </td>
                                                <td class="text-center fw-bold text-primary">
                                                    <?= is_numeric($n_akhir) ? number_format($n_akhir, 2) : $n_akhir; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="10" class="text-center py-4 text-muted">Belum ada siswa di kelas ini.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php if (!empty($siswa_list)): ?>
                        <div class="card-footer bg-white py-3 text-end">
                            <button type="submit" name="simpan_nilai" class="btn btn-success fw-bold"><i class="bi bi-save me-1"></i> Simpan Semua Nilai</button>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        <?php else: ?>
            <div class="card shadow-sm border-0 text-center py-5">
                <div class="card-body text-muted">
                    <i class="bi bi-file-earmark-spreadsheet fs-1 d-block mb-3 text-secondary opacity-50"></i>
                    <p class="mb-0">Silakan pilih kelas dan mata pelajaran pada form di atas, lalu klik <strong>Tampilkan Siswa</strong>.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Tambah Tugas Baru -->
    <div class="modal fade" id="modalTambahTugas" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="nilai.php?id_kelas=<?= $id_kelas; ?>&id_mapel=<?= $id_mapel; ?>">
                    <input type="hidden" name="id_kelas" value="<?= $id_kelas; ?>">
                    <input type="hidden" name="id_mapel" value="<?= $id_mapel; ?>">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Tambah Kolom Tugas Baru</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nama / Judul Tugas</label>
                            <input type="text" name="nama_tugas" class="form-control" placeholder="Contoh: Tugas 1 - Aljabar, PR Bab 2" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Tanggal Pemberian Tugas</label>
                            <input type="date" name="tanggal_tugas" class="form-control" value="<?= date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="tambah_tugas" class="btn btn-success fw-bold">Tambah Tugas</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Pengaturan Bobot -->
    <div class="modal fade" id="modalBobot" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="nilai.php?id_kelas=<?= $id_kelas; ?>&id_mapel=<?= $id_mapel; ?>">
                    <input type="hidden" name="id_kelas" value="<?= $id_kelas; ?>">
                    <input type="hidden" name="id_mapel" value="<?= $id_mapel; ?>">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold"><i class="bi bi-sliders me-2"></i>Pengaturan Bobot Penilaian</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-3">Atur persentase bobot nilai komponen. Total keseluruhan persentase wajib tepat <strong>100%</strong>.</p>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Bobot Kehadiran (%)</label>
                            <input type="number" name="bobot_kehadiran" class="form-control" value="<?= $b_hadir; ?>" min="0" max="100" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Bobot Nilai Tugas (Rata-rata) (%)</label>
                            <input type="number" name="bobot_tugas" class="form-control" value="<?= $b_tugas; ?>" min="0" max="100" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Bobot Nilai UTS (%)</label>
                            <input type="number" name="bobot_uts" class="form-control" value="<?= $b_uts; ?>" min="0" max="100" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Bobot Nilai UAS (%)</label>
                            <input type="number" name="bobot_uas" class="form-control" value="<?= $b_uas; ?>" min="0" max="100" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="simpan_bobot" class="btn btn-primary fw-bold">Simpan Bobot</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function splitValue(val) {
            if (val) {
                const parts = val.split('|');
                document.getElementById('id_kelas').value = parts[0];
                document.getElementById('id_mapel').value = parts[1];
            } else {
                document.getElementById('id_kelas').value = '';
                document.getElementById('id_mapel').value = '';
            }
        }
    </script>
</body>
</html>