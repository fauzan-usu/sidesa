<?php
require_once __DIR__ . '/../../includes/auth.php';
requirePeran('superadmin');

$db = getDB();

// Tambah/Edit via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id   = (int) ($_POST['id'] ?? 0);
    $data = [
        'kode_desa'   => trim($_POST['kode_desa']),
        'nama_desa'   => trim($_POST['nama_desa']),
        'kecamatan'   => trim($_POST['kecamatan']),
        'kabupaten'   => trim($_POST['kabupaten']),
        'provinsi'    => trim($_POST['provinsi']) ?: 'Sumatera Utara',
        'kode_pos'    => trim($_POST['kode_pos']),
        'kepala_desa' => trim($_POST['kepala_desa']),
        'deskripsi'   => trim($_POST['deskripsi']),
    ];
    if ($id > 0) {
        $sets = implode(', ', array_map(fn($k) => "$k=?", array_keys($data)));
        $db->prepare("UPDATE desa SET $sets WHERE id=?")->execute([...array_values($data), $id]);
        setFlash('success', "Desa {$data['nama_desa']} berhasil diperbarui.");
    } else {
        $cols = implode(',', array_keys($data));
        $phs  = implode(',', array_fill(0, count($data), '?'));
        $db->prepare("INSERT INTO desa ($cols) VALUES ($phs)")->execute(array_values($data));
        setFlash('success', "Desa {$data['nama_desa']} berhasil ditambahkan.");
    }
    header('Location: index.php'); exit;
}

// Hapus desa
if (isset($_GET['hapus'])) {
    $id = (int) $_GET['hapus'];
    $nama = $db->prepare("SELECT nama_desa FROM desa WHERE id=?");
    $nama->execute([$id]);
    $row = $nama->fetch();
    if ($row) {
        $db->prepare("UPDATE desa SET aktif=0 WHERE id=?")->execute([$id]);
        setFlash('success', "Desa {$row['nama_desa']} berhasil dinonaktifkan.");
    }
    header('Location: index.php'); exit;
}

// Ambil data untuk edit
$editDesa = [];
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM desa WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editDesa = $stmt->fetch() ?: [];
}

$desas = $db->query("SELECT d.*, COUNT(w.id) as total_warga FROM desa d LEFT JOIN warga w ON d.id=w.id_desa AND w.status_hidup='Hidup' WHERE d.aktif=1 GROUP BY d.id ORDER BY d.nama_desa")->fetchAll();

$pageTitle = 'Manajemen Desa';
$pageBreadcrumb = ['Dashboard'=>APP_URL.'/index.php','Manajemen Desa'=>null];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row">
    <!-- Form -->
    <div style="flex:1;min-width:280px;max-width:400px">
        <div class="card">
            <div class="card-header">
                <span class="card-title"><?= $editDesa ? 'Edit Desa' : 'Tambah Desa Baru' ?></span>
                <?php if ($editDesa): ?><a href="index.php" class="btn btn-secondary btn-sm">Batal Edit</a><?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST" style="display:flex;flex-direction:column;gap:14px">
                    <?php if ($editDesa): ?><input type="hidden" name="id" value="<?= $editDesa['id'] ?>"><?php endif; ?>
                    <div class="form-group">
                        <label>Kode Desa <span class="req">*</span></label>
                        <input type="text" name="kode_desa" value="<?= e($editDesa['kode_desa'] ?? '') ?>" placeholder="DSA-001" required>
                    </div>
                    <div class="form-group">
                        <label>Nama Desa <span class="req">*</span></label>
                        <input type="text" name="nama_desa" value="<?= e($editDesa['nama_desa'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Kecamatan <span class="req">*</span></label>
                        <input type="text" name="kecamatan" value="<?= e($editDesa['kecamatan'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Kabupaten/Kota <span class="req">*</span></label>
                        <input type="text" name="kabupaten" value="<?= e($editDesa['kabupaten'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Provinsi</label>
                        <input type="text" name="provinsi" value="<?= e($editDesa['provinsi'] ?? 'Sumatera Utara') ?>">
                    </div>
                    <div class="form-group">
                        <label>Kode Pos</label>
                        <input type="text" name="kode_pos" value="<?= e($editDesa['kode_pos'] ?? '') ?>" maxlength="10">
                    </div>
                    <div class="form-group">
                        <label>Kepala Desa</label>
                        <input type="text" name="kepala_desa" value="<?= e($editDesa['kepala_desa'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea name="deskripsi" rows="3"><?= e($editDesa['deskripsi'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary"><?= $editDesa ? 'Simpan Perubahan' : 'Tambah Desa' ?></button>
                </form>
            </div>
        </div>
    </div>

    <!-- Tabel -->
    <div style="flex:2;min-width:300px">
        <div class="card">
            <div class="card-header">
                <span class="card-title">Daftar Desa (<?= count($desas) ?>)</span>
            </div>
            <div class="table-wrap">
                <table class="dt">
                    <thead>
                        <tr><th>Kode</th><th>Nama Desa</th><th>Kecamatan</th><th>Kepala Desa</th><th>Warga</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($desas)): ?>
                        <tr><td colspan="6" class="text-center text-muted" style="padding:32px">Belum ada desa terdaftar.</td></tr>
                        <?php else: ?>
                        <?php foreach ($desas as $d): ?>
                        <tr>
                            <td><span class="badge badge-brand"><?= e($d['kode_desa']) ?></span></td>
                            <td><strong><?= e($d['nama_desa']) ?></strong><br><small class="text-muted"><?= e($d['kabupaten']) ?></small></td>
                            <td style="font-size:12.5px"><?= e($d['kecamatan']) ?></td>
                            <td style="font-size:12.5px"><?= e($d['kepala_desa'] ?: '-') ?></td>
                            <td style="text-align:center"><span class="badge badge-green"><?= number_format($d['total_warga']) ?></span></td>
                            <td style="white-space:nowrap">
                                <a href="?edit=<?= $d['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="Edit">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </a>
                                <a href="?hapus=<?= $d['id'] ?>" class="btn btn-danger btn-sm btn-icon" onclick="return konfirmasiHapus('<?= e(addslashes($d['nama_desa'])) ?>')" title="Nonaktifkan">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
