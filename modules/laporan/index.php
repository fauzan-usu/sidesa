<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db     = getDB();
$idDesa = getIdDesaAktif();
$fDesa  = (int) ($_GET['desa'] ?? $idDesa ?? 0);

// WHERE builder
$where  = $fDesa ? "WHERE w.id_desa=$fDesa AND w.status_hidup='Hidup'" : "WHERE w.status_hidup='Hidup'";
$wTitle = '';
if ($fDesa) {
    $rDesa = $db->prepare("SELECT nama_desa, kecamatan, kabupaten FROM desa WHERE id=?");
    $rDesa->execute([$fDesa]);
    $rowDesa = $rDesa->fetch();
    $wTitle = $rowDesa ? " — {$rowDesa['nama_desa']}" : '';
}

// Statistik kunci — $where sudah pakai alias w., tambahan kondisi juga pakai alias w.
$total        = $db->query("SELECT COUNT(*) FROM warga w $where")->fetchColumn();
$lakiLaki     = $db->query("SELECT COUNT(*) FROM warga w $where AND w.jenis_kelamin='L'")->fetchColumn();
$perempuan    = $db->query("SELECT COUNT(*) FROM warga w $where AND w.jenis_kelamin='P'")->fetchColumn();
$dtks         = $db->query("SELECT COUNT(*) FROM warga w $where AND w.status_dtks=1")->fetchColumn();

// Distribusi agama
$agama = $db->query("SELECT agama, COUNT(*) as n FROM warga w $where GROUP BY agama ORDER BY n DESC")->fetchAll();

// Distribusi pendidikan
$pendidikan = $db->query("SELECT pendidikan, COUNT(*) as n FROM warga w $where GROUP BY pendidikan ORDER BY FIELD(pendidikan,'Tidak/Belum Sekolah','SD/Sederajat','SMP/Sederajat','SMA/Sederajat','D1/D2/D3','S1','S2','S3')")->fetchAll();

// Distribusi pekerjaan (top 8)
$pekerjaan = $db->query("SELECT IFNULL(pekerjaan,'Tidak/Belum Bekerja') as pekerjaan, COUNT(*) as n FROM warga w $where GROUP BY pekerjaan ORDER BY n DESC LIMIT 8")->fetchAll();

// Distribusi usia
$wargaTgl = $db->query("SELECT tanggal_lahir FROM warga w $where")->fetchAll();
$grupUsia = ['Balita (0-4)'=>0,'Anak (5-14)'=>0,'Remaja (15-25)'=>0,'Dewasa (26-45)'=>0,'Madya (46-59)'=>0,'Lansia (60+)'=>0];
foreach ($wargaTgl as $w) {
    $kel = kelompokUsia(hitungUmur($w['tanggal_lahir']));
    $grupUsia[$kel]++;
}

// Desa untuk filter
$desas = isSuperadmin() ? $db->query("SELECT id, nama_desa FROM desa WHERE aktif=1 ORDER BY nama_desa")->fetchAll() : [];

