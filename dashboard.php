<?php
// ============================================================
// SIMDESA — Dashboard Utama
// File: dashboard.php
// ============================================================
require_once __DIR__ . '/includes/init.php';
requireLogin();

$pageTitle = 'Dashboard';
$breadcrumb = 'SIMDESA › Dashboard';
$db = db();
$user = currentUser();

// Filter berdasarkan hak akses desa
$desaFilter = allowedDesaId();
$whereWarga = $desaFilter ? "WHERE id_desa = $desaFilter" : '';
$whereDesa  = $desaFilter ? "WHERE id = $desaFilter" : '';

// Statistik utama
$totalWarga     = $db->fetchOne("SELECT COUNT(*) as total FROM warga $whereWarga")['total'] ?? 0;
$totalAktif     = $db->fetchOne("SELECT COUNT(*) as total FROM warga " . ($whereWarga ? "$whereWarga AND status_warga='Aktif'" : "WHERE status_warga='Aktif'"))['total'] ?? 0;
$totalLaki      = $db->fetchOne("SELECT COUNT(*) as total FROM warga " . ($whereWarga ? "$whereWarga AND jenis_kelamin='L'" : "WHERE jenis_kelamin='L'"))['total'] ?? 0;
$totalPerempuan = $db->fetchOne("SELECT COUNT(*) as total FROM warga " . ($whereWarga ? "$whereWarga AND jenis_kelamin='P'" : "WHERE jenis_kelamin='P'"))['total'] ?? 0;
$totalDesa      = $db->fetchOne("SELECT COUNT(*) as total FROM desa $whereDesa")['total'] ?? 0;
$totalKK        = $db->fetchOne("SELECT COUNT(DISTINCT no_kk) as total FROM warga $whereWarga")['total'] ?? 0;

// Statistik per desa (untuk superadmin)
$statsPerDesa = [];
if (!$desaFilter) {
    $statsPerDesa = $db->fetchAll(
        "SELECT d.nama_desa, COUNT(w.id) as total_warga,
         COALESCE(SUM(w.jenis_kelamin='L'), 0) as laki,
         COALESCE(SUM(w.jenis_kelamin='P'), 0) as perempuan
         FROM desa d LEFT JOIN warga w ON d.id = w.id_desa
         GROUP BY d.id ORDER BY total_warga DESC LIMIT 5"
    );
}

// Statistik pendidikan
$statsPendidikan = $db->fetchAll(
    "SELECT pendidikan, COUNT(*) as total FROM warga $whereWarga GROUP BY pendidikan ORDER BY total DESC LIMIT 6"
);

// Statistik agama
$statsAgama = $db->fetchAll(
    "SELECT agama, COUNT(*) as total FROM warga $whereWarga GROUP BY agama ORDER BY total DESC"
);

// Warga terbaru
$wargaTerbaru = $db->fetchAll(
    "SELECT w.*, d.nama_desa FROM warga w JOIN desa d ON w.id_desa = d.id 
     " . ($desaFilter ? "WHERE w.id_desa = $desaFilter" : '') . "
     ORDER BY w.created_at DESC LIMIT 7"
);

require_once 'includes/header.php';
?>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">👥</div>
        <div>
            <div class="stat-value"><?= number_format($totalWarga) ?></div>
            <div class="stat-label">Total Warga Terdaftar</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">✅</div>
        <div>
            <div class="stat-value"><?= number_format($totalAktif) ?></div>
            <div class="stat-label">Warga Aktif</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon gold">🏠</div>
        <div>
            <div class="stat-value"><?= number_format($totalKK) ?></div>
            <div class="stat-label">Kepala Keluarga</div>
        </div>
    </div>
    <?php if (!$desaFilter): ?>
    <div class="stat-card">
        <div class="stat-icon blue">🏡</div>
        <div>
            <div class="stat-value"><?= number_format($totalDesa) ?></div>
            <div class="stat-label">Desa Terdaftar</div>
        </div>
    </div>
    <?php endif; ?>
    <div class="stat-card">
        <div class="stat-icon blue">♂️</div>
        <div>
            <div class="stat-value"><?= number_format($totalLaki) ?></div>
            <div class="stat-label">Warga Laki-laki</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">♀️</div>
        <div>
            <div class="stat-value"><?= number_format($totalPerempuan) ?></div>
            <div class="stat-label">Warga Perempuan</div>
        </div>
    </div>
</div>

