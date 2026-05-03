<?php
/**
 * MANAJEMEN BERITA DESA — DAFTAR
 * Akses: superadmin & admin_desa
 * FNA & Kawan-kawan — 2025
 */
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requirePeran('superadmin', 'admin_desa');

$db     = getDB();
$idDesa = getIdDesaAktif();

// Filter
$fStatus = $_GET['status'] ?? '';
$fKat    = $_GET['kat']    ?? '';
$cari    = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

// Build WHERE
$clauses = [];
$params  = [];

if (!isSuperadmin()) {
    $clauses[] = "b.id_desa = ?";
    $params[]  = $idDesa;
} elseif ($idDesa) {
    $clauses[] = "b.id_desa = ?";
    $params[]  = $idDesa;
}

if ($fStatus) { $clauses[] = "b.status = ?";    $params[] = $fStatus; }
if ($fKat)    { $clauses[] = "b.kategori = ?";  $params[] = $fKat; }
if ($cari)    { $clauses[] = "b.judul LIKE ?";  $params[] = "%$cari%"; }

$whereStr = $clauses ? "WHERE " . implode(" AND ", $clauses) : "";

// Total
$stmtTotal = $db->prepare("SELECT COUNT(*) FROM berita b $whereStr");
$stmtTotal->execute($params);
$total    = (int) $stmtTotal->fetchColumn();
$totalPage = max(1, ceil($total / $perPage));
$page      = min($page, $totalPage);
$offset    = ($page - 1) * $perPage;

