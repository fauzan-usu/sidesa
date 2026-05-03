<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db     = getDB();
$idDesa = getIdDesaAktif();

// Parameter pencarian & filter
$cari     = trim($_GET['q'] ?? '');
$fDesa    = (int) ($_GET['desa'] ?? $idDesa ?? 0);
$fJK      = $_GET['jk'] ?? '';
$fAgama   = $_GET['agama'] ?? '';
$fStatus  = $_GET['status'] ?? 'Hidup';
$fPendidikan = $_GET['pendidikan'] ?? '';
$page     = max(1, (int) ($_GET['page'] ?? 1));
$perPage  = 20;

// Bangun klausa WHERE dinamis
$params  = [];
$clauses = [];
if (!isSuperadmin() && $idDesa) {
    $clauses[] = "w.id_desa = ?"; $params[] = $idDesa;
} elseif ($fDesa > 0) {
    $clauses[] = "w.id_desa = ?"; $params[] = $fDesa;
}
if ($cari) {
    $clauses[] = "(w.nama_lengkap LIKE ? OR w.nik LIKE ? OR w.no_kk LIKE ?)";
    $params[] = "%$cari%"; $params[] = "%$cari%"; $params[] = "%$cari%";
}
if ($fJK)           { $clauses[] = "w.jenis_kelamin = ?"; $params[] = $fJK; }
if ($fAgama)        { $clauses[] = "w.agama = ?";         $params[] = $fAgama; }
if ($fStatus)       { $clauses[] = "w.status_hidup = ?";  $params[] = $fStatus; }
if ($fPendidikan)   { $clauses[] = "w.pendidikan = ?";    $params[] = $fPendidikan; }

$whereStr = $clauses ? "WHERE " . implode(" AND ", $clauses) : "";

// Hitung total
$stmtTotal = $db->prepare("SELECT COUNT(*) FROM warga w $whereStr");
$stmtTotal->execute($params);
$total = (int) $stmtTotal->fetchColumn();
$totalPage = max(1, ceil($total / $perPage));
$page = min($page, $totalPage);
$offset = ($page - 1) * $perPage;

