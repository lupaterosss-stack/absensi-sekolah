<?php
// admin/guru.php
session_start();
require_once '../config/koneksi.php';

// Proteksi Admin
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$pesan = '';
$error = '';

// ==========================================
// PROSES TAMBAH GURU (DENGAN AKUN USERS)
// ==========================================
if (isset($_POST['tambah_guru'])) {
    $nip = mysqli_real_escape_string($koneksi, trim($_POST['nip']));
    $nama_guru = mysqli_real_escape_string($koneksi, trim($_POST['nama_guru']));
    $jenis_kelamin = mysqli_real_escape_string($koneksi, $_POST['jenis_kelamin']);
    $username = mysqli_real_escape_string($koneksi, trim($_POST['username']));
    $password = trim($_POST['password']);

    if (!empty($nama_guru) && !empty($username) && !empty($password) && !empty($jenis_kelamin)) {
        // 1. Cek Duplikat Username
        $cek_user = mysqli_query($koneksi, "SELECT id_user FROM users WHERE username = '$username'");
        
        // 2. Cek Duplikat Guru (Nama atau NIP)
        $sql_cek_guru = "SELECT id_guru FROM guru WHERE nama_guru = '$nama_guru'";
        if (!empty($nip)) {
            $sql_cek_guru .= " OR nip = '$nip'";
        }
        $cek_guru = mysqli_query($koneksi, $sql_cek_guru);

        if (mysqli_num_rows($cek_user) > 0) {
            $error = "Gagal: Username <strong>$username</strong> sudah terdaftar! Gunakan username lain.";
        } elseif (mysqli_num_rows($cek_guru) > 0) {
            $error = "Gagal: Guru dengan Nama atau NIP tersebut sudah terdaftar!";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $val_nip = !empty($nip) ? "'$nip'" : "NULL";

            mysqli_begin_transaction($koneksi);

            try {
                // Insert ke Tabel `users` (Status Aktif)
                $query_user = "INSERT INTO users (username, password, role, status_akun) 
                               VALUES ('$username', '$password_hash', 'guru', 'Aktif')";
                mysqli_query($koneksi, $query_user);

                $id_user_baru = mysqli_insert_id($koneksi);

                // Insert ke Tabel `guru` (Termasuk jenis_kelamin)
                $query_guru = "INSERT INTO guru (id_user, nip, nama_guru, jenis_kelamin) 
                               VALUES ('$id_user_baru', $val_nip, '$nama_guru', '$jenis_kelamin')";
                mysqli_query($koneksi, $query_guru);

                mysqli_commit($koneksi);

                header("Location: guru.php?status=sukses_tambah");
                exit();
            } catch (Exception $e) {
                mysqli_rollback($koneksi);
                $error = "Gagal menambah data guru: " . $e->getMessage();
            }
        }
    } else {
        $error = "Nama Guru, Jenis Kelamin, Username, dan Password wajib diisi!";
    }
}

// ==========================================
// PROSES EDIT GURU
// ==========================================
if (isset($_POST['edit_guru'])) {
    $id_guru = mysqli_real_escape_string($koneksi, $_POST['id_guru']);
    $nip = mysqli_real_escape_string($koneksi, trim($_POST['nip']));
    $nama_guru = mysqli_real_escape_string($koneksi, trim($_POST['nama_guru']));
    $jenis_kelamin = mysqli_real_escape_string($koneksi, $_POST['jenis_kelamin']);

    if (!empty($nama_guru) && !empty($jenis_kelamin)) {
        // Cek duplikat untuk guru lain
        $sql_cek = "SELECT id_guru FROM guru WHERE (nama_guru = '$nama_guru'";
        if (!empty($nip)) {
            $sql_cek .= " OR nip = '$nip'";
        }
        $sql_cek .= ") AND id_guru != '$id_guru'";

        $result_cek = mysqli_query($koneksi, $sql_cek);

        if (mysqli_num_rows($result_cek) > 0) {
            $error = "Gagal memperbarui: Nama atau NIP tersebut sudah digunakan oleh guru lain!";
        } else {
            $val_nip = !empty($nip) ? "'$nip'" : "NULL";
            
            $query_edit = "UPDATE guru SET nip = $val_nip, nama_guru = '$nama_guru', jenis_kelamin = '$jenis_kelamin' WHERE id_guru = '$id_guru'";
            if (mysqli_query($koneksi, $query_edit)) {
                header("Location: guru.php?status=sukses_edit");
                exit();
            } else {
                $error = "Gagal memperbarui data: " . mysqli_error($koneksi);
            }
        }
    } else {
        $error = "Nama guru dan jenis kelamin tidak boleh kosong!";
    }
}