// Data
$stmt = $db->prepare("SELECT b.*, d.nama_desa, p.nama_lengkap AS penulis
    FROM berita b
    JOIN desa d ON b.id_desa = d.id
    LEFT JOIN pengguna p ON b.id_penulis = p.id
    $whereStr
    ORDER BY b.created_at DESC
    LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$beritaList = $stmt->fetchAll();

// Daftar desa untuk filter superadmin
$desList = isSuperadmin()
    ? $db->query("SELECT id, nama_desa FROM desa WHERE aktif=1 ORDER BY nama_desa")->fetchAll()
    : [];

$badgeWarna = [
    'draft'   => 'background:#f1f5f9;color:#64748b',
    'terbit'  => 'background:#d1fae5;color:#065f46',
    'arsip'   => 'background:#fef3c7;color:#92400e',
];
$labelKat = ['berita'=>'Berita','pengumuman'=>'Pengumuman','agenda'=>'Agenda','pembangunan'=>'Pembangunan','sosial'=>'Sosial','lainnya'=>'Lainnya'];

$pageTitle      = 'Manajemen Berita';
$pageBreadcrumb = ['Dashboard' => APP_URL.'/index.php', 'Berita' => null];
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- TOOLBAR -->
<div class="card mb-3">
    <form method="GET" class="search-bar">
        <div class="search-input-wrap" style="flex:2">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" name="q" value="<?= e($cari) ?>" placeholder="Cari judul berita…">
        </div>
        <?php if (isSuperadmin() && !empty($desList)): ?>
        <select name="filter_desa" onchange="this.form.submit()" style="max-width:180px">
            <option value="">Semua Desa</option>
            <?php foreach ($desList as $d): ?>
            <option value="<?= $d['id'] ?>" <?= ($_GET['filter_desa']??'')==$d['id']?'selected':'' ?>><?= e($d['nama_desa']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <select name="status" style="max-width:130px">
            <option value="">Semua Status</option>
            <option value="draft"  <?= $fStatus==='draft' ?'selected':'' ?>>Draft</option>
            <option value="terbit" <?= $fStatus==='terbit'?'selected':'' ?>>Terbit</option>
            <option value="arsip"  <?= $fStatus==='arsip' ?'selected':'' ?>>Arsip</option>
        </select>
        <select name="kat" style="max-width:150px">
            <option value="">Semua Kategori</option>
            <?php foreach ($labelKat as $k => $lbl): ?>
            <option value="<?= $k ?>" <?= $fKat===$k?'selected':'' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Cari</button>
        <a href="index.php" class="btn btn-secondary btn-sm">Reset</a>
    </form>
</div>

<!-- ACTION BAR -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px">
    <p class="text-muted" style="font-size:13px">
        Menampilkan <strong><?= number_format($total) ?></strong> berita
    </p>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php
        // Ambil id desa yang relevan untuk link portal
        $portalId = $idDesa;
        if (!$portalId && isSuperadmin()) {
            $firstDesa = $db->query("SELECT id FROM desa WHERE aktif=1 LIMIT 1")->fetchColumn();
            $portalId  = $firstDesa ?: 1;
        }
        ?>
        <a href="<?= APP_URL ?>/portal.php?id=<?= $portalId ?>" target="_blank" class="btn btn-secondary btn-sm">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            Lihat Portal
        </a>
        <a href="profil.php" class="btn btn-secondary btn-sm">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 010 14.14M4.93 4.93a10 10 0 000 14.14"/></svg>
            Pengaturan Portal
        </a>
        <a href="form.php" class="btn btn-primary btn-sm">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Tulis Berita
        </a>
    </div>
</div>

<!-- TABEL -->
<div class="card">
    <div class="table-wrap">
        <table class="dt" id="tblBerita">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Judul Berita</th>
                    <?php if (isSuperadmin()): ?><th>Desa</th><?php endif; ?>
                    <th>Kategori</th>
                    <th>Status</th>
                    <th>Featured</th>
                    <th class="num">Views</th>
                    <th>Tanggal Terbit</th>
                    <th style="text-align:center">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($beritaList)): ?>
            <tr>
                <td colspan="9">
                    <div class="empty-state">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        <p>Belum ada berita. Klik <strong>Tulis Berita</strong> untuk memulai.</p>
                    </div>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($beritaList as $i => $b): ?>
            <tr>
                <td class="text-muted" style="font-size:12px"><?= $offset + $i + 1 ?></td>
                <td style="max-width:320px">
                    <div style="font-weight:600;color:var(--text-1);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:300px"><?= e($b['judul']) ?></div>
                    <?php if ($b['penulis']): ?>
                    <div style="font-size:11.5px;color:var(--text-3)">✍️ <?= e($b['penulis']) ?></div>
                    <?php endif; ?>
                </td>
                <?php if (isSuperadmin()): ?>
                <td style="font-size:12.5px"><?= e($b['nama_desa']) ?></td>
                <?php endif; ?>
                <td>
                    <span style="display:inline-block;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:700;background:var(--brand-light);color:var(--brand)">
                        <?= e($labelKat[$b['kategori']] ?? $b['kategori']) ?>
                    </span>
                </td>
                <td>
                    <span style="display:inline-block;padding:3px 10px;border-radius:999px;font-size:11.5px;font-weight:700;<?= $badgeWarna[$b['status']] ?? '' ?>">
                        <?= ucfirst($b['status']) ?>
                    </span>
                </td>
                <td style="text-align:center">
                    <?php if ($b['tampil_di_depan']): ?>
                    <span title="Tampil di halaman depan portal" style="color:#f59e0b;font-size:18px">★</span>
                    <?php else: ?>
                    <span style="color:var(--text-3);font-size:16px">☆</span>
                    <?php endif; ?>
                </td>
                <td class="num text-muted"><?= number_format($b['views']) ?></td>
                <td style="font-size:12.5px;color:var(--text-3)">
                    <?= $b['tgl_terbit'] ? formatTanggal($b['tgl_terbit'], 'd M Y') : '<span style="color:#aaa">Belum dijadwalkan</span>' ?>
                </td>
                <td style="text-align:center;white-space:nowrap">
                    <a href="<?= APP_URL ?>/portal.php?id=<?= $b['id_desa'] ?>&berita=<?= e($b['slug']) ?>" target="_blank" class="btn btn-secondary btn-sm btn-icon" title="Lihat di Portal">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </a>
                    <a href="form.php?id=<?= $b['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="Edit">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </a>
                    <a href="hapus.php?id=<?= $b['id'] ?>" class="btn btn-danger btn-sm btn-icon" title="Hapus"
                       onclick="return konfirmasiHapus('<?= e(addslashes($b['judul'])) ?>')">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPage > 1): ?>
    <div class="pagination">
        <span>Halaman <?= $page ?> dari <?= $totalPage ?> (<?= number_format($total) ?> berita)</span>
        <div class="page-links">
            <?php $qBase = http_build_query(array_filter(['q'=>$cari,'status'=>$fStatus,'kat'=>$fKat])); ?>
            <a href="?<?= $qBase ?>&page=<?= max(1,$page-1) ?>" class="page-link <?= $page<=1?'disabled':'' ?>">‹</a>
            <?php for ($p = max(1,$page-2); $p <= min($totalPage,$page+2); $p++): ?>
            <a href="?<?= $qBase ?>&page=<?= $p ?>" class="page-link <?= $p===$page?'active':'' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <a href="?<?= $qBase ?>&page=<?= min($totalPage,$page+1) ?>" class="page-link <?= $page>=$totalPage?'disabled':'' ?>">›</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
