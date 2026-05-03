<?php
/**
 * PENGATURAN INTEGRASI SIAK
 * FNA & Kawan-kawan — 2025
 */
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requirePeran('superadmin', 'admin_desa');

$db     = getDB();
$idDesa = getIdDesaAktif();

$stmtCfg = $db->prepare("SELECT * FROM siak_config WHERE id_desa=?");
$stmtCfg->execute([$idDesa ?: 0]);
$config = $stmtCfg->fetch() ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'mode'            => in_array($_POST['mode']??'', ['simulasi','aktif','nonaktif']) ? $_POST['mode'] : 'simulasi',
        'verifikasi_aktif'=> isset($_POST['verifikasi_aktif']) ? 1 : 0,
        'status_mou'      => in_array($_POST['status_mou']??'', ['belum_diajukan','dalam_proses','disetujui','ditolak']) ? $_POST['status_mou'] : 'belum_diajukan',
        'nomor_mou'       => trim($_POST['nomor_mou'] ?? ''),
        'tgl_mou'         => !empty($_POST['tgl_mou']) ? $_POST['tgl_mou'] : null,
        'catatan'         => trim($_POST['catatan'] ?? ''),
    ];
    // Kredensial hanya disimpan jika diisi (tidak hapus yang sudah ada)
    if (!empty($_POST['client_id']))     $data['client_id']     = trim($_POST['client_id']);
    if (!empty($_POST['client_secret'])) $data['client_secret'] = trim($_POST['client_secret']);

    if ($config) {
        $sets = implode(',', array_map(fn($k) => "$k=?", array_keys($data)));
        $db->prepare("UPDATE siak_config SET $sets WHERE id_desa=?")->execute([...array_values($data), $idDesa]);
    } else {
        $data['id_desa'] = $idDesa;
        $cols = implode(',', array_keys($data));
        $phs  = implode(',', array_fill(0, count($data), '?'));
        $db->prepare("INSERT INTO siak_config ($cols) VALUES ($phs)")->execute(array_values($data));
    }
    logAktivitas('Update pengaturan SIAK', 'siak_config', $idDesa);
    setFlash('success', 'Pengaturan SIAK berhasil disimpan.');
    header('Location: pengaturan.php'); exit;
}

$val = fn(string $k, string $fb='') => htmlspecialchars($_POST[$k] ?? $config[$k] ?? $fb, ENT_QUOTES);

$pageTitle      = 'Pengaturan Integrasi SIAK';
$pageBreadcrumb = ['Dashboard'=>APP_URL.'/index.php','Integrasi SIAK'=>APP_URL.'/modules/siak/index.php','Pengaturan'=>null];
require_once __DIR__ . '/../../includes/header.php';
?>

<div style="max-width:700px">
<form method="POST">
<div class="card mb-3">
    <div class="card-header"><span class="card-title">⚙️ Mode Operasi</span></div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:14px">
        <?php foreach ([
            ['simulasi', '🔵 Mode Simulasi', 'Tidak menghubungi server Dukcapil. Cocok untuk pengembangan dan pengujian.'],
            ['aktif',    '🟢 Mode Aktif (Live)', 'Menghubungi API SIAK Dukcapil yang sesungguhnya. Memerlukan MOU dan kredensial API.'],
            ['nonaktif', '⬜ Nonaktif', 'Menonaktifkan seluruh fitur integrasi SIAK untuk desa ini.'],
        ] as [$v, $lbl, $sub]): ?>
        <label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;padding:12px 16px;border:1.5px solid <?= ($val('mode','simulasi')===$v)?'var(--brand)':'var(--border)' ?>;border-radius:var(--radius-lg);background:<?= ($val('mode','simulasi')===$v)?'var(--brand-light)':'var(--surface)' ?>">
            <input type="radio" name="mode" value="<?= $v ?>" <?= $val('mode','simulasi')===$v?'checked':'' ?> style="width:auto;margin-top:3px">
            <div>
                <div style="font-weight:700;font-size:13.5px"><?= $lbl ?></div>
                <div style="font-size:12.5px;color:var(--text-3);margin-top:2px"><?= $sub ?></div>
            </div>
        </label>
        <?php endforeach; ?>
        <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:var(--bg);border-radius:var(--radius);border:1px solid var(--border)">
            <input type="checkbox" name="verifikasi_aktif" id="vauto" value="1" <?= $val('verifikasi_aktif')?'checked':'' ?> style="width:auto;accent-color:var(--brand)">
            <label for="vauto" style="cursor:pointer;font-size:13px;font-weight:600;margin:0">
                Aktifkan verifikasi NIK otomatis saat menginput warga baru
            </label>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><span class="card-title">📋 Status MOU Dukcapil</span></div>
    <div class="card-body">
        <div class="form-grid mb-2">
            <div class="form-group">
                <label class="field">Status MOU</label>
                <select name="status_mou">
                    <?php foreach (['belum_diajukan'=>'Belum Diajukan','dalam_proses'=>'Dalam Proses Pengajuan','disetujui'=>'MOU Disetujui','ditolak'=>'Ditolak'] as $v => $lbl): ?>
                    <option value="<?= $v ?>" <?= $val('status_mou')===$v?'selected':'' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="field">Tanggal MOU</label>
                <input type="date" name="tgl_mou" value="<?= $val('tgl_mou') ?>">
            </div>
            <div class="form-group full">
                <label class="field">Nomor MOU / Perjanjian Kerja Sama</label>
                <input type="text" name="nomor_mou" value="<?= $val('nomor_mou') ?>" placeholder="Contoh: 470/MOU-DUKCAPIL/2024/001">
            </div>
            <div class="form-group full">
                <label class="field">Catatan</label>
                <textarea name="catatan" rows="3" placeholder="Catatan proses MOU, kontak Dukcapil, dsb."><?= $val('catatan') ?></textarea>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <span class="card-title">🔑 Kredensial API Dukcapil</span>
        <span style="font-size:12px;color:var(--amber);font-weight:600">⚠ Hanya diisi setelah MOU disetujui</span>
    </div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:14px">
        <div style="padding:12px 16px;background:#fef3c7;border-radius:var(--radius);border:1px solid #fcd34d;font-size:13px;color:var(--amber)">
            <strong>Perhatian:</strong> Kredensial ini diberikan langsung oleh Ditjen Dukcapil Kemendagri setelah MOU disetujui. Jangan isi dengan sembarang nilai. Jika dikosongkan, nilai yang sudah tersimpan tidak akan berubah.
        </div>
        <div class="form-group">
            <label class="field">Client ID</label>
            <input type="text" name="client_id" placeholder="Diisi setelah MOU disetujui Dukcapil" autocomplete="off">
            <?php if (!empty($config['client_id'])): ?>
            <span class="form-hint" style="color:var(--green)">✅ Client ID sudah tersimpan (nilai disembunyikan untuk keamanan)</span>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label class="field">Client Secret</label>
            <input type="password" name="client_secret" placeholder="Diisi setelah MOU disetujui Dukcapil" autocomplete="off">
            <?php if (!empty($config['client_secret'])): ?>
            <span class="form-hint" style="color:var(--green)">✅ Client Secret sudah tersimpan</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div style="display:flex;gap:10px;justify-content:flex-end">
    <a href="index.php" class="btn btn-secondary">Batal</a>
    <button type="submit" class="btn btn-primary">💾 Simpan Pengaturan</button>
</div>
</form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
