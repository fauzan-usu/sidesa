<?php
/**
 * DASHBOARD INTEGRASI SIAK KEMENDAGRI
 * FNA & Kawan-kawan — 2025
 */
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requirePeran('superadmin', 'admin_desa');

require_once __DIR__ . '/../../includes/SiakClient.php';

$db     = getDB();
$idDesa = getIdDesaAktif();

// Ambil konfigurasi SIAK untuk desa ini
$stmtCfg = $db->prepare("SELECT sc.*, d.nama_desa FROM siak_config sc JOIN desa d ON sc.id_desa=d.id WHERE sc.id_desa=?");
$stmtCfg->execute([$idDesa ?: 0]);
$config = $stmtCfg->fetch();

// Jika belum ada config (desa baru), buat default
if (!$config && $idDesa) {
    $db->prepare("INSERT IGNORE INTO siak_config (id_desa, mode, verifikasi_aktif, status_mou) VALUES (?,?,?,?)")
       ->execute([$idDesa, 'simulasi', 0, 'belum_diajukan']);
    $stmtCfg->execute([$idDesa]);
    $config = $stmtCfg->fetch();
}

$siak = $config ? new SiakClient($config, $db) : null;

// Statistik 30 hari terakhir
$stat = $siak ? $siak->getStatistik((int)($config['id_desa'] ?? 0)) : [];

// Log verifikasi terbaru
$logTerbaru = $db->prepare(
    "SELECT l.*, p.nama_lengkap as operator FROM siak_log_verifikasi l
     LEFT JOIN pengguna p ON l.id_pengguna=p.id
     WHERE l.id_desa=? ORDER BY l.created_at DESC LIMIT 10"
);
$logTerbaru->execute([$idDesa ?: 0]);
$logs = $logTerbaru->fetchAll();

// Statistik wilayah yang sudah dicache
$cacheWilayah = [
    'provinsi'  => (int)$db->query("SELECT COUNT(*) FROM wilayah_provinsi")->fetchColumn(),
    'kabupaten' => (int)$db->query("SELECT COUNT(*) FROM wilayah_kabupaten")->fetchColumn(),
    'kecamatan' => (int)$db->query("SELECT COUNT(*) FROM wilayah_kecamatan")->fetchColumn(),
    'desa'      => (int)$db->query("SELECT COUNT(*) FROM wilayah_desa")->fetchColumn(),
];

// Warga yang sudah/belum diverifikasi
$statWarga = ['verified'=>0,'unverified'=>0];
if ($idDesa) {
    $statWarga['verified']   = (int)$db->prepare("SELECT COUNT(*) FROM warga WHERE id_desa=? AND siak_verified=1 AND status_hidup='Hidup'")->execute([$idDesa]) ? $db->query("SELECT COUNT(*) FROM warga WHERE id_desa=$idDesa AND siak_verified=1 AND status_hidup='Hidup'")->fetchColumn() : 0;
    $statWarga['unverified'] = (int)$db->query("SELECT COUNT(*) FROM warga WHERE id_desa=$idDesa AND siak_verified=0 AND status_hidup='Hidup'")->fetchColumn();
}

$statusMouLabel = [
    'belum_diajukan' => ['label'=>'Belum Diajukan',  'warna'=>'gray'],
    'dalam_proses'   => ['label'=>'Dalam Proses',    'warna'=>'amber'],
    'disetujui'      => ['label'=>'MOU Disetujui',   'warna'=>'green'],
    'ditolak'        => ['label'=>'Ditolak',          'warna'=>'red'],
];
$modeLabel = ['simulasi'=>'Mode Simulasi','aktif'=>'Mode Aktif (Live)','nonaktif'=>'Nonaktif'];
$statusBadge = fn(string $s) => $statusMouLabel[$s] ?? ['label'=>$s,'warna'=>'gray'];

$pageTitle      = 'Integrasi SIAK Kemendagri';
$pageBreadcrumb = ['Dashboard'=>APP_URL.'/index.php','Integrasi SIAK'=>null];
$pageSub        = 'Sinkronisasi data kependudukan dengan SIAK Terpusat Kemendagri';
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- BANNER STATUS -->
<?php if (!$config || ($config['mode'] ?? '') === 'simulasi'): ?>
<div class="alert alert-info mb-3" style="display:flex;align-items:flex-start;gap:12px;padding:16px 20px">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <div>
        <strong>Mode Simulasi Aktif</strong> — Sistem berjalan dalam mode pengembangan. Verifikasi NIK tidak menghubungi server Dukcapil yang sesungguhnya.
        Untuk mengaktifkan integrasi nyata, desa perlu mengajukan MOU ke Ditjen Dukcapil Kemendagri terlebih dahulu.
        <a href="panduan_mou.php" style="color:var(--brand);font-weight:700">Lihat panduan MOU →</a>
    </div>
