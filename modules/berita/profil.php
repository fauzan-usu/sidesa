<?php
/**
 * PENGATURAN PROFIL PORTAL DESA
 * FNA & Kawan-kawan — 2025
 */
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requirePeran('superadmin', 'admin_desa');

$db     = getDB();
$idDesa = getIdDesaAktif();

// Superadmin memilih desa lewat GET ?desa=
if (isSuperadmin()) {
    $desaParam = (int)($_GET['desa'] ?? $idDesa ?? 0);
    if (!$desaParam) {
        // Redirect ke desa pertama
        $first = $db->query("SELECT id FROM desa WHERE aktif=1 LIMIT 1")->fetchColumn();
        if ($first) { header("Location: profil.php?desa=$first"); exit; }
    }
    $idDesa = $desaParam;
}

if (!$idDesa) {
    setFlash('error','Desa tidak ditemukan.'); header('Location: index.php'); exit;
}

// Ambil data desa & profil
$stDesa = $db->prepare("SELECT * FROM desa WHERE id=?");
$stDesa->execute([$idDesa]);
$rowDesa = $stDesa->fetch();

$stProfil = $db->prepare("SELECT * FROM profil_desa WHERE id_desa=?");
$stProfil->execute([$idDesa]);
$profil   = $stProfil->fetch() ?: [];

// Proses simpan
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'slogan'       => trim($_POST['slogan'] ?? ''),
        'visi'         => trim($_POST['visi'] ?? ''),
        'misi'         => trim($_POST['misi'] ?? ''),
        'sejarah'      => trim($_POST['sejarah'] ?? ''),
        'warna_tema'   => trim($_POST['warna_tema'] ?? '#5B4FCF'),
        'luas_wilayah' => !empty($_POST['luas_wilayah']) ? (float)$_POST['luas_wilayah'] : null,
        'jumlah_dusun' => !empty($_POST['jumlah_dusun']) ? (int)$_POST['jumlah_dusun'] : null,
        'batas_utara'  => trim($_POST['batas_utara'] ?? ''),
        'batas_selatan'=> trim($_POST['batas_selatan'] ?? ''),
        'batas_timur'  => trim($_POST['batas_timur'] ?? ''),
        'batas_barat'  => trim($_POST['batas_barat'] ?? ''),
        'telepon'      => trim($_POST['telepon'] ?? ''),
        'email'        => trim($_POST['email'] ?? ''),
        'jam_layanan'  => trim($_POST['jam_layanan'] ?? ''),
        'portal_aktif' => isset($_POST['portal_aktif']) ? 1 : 0,
    ];

    // Validasi warna hex
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $data['warna_tema'])) {
        $data['warna_tema'] = '#5B4FCF';
    }

    // Upload foto sampul
    $data['foto_sampul'] = $profil['foto_sampul'] ?? null;
    if (!empty($_FILES['foto_sampul']['name'])) {
        $file    = $_FILES['foto_sampul'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp'];
        if (!in_array($ext, $allowed)) {
            $errors[] = "Format foto harus JPG, PNG, atau WEBP.";
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = "Ukuran foto sampul maksimal 5 MB.";
        } else {
            $dir = UPLOAD_DIR . 'profil/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $namaFile = "sampul_{$idDesa}_" . time() . ".$ext";
            if (move_uploaded_file($file['tmp_name'], $dir . $namaFile)) {
                // Hapus lama
                if (!empty($profil['foto_sampul']) && file_exists($dir . $profil['foto_sampul'])) {
                    unlink($dir . $profil['foto_sampul']);
                }
                $data['foto_sampul'] = $namaFile;
            } else {
                $errors[] = "Gagal menyimpan foto sampul. Cek permission folder uploads/profil/.";
            }
        }
    }

    if (empty($errors)) {
        // Update data desa (kepala_desa, bendahara)
        $db->prepare("UPDATE desa SET kepala_desa=?, bendahara=? WHERE id=?")
           ->execute([trim($_POST['kepala_desa']??''), trim($_POST['bendahara']??''), $idDesa]);

        // Upsert profil_desa
        if ($profil) {
            $sets = implode(', ', array_map(fn($k) => "$k=?", array_keys($data)));
            $db->prepare("UPDATE profil_desa SET $sets WHERE id_desa=?")
               ->execute([...array_values($data), $idDesa]);
        } else {
            $data['id_desa'] = $idDesa;
            $cols = implode(',', array_keys($data));
            $phs  = implode(',', array_fill(0, count($data), '?'));
            $db->prepare("INSERT INTO profil_desa ($cols) VALUES ($phs)")
               ->execute(array_values($data));
        }

        logAktivitas('Update profil portal', 'profil_desa', $idDesa, $rowDesa['nama_desa']);
        setFlash('success', 'Pengaturan portal berhasil disimpan.');
        header("Location: profil.php" . (isSuperadmin() ? "?desa=$idDesa" : "")); exit;
    }
}

