<?php
// jadwal_kelas.php
require_once 'config/koneksi.php';

$query_kelas = mysqli_query($koneksi, "SELECT * FROM kelas ORDER BY nama_kelas ASC");
$list_kelas = mysqli_fetch_all($query_kelas, MYSQLI_ASSOC);

$id_kelas_pilih = isset($_GET['kelas']) ? intval($_GET['kelas']) : ($list_kelas[0]['id_kelas'] ?? 0);
$daftar_hari = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

// Ambil jadwal lengkap khusus kelas terpilih
$stmt = mysqli_prepare($koneksi, "
    SELECT j.*, m.nama_mapel, g.nama_guru 
    FROM jadwal j
    JOIN mapel m ON j.id_mapel = m.id_mapel
    JOIN guru g ON j.id_guru = g.id_guru
    WHERE j.id_kelas = ?
    ORDER BY j.jam_mulai ASC
");
mysqli_stmt_bind_param($stmt, "i", $id_kelas_pilih);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$jadwal = [];
while ($row = mysqli_fetch_assoc($res)) {
    $jadwal[$row['hari']][] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Pelajaran Kelas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-3">
    <div class="container" style="max-width: 600px;">
        <h4 class="fw-bold text-center mb-3">Jadwal Pelajaran Siswa</h4>

        <!-- Pilih Kelas -->
        <form action="" method="GET" class="mb-4">
            <select name="kelas" class="form-select form-select-lg fw-bold text-primary" onchange="this.form.submit()">
                <?php foreach ($list_kelas as $k): ?>
                    <option value="<?= $k['id_kelas']; ?>" <?= ($k['id_kelas'] == $id_kelas_pilih) ? 'selected' : ''; ?>>
                        Kelas <?= htmlspecialchars($k['nama_kelas']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <!-- List Jadwal Hari -->
        <?php foreach ($daftar_hari as $hari): ?>
            <div class="card mb-3 border-0 shadow-sm">
                <div class="card-header bg-primary text-white fw-bold">
                    <?= $hari; ?>
                </div>
                <div class="card-body p-2">
                    <?php if (isset($jadwal[$hari]) && count($jadwal[$hari]) > 0): ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($jadwal[$hari] as $j): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-2 py-2">
                                    <div>
                                        <div class="fw-bold"><?= htmlspecialchars($j['nama_mapel']); ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($j['nama_guru']); ?></small>
                                    </div>
                                    <span class="badge bg-secondary">
                                        <?= date('H:i', strtotime($j['jam_mulai'])); ?> - <?= date('H:i', strtotime($j['jam_selesai'])); ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="text-center text-muted py-2 small">Tidak ada jam pelajaran</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>