<?php
// guru/rekap_absensi.php
session_start();
require_once '../config/koneksi.php';

// Proteksi Halaman: Cek Sesi & Role Guru
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'guru') {
    header("Location: ../login.php");
    exit();
}

$id_user = $_SESSION['id_user'];

// ==========================================================
// AMBIL ID GURU
// ==========================================================
$stmt_guru = mysqli_prepare(
    $koneksi,
    "SELECT id_guru FROM guru WHERE id_user = ?"
);

mysqli_stmt_bind_param($stmt_guru, "i", $id_user);
mysqli_stmt_execute($stmt_guru);

$guru = mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmt_guru)
);

$id_guru = $guru['id_guru'] ?? 0;

mysqli_stmt_close($stmt_guru);

// ==========================================================
// AMBIL DAFTAR KELAS & MAPEL YANG DIAJAR GURU
// ==========================================================
$stmt_jadwal = mysqli_prepare(
    $koneksi,
    "SELECT DISTINCT
        k.id_kelas,
        k.nama_kelas,
        m.id_mapel,
        m.nama_mapel
     FROM jadwal j
     JOIN kelas k ON j.id_kelas = k.id_kelas
     JOIN mapel m ON j.id_mapel = m.id_mapel
     WHERE j.id_guru = ?
     ORDER BY k.nama_kelas ASC, m.nama_mapel ASC"
);

mysqli_stmt_bind_param($stmt_jadwal, "i", $id_guru);
mysqli_stmt_execute($stmt_jadwal);

$list_filter = mysqli_fetch_all(
    mysqli_stmt_get_result($stmt_jadwal),
    MYSQLI_ASSOC
);

mysqli_stmt_close($stmt_jadwal);

// ==========================================================
// FILTER
// ==========================================================
$selected_filter = $_GET['filter_jadwal'] ?? '';

$semester = $_GET['semester'] ?? 'Ganjil';

$tahun_ajaran = $_GET['tahun_ajaran']
    ?? date('Y') . '/' . (date('Y') + 1);

$rekap_data = [];
$info_kbm = '';