// ==========================================
// PROSES HAPUS GURU (+ AKUN TERKAIT)
// ==========================================
if (isset($_GET['hapus'])) {
    $id_guru = mysqli_real_escape_string($koneksi, $_GET['hapus']);
    
    // Ambil id_user terkait
    $get_guru = mysqli_query($koneksi, "SELECT id_user FROM guru WHERE id_guru = '$id_guru'");
    if ($row_guru = mysqli_fetch_assoc($get_guru)) {
        $id_user_terkait = $row_guru['id_user'];

        // Hapus data guru
        if (mysqli_query($koneksi, "DELETE FROM guru WHERE id_guru = '$id_guru'")) {
            // Hapus akun di tabel users
            if (!empty($id_user_terkait)) {
                mysqli_query($koneksi, "DELETE FROM users WHERE id_user = '$id_user_terkait'");
            }
            header("Location: guru.php?status=sukses_hapus");
            exit();
        } else {
            header("Location: guru.php?status=gagal_hapus");
            exit();
        }
    }
}

// ==========================================
// QUERY UTAMA TAMPIL DATA GURU
// ==========================================
$query_guru = "
    SELECT 
        g.id_guru,
        g.nip,
        g.nama_guru,
        g.jenis_kelamin,
        u.username,
        u.status_akun,
        COUNT(DISTINCT j.id_mapel) AS total_mapel,
        COUNT(DISTINCT j.id_kelas) AS total_kelas
    FROM guru g
    LEFT JOIN users u ON g.id_user = u.id_user
    LEFT JOIN jadwal j ON g.id_guru = j.id_guru
    GROUP BY g.id_guru, g.nip, g.nama_guru, g.jenis_kelamin, u.username, u.status_akun
    ORDER BY g.nama_guru ASC
";
$result_guru = mysqli_query($koneksi, $query_guru);
$list_guru = mysqli_fetch_all($result_guru, MYSQLI_ASSOC);

// Query Detail Jadwal Mengajar Lengkap
$query_jadwal_detail = "
    SELECT 
        j.id_guru,
        j.hari,
        j.jam_mulai,
        j.jam_selesai,
        m.nama_mapel,
        m.kode_mapel,
        k.nama_kelas
    FROM jadwal j
    JOIN mapel m ON j.id_mapel = m.id_mapel
    JOIN kelas k ON j.id_kelas = k.id_kelas
    ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'), j.jam_mulai ASC
";
$result_jadwal = mysqli_query($koneksi, $query_jadwal_detail);