// Nilai form: POST → profil DB → fallback
$val = fn(string $k, string $fb='') => htmlspecialchars($_POST[$k] ?? $profil[$k] ?? $rowDesa[$k] ?? $fb, ENT_QUOTES);

// Daftar desa untuk switching superadmin
$desList = isSuperadmin()
    ? $db->query("SELECT id, nama_desa FROM desa WHERE aktif=1 ORDER BY nama_desa")->fetchAll()
    : [];

$pageTitle      = 'Pengaturan Portal Desa';
$pageBreadcrumb = ['Dashboard'=>APP_URL.'/index.php','Berita'=>APP_URL.'/modules/berita/index.php','Pengaturan Portal'=>null];
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Switcher desa untuk superadmin -->
<?php if (isSuperadmin() && !empty($desList)): ?>
<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap">
    <span style="font-size:13px;font-weight:600;color:var(--text-2)">Pengaturan untuk:</span>
    <div style="display:flex;gap:6px;flex-wrap:wrap">
        <?php foreach ($desList as $d): ?>
        <a href="profil.php?desa=<?= $d['id'] ?>"
           style="padding:6px 14px;border-radius:20px;font-size:12.5px;font-weight:600;border:1.5px solid var(--border);text-decoration:none;transition:background .15s;<?= $idDesa==$d['id'] ? 'background:var(--brand);color:#fff;border-color:var(--brand)' : 'color:var(--text-2);background:#fff' ?>">
            <?= e($d['nama_desa']) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-error mb-2"><?php foreach ($errors as $err): ?><div>• <?= e($err) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">