// ==========================================================
// PROSES REKAP
// ==========================================================
if (!empty($selected_filter)) {

    // Format filter:
    // id_kelas|id_mapel
    $parts = explode('|', $selected_filter);

    if (count($parts) == 2) {

        $id_kelas_filter = intval($parts[0]);
        $id_mapel_filter = intval($parts[1]);

        // ==================================================
        // AMBIL INFO KELAS & MAPEL
        // ==================================================
        $stmt_info = mysqli_prepare(
            $koneksi,
            "SELECT
                k.nama_kelas,
                m.nama_mapel
             FROM kelas k
             CROSS JOIN mapel m
             WHERE k.id_kelas = ?
               AND m.id_mapel = ?"
        );

        mysqli_stmt_bind_param(
            $stmt_info,
            "ii",
            $id_kelas_filter,
            $id_mapel_filter
        );

        mysqli_stmt_execute($stmt_info);

        $res_info = mysqli_fetch_assoc(
            mysqli_stmt_get_result($stmt_info)
        );

        if ($res_info) {
            $info_kbm =
                "Kelas: <strong>" .
                htmlspecialchars($res_info['nama_kelas']) .
                "</strong> | Mapel: <strong>" .
                htmlspecialchars($res_info['nama_mapel']) .
                "</strong>";
        }

        mysqli_stmt_close($stmt_info);


        // ==================================================
        // AMBIL DAFTAR SISWA
        // ==================================================
        $stmt_siswa = mysqli_prepare(
            $koneksi,
            "SELECT
                id_siswa,
                nisn,
                nama_lengkap
             FROM siswa
             WHERE id_kelas = ?
             ORDER BY nama_lengkap ASC"
        );

        mysqli_stmt_bind_param(
            $stmt_siswa,
            "i",
            $id_kelas_filter
        );

        mysqli_stmt_execute($stmt_siswa);

        $result_siswa = mysqli_stmt_get_result($stmt_siswa);

        $siswas = mysqli_fetch_all(
            $result_siswa,
            MYSQLI_ASSOC
        );

        mysqli_stmt_close($stmt_siswa);


        // ==================================================
        // BULAN SEMESTER
        // ==================================================
        $bulan_awal = ($semester == 'Ganjil')
            ? ['07', '08', '09', '10', '11', '12']
            : ['01', '02', '03', '04', '05', '06'];

        $tahun_parts = explode('/', $tahun_ajaran);

        $tahun_dasar = intval($tahun_parts[0]);

        $tahun_genap = isset($tahun_parts[1])
            ? intval($tahun_parts[1])
            : ($tahun_dasar + 1);


        // ==================================================
        // HITUNG REKAP SETIAP SISWA
        // ==================================================
        foreach ($siswas as $s) {

            $id_s = intval($s['id_siswa']);

            $hadir = 0;
            $izin = 0;
            $sakit = 0;
            $alpa = 0;


            // ==============================================
            // PER BULAN
            // ==============================================
            foreach ($bulan_awal as $bln) {

                // Semester Ganjil:
                // Juli - Desember menggunakan tahun dasar
                //
                // Semester Genap:
                // Januari - Juni menggunakan tahun berikutnya
                if (
                    $semester == 'Genap'
                    && intval($bln) <= 6
                ) {
                    $thn_target = $tahun_genap;
                } else {
                    $thn_target = $tahun_dasar;
                }

                $prefix_tgl = "{$thn_target}-{$bln}";


                // ==========================================
                // QUERY ABSENSI
                //
                // PENTING:
                // id_mapel TIDAK berada di tabel absensi.
                // id_mapel berada di tabel jadwal.
                //
                // absensi -> id_jadwal -> jadwal -> id_mapel
                // ==========================================
                $query_absen = mysqli_prepare(
                    $koneksi,
                    "SELECT
                        a.status,
                        COUNT(*) AS jml
                     FROM absensi a
                     INNER JOIN jadwal j
                        ON a.id_jadwal = j.id_jadwal
                     WHERE a.id_siswa = ?
                       AND j.id_kelas = ?
                       AND j.id_mapel = ?
                       AND a.tanggal LIKE ?
                     GROUP BY a.status"
                );

                $param_like = "{$prefix_tgl}%";

                mysqli_stmt_bind_param(
                    $query_absen,
                    "iiis",
                    $id_s,
                    $id_kelas_filter,
                    $id_mapel_filter,
                    $param_like
                );

                mysqli_stmt_execute($query_absen);

                $res_absen = mysqli_stmt_get_result(
                    $query_absen
                );


                // ==========================================
                // HITUNG STATUS
                // ==========================================
                while ($row = mysqli_fetch_assoc($res_absen)) {

                    $jumlah = intval($row['jml']);

                    switch (strtoupper(trim($row['status']))) {

                        case 'H':
                        case 'HADIR':
                            $hadir += $jumlah;
                            break;

                        case 'I':
                        case 'IZIN':
                            $izin += $jumlah;
                            break;

                        case 'S':
                        case 'SAKIT':
                            $sakit += $jumlah;
                            break;

                        case 'A':
                        case 'ALPA':
                            $alpa += $jumlah;
                            break;
                    }
                }

                mysqli_stmt_close($query_absen);
            }


            // ==============================================
            // MASUKKAN KE DATA REKAP
            // ==============================================
            $rekap_data[] = [
                'nisn' => $s['nisn'] ?? '-',
                'nama' => $s['nama_lengkap'],
                'hadir' => $hadir,
                'izin' => $izin,
                'sakit' => $sakit,
                'alpa' => $alpa,
                'total' => (
                    $hadir +
                    $izin +
                    $sakit +
                    $alpa
                )
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Rekap Absensi Per Semester - Portal Guru</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css"
    >
</head>

<body class="bg-light">

    <!-- =====================================================
         NAVBAR PORTAL GURU
    ====================================================== -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">

        <div class="container">

            <a
                class="navbar-brand fw-bold"
                href="index.php"
            >
                <i class="bi bi-journal-check me-2"></i>
                Portal Guru
            </a>

            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navGuru"
            >
                <span class="navbar-toggler-icon"></span>
            </button>

            <div
                class="collapse navbar-collapse"
                id="navGuru"
            >

                <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                    <li class="nav-item">
                        <a
                            class="nav-link"
                            href="index.php"
                        >
                            Dashboard
                        </a>
                    </li>


                    <!-- MENU ABSENSI -->
                    <li class="nav-item dropdown">

                        <a
                            class="nav-link dropdown-toggle active"
                            href="#"
                            id="navbarDropdownAbsensi"
                            role="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                        >
                            <i class="bi bi-check2-square me-1"></i>
                            Absensi
                        </a>

                        <ul
                            class="dropdown-menu shadow border-0"
                            aria-labelledby="navbarDropdownAbsensi"
                        >

                            <li>
                                <a
                                    class="dropdown-item py-2"
                                    href="absensi.php"
                                >
                                    <i class="bi bi-pencil-square me-2 text-primary"></i>
                                    Input Absensi Manual
                                </a>
                            </li>

                            <li>
                                <a
                                    class="dropdown-item py-2"
                                    href="generate_qr.php"
                                >
                                    <i class="bi bi-qr-code-scan me-2 text-success"></i>
                                    Buat QR Code Absensi
                                </a>
                            </li>

                            <li>
                                <hr class="dropdown-divider">
                            </li>

                            <li>
                                <a
                                    class="dropdown-item py-2 active"
                                    href="rekap_absensi.php"
                                >
                                    <i class="bi bi-file-earmark-text me-2 text-warning"></i>
                                    Rekap Absensi Semester
                                </a>
                            </li>

                            <li>
                                <a
                                    class="dropdown-item py-2"
                                    href="ekspor_absensi_csv.php"
                                >
                                    <i class="bi bi-file-earmark-spreadsheet me-2 text-info"></i>
                                    Ekspor ke CSV
                                </a>
                            </li>

                        </ul>

                    </li>


                    <!-- NILAI -->
                    <li class="nav-item">
                        <a
                            class="nav-link"
                            href="nilai.php"
                        >
                            Input Nilai
                        </a>
                    </li>

                </ul>


                <!-- LOGOUT -->
                <div class="d-flex align-items-center">

                    <a
                        href="../logout.php"
                        class="btn btn-outline-light btn-sm"
                    >
                        <i class="bi bi-box-arrow-right me-1"></i>
                        Logout
                    </a>

                </div>

            </div>
        </div>
    </nav>


    <!-- =====================================================
         CONTENT
    ====================================================== -->
    <div class="container my-4">

        <!-- HEADER -->
        <div class="d-flex align-items-center mb-4">

            <a
                href="index.php"
                class="btn btn-outline-secondary btn-sm me-3"
            >
                <i class="bi bi-arrow-left"></i>
                Kembali
            </a>

            <h4 class="fw-bold mb-0">

                <i
                    class="bi bi-file-earmark-text text-warning me-2"
                ></i>

                Rekap Absensi Per Semester

            </h4>

        </div>


        <!-- =================================================
             FILTER FORM
        ================================================== -->
        <div class="card shadow-sm border-0 mb-4">

            <div class="card-body">

                <form
                    method="GET"
                    action=""
                    class="row g-3 align-items-end"
                >

                    <!-- KELAS & MAPEL -->
                    <div class="col-md-5">

                        <label
                            for="filter_jadwal"
                            class="form-label small fw-bold"
                        >
                            Pilih Kelas & Mata Pelajaran
                        </label>

                        <select
                            name="filter_jadwal"
                            id="filter_jadwal"
                            class="form-select"
                            required
                        >

                            <option value="">
                                -- Pilih Kelas & Mapel --
                            </option>

                            <?php foreach ($list_filter as $f): ?>

                                <?php
                                $val_opt =
                                    $f['id_kelas'] .
                                    '|' .
                                    $f['id_mapel'];
                                ?>

                                <option
                                    value="<?= htmlspecialchars($val_opt); ?>"
                                    <?= ($selected_filter == $val_opt) ? 'selected' : ''; ?>
                                >
                                    Kelas:
                                    <?= htmlspecialchars($f['nama_kelas']); ?>

                                    —

                                    Mapel:
                                    <?= htmlspecialchars($f['nama_mapel']); ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- SEMESTER -->
                    <div class="col-md-3">

                        <label
                            for="semester"
                            class="form-label small fw-bold"
                        >
                            Semester
                        </label>

                        <select
                            name="semester"
                            id="semester"
                            class="form-select"
                        >

                            <option
                                value="Ganjil"
                                <?= ($semester == 'Ganjil') ? 'selected' : ''; ?>
                            >
                                Semester Ganjil
                            </option>

                            <option
                                value="Genap"
                                <?= ($semester == 'Genap') ? 'selected' : ''; ?>
                            >
                                Semester Genap
                            </option>

                        </select>

                    </div>


                    <!-- TAHUN AJARAN -->
                    <div class="col-md-2">

                        <label
                            for="tahun_ajaran"
                            class="form-label small fw-bold"
                        >
                            Tahun Ajaran
                        </label>

                        <input
                            type="text"
                            name="tahun_ajaran"
                            id="tahun_ajaran"
                            class="form-control"
                            value="<?= htmlspecialchars($tahun_ajaran); ?>"
                            placeholder="2025/2026"
                            required
                        >

                    </div>


                    <!-- BUTTON -->
                    <div class="col-md-2">

                        <button
                            type="submit"
                            class="btn btn-warning w-100 fw-bold"
                        >
                            <i class="bi bi-search me-1"></i>
                            Tampilkan
                        </button>

                    </div>

                </form>

            </div>

        </div>


        <!-- =================================================
             HASIL REKAP
        ================================================== -->
        <?php if (!empty($selected_filter)): ?>

            <div class="card shadow-sm border-0">

                <div
                    class="card-header bg-white py-3 d-flex justify-content-between align-items-center"
                >

                    <div>

                        <h5 class="fw-bold mb-1">
                            Laporan Rekap Kehadiran
                        </h5>

                        <p class="text-muted small mb-0">

                            <?= $info_kbm; ?>

                            |

                            Semester:
                            <strong>
                                <?= htmlspecialchars($semester); ?>
                            </strong>

                            (TA:
                            <?= htmlspecialchars($tahun_ajaran); ?>
                            )

                        </p>

                    </div>


                    <button
                        onclick="window.print();"
                        class="btn btn-outline-secondary btn-sm"
                    >
                        <i class="bi bi-printer me-1"></i>
                        Cetak Rekap
                    </button>

                </div>


                <div class="card-body p-0">

                    <div class="table-responsive">

                        <table
                            class="table table-striped table-hover align-middle mb-0"
                        >

                            <thead class="table-light">

                                <tr>

                                    <th
                                        class="ps-3"
                                        width="5%"
                                    >
                                        No
                                    </th>

                                    <th width="15%">
                                        NISN
                                    </th>

                                    <th width="40%">
                                        Nama Siswa
                                    </th>

                                    <th
                                        class="text-center"
                                        width="8%"
                                    >
                                        Hadir (H)
                                    </th>

                                    <th
                                        class="text-center"
                                        width="8%"
                                    >
                                        Izin (I)
                                    </th>

                                    <th
                                        class="text-center"
                                        width="8%"
                                    >
                                        Sakit (S)
                                    </th>

                                    <th
                                        class="text-center"
                                        width="8%"
                                    >
                                        Alpa (A)
                                    </th>

                                    <th
                                        class="text-center pe-3"
                                        width="8%"
                                    >
                                        Total Pertemuan
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php if (!empty($rekap_data)): ?>

                                    <?php
                                    $no = 1;

                                    foreach ($rekap_data as $row):
                                    ?>

                                        <tr>

                                            <td class="ps-3">
                                                <?= $no++; ?>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars($row['nisn']); ?>
                                            </td>

                                            <td>
                                                <strong>
                                                    <?= htmlspecialchars($row['nama']); ?>
                                                </strong>
                                            </td>

                                            <td class="text-center">

                                                <span class="badge bg-success">
                                                    <?= $row['hadir']; ?>
                                                </span>

                                            </td>

                                            <td class="text-center">

                                                <span class="badge bg-info text-dark">
                                                    <?= $row['izin']; ?>
                                                </span>

                                            </td>

                                            <td class="text-center">

                                                <span class="badge bg-warning text-dark">
                                                    <?= $row['sakit']; ?>
                                                </span>

                                            </td>

                                            <td class="text-center">

                                                <span class="badge bg-danger">
                                                    <?= $row['alpa']; ?>
                                                </span>

                                            </td>

                                            <td class="text-center pe-3 fw-bold">

                                                <?= $row['total']; ?>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                <?php else: ?>

                                    <tr>

                                        <td
                                            colspan="8"
                                            class="text-center py-4 text-muted"
                                        >
                                            Tidak ada data siswa atau absensi
                                            untuk kelas/mapel ini.
                                        </td>

                                    </tr>

                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        <?php else: ?>

            <!-- =================================================
                 BELUM MEMILIH FILTER
            ================================================== -->
            <div
                class="card shadow-sm border-0 text-center py-5"
            >

                <div class="card-body text-muted">

                    <i
                        class="bi bi-file-earmark-bar-graph fs-1 d-block mb-3 text-secondary opacity-50"
                    ></i>

                    <p class="mb-0">

                        Silakan pilih kelas, mata pelajaran,
                        dan semester pada form di atas,
                        lalu klik
                        <strong>Tampilkan</strong>.

                    </p>

                </div>

            </div>

        <?php endif; ?>

    </div>


    <!-- BOOTSTRAP JS -->
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
    ></script>

</body>

</html>