</div>
<?php elseif (($config['mode'] ?? '') === 'aktif'): ?>
<div class="alert alert-success mb-3" style="display:flex;align-items:center;gap:12px;padding:14px 20px">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    <strong>Integrasi SIAK Aktif</strong> — Sistem terhubung ke SIAK Terpusat Kemendagri. MOU: <strong><?= e($config['nomor_mou'] ?? '-') ?></strong>
</div>
<?php endif; ?>

<!-- KPI CARDS -->
<div class="stats-grid mb-3">
    <div class="stat-card">
        <div class="stat-icon brand"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
        <div class="stat-info">
            <div class="stat-num" style="color:var(--green)"><?= number_format($stat['sesuai'] ?? 0) ?></div>
            <div class="stat-label">Verifikasi Sesuai (30 hari)</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
        <div class="stat-info">
            <div class="stat-num" style="color:var(--red)"><?= number_format(($stat['tidak_sesuai']??0) + ($stat['tidak_ditemukan']??0)) ?></div>
            <div class="stat-label">Verifikasi Tidak Sesuai</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon teal"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="23 11 17 17 14 14"/></svg></div>
        <div class="stat-info">
            <div class="stat-num"><?= number_format($statWarga['verified']) ?></div>
            <div class="stat-label">Warga Terverifikasi SIAK</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="23" y1="11" x2="17" y2="11"/></svg></div>
        <div class="stat-info">
            <div class="stat-num"><?= number_format($statWarga['unverified']) ?></div>
            <div class="stat-label">Warga Belum Diverifikasi</div>
        </div>
    </div>
</div>