<div style="display:grid;grid-template-columns:1fr 320px;gap:24px;align-items:flex-start">

    <!-- ── KOLOM KIRI ── -->
    <div style="display:flex;flex-direction:column;gap:20px">

        <!-- Identitas Desa -->
        <div class="card">
            <div class="card-header"><span class="card-title">Identitas Desa</span></div>
            <div class="card-body">
                <div class="form-grid mb-2">
                    <div class="form-group">
                        <label class="field">Kepala Desa</label>
                        <input type="text" name="kepala_desa" value="<?= $val('kepala_desa') ?>" placeholder="Nama lengkap kepala desa">
                    </div>
                    <div class="form-group">
                        <label class="field">Bendahara Desa</label>
                        <input type="text" name="bendahara" value="<?= $val('bendahara') ?>" placeholder="Nama bendahara">
                    </div>
                    <div class="form-group">
                        <label class="field">Telepon Kantor</label>
                        <input type="tel" name="telepon" value="<?= $val('telepon') ?>" placeholder="08xx-xxxx-xxxx">
                    </div>
                    <div class="form-group">
                        <label class="field">Email Desa</label>
                        <input type="email" name="email" value="<?= $val('email') ?>" placeholder="desa@email.com">
                    </div>
                    <div class="form-group full">
                        <label class="field">Slogan / Tagline Desa</label>
                        <input type="text" name="slogan" value="<?= $val('slogan') ?>" placeholder="Contoh: Desa Maju, Sejahtera, dan Mandiri" maxlength="200">
                    </div>
                    <div class="form-group full">
                        <label class="field">Jam Layanan</label>
                        <input type="text" name="jam_layanan" value="<?= $val('jam_layanan','Senin–Jumat, 08.00–16.00 WIB') ?>" placeholder="Senin–Jumat, 08.00–16.00 WIB">
                    </div>
                </div>
            </div>
        </div>

        <!-- Visi, Misi, Sejarah -->
        <div class="card">
            <div class="card-header"><span class="card-title">Visi, Misi &amp; Sejarah</span></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:16px">
                <div class="form-group">
                    <label class="field">Visi Desa</label>
                    <textarea name="visi" rows="3" placeholder="Terwujudnya desa yang…"><?= $val('visi') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="field">Misi Desa</label>
                    <textarea name="misi" rows="5" placeholder="1. …&#10;2. …&#10;3. …"><?= $val('misi') ?></textarea>
                    <span class="form-hint">Gunakan angka bernomor untuk memisahkan setiap poin misi.</span>
                </div>
                <div class="form-group">
                    <label class="field">Sejarah Singkat Desa <span style="color:var(--text-3);font-weight:400">(opsional)</span></label>
                    <textarea name="sejarah" rows="5" placeholder="Desa ini didirikan pada…"><?= $val('sejarah') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Batas Wilayah -->
        <div class="card">
            <div class="card-header"><span class="card-title">Data Geografis</span></div>
            <div class="card-body">
                <div class="form-grid mb-2">
                    <div class="form-group">
                        <label class="field">Luas Wilayah (Ha)</label>
                        <input type="number" name="luas_wilayah" step="0.01" min="0" value="<?= $val('luas_wilayah') ?>" placeholder="125.50">
                    </div>
                    <div class="form-group">
                        <label class="field">Jumlah Dusun</label>
                        <input type="number" name="jumlah_dusun" min="0" value="<?= $val('jumlah_dusun') ?>" placeholder="5">
                    </div>
                    <div class="form-group">
                        <label class="field">Batas Utara</label>
                        <input type="text" name="batas_utara" value="<?= $val('batas_utara') ?>" placeholder="Desa / kecamatan sebelah utara">
                    </div>
                    <div class="form-group">
                        <label class="field">Batas Selatan</label>
                        <input type="text" name="batas_selatan" value="<?= $val('batas_selatan') ?>" placeholder="Desa / kecamatan sebelah selatan">
                    </div>
                    <div class="form-group">
                        <label class="field">Batas Timur</label>
                        <input type="text" name="batas_timur" value="<?= $val('batas_timur') ?>" placeholder="Desa / kecamatan sebelah timur">
                    </div>
                    <div class="form-group">
                        <label class="field">Batas Barat</label>
                        <input type="text" name="batas_barat" value="<?= $val('batas_barat') ?>" placeholder="Desa / kecamatan sebelah barat">
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /.kolom-kiri -->

    <!-- ── KOLOM KANAN ── -->
    <div style="display:flex;flex-direction:column;gap:16px;position:sticky;top:80px">

        <!-- Aksi simpan -->
        <div class="card" style="box-shadow:none">
            <div class="card-body" style="padding:16px;display:flex;flex-direction:column;gap:10px">
                <div style="display:flex;align-items:center;gap:10px;padding:10px;background:var(--bg);border-radius:var(--radius);border:1px solid var(--border)">
                    <input type="checkbox" name="portal_aktif" id="portalAktif" value="1"
                           <?= ($profil['portal_aktif'] ?? 1) ? 'checked' : '' ?>
                           style="width:auto;accent-color:var(--brand)">
                    <label for="portalAktif" style="cursor:pointer;font-size:13px;font-weight:600;margin:0">
                        Portal Aktif (tampil ke publik)
                    </label>
                </div>
                <a href="<?= APP_URL ?>/portal.php?id=<?= $idDesa ?>" target="_blank" class="btn btn-secondary" style="justify-content:center">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    Preview Portal
                </a>
                <button type="submit" class="btn btn-primary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
                    Simpan Pengaturan
                </button>
            </div>
        </div>

        <!-- Warna Tema -->
        <div class="card" style="box-shadow:none">
            <div class="card-header" style="padding:12px 16px"><span class="card-title" style="font-size:13px">Warna Tema Portal</span></div>
            <div class="card-body" style="padding:14px 16px">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
                    <input type="color" name="warna_tema" id="warnaTema"
                           value="<?= $val('warna_tema','#5B4FCF') ?>"
                           style="width:48px;height:40px;border:none;border-radius:8px;cursor:pointer;padding:2px">
                    <div>
                        <div id="warnaPreviewText" style="font-size:13px;font-weight:700"><?= $val('warna_tema','#5B4FCF') ?></div>
                        <div style="font-size:11.5px;color:var(--text-3)">Warna utama portal desa</div>
                    </div>
                </div>
                <!-- Palet preset -->
                <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:6px">
                    <?php foreach (['#5B4FCF','#0051d5','#16a34a','#dc2626','#9333ea','#0891b2','#d97706','#be185d','#1d4ed8','#065f46'] as $hex): ?>
                    <button type="button" onclick="piliWarna('<?= $hex ?>')"
                            style="height:28px;border-radius:6px;border:2px solid transparent;cursor:pointer;background:<?= $hex ?>;transition:transform .1s,border-color .1s"
                            onmouseover="this.style.transform='scale(1.15)'" onmouseout="this.style.transform=''">
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Foto Sampul -->
        <div class="card" style="box-shadow:none">
            <div class="card-header" style="padding:12px 16px"><span class="card-title" style="font-size:13px">Foto Sampul Portal</span></div>
            <div class="card-body" style="padding:14px 16px">
                <?php if (!empty($profil['foto_sampul'])): ?>
                <div style="margin-bottom:10px;border-radius:8px;overflow:hidden">
                    <img src="<?= UPLOAD_URL ?>profil/<?= e($profil['foto_sampul']) ?>" alt="Foto sampul"
                         style="width:100%;height:100px;object-fit:cover;display:block">
                </div>
                <?php endif; ?>
                <label for="fotoSampul" style="display:block;border:2px dashed var(--border);border-radius:var(--radius);padding:16px;text-align:center;cursor:pointer;font-size:13px;color:var(--text-3)" id="dropSampul">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="display:block;margin:0 auto 6px;opacity:.4"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    <?= empty($profil['foto_sampul']) ? 'Upload foto sampul' : 'Ganti foto sampul' ?><br>
                    <span style="font-size:11px">JPG, PNG, WEBP · Maks 5 MB</span>
                </label>
                <input type="file" name="foto_sampul" id="fotoSampul" accept="image/*" style="display:none">
            </div>
        </div>

        <!-- Info -->
        <div style="background:var(--brand-light);border:1px solid var(--border);border-radius:var(--radius);padding:14px;font-size:12.5px;color:var(--brand-dark)">
            <strong>💡 Tips:</strong> Setelah menyimpan pengaturan, klik <em>Preview Portal</em> untuk melihat tampilan yang akan dilihat publik.
        </div>

    </div><!-- /.kolom-kanan -->
</div>

</form>

<script>
const inputWarna = document.getElementById('warnaTema');
const teksWarna  = document.getElementById('warnaPreviewText');

function piliWarna(hex) {
    inputWarna.value = hex;
    teksWarna.textContent = hex;
}
inputWarna.addEventListener('input', () => {
    teksWarna.textContent = inputWarna.value;
});

// Preview foto sampul
document.getElementById('fotoSampul').addEventListener('change', function() {
    if (!this.files[0]) return;
    const zone = document.getElementById('dropSampul');
    zone.style.padding = '0';
    zone.innerHTML = `<img src="${URL.createObjectURL(this.files[0])}" style="width:100%;height:100px;object-fit:cover;display:block;border-radius:6px">`;
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