<!-- Row: Tabel + Statistik -->
<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;margin-bottom:20px;" class="responsive-grid">
    
    <!-- Warga Terbaru -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">🕐 Entri Warga Terbaru</span>
            <a href="<?= APP_URL ?>/modules/warga/index.php" class="btn btn-outline btn-sm">Lihat Semua →</a>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Nama Lengkap</th>
                        <th>NIK</th>
                        <th>Desa</th>
                        <th>JK</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($wargaTerbaru)): ?>
                    <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:30px;">Belum ada data warga</td></tr>
                    <?php else: ?>
                    <?php foreach ($wargaTerbaru as $w): ?>
                    <tr>
                        <td>
                            <a href="<?= APP_URL ?>/modules/warga/detail.php?id=<?= $w['id'] ?>" 
                               style="color:var(--primary);text-decoration:none;font-weight:500;">
                                <?= clean($w['nama_lengkap']) ?>
                            </a>
                        </td>
                        <td><code style="font-size:0.8rem;color:var(--text-secondary);"><?= clean($w['nik']) ?></code></td>
                        <td><?= clean($w['nama_desa']) ?></td>
                        <td><?= $w['jenis_kelamin'] === 'L' ? '♂' : '♀' ?></td>
                        <td>
                            <?php
                            $statusClass = match($w['status_warga']) {
                                'Aktif' => 'badge-success',
                                'Meninggal' => 'badge-danger',
                                'Pindah' => 'badge-warning',
                                default => 'badge-secondary'
                            };
                            ?>
                            <span class="badge <?= $statusClass ?>"><?= $w['status_warga'] ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Panel Kanan -->
    <div style="display:flex;flex-direction:column;gap:16px;">

        <!-- Statistik Agama -->
        <div class="card">
            <div class="card-header"><span class="card-title">🕌 Sebaran Agama</span></div>
            <div class="card-body" style="padding:16px 20px;">
                <?php foreach ($statsAgama as $a): 
                    $pct = $totalWarga > 0 ? round(($a['total'] / $totalWarga) * 100) : 0;
                ?>
                <div style="margin-bottom:10px;">
                    <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:4px;">
                        <span><?= clean($a['agama']) ?></span>
                        <span style="color:var(--text-muted);"><?= $a['total'] ?> (<?= $pct ?>%)</span>
                    </div>
                    <div style="height:6px;background:var(--bg-main);border-radius:3px;overflow:hidden;">
                        <div style="height:100%;width:<?= $pct ?>%;background:var(--primary);border-radius:3px;transition:width 0.6s ease;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($statsAgama)): ?>
                <p style="color:var(--text-muted);font-size:0.88rem;">Belum ada data</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Aksi Cepat -->
        <div class="card">
            <div class="card-header"><span class="card-title">⚡ Aksi Cepat</span></div>
            <div class="card-body" style="padding:14px 16px;display:flex;flex-direction:column;gap:8px;">
                <a href="<?= APP_URL ?>/modules/warga/create.php" class="btn btn-primary btn-sm">➕ Tambah Warga Baru</a>
                <a href="<?= APP_URL ?>/modules/laporan/index.php" class="btn btn-success btn-sm">📊 Buat Laporan</a>
                <?php if (hasRole(['superadmin', 'admin'])): ?>
                <a href="<?= APP_URL ?>/modules/desa/create.php" class="btn btn-ghost btn-sm">🏡 Tambah Desa</a>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php if (!$desaFilter && !empty($statsPerDesa)): ?>
<!-- Statistik Per Desa -->
<div class="card">
    <div class="card-header"><span class="card-title">🏘️ Statistik Warga per Desa</span></div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nama Desa</th>
                    <th>Total Warga</th>
                    <th>Laki-laki</th>
                    <th>Perempuan</th>
                    <th>Proporsi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($statsPerDesa as $i => $d): 
                    $pct = $totalWarga > 0 ? round(($d['total_warga'] / $totalWarga) * 100) : 0;
                ?>
                <tr>
                    <td style="color:var(--text-muted);"><?= $i + 1 ?></td>
                    <td><strong><?= clean($d['nama_desa']) ?></strong></td>
                    <td><?= number_format((int)($d['total_warga'] ?? 0)) ?></td>
                    <td><?= number_format((int)($d['laki'] ?? 0)) ?></td>
                    <td><?= number_format((int)($d['perempuan'] ?? 0)) ?></td>
                    <td style="width:180px;">
                        <div style="height:8px;background:var(--bg-main);border-radius:4px;overflow:hidden;">
                            <div style="height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg,var(--primary),var(--accent));border-radius:4px;"></div>
                        </div>
                        <span style="font-size:0.78rem;color:var(--text-muted);"><?= $pct ?>%</span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<style>
@media (max-width: 900px) {
    .responsive-grid { grid-template-columns: 1fr !important; }
}
</style>

<?php require_once 'includes/footer.php'; ?>