// Statistik per desa (hanya superadmin)
$perDesaStat = [];
if (isSuperadmin() && !$fDesa) {
    $perDesaStat = $db->query("SELECT d.id, d.nama_desa, d.kecamatan,
        COUNT(w.id) as total,
        SUM(w.jenis_kelamin='L') as laki,
        SUM(w.jenis_kelamin='P') as perempuan,
        SUM(w.status_dtks=1) as dtks
        FROM desa d LEFT JOIN warga w ON d.id=w.id_desa AND w.status_hidup='Hidup'
        WHERE d.aktif=1 GROUP BY d.id ORDER BY total DESC")->fetchAll();
}

$pageTitle = 'Laporan & Statistik';
$pageBreadcrumb = ['Dashboard'=>APP_URL.'/index.php','Laporan & Statistik'=>null];
require_once __DIR__ . '/../../includes/header.php';

// Fungsi bar chart horizontal inline
function barChart(array $data, int $total, string $colorVar = '--brand'): void {
    if ($total === 0) { echo '<p class="text-muted text-center">Tidak ada data.</p>'; return; }
    foreach ($data as $row) {
        $label = is_array($row) ? array_values($row)[0] : '';
        $n     = is_array($row) ? array_values($row)[1] : 0;
        $pct   = round($n / $total * 100, 1);
        echo '<div style="margin-bottom:10px">
            <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:3px">
                <span style="color:var(--text-2)">' . htmlspecialchars($label) . '</span>
                <span style="font-weight:600">' . number_format($n) . ' <span style="color:var(--text-3);font-weight:400">(' . $pct . '%)</span></span>
            </div>
            <div style="background:var(--border);border-radius:4px;height:7px;overflow:hidden">
                <div style="height:100%;width:' . $pct . '%;background:' . $colorVar . ';border-radius:4px"></div>
            </div>
        </div>';
    }
}
?>

<!-- Filter desa -->
<?php if (isSuperadmin()): ?>
<form method="GET" class="card" style="padding:14px 20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:20px">
    <label style="font-weight:600;font-size:13px;color:var(--text-2)">Filter Desa:</label>
    <select name="desa" style="min-width:200px">
        <option value="">— Semua Desa —</option>
        <?php foreach ($desas as $d): ?>
        <option value="<?= $d['id'] ?>" <?= $fDesa==$d['id']?'selected':'' ?>><?= e($d['nama_desa']) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Terapkan</button>
    <?php if ($fDesa): ?><a href="index.php" class="btn btn-secondary btn-sm">Reset</a><?php endif; ?>
    <a href="cetak_laporan.php?desa=<?= $fDesa ?>" target="_blank" class="btn btn-teal btn-sm" style="margin-left:auto">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        Cetak Laporan
    </a>
    <a href="export_excel.php?desa=<?= $fDesa ?>" class="btn btn-success btn-sm">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        Export Excel
    </a>
</form>
<?php endif; ?>

<!-- STAT CARDS -->
<div class="stats-grid mb-3">
    <div class="stat-card">
        <div class="stat-icon brand"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
        <div class="stat-info"><div class="stat-num"><?= number_format($total) ?></div><div class="stat-label">Total Warga<?= $wTitle ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon teal"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 10-16 0"/></svg></div>
        <div class="stat-info"><div class="stat-num"><?= number_format($lakiLaki) ?></div><div class="stat-label">Laki-laki (<?= $total ? round($lakiLaki/$total*100) : 0 ?>%)</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 10-16 0"/></svg></div>
        <div class="stat-info"><div class="stat-num"><?= number_format($perempuan) ?></div><div class="stat-label">Perempuan (<?= $total ? round($perempuan/$total*100) : 0 ?>%)</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg></div>
        <div class="stat-info"><div class="stat-num"><?= number_format($dtks) ?></div><div class="stat-label">Warga DTKS (<?= $total ? round($dtks/$total*100) : 0 ?>%)</div></div>
    </div>
</div>

<!-- GRAFIK 3 KOLOM -->
<div class="row mb-3">
    <div class="col-6">
        <div class="card">
            <div class="card-header"><span class="card-title">Distribusi Usia</span></div>
            <div class="card-body">
                <?php barChart(array_map(fn($k,$v)=>[$k,$v], array_keys($grupUsia), array_values($grupUsia)), $total, 'var(--brand)'); ?>
            </div>
        </div>
    </div>
    <div class="col-6">
        <div class="card">
            <div class="card-header"><span class="card-title">Tingkat Pendidikan</span></div>
            <div class="card-body">
                <?php barChart($pendidikan, $total, 'var(--teal)'); ?>
            </div>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-6">
        <div class="card">
            <div class="card-header"><span class="card-title">Distribusi Agama</span></div>
            <div class="card-body">
                <?php
                $colors = ['#5B4FCF','#0D9B8A','#C4731A','#1A7A4A','#C0392B','#888'];
                $i = 0;
                foreach ($agama as $row) {
                    $n   = $row['n'];
                    $pct = $total > 0 ? round($n / $total * 100, 1) : 0;
                    echo '<div style="margin-bottom:10px">
                        <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:3px">
                            <span style="color:var(--text-2)">' . e($row['agama']) . '</span>
                            <span style="font-weight:600">' . number_format($n) . ' <span style="color:var(--text-3);font-weight:400">(' . $pct . '%)</span></span>
                        </div>
                        <div style="background:var(--border);border-radius:4px;height:7px;overflow:hidden">
                            <div style="height:100%;width:' . $pct . '%;background:' . $colors[$i % count($colors)] . ';border-radius:4px"></div>
                        </div>
                    </div>';
                    $i++;
                }
                ?>
            </div>
        </div>
    </div>
    <div class="col-6">
        <div class="card">
            <div class="card-header"><span class="card-title">Jenis Pekerjaan (Top 8)</span></div>
            <div class="card-body">
                <?php barChart($pekerjaan, $total, 'var(--amber)'); ?>
            </div>
        </div>
    </div>
</div>

<!-- TABEL PER DESA (hanya superadmin & semua desa) -->
<?php if (!empty($perDesaStat)): ?>
<div class="card mb-3">
    <div class="card-header"><span class="card-title">Ringkasan Per Desa</span></div>
    <div class="table-wrap">
        <table class="dt">
            <thead>
                <tr><th>Nama Desa</th><th>Kecamatan</th><th style="text-align:center">Total Warga</th><th style="text-align:center">Laki-laki</th><th style="text-align:center">Perempuan</th><th style="text-align:center">DTKS</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                <?php foreach ($perDesaStat as $row): ?>
                <tr>
                    <td><strong><?= e($row['nama_desa']) ?></strong></td>
                    <td style="font-size:12.5px"><?= e($row['kecamatan']) ?></td>
                    <td style="text-align:center"><span class="badge badge-brand"><?= number_format($row['total']) ?></span></td>
                    <td style="text-align:center"><?= number_format($row['laki']) ?></td>
                    <td style="text-align:center"><?= number_format($row['perempuan']) ?></td>
                    <td style="text-align:center"><span class="badge badge-red"><?= number_format($row['dtks']) ?></span></td>
                    <td><a href="?desa=<?= $row['id'] ?>" class="btn btn-secondary btn-sm">Detail</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