// Ambil data warga
$stmt = $db->prepare("SELECT w.*, d.nama_desa FROM warga w JOIN desa d ON w.id_desa=d.id $whereStr ORDER BY w.nama_lengkap ASC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$wargas = $stmt->fetchAll();

// Data untuk filter
$desas = isSuperadmin() ? $db->query("SELECT id, nama_desa FROM desa WHERE aktif=1 ORDER BY nama_desa")->fetchAll() : [];

$pageTitle      = 'Data Warga';
$pageBreadcrumb = ['Dashboard' => APP_URL . '/index.php', 'Data Warga' => null];
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- TOOLBAR -->
<div class="card mb-3">
    <form method="GET" class="search-bar">
        <div class="search-input-wrap" style="flex:2">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" name="q" value="<?= e($cari) ?>" placeholder="Cari nama, NIK, atau No. KK…">
        </div>
        <?php if (isSuperadmin()): ?>
        <select name="desa" style="max-width:160px">
            <option value="">Semua Desa</option>
            <?php foreach ($desas as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $fDesa == $d['id'] ? 'selected' : '' ?>><?= e($d['nama_desa']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <select name="jk" style="max-width:120px">
            <option value="">Semua JK</option>
            <option value="L" <?= $fJK==='L' ? 'selected' : '' ?>>Laki-laki</option>
            <option value="P" <?= $fJK==='P' ? 'selected' : '' ?>>Perempuan</option>
        </select>
        <select name="status" style="max-width:120px">
            <option value="">Semua Status</option>
            <option value="Hidup"    <?= $fStatus==='Hidup'    ? 'selected' : '' ?>>Hidup</option>
            <option value="Meninggal"<?= $fStatus==='Meninggal'? 'selected' : '' ?>>Meninggal</option>
            <option value="Pindah"   <?= $fStatus==='Pindah'   ? 'selected' : '' ?>>Pindah</option>
        </select>
        <button type="submit" class="btn btn-primary">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            Cari
        </button>
        <a href="modules/warga/index.php" class="btn btn-secondary">Reset</a>
    </form>
</div>

<!-- ACTION BAR -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px">
    <p class="text-muted" style="font-size:13px">Menampilkan <strong><?= number_format($total) ?></strong> data warga</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="modules/laporan/export_excel.php?<?= http_build_query(['desa'=>$fDesa,'jk'=>$fJK,'agama'=>$fAgama,'status'=>$fStatus,'q'=>$cari]) ?>" class="btn btn-success btn-sm">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            Export Excel
        </a>
        <a href="modules/laporan/cetak_warga.php?<?= http_build_query(['desa'=>$fDesa,'jk'=>$fJK,'agama'=>$fAgama,'status'=>$fStatus,'q'=>$cari]) ?>" target="_blank" class="btn btn-teal btn-sm">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Cetak PDF
        </a>
        <a href="modules/warga/form.php" class="btn btn-primary btn-sm">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Tambah Warga
        </a>
    </div>
</div>

<!-- TABEL WARGA -->
<div class="card">
    <div class="table-wrap">
        <table class="dt" id="tblWarga">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nama Lengkap</th>
                    <th>NIK</th>
                    <th>Desa</th>
                    <th>Tgl Lahir / Umur</th>
                    <th>JK</th>
                    <th>Agama</th>
                    <th>Pekerjaan</th>
                    <th>Status</th>
                    <th style="text-align:center" title="Status Verifikasi SIAK Dukcapil">SIAK</th>
                    <th style="text-align:center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($wargas)): ?>
                <tr>
                    <td colspan="10">
                        <div class="empty-state">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                            <p>Tidak ada data warga yang ditemukan.</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($wargas as $i => $w): ?>
                <tr>
                    <td class="text-muted" style="font-size:12px"><?= ($offset + $i + 1) ?></td>
                    <td>
                        <div style="font-weight:600;color:var(--text-1)"><?= e($w['nama_lengkap']) ?></div>
                        <div style="font-size:11.5px;color:var(--text-3)"><?= e($w['rt'] ? "RT {$w['rt']}/RW {$w['rw']}" : '') ?></div>
                    </td>
                    <td style="font-family:monospace;font-size:12.5px"><?= e($w['nik']) ?></td>
                    <td style="font-size:12.5px"><?= e($w['nama_desa']) ?></td>
                    <td style="font-size:12.5px">
                        <?= formatTanggal($w['tanggal_lahir']) ?>
                        <span class="text-muted">(<?= hitungUmur($w['tanggal_lahir']) ?> th)</span>
                    </td>
                    <td><span class="badge <?= $w['jenis_kelamin']==='L' ? 'badge-brand' : 'badge-amber' ?>"><?= $w['jenis_kelamin']==='L' ? 'L' : 'P' ?></span></td>
                    <td style="font-size:12.5px"><?= e($w['agama']) ?></td>
                    <td style="font-size:12.5px"><?= e($w['pekerjaan'] ?: '-') ?></td>
                    <td>
                        <?php $statusColor = ['Hidup'=>'badge-green','Meninggal'=>'badge-red','Pindah'=>'badge-amber']; ?>
                        <span class="badge <?= $statusColor[$w['status_hidup']] ?? 'badge-gray' ?>"><?= e($w['status_hidup']) ?></span>
                    </td>
                    <td style="text-align:center" title="<?= $w['siak_verified'] ? 'NIK terverifikasi SIAK Dukcapil pada '.formatTanggal($w['siak_verified_at']??'','d M Y') : 'Belum diverifikasi ke SIAK' ?>">
                        <?php if ($w['siak_verified']): ?>
                        <span style="font-size:16px" title="Terverifikasi">✅</span>
                        <?php else: ?>
                        <span style="font-size:16px;opacity:.3" title="Belum diverifikasi">○</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;white-space:nowrap">
                        <a href="modules/warga/detail.php?id=<?= $w['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="Detail">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </a>
                        <a href="modules/warga/form.php?id=<?= $w['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="Edit">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </a>
                        <a href="modules/warga/hapus.php?id=<?= $w['id'] ?>" class="btn btn-danger btn-sm btn-icon" title="Hapus" onclick="return konfirmasiHapus('<?= e(addslashes($w['nama_lengkap'])) ?>')">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($totalPage > 1): ?>
    <div class="pagination">
        <span>Halaman <?= $page ?> dari <?= $totalPage ?> (<?= number_format($total) ?> data)</span>
        <div class="page-links">
            <?php
            $qBase = http_build_query(array_filter(['q'=>$cari,'desa'=>$fDesa,'jk'=>$fJK,'status'=>$fStatus]));
            ?>
            <a href="?<?= $qBase ?>&page=1" class="page-link <?= $page===1?'disabled':'' ?>">«</a>
            <a href="?<?= $qBase ?>&page=<?= max(1,$page-1) ?>" class="page-link <?= $page===1?'disabled':'' ?>">‹</a>
            <?php for ($p = max(1,$page-2); $p <= min($totalPage,$page+2); $p++): ?>
            <a href="?<?= $qBase ?>&page=<?= $p ?>" class="page-link <?= $p===$page?'active':'' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <a href="?<?= $qBase ?>&page=<?= min($totalPage,$page+1) ?>" class="page-link <?= $page===$totalPage?'disabled':'' ?>">›</a>
            <a href="?<?= $qBase ?>&page=<?= $totalPage ?>" class="page-link <?= $page===$totalPage?'disabled':'' ?>">»</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
