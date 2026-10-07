<?php
// admin/siswa.php
session_start();
require_once '../config/koneksi.php';

// =====================================================
// PROTEKSI HALAMAN: HANYA ADMIN
// =====================================================
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$pesan = "";
$tipe_pesan = "";

// =====================================================
// AMBIL DAFTAR KELAS
// =====================================================
$query_kelas_list = mysqli_query(
    $koneksi,
    "SELECT * FROM kelas ORDER BY nama_kelas ASC"
);

$kelas_options = [];

while ($k = mysqli_fetch_assoc($query_kelas_list)) {
    $kelas_options[] = $k;
}


// =====================================================
// 1. PROSES TAMBAH SISWA
// =====================================================
if (isset($_POST['tambah'])) {

    $nisn = trim($_POST['nisn'] ?? '');
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $id_kelas = intval($_POST['id_kelas'] ?? 0);
    $jenis_kelamin = trim($_POST['jenis_kelamin'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Validasi data
    if (
        !empty($nisn) &&
        !empty($nama_lengkap) &&
        $id_kelas > 0 &&
        !empty($username) &&
        !empty($password)
    ) {

        // -------------------------------------------------
        // CEK NISN
        // -------------------------------------------------
        $stmt_cek_nisn = mysqli_prepare(
            $koneksi,
            "SELECT id_siswa FROM siswa WHERE nisn = ?"
        );

        mysqli_stmt_bind_param(
            $stmt_cek_nisn,
            "s",
            $nisn
        );

        mysqli_stmt_execute($stmt_cek_nisn);

        $res_cek_nisn = mysqli_stmt_get_result($stmt_cek_nisn);


        // -------------------------------------------------
        // CEK USERNAME
        // -------------------------------------------------
        $stmt_cek_user = mysqli_prepare(
            $koneksi,
            "SELECT id_user FROM users WHERE username = ?"
        );

        mysqli_stmt_bind_param(
            $stmt_cek_user,
            "s",
            $username
        );

        mysqli_stmt_execute($stmt_cek_user);

        $res_cek_user = mysqli_stmt_get_result($stmt_cek_user);


        // -------------------------------------------------
        // JIKA NISN SUDAH ADA
        // -------------------------------------------------
        if (mysqli_num_rows($res_cek_nisn) > 0) {

            $pesan = "NISN '{$nisn}' sudah terdaftar! Gunakan NISN yang lain.";
            $tipe_pesan = "warning";


        // -------------------------------------------------
        // JIKA USERNAME SUDAH ADA
        // -------------------------------------------------
        } elseif (mysqli_num_rows($res_cek_user) > 0) {

            $pesan = "Username '{$username}' sudah digunakan oleh akun lain!";
            $tipe_pesan = "warning";


        // -------------------------------------------------
        // JIKA NISN DAN USERNAME BELUM ADA
        // -------------------------------------------------
        } else {

            // Hash password
            $password_hash = password_hash(
                $password,
                PASSWORD_BCRYPT
            );

            $role = 'siswa';


            // -------------------------------------------------
            // SIMPAN AKUN KE TABEL USERS
            // -------------------------------------------------
            $stmt_user = mysqli_prepare(
                $koneksi,
                "INSERT INTO users (username, password, role)
                 VALUES (?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt_user,
                "sss",
                $username,
                $password_hash,
                $role
            );


            if (mysqli_stmt_execute($stmt_user)) {

                // Ambil ID user yang baru dibuat
                $id_user_baru = mysqli_insert_id($koneksi);


                // -------------------------------------------------
                // SIMPAN DATA SISWA
                // -------------------------------------------------
                $stmt_siswa = mysqli_prepare(
                    $koneksi,
                    "INSERT INTO siswa
                    (id_user, nisn, nama_lengkap, id_kelas, jenis_kelamin)
                    VALUES (?, ?, ?, ?, ?)"
                );

                mysqli_stmt_bind_param(
                    $stmt_siswa,
                    "issis",
                    $id_user_baru,
                    $nisn,
                    $nama_lengkap,
                    $id_kelas,
                    $jenis_kelamin
                );


                if (mysqli_stmt_execute($stmt_siswa)) {

                    $pesan = "Data siswa berhasil ditambahkan!";
                    $tipe_pesan = "success";

                } else {

                    // -------------------------------------------------
                    // JIKA PROFIL SISWA GAGAL,
                    // HAPUS USER YANG SUDAH TERBUAT
                    // -------------------------------------------------
                    $stmt_hapus_user = mysqli_prepare(
                        $koneksi,
                        "DELETE FROM users WHERE id_user = ?"
                    );

                    mysqli_stmt_bind_param(
                        $stmt_hapus_user,
                        "i",
                        $id_user_baru
                    );

                    mysqli_stmt_execute($stmt_hapus_user);

                    $pesan = "Gagal menambahkan profil siswa.";
                    $tipe_pesan = "danger";
                }

                mysqli_stmt_close($stmt_siswa);

            } else {

                $pesan = "Gagal membuat akun user siswa.";
                $tipe_pesan = "danger";
            }

            mysqli_stmt_close($stmt_user);
        }

        mysqli_stmt_close($stmt_cek_nisn);
        mysqli_stmt_close($stmt_cek_user);

    } else {

        $pesan = "Semua data wajib diisi dengan lengkap!";
        $tipe_pesan = "warning";
    }
}


// =====================================================
// 2. PROSES EDIT SISWA
// =====================================================
if (isset($_POST['edit'])) {

    $id_siswa = intval($_POST['id_siswa'] ?? 0);
    $nisn = trim($_POST['nisn'] ?? '');
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $id_kelas = intval($_POST['id_kelas'] ?? 0);
    $jenis_kelamin = trim($_POST['jenis_kelamin'] ?? '');


    if (
        $id_siswa > 0 &&
        !empty($nisn) &&
        !empty($nama_lengkap) &&
        $id_kelas > 0
    ) {

        // -------------------------------------------------
        // CEK NISN AGAR TIDAK DIPAKAI SISWA LAIN
        // -------------------------------------------------
        $stmt_cek_nisn_edit = mysqli_prepare(
            $koneksi,
            "SELECT id_siswa
             FROM siswa
             WHERE nisn = ?
             AND id_siswa != ?"
        );

        mysqli_stmt_bind_param(
            $stmt_cek_nisn_edit,
            "si",
            $nisn,
            $id_siswa
        );

        mysqli_stmt_execute($stmt_cek_nisn_edit);

        $res_cek_nisn_edit = mysqli_stmt_get_result(
            $stmt_cek_nisn_edit
        );


        if (mysqli_num_rows($res_cek_nisn_edit) > 0) {

            $pesan = "NISN '{$nisn}' sudah digunakan oleh siswa lain!";
            $tipe_pesan = "warning";

        } else {

            // -------------------------------------------------
            // UPDATE DATA SISWA
            // -------------------------------------------------
            $stmt_upd = mysqli_prepare(
                $koneksi,
                "UPDATE siswa
                 SET nisn = ?,
                     nama_lengkap = ?,
                     id_kelas = ?,
                     jenis_kelamin = ?
                 WHERE id_siswa = ?"
            );

            mysqli_stmt_bind_param(
                $stmt_upd,
                "ssisi",
                $nisn,
                $nama_lengkap,
                $id_kelas,
                $jenis_kelamin,
                $id_siswa
            );


            if (mysqli_stmt_execute($stmt_upd)) {

                $pesan = "Data siswa berhasil diperbarui!";
                $tipe_pesan = "success";

            } else {

                $pesan = "Gagal memperbarui data siswa.";
                $tipe_pesan = "danger";
            }

            mysqli_stmt_close($stmt_upd);
        }

        mysqli_stmt_close($stmt_cek_nisn_edit);

    } else {

        $pesan = "Data edit siswa belum lengkap!";
        $tipe_pesan = "warning";
    }
}


// =====================================================
// 3. PROSES RESET PASSWORD SISWA
// =====================================================
if (isset($_POST['reset_password'])) {

    $id_user_reset = intval($_POST['id_user'] ?? 0);
    $password_baru = trim($_POST['password_baru'] ?? '');


    if (
        $id_user_reset > 0 &&
        !empty($password_baru)
    ) {

        $password_hash = password_hash(
            $password_baru,
            PASSWORD_BCRYPT
        );


        $stmt_reset = mysqli_prepare(
            $koneksi,
            "UPDATE users
             SET password = ?
             WHERE id_user = ?"
        );

        mysqli_stmt_bind_param(
            $stmt_reset,
            "si",
            $password_hash,
            $id_user_reset
        );


        if (mysqli_stmt_execute($stmt_reset)) {

            $pesan = "Password akun berhasil direset!";
            $tipe_pesan = "success";

        } else {

            $pesan = "Gagal mereset password.";
            $tipe_pesan = "danger";
        }

        mysqli_stmt_close($stmt_reset);

    } else {

        $pesan = "Password baru tidak boleh kosong!";
        $tipe_pesan = "warning";
    }
}


// =====================================================
// 4. PROSES HAPUS SISWA
// =====================================================
if (isset($_GET['hapus'])) {

    $id_siswa_hapus = intval($_GET['hapus']);


    if ($id_siswa_hapus > 0) {

        // -------------------------------------------------
        // AMBIL ID USER SISWA
        // -------------------------------------------------
        $stmt_get_user = mysqli_prepare(
            $koneksi,
            "SELECT id_user
             FROM siswa
             WHERE id_siswa = ?"
        );

        mysqli_stmt_bind_param(
            $stmt_get_user,
            "i",
            $id_siswa_hapus
        );

        mysqli_stmt_execute($stmt_get_user);

        $res_user = mysqli_stmt_get_result(
            $stmt_get_user
        );

        $data_siswa = mysqli_fetch_assoc($res_user);


        if ($data_siswa) {

            $id_user_del = intval($data_siswa['id_user']);


            // -------------------------------------------------
            // HAPUS DATA SISWA
            // -------------------------------------------------
            $stmt_del_siswa = mysqli_prepare(
                $koneksi,
                "DELETE FROM siswa
                 WHERE id_siswa = ?"
            );

            mysqli_stmt_bind_param(
                $stmt_del_siswa,
                "i",
                $id_siswa_hapus
            );

            mysqli_stmt_execute($stmt_del_siswa);


            // -------------------------------------------------
            // HAPUS AKUN USER
            // -------------------------------------------------
            $stmt_del_user = mysqli_prepare(
                $koneksi,
                "DELETE FROM users
                 WHERE id_user = ?"
            );

            mysqli_stmt_bind_param(
                $stmt_del_user,
                "i",
                $id_user_del
            );

            mysqli_stmt_execute($stmt_del_user);


            $pesan = "Data siswa berhasil dihapus!";
            $tipe_pesan = "success";


            mysqli_stmt_close($stmt_del_siswa);
            mysqli_stmt_close($stmt_del_user);
        }

        mysqli_stmt_close($stmt_get_user);
    }
}


// =====================================================
// AMBIL DAFTAR SISWA
// =====================================================
$query_siswa = mysqli_query(
    $koneksi,
    "
    SELECT
        s.id_siswa,
        s.id_user,
        s.nisn,
        s.nama_lengkap,
        s.id_kelas,
        s.jenis_kelamin,
        k.nama_kelas,
        u.username
    FROM siswa s
    LEFT JOIN kelas k
        ON s.id_kelas = k.id_kelas
    LEFT JOIN users u
        ON s.id_user = u.id_user
    ORDER BY
        k.nama_kelas ASC,
        s.nama_lengkap ASC
    "
);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Kelola Data Siswa - Admin</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

</head>


<body class="bg-light">


<!-- =====================================================
     NAVBAR
===================================================== -->

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">

    <div class="container">

        <a class="navbar-brand fw-bold"
           href="index.php">

            <i class="bi bi-shield-lock me-2"></i>

            Panel Admin

        </a>


        <div class="collapse navbar-collapse">

            <ul class="navbar-nav me-auto">

                <li class="nav-item">

                    <a class="nav-link"
                       href="index.php">

                        Dashboard

                    </a>

                </li>


                <li class="nav-item">

                    <a class="nav-link active"
                       href="siswa.php">

                        Siswa

                    </a>

                </li>


                <li class="nav-item">

                    <a class="nav-link"
                       href="guru.php">

                        Guru

                    </a>

                </li>


                <li class="nav-item">

                    <a class="nav-link"
                       href="kelas.php">

                        Kelas

                    </a>

                </li>


                <li class="nav-item">

                    <a class="nav-link"
                       href="mapel.php">

                        Mapel

                    </a>

                </li>


                <li class="nav-item">

                    <a class="nav-link"
                       href="jadwal.php">

                        Jadwal

                    </a>

                </li>

            </ul>


            <a href="../logout.php"
               class="btn btn-outline-light btn-sm">

                Logout

            </a>

        </div>

    </div>

</nav>



<!-- =====================================================
     CONTENT
===================================================== -->

<div class="container my-4">


    <!-- HEADER -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h4 class="fw-bold">

            <i class="bi bi-people me-2 text-success"></i>

            Kelola Data Siswa

        </h4>


        <button
            class="btn btn-success fw-semibold"
            data-bs-toggle="modal"
            data-bs-target="#modalTambah">

            <i class="bi bi-person-plus me-1"></i>

            Tambah Siswa

        </button>

    </div>



    <!-- =====================================================
         PESAN / ALERT
    ====================================================== -->

    <?php if (!empty($pesan)): ?>

        <div
            class="alert alert-<?= htmlspecialchars($tipe_pesan); ?> alert-dismissible fade show"
            role="alert">

            <?= htmlspecialchars($pesan); ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert">
            </button>

        </div>

    <?php endif; ?>



    <!-- =====================================================
         TABEL SISWA
    ====================================================== -->

    <div class="card shadow-sm border-0">

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">

                        <tr>

                            <th class="ps-3">
                                No
                            </th>

                            <th>
                                NISN
                            </th>

                            <th>
                                Nama Lengkap
                            </th>

                            <th>
                                Kelas
                            </th>

                            <th>
                                L/P
                            </th>

                            <th>
                                Username
                            </th>

                            <th
                                class="text-center"
                                style="width: 180px;">

                                Aksi

                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php if (
                        $query_siswa &&
                        mysqli_num_rows($query_siswa) > 0
                    ): ?>

                        <?php

                        $no = 1;

                        while (
                            $s = mysqli_fetch_assoc($query_siswa)
                        ):

                        ?>

                        <tr>

                            <!-- NO -->

                            <td class="ps-3">

                                <?= $no++; ?>

                            </td>


                            <!-- NISN -->

                            <td>

                                <?= htmlspecialchars(
                                    $s['nisn']
                                ); ?>

                            </td>


                            <!-- NAMA -->

                            <td class="fw-bold">

                                <?= htmlspecialchars(
                                    $s['nama_lengkap']
                                ); ?>

                            </td>


                            <!-- KELAS -->

                            <td>

                                <span class="badge bg-primary">

                                    <?= htmlspecialchars(
                                        $s['nama_kelas']
                                        ?? 'Belum Diatur'
                                    ); ?>

                                </span>

                            </td>


                            <!-- JENIS KELAMIN -->

                            <td>

                                <?= $s['jenis_kelamin'] === 'L'
                                    ? 'L'
                                    : 'P'; ?>

                            </td>


                            <!-- USERNAME -->

                            <td>

                                <span
                                    class="badge bg-light text-dark border">

                                    <?= htmlspecialchars(
                                        $s['username']
                                    ); ?>

                                </span>

                            </td>


                            <!-- AKSI -->

                            <td class="text-center">


                                <!-- RESET PASSWORD -->

                                <button
                                    class="btn btn-sm btn-outline-danger me-1"
                                    title="Reset Password"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalReset<?= $s['id_siswa']; ?>">

                                    <i class="bi bi-key"></i>

                                </button>


                                <!-- EDIT -->

                                <button
                                    class="btn btn-sm btn-outline-warning me-1"
                                    title="Edit Siswa"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalEdit<?= $s['id_siswa']; ?>">

                                    <i class="bi bi-pencil"></i>

                                </button>


                                <!-- HAPUS -->

                                <a
                                    href="siswa.php?hapus=<?= $s['id_siswa']; ?>"
                                    class="btn btn-sm btn-outline-secondary"
                                    title="Hapus Siswa"
                                    onclick="return confirm('Yakin hapus data siswa ini?')">

                                    <i class="bi bi-trash"></i>

                                </a>

                            </td>

                        </tr>



                        <!-- =====================================================
                             MODAL RESET PASSWORD
                        ====================================================== -->

                        <div
                            class="modal fade"
                            id="modalReset<?= $s['id_siswa']; ?>"
                            tabindex="-1">

                            <div class="modal-dialog modal-sm">

                                <div class="modal-content">

                                    <form
                                        action="siswa.php"
                                        method="POST">


                                        <div class="modal-header">

                                            <h6 class="modal-title fw-bold">

                                                Reset Password

                                            </h6>

                                            <button
                                                type="button"
                                                class="btn-close"
                                                data-bs-dismiss="modal">
                                            </button>

                                        </div>


                                        <div class="modal-body">

                                            <input
                                                type="hidden"
                                                name="id_user"
                                                value="<?= $s['id_user']; ?>">


                                            <p class="small text-muted mb-2">

                                                Ubah password untuk akun:

                                                <strong>

                                                    <?= htmlspecialchars(
                                                        $s['username']
                                                    ); ?>

                                                </strong>

                                            </p>


                                            <div class="mb-3">

                                                <label
                                                    class="form-label small fw-semibold">

                                                    Password Baru

                                                </label>


                                                <input
                                                    type="password"
                                                    name="password_baru"
                                                    class="form-control form-control-sm"
                                                    placeholder="Ketik password baru"
                                                    required>

                                            </div>

                                        </div>


                                        <div class="modal-footer p-2">

                                            <button
                                                type="button"
                                                class="btn btn-sm btn-secondary"
                                                data-bs-dismiss="modal">

                                                Batal

                                            </button>


                                            <button
                                                type="submit"
                                                name="reset_password"
                                                class="btn btn-sm btn-danger">

                                                Simpan Password

                                            </button>

                                        </div>

                                    </form>

                                </div>

                            </div>

                        </div>



                        <!-- =====================================================
                             MODAL EDIT SISWA
                        ====================================================== -->

                        <div
                            class="modal fade"
                            id="modalEdit<?= $s['id_siswa']; ?>"
                            tabindex="-1">

                            <div class="modal-dialog">

                                <div class="modal-content">

                                    <form
                                        action="siswa.php"
                                        method="POST">


                                        <div class="modal-header">

                                            <h5 class="modal-title fw-bold">

                                                Edit Data Siswa

                                            </h5>


                                            <button
                                                type="button"
                                                class="btn-close"
                                                data-bs-dismiss="modal">
                                            </button>

                                        </div>


                                        <div class="modal-body">


                                            <!-- ID SISWA -->

                                            <input
                                                type="hidden"
                                                name="id_siswa"
                                                value="<?= $s['id_siswa']; ?>">



                                            <!-- NISN -->

                                            <div class="mb-3">

                                                <label
                                                    class="form-label fw-semibold">

                                                    NISN

                                                </label>


                                                <input
                                                    type="text"
                                                    name="nisn"
                                                    class="form-control"
                                                    value="<?= htmlspecialchars($s['nisn']); ?>"
                                                    required>

                                            </div>



                                            <!-- NAMA -->

                                            <div class="mb-3">

                                                <label
                                                    class="form-label fw-semibold">

                                                    Nama Lengkap

                                                </label>


                                                <input
                                                    type="text"
                                                    name="nama_lengkap"
                                                    class="form-control"
                                                    value="<?= htmlspecialchars($s['nama_lengkap']); ?>"
                                                    required>

                                            </div>



                                            <!-- KELAS -->

                                            <div class="mb-3">

                                                <label
                                                    class="form-label fw-semibold">

                                                    Kelas

                                                </label>


                                                <select
                                                    name="id_kelas"
                                                    class="form-select"
                                                    required>


                                                    <?php foreach (
                                                        $kelas_options
                                                        as $k
                                                    ): ?>

                                                        <option
                                                            value="<?= $k['id_kelas']; ?>"
                                                            <?= (
                                                                $s['id_kelas']
                                                                == $k['id_kelas']
                                                            )
                                                                ? 'selected'
                                                                : ''; ?>>

                                                            <?= htmlspecialchars(
                                                                $k['nama_kelas']
                                                            ); ?>

                                                        </option>

                                                    <?php endforeach; ?>


                                                </select>

                                            </div>



                                            <!-- JENIS KELAMIN -->

                                            <div class="mb-3">

                                                <label
                                                    class="form-label fw-semibold">

                                                    Jenis Kelamin

                                                </label>


                                                <select
                                                    name="jenis_kelamin"
                                                    class="form-select"
                                                    required>


                                                    <option
                                                        value="L"
                                                        <?= (
                                                            $s['jenis_kelamin']
                                                            === 'L'
                                                        )
                                                            ? 'selected'
                                                            : ''; ?>>

                                                        Laki-Laki

                                                    </option>


                                                    <option
                                                        value="P"
                                                        <?= (
                                                            $s['jenis_kelamin']
                                                            === 'P'
                                                        )
                                                            ? 'selected'
                                                            : ''; ?>>

                                                        Perempuan

                                                    </option>


                                                </select>

                                            </div>


                                        </div>


                                        <div class="modal-footer">

                                            <button
                                                type="button"
                                                class="btn btn-secondary"
                                                data-bs-dismiss="modal">

                                                Batal

                                            </button>


                                            <button
                                                type="submit"
                                                name="edit"
                                                class="btn btn-primary">

                                                Simpan Perubahan

                                            </button>

                                        </div>


                                    </form>

                                </div>

                            </div>

                        </div>


                        <?php endwhile; ?>


                    <?php else: ?>

                        <tr>

                            <td
                                colspan="7"
                                class="text-center py-4 text-muted">

                                Belum ada data siswa.

                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>



<!-- =====================================================
     MODAL TAMBAH SISWA
===================================================== -->

<div
    class="modal fade"
    id="modalTambah"
    tabindex="-1">

    <div class="modal-dialog">

        <div class="modal-content">

            <form
                action="siswa.php"
                method="POST">


                <!-- HEADER -->

                <div class="modal-header">

                    <h5 class="modal-title fw-bold">

                        Tambah Data Siswa

                    </h5>


                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                    </button>

                </div>



                <!-- BODY -->

                <div class="modal-body">


                    <!-- NISN -->

                    <div class="mb-3">

                        <label
                            class="form-label fw-semibold">

                            NISN

                        </label>


                        <input
                            type="text"
                            name="nisn"
                            class="form-control"
                            placeholder="Masukkan NISN"
                            required>

                    </div>



                    <!-- NAMA -->

                    <div class="mb-3">

                        <label
                            class="form-label fw-semibold">

                            Nama Lengkap Siswa

                        </label>


                        <input
                            type="text"
                            name="nama_lengkap"
                            class="form-control"
                            placeholder="Masukkan nama lengkap"
                            required>

                    </div>



                    <!-- KELAS -->

                    <div class="mb-3">

                        <label
                            class="form-label fw-semibold">

                            Pilih Kelas

                        </label>


                        <select
                            name="id_kelas"
                            class="form-select"
                            required>


                            <option value="">

                                -- Pilih Kelas --

                            </option>


                            <?php foreach (
                                $kelas_options
                                as $k
                            ): ?>

                                <option
                                    value="<?= $k['id_kelas']; ?>">

                                    <?= htmlspecialchars(
                                        $k['nama_kelas']
                                    ); ?>

                                </option>

                            <?php endforeach; ?>


                        </select>

                    </div>



                    <!-- JENIS KELAMIN -->

                    <div class="mb-3">

                        <label
                            class="form-label fw-semibold">

                            Jenis Kelamin

                        </label>


                        <select
                            name="jenis_kelamin"
                            class="form-select"
                            required>

                            <option value="L">

                                Laki-Laki

                            </option>


                            <option value="P">

                                Perempuan

                            </option>

                        </select>

                    </div>



                    <hr>



                    <!-- AKUN LOGIN -->

                    <h6 class="fw-bold text-secondary mb-3">

                        Akun Login Siswa

                    </h6>



                    <!-- USERNAME -->

                    <div class="mb-3">

                        <label
                            class="form-label fw-semibold">

                            Username / NISN

                        </label>


                        <input
                            type="text"
                            name="username"
                            class="form-control"
                            placeholder="Masukkan username"
                            required>

                    </div>



                    <!-- PASSWORD -->

                    <div class="mb-3">

                        <label
                            class="form-label fw-semibold">

                            Password

                        </label>


                        <input
                            type="password"
                            name="password"
                            class="form-control"
                            placeholder="Masukkan password"
                            required>

                    </div>


                </div>



                <!-- FOOTER -->

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">

                        Batal

                    </button>


                    <button
                        type="submit"
                        name="tambah"
                        class="btn btn-success">

                        <i class="bi bi-save me-1"></i>

                        Simpan

                    </button>

                </div>


            </form>

        </div>

    </div>

</div>



<!-- =====================================================
     BOOTSTRAP JAVASCRIPT
===================================================== -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>