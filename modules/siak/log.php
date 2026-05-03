<?php
/**
 * LOG VERIFIKASI NIK — SIAK KEMENDAGRI
 * FNA & Kawan-kawan — 2025
 */
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requirePeran('superadmin', 'admin_desa');

$db     = getDB();
$idDesa = getIdDesaAktif();

// Filter
$fStatus = $_GET['status'] ?? '';
$fMode   = $_GET['mode']   ?? '';
$cari    = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

// Build WHERE
$clauses = [];
$params  = [];
if (!isSuperadmin() && $idDesa) { $clauses[] = "l.id_desa=?";          $params[] = $idDesa; }
elseif ($idDesa)                 { $clauses[] = "l.id_desa=?";          $params[] = $idDesa; }
if ($fStatus)                    { $clauses[] = "l.status_respons=?";   $params[] = $fStatus; }
if ($fMode)                      { $clauses[] = "l.mode=?";             $params[] = $fMode; }
if ($cari)                       { $clauses[] = "(l.nik_input LIKE ? OR l.nama_input LIKE ?)"; $params[] = "%$cari%"; $params[] = "%$cari%"; }

$whereStr = $clauses ? "WHERE ".implode(" AND ", $clauses) : "";

// Total
$stmtTotal = $db->prepare("SELECT COUNT(*) FROM siak_log_verifikasi l $whereStr");
$stmtTotal->execute($params);
$total    = (int)$stmtTotal->fetchColumn();
$totalPage = max(1, ceil($total / $perPage));
$page      = min($page, $totalPage);
$offset    = ($page - 1) * $perPage;

// Data
$stmt = $db->prepare(
    "SELECT l.*, d.nama_desa, p.nama_lengkap AS operator
     FROM siak_log_verifikasi l
     LEFT JOIN desa d ON l.id_desa = d.id
     LEFT JOIN pengguna p ON l.id_pengguna = p.id
     $whereStr
     ORDER BY l.created_at DESC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Ringkasan statistik
$statStmt = $db->prepare(
    "SELECT
        COUNT(*) as total,
        SUM(status_respons='sesuai') as sesuai,
        SUM(status_respons='tidak_sesuai') as tidak_sesuai,
        SUM(status_respons='tidak_ditemukan') as tidak_ditemukan,
        SUM(status_respons='error') as error,
        SUM(mode='simulasi') as simulasi,
        SUM(mode='aktif') as aktif,
        ROUND(AVG(durasi_ms)) as avg_durasi
     FROM siak_log_verifikasi l $whereStr"
);
$statStmt->execute($params);
$ringkasan = $statStmt->fetch();

$statusGaya = [
    'sesuai'          => 'background:#d1fae5;color:#065f46',
    'tidak_sesuai'    => 'background:#fee2e2;color:#991b1b',
    'tidak_ditemukan' => 'background:#fef3c7;color:#92400e',
    'error'           => 'background:#fee2e2;color:#991b1b',
    'simulasi'        => 'background:#ede9ff;color:#5B4FCF',
];

$pageTitle      = 'Log Verifikasi NIK SIAK';
$pageBreadcrumb = ['Dashboard'=>APP_URL.'/index.php','Integrasi SIAK'=>APP_URL.'/modules/siak/index.php','Log Verifikasi'=>null];
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- STAT MINI -->
<div class="stats-grid mb-3">
    <?php foreach ([
        ['Total Verifikasi',    $ringkasan['total']??0,           'brand'],
        ['Sesuai',              $ringkasan['sesuai']??0,          'green'],
        ['Tidak Sesuai/Ditemukan', ($ringkasan['tidak_sesuai']??0)+($ringkasan['tidak_ditemukan']??0), 'red'],
        ['Rata-rata Waktu',     ($ringkasan['avg_durasi']??0).'ms','teal'],
    ] as [$lbl, $val, $warna]): ?>
    <div class="stat-card">
        <div class="stat-icon <?= $warna ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-num"><?= is_numeric($val) ? number_format((int)$val) : $val ?></div>
            <div class="stat-label"><?= $lbl ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- TOOLBAR FILTER -->
<div class="card mb-3">
    <form method="GET" class="search-bar">
        <div class="search-input-wrap" style="flex:2">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" name="q" value="<?= e($cari) ?>" placeholder="Cari NIK atau nama…">
        </div>
        <select name="status" style="max-width:160px">
            <option value="">Semua Status</option>
            <?php foreach (['sesuai'=>'Sesuai','tidak_sesuai'=>'Tidak Sesuai','tidak_ditemukan'=>'Tidak Ditemukan','error'=>'Error'] as $v => $lbl): ?>
            <option value="<?= $v ?>" <?= $fStatus===$v?'selected':'' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
        </select>
        <select name="mode" style="max-width:140px">
            <option value="">Semua Mode</option>
            <option value="simulasi" <?= $fMode==='simulasi'?'selected':'' ?>>Simulasi</option>
            <option value="aktif"    <?= $fMode==='aktif'   ?'selected':'' ?>>Aktif (Live)</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Cari</button>
        <a href="log.php" class="btn btn-secondary btn-sm">Reset</a>
    </form>
</div>

<!-- TABEL LOG -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Log Verifikasi NIK (<?= number_format($total) ?> entri)</span>
        <a href="log.php?export=csv&<?= http_build_query(array_filter(['q'=>$cari,'status'=>$fStatus,'mode'=>$fMode])) ?>"
           class="btn btn-success btn-sm">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export CSV
        </a>
    </div>
    <div class="table-wrap">
        <table class="dt">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>NIK (disamarkan)</th>
                    <th>Nama</th>
                    <th>Tgl Lahir</th>
                    <th>Status</th>
                    <th>Kode Respons</th>
                    <th style="text-align:right">Durasi</th>
                    <th>Mode</th>
                    <th>Desa</th>
                    <th>Operator</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                <tr><td colspan="10" class="text-center text-muted" style="padding:40px">
                    <div style="font-size:36px;margin-bottom:8px">📋</div>
                    Belum ada log verifikasi NIK.
                </td></tr>
                <?php else: ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td style="font-size:12px;color:var(--text-3);white-space:nowrap">
                        <?= formatTanggal($log['created_at'], 'd M Y') ?><br>
                        <span style="font-family:monospace"><?= date('H:i:s', strtotime($log['created_at'])) ?></span>
                    </td>
                    <td style="font-family:monospace;font-size:13px;letter-spacing:1px">
                        <?= e(substr($log['nik_input'], 0, 6)) ?><span style="color:var(--text-3)">••••••</span><?= e(substr($log['nik_input'], 12)) ?>
                    </td>
                    <td style="font-size:13px"><?= e($log['nama_input'] ?? '—') ?></td>
                    <td style="font-size:12px;color:var(--text-3)">
                        <?= $log['tgl_lahir_input'] ? formatTanggal($log['tgl_lahir_input'], 'd M Y') : '—' ?>
                    </td>
                    <td>
                        <span style="display:inline-block;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:700;<?= $statusGaya[$log['status_respons']] ?? 'background:#eee;color:#666' ?>">
                            <?= ucfirst(str_replace('_', ' ', $log['status_respons'])) ?>
                        </span>
                    </td>
                    <td style="font-family:monospace;font-size:12px;color:var(--text-3)"><?= e($log['kode_respons'] ?? '—') ?></td>
                    <td style="text-align:right;font-size:12.5px;font-variant-numeric:tabular-nums">
                        <?php $ms = (int)$log['durasi_ms']; ?>
                        <span style="color:<?= $ms<500?'var(--green)':($ms<2000?'var(--amber)':'var(--red)') ?>">
                            <?= number_format($ms) ?>ms
                        </span>
                    </td>
                    <td>
                        <span style="font-size:11px;padding:2px 8px;border-radius:6px;font-weight:700;background:<?= $log['mode']==='aktif'?'#d1fae5':'#ede9ff' ?>;color:<?= $log['mode']==='aktif'?'#065f46':'#5B4FCF' ?>">
                            <?= ucfirst($log['mode']) ?>
                        </span>
                    </td>
                    <td style="font-size:12.5px"><?= e($log['nama_desa'] ?? '—') ?></td>
                    <td style="font-size:12.5px"><?= e($log['operator'] ?? 'Sistem') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPage > 1): ?>
    <div class="pagination">
        <span>Halaman <?= $page ?> dari <?= $totalPage ?></span>
        <div class="page-links">
            <?php $qBase = http_build_query(array_filter(['q'=>$cari,'status'=>$fStatus,'mode'=>$fMode])); ?>
            <a href="?<?= $qBase ?>&page=<?= max(1,$page-1) ?>" class="page-link <?= $page<=1?'disabled':'' ?>">‹</a>
            <?php for ($p=max(1,$page-2); $p<=min($totalPage,$page+2); $p++): ?>
            <a href="?<?= $qBase ?>&page=<?= $p ?>" class="page-link <?= $p===$page?'active':'' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <a href="?<?= $qBase ?>&page=<?= min($totalPage,$page+1) ?>" class="page-link <?= $page>=$totalPage?'disabled':'' ?>">›</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