<div class="row mb-3">

    <!-- STATUS INTEGRASI -->
    <div class="col-6">
        <div class="card">
            <div class="card-header">
                <span class="card-title">Status Integrasi SIAK</span>
                <a href="pengaturan.php" class="btn btn-secondary btn-sm">⚙️ Pengaturan</a>
            </div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:14px">
                <?php if ($config): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;background:var(--bg);border-radius:var(--radius);border:1px solid var(--border)">
                    <span style="font-size:13px;color:var(--text-2);font-weight:600">Mode Operasi</span>
                    <span style="font-size:13px;font-weight:700;color:<?= $config['mode']==='aktif'?'var(--green)':'var(--amber)' ?>">
                        <?= $modeLabel[$config['mode']] ?? $config['mode'] ?>
                    </span>
                </div>
                <?php
                $mou = $statusBadge($config['status_mou'] ?? 'belum_diajukan');
                $mouWarna = ['gray'=>'#888','amber'=>'var(--amber)','green'=>'var(--green)','red'=>'var(--red)'];
                ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;background:var(--bg);border-radius:var(--radius);border:1px solid var(--border)">
                    <span style="font-size:13px;color:var(--text-2);font-weight:600">Status MOU Dukcapil</span>
                    <span style="font-size:13px;font-weight:700;color:<?= $mouWarna[$mou['warna']]?:'#888' ?>"><?= $mou['label'] ?></span>
                </div>
                <?php if ($config['nomor_mou']): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;background:var(--bg);border-radius:var(--radius);border:1px solid var(--border)">
                    <span style="font-size:13px;color:var(--text-2);font-weight:600">Nomor MOU</span>
                    <span style="font-size:13px;font-weight:700"><?= e($config['nomor_mou']) ?></span>
                </div>
                <?php endif; ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;background:var(--bg);border-radius:var(--radius);border:1px solid var(--border)">
                    <span style="font-size:13px;color:var(--text-2);font-weight:600">Verifikasi Otomatis</span>
                    <span style="font-size:13px;font-weight:700;color:<?= $config['verifikasi_aktif']?'var(--green)':'var(--text-3)' ?>">
                        <?= $config['verifikasi_aktif'] ? '✅ Aktif' : '⬜ Nonaktif' ?>
                    </span>
                </div>
                <?php endif; ?>

                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:4px">
                    <a href="verifikasi_nik.php" class="btn btn-primary btn-sm">🔍 Verifikasi NIK Manual</a>
                    <a href="sinkron_wilayah.php" class="btn btn-secondary btn-sm">🗺️ Sinkron Wilayah</a>
                    <a href="panduan_mou.php" class="btn btn-secondary btn-sm">📋 Panduan MOU</a>
                </div>
            </div>
        </div>
    </div>

    <!-- CACHE WILAYAH KEMENDAGRI -->
    <div class="col-6">
        <div class="card">
            <div class="card-header">
                <span class="card-title">Cache Wilayah Kemendagri</span>
                <a href="sinkron_wilayah.php" class="btn btn-secondary btn-sm">🔄 Perbarui</a>
            </div>
            <div class="card-body">
                <p style="font-size:13px;color:var(--text-3);margin-bottom:14px">Data wilayah referensi dari Kemendagri yang sudah diunduh ke database lokal. Digunakan untuk validasi kode wilayah pada NIK.</p>
                <?php foreach ([
                    ['Provinsi',          $cacheWilayah['provinsi'],  34,    '#5B4FCF'],
                    ['Kabupaten/Kota',    $cacheWilayah['kabupaten'], 514,   '#0D9B8A'],
                    ['Kecamatan',         $cacheWilayah['kecamatan'], 7277,  '#C4731A'],
                    ['Desa/Kelurahan',    $cacheWilayah['desa'],      83931, '#1A7A4A'],
                ] as [$nama, $ada, $total, $warna]): ?>
                <div style="margin-bottom:12px">
                    <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:3px">
                        <span style="color:var(--text-2);font-weight:600"><?= $nama ?></span>
                        <span style="color:var(--text-1);font-weight:700"><?= number_format($ada) ?> <span style="color:var(--text-3);font-weight:400">/ <?= number_format($total) ?></span></span>
                    </div>
                    <div style="background:var(--border);border-radius:4px;height:6px;overflow:hidden">
                        <div style="height:100%;width:<?= $total>0?min(100,round($ada/$total*100)):0 ?>%;background:<?= $warna ?>;border-radius:4px"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if ($cacheWilayah['provinsi'] === 0): ?>
                <div style="text-align:center;padding:12px;background:var(--amber-light);border-radius:var(--radius);font-size:13px;color:var(--amber);margin-top:8px">
                    ⚠️ Data wilayah belum diunduh. <a href="sinkron_wilayah.php" style="color:var(--amber);font-weight:700">Klik Sinkron Wilayah</a> untuk mengunduh dari Kemendagri.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- LOG VERIFIKASI TERBARU -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Log Verifikasi NIK Terbaru</span>
        <a href="log.php" class="btn btn-secondary btn-sm">Lihat Semua Log</a>
    </div>
    <div class="table-wrap">
        <table class="dt">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>NIK</th>
                    <th>Nama</th>
                    <th>Status</th>
                    <th>Durasi</th>
                    <th>Mode</th>
                    <th>Operator</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                <tr><td colspan="7" class="text-center text-muted" style="padding:32px">Belum ada log verifikasi NIK.</td></tr>
                <?php else: ?>
                <?php foreach ($logs as $log):
                    $statusGaya = [
                        'sesuai'          => 'background:#d1fae5;color:#065f46',
                        'tidak_sesuai'    => 'background:#fee2e2;color:#991b1b',
                        'tidak_ditemukan' => 'background:#fef3c7;color:#92400e',
                        'error'           => 'background:#fee2e2;color:#991b1b',
                        'simulasi'        => 'background:#ede9ff;color:#5B4FCF',
                    ][$log['status_respons']] ?? 'background:#eee;color:#666';
                ?>
                <tr>
                    <td style="font-size:12px;color:var(--text-3)"><?= formatTanggal($log['created_at'],'d M Y H:i') ?></td>
                    <td style="font-family:monospace;font-size:12.5px"><?= e(substr($log['nik_input'],0,6).'**********') ?></td>
                    <td style="font-size:13px"><?= e($log['nama_input'] ?? '-') ?></td>
                    <td><span style="display:inline-block;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:700;<?= $statusGaya ?>"><?= ucfirst(str_replace('_',' ',$log['status_respons'])) ?></span></td>
                    <td style="font-size:12px;color:var(--text-3)"><?= $log['durasi_ms'] ?>ms</td>
                    <td><span style="font-size:11px;padding:2px 8px;border-radius:6px;background:<?= $log['mode']==='aktif'?'#d1fae5':'#ede9ff' ?>;color:<?= $log['mode']==='aktif'?'#065f46':'#5B4FCF' ?>;font-weight:700"><?= ucfirst($log['mode']) ?></span></td>
                    <td style="font-size:12.5px"><?= e($log['operator'] ?? 'Sistem') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