$jadwal_per_guru = [];
if ($result_jadwal) {
    while ($j_row = mysqli_fetch_assoc($result_jadwal)) {
        $jadwal_per_guru[$j_row['id_guru']][] = $j_row;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Guru & Rincian Pengajaran - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">

    <!-- Navbar Admin -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-shield-lock me-2"></i>Panel Admin</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active" href="guru.php">Data Guru</a></li>
                    <li class="nav-item"><a class="nav-link" href="siswa.php">Data Siswa</a></li>
                    <li class="nav-item"><a class="nav-link" href="kelas.php">Data Kelas</a></li>
                    <li class="nav-item"><a class="nav-link" href="jadwal.php">Jadwal</a></li>
                </ul>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        
        <!-- Notifikasi Status -->
        <?php if (isset($_GET['status'])): ?>
            <?php if ($_GET['status'] == 'sukses_tambah'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">Data guru dan akun pengguna berhasil ditambahkan!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php elseif ($_GET['status'] == 'sukses_edit'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">Data guru berhasil diperbarui!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php elseif ($_GET['status'] == 'sukses_hapus'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">Data guru beserta akun pengguna berhasil dihapus!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php elseif ($_GET['status'] == 'gagal_hapus'): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">Gagal menghapus guru (kemungkinan masih terikat dengan data jadwal).<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert"><?= $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-person-badge me-2 text-primary"></i>Master Data Guru</h4>
                <p class="text-muted small mb-0">Kelola informasi guru beserta rincian jadwal kelas dan mata pelajaran.</p>
            </div>
            <!-- Tombol Memicu Modal Tambah Guru -->
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahGuru">
                <i class="bi bi-plus-lg me-1"></i>Tambah Guru
            </button>
        </div>

        <!-- Tabel Utama Guru -->
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;" class="text-center">No</th>
                                <th>NIP</th>
                                <th>Nama Guru</th>
                                <th>L/P</th>
                                <th>Username</th>
                                <th>Mata Pelajaran</th>
                                <th>Kelas Mengajar</th>
                                <th class="text-center" style="width: 220px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($list_guru) > 0): ?>
                                <?php $no = 1; foreach ($list_guru as $row): ?>
                                    <?php $id_g = $row['id_guru']; ?>
                                    <tr>
                                        <td class="text-center"><?= $no++; ?></td>
                                        <td><code><?= htmlspecialchars($row['nip'] ?? '-'); ?></code></td>
                                        <td class="fw-bold"><?= htmlspecialchars($row['nama_guru']); ?></td>
                                        <td>
                                            <?php if (($row['jenis_kelamin'] ?? '') == 'Laki-laki'): ?>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2">
                                                    <i class="bi bi-gender-male me-1"></i>L
                                                </span>
                                            <?php elseif (($row['jenis_kelamin'] ?? '') == 'Perempuan'): ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2">
                                                    <i class="bi bi-gender-female me-1"></i>P
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-primary fw-semibold"><?= htmlspecialchars($row['username'] ?? '-'); ?></td>
                                        <td>
                                            <span class="badge bg-info text-dark px-2 py-1">
                                                <?= $row['total_mapel']; ?> Mapel
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success px-2 py-1">
                                                <?= $row['total_kelas']; ?> Kelas
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-info text-white fw-semibold" data-bs-toggle="modal" data-bs-target="#modalDetail<?= $id_g; ?>" title="Rincian Jadwal">
                                                <i class="bi bi-calendar3"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $id_g; ?>" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <a href="guru.php?hapus=<?= $id_g; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Yakin ingin menghapus guru ini beserta akun penggunanya?')" title="Hapus">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">Belum ada data guru.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL TAMBAH GURU -->
    <div class="modal fade" id="modalTambahGuru" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form action="" method="POST">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2"></i>Tambah Data Guru</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label for="nama_guru" class="form-label fw-semibold">Nama Lengkap Guru <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nama_guru" name="nama_guru" required placeholder="Contoh: Drs. Budi Santoso, M.Pd">
                        </div>
                        <div class="mb-3">
                            <label for="nip" class="form-label fw-semibold">NIP</label>
                            <input type="text" class="form-control" id="nip" name="nip" placeholder="Masukkan NIP (Opsional)">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Jenis Kelamin <span class="text-danger">*</span></label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="jenis_kelamin" id="jk_l" value="Laki-laki" required>
                                    <label class="form-check-label" for="jk_l">Laki-laki</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="jenis_kelamin" id="jk_p" value="Perempuan" required>
                                    <label class="form-check-label" for="jk_p">Perempuan</label>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="username" class="form-label fw-semibold">Username Login <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" required placeholder="Masukkan username">
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" required placeholder="Masukkan password">
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="tambah_guru" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Simpan Guru & Akun</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL EDIT & DETAIL JADWAL PER GURU -->
    <?php foreach ($list_guru as $row): ?>
        <?php 
            $id_g = $row['id_guru'];
            $detail_jadwal = $jadwal_per_guru[$id_g] ?? [];
            $jk_saat_ini = $row['jenis_kelamin'] ?? '';
        ?>

        <!-- MODAL EDIT GURU -->
        <div class="modal fade" id="modalEdit<?= $id_g; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <form action="" method="POST">
                        <input type="hidden" name="id_guru" value="<?= $id_g; ?>">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Data Guru</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Nama Lengkap Guru <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nama_guru" value="<?= htmlspecialchars($row['nama_guru']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">NIP</label>
                                <input type="text" class="form-control" name="nip" value="<?= htmlspecialchars($row['nip'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Jenis Kelamin <span class="text-danger">*</span></label>
                                <div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="jenis_kelamin" id="edit_jk_l_<?= $id_g; ?>" value="Laki-laki" <?= ($jk_saat_ini == 'Laki-laki') ? 'checked' : ''; ?> required>
                                        <label class="form-check-label" for="edit_jk_l_<?= $id_g; ?>">Laki-laki</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="jenis_kelamin" id="edit_jk_p_<?= $id_g; ?>" value="Perempuan" <?= ($jk_saat_ini == 'Perempuan') ? 'checked' : ''; ?> required>
                                        <label class="form-check-label" for="edit_jk_p_<?= $id_g; ?>">Perempuan</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" name="edit_guru" class="btn btn-warning btn-sm"><i class="bi bi-save me-1"></i>Perbarui</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL DETAIL JADWAL GURU -->
        <div class="modal fade" id="modalDetail<?= $id_g; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold">
                            <i class="bi bi-person-lines-fill me-2"></i>Rincian Penugasan Mengajar
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="card bg-light border-0 mb-3">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-5 mb-2 mb-md-0">
                                        <small class="text-muted d-block">Nama Lengkap Guru:</small>
                                        <span class="fs-6 fw-bold text-primary"><?= htmlspecialchars($row['nama_guru']); ?></span>
                                    </div>
                                    <div class="col-md-4 mb-2 mb-md-0">
                                        <small class="text-muted d-block">NIP / ID Guru:</small>
                                        <span class="fw-bold text-dark"><?= htmlspecialchars($row['nip'] ?? 'G' .$id_g); ?></span>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted d-block">Jenis Kelamin:</small>
                                        <span class="fw-bold text-dark"><?= htmlspecialchars($row['jenis_kelamin'] ?? '-'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h6 class="fw-bold mb-2 text-dark"><i class="bi bi-clock-history me-1 text-primary"></i>Jadwal Mengajar Per Hari & Jam</h6>
                        
                        <div class="table-responsive rounded border">
                            <table class="table table-striped table-hover align-middle mb-0" style="font-size: 13px;">
                                <thead class="table-dark">
                                    <tr>
                                        <th style="width: 100px;">Hari</th>
                                        <th style="width: 140px;">Jam / Waktu</th>
                                        <th>Kelas</th>
                                        <th>Mata Pelajaran</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($detail_jadwal)): ?>
                                        <?php foreach ($detail_jadwal as$j): ?>
                                            <tr>
                                                <td><span class="badge bg-secondary"><?= htmlspecialchars($j['hari']); ?></span></td>
                                                <td class="fw-bold text-nowrap">
                                                    <i class="bi bi-clock me-1 text-primary"></i>
                                                    <?= date('H:i', strtotime($j['jam_mulai'])); ?> - <?= date('H:i', strtotime($j['jam_selesai'])); ?>
                                                </td>
                                                <td><span class="badge bg-success fs-6 fw-normal">Kelas <?= htmlspecialchars($j['nama_kelas']); ?></span></td>
                                                <td>
                                                    <strong class="text-dark"><?= htmlspecialchars($j['nama_mapel']); ?></strong>
                                                    <small class="text-muted"> (<?= htmlspecialchars($j['kode_mapel'] ?? '-'); ?>)</small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">
                                                <em>Belum ada jadwal mengajar yang terdaftar untuk guru ini.</em>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>