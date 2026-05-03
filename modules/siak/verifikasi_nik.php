<?php
/**
 * VERIFIKASI NIK MANUAL KE SIAK KEMENDAGRI
 * FNA & Kawan-kawan — 2025
 */
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requirePeran('superadmin', 'admin_desa');
require_once __DIR__ . '/../../includes/SiakClient.php';

$db     = getDB();
$idDesa = getIdDesaAktif();

// Ambil konfigurasi SIAK
$stmtCfg = $db->prepare("SELECT * FROM siak_config WHERE id_desa=?");
$stmtCfg->execute([$idDesa ?: 0]);
$config = $stmtCfg->fetch();

if (!$config) {
    setFlash('error', 'Konfigurasi SIAK belum disiapkan. Buka menu Integrasi SIAK terlebih dahulu.');
    header('Location: index.php'); exit;
}

$siak   = new SiakClient($config, $db);
$hasil  = null;
$errors = [];

// Pre-fill dari parameter URL (misal dari halaman detail warga)
$nikPrefill = trim($_GET['nik'] ?? '');
if ($nikPrefill && strlen($nikPrefill) === 16 && ctype_digit($nikPrefill)) {
    // Cari data warga untuk pre-fill nama dan tanggal lahir
    $sw = $db->prepare("SELECT nama_lengkap, tanggal_lahir FROM warga WHERE nik=?");
    $sw->execute([$nikPrefill]);
    $prefillData = $sw->fetch() ?: [];
}

// Jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nik    = trim($_POST['nik'] ?? '');
    $nama   = trim($_POST['nama'] ?? '');
    $tglLhr = trim($_POST['tanggal_lahir'] ?? '');

    if (strlen($nik) !== 16 || !ctype_digit($nik)) {
        $errors[] = 'NIK harus 16 digit angka.';
    }
    if (empty($nama)) {
        $errors[] = 'Nama lengkap wajib diisi.';
    }

    if (empty($errors)) {
        // Lakukan verifikasi
        $hasil = $siak->verifikasiNIK(
            $nik, $nama, $tglLhr,
            (int)$config['id_desa'],
            (int)$_SESSION['id_pengguna']
        );

        // Jika sesuai dan ada warga dengan NIK ini, update status verifikasi
        if ($hasil['status'] === 'sesuai') {
            $db->prepare("UPDATE warga SET siak_verified=1, siak_verified_at=NOW() WHERE nik=? AND id_desa=?")
               ->execute([$nik, $config['id_desa']]);
        }
    }
}

// Cari warga di database lokal berdasarkan NIK (untuk cross-check)
$wargaLokal = null;
if (!empty($_POST['nik']) && strlen($_POST['nik']) === 16) {
    $sw = $db->prepare("SELECT w.*, d.nama_desa FROM warga w JOIN desa d ON w.id_desa=d.id WHERE w.nik=?");
    $sw->execute([trim($_POST['nik'])]);
    $wargaLokal = $sw->fetch();
}

$pageTitle      = 'Verifikasi NIK ke SIAK';
$pageBreadcrumb = ['Dashboard'=>APP_URL.'/index.php','Integrasi SIAK'=>APP_URL.'/modules/siak/index.php','Verifikasi NIK'=>null];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row">
    <!-- FORM VERIFIKASI -->
    <div class="col-6">
        <div class="card">
            <div class="card-header">
                <span class="card-title">🔍 Verifikasi NIK ke SIAK Kemendagri</span>
                <span style="font-size:11.5px;padding:4px 10px;border-radius:999px;font-weight:700;background:<?= $config['mode']==='aktif'?'#d1fae5':'#ede9ff' ?>;color:<?= $config['mode']==='aktif'?'#065f46':'#5B4FCF' ?>">
                    <?= $config['mode']==='aktif' ? '🟢 Mode Aktif' : '🔵 Mode Simulasi' ?>
                </span>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                <div class="alert alert-error mb-2"><?php foreach ($errors as $e): ?><div>• <?= e($e) ?></div><?php endforeach; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group mb-2">
                        <label class="field">Nomor Induk Kependudukan (NIK) <span class="req">*</span></label>
                        <input type="text" name="nik" maxlength="16"
                               value="<?= e($_POST['nik'] ?? $nikPrefill ?? '') ?>"
                               placeholder="Masukkan 16 digit NIK"
                               oninput="this.value=this.value.replace(/\D/g,'').slice(0,16);updateNIKInfo(this.value)"
                               style="font-family:monospace;font-size:18px;letter-spacing:2px;text-align:center" required autofocus>
                        <div id="nikInfo" style="font-size:12px;color:var(--text-3);margin-top:4px"></div>
                    </div>
                    <div class="form-group mb-2">
                        <label class="field">Nama Lengkap (sesuai KTP) <span class="req">*</span></label>
                        <input type="text" name="nama" value="<?= e($_POST['nama'] ?? $wargaLokal['nama_lengkap'] ?? $prefillData['nama_lengkap'] ?? '') ?>"
                               placeholder="Nama sesuai KTP" required>
                    </div>
                    <div class="form-group mb-3">
                        <label class="field">Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir"
                               value="<?= e($_POST['tanggal_lahir'] ?? $wargaLokal['tanggal_lahir'] ?? $prefillData['tanggal_lahir'] ?? '') ?>"
                               max="<?= date('Y-m-d') ?>">
                        <span class="form-hint">Opsional namun meningkatkan akurasi verifikasi</span>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;padding:12px">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        Verifikasi ke SIAK Kemendagri
                    </button>
                </form>

                <!-- Data warga lokal jika ditemukan -->
                <?php if ($wargaLokal): ?>
                <div style="margin-top:18px;padding:14px;background:#f0f5ff;border-radius:var(--radius);border:1px solid var(--brand-light)">
                    <div style="font-size:12px;font-weight:700;color:var(--brand);margin-bottom:8px;letter-spacing:.04em">DATA DI DATABASE LOKAL SiDesa</div>
                    <?php foreach ([
                        'Nama'     => $wargaLokal['nama_lengkap'],
                        'Desa'     => $wargaLokal['nama_desa'],
                        'TTL'      => $wargaLokal['tempat_lahir'].', '.formatTanggal($wargaLokal['tanggal_lahir']),
                        'Terdaftar'=> formatTanggal($wargaLokal['created_at'],'d M Y'),
                    ] as $k => $v): ?>
                    <div style="display:flex;gap:8px;font-size:13px;padding:3px 0">
                        <span style="color:var(--text-3);min-width:80px"><?= $k ?></span>
                        <span style="font-weight:600"><?= e($v) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <div style="display:flex;gap:8px;font-size:13px;padding:3px 0">
                        <span style="color:var(--text-3);min-width:80px">Status SIAK</span>
                        <span style="font-weight:700;color:<?= $wargaLokal['siak_verified']?'var(--green)':'var(--amber)' ?>">
                            <?= $wargaLokal['siak_verified'] ? '✅ Terverifikasi' : '⏳ Belum diverifikasi' ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- HASIL VERIFIKASI -->
    <div class="col-6">
        <?php if ($hasil): ?>
        <?php
        $statusConfig = [
            'sesuai'          => ['bg'=>'#d1fae5','border'=>'#16a34a','icon'=>'✅','judul'=>'NIK SESUAI','sub'=>'Data cocok dengan database SIAK Kemendagri'],
            'tidak_sesuai'    => ['bg'=>'#fee2e2','border'=>'#dc2626','icon'=>'❌','judul'=>'TIDAK SESUAI','sub'=>'Data tidak cocok dengan database SIAK'],
            'tidak_ditemukan' => ['bg'=>'#fef3c7','border'=>'#d97706','icon'=>'⚠️','judul'=>'TIDAK DITEMUKAN','sub'=>'NIK tidak ditemukan dalam database SIAK'],
            'error'           => ['bg'=>'#fee2e2','border'=>'#dc2626','icon'=>'🔴','judul'=>'ERROR KONEKSI','sub'=>'Gagal menghubungi server SIAK'],
        ];
        $sc = $statusConfig[$hasil['status']] ?? $statusConfig['error'];
        ?>
        <div style="background:<?= $sc['bg'] ?>;border:2px solid <?= $sc['border'] ?>;border-radius:var(--radius-lg);padding:24px;text-align:center;margin-bottom:16px">
            <div style="font-size:40px;margin-bottom:10px"><?= $sc['icon'] ?></div>
            <div style="font-size:20px;font-weight:900;color:<?= $sc['border'] ?>;letter-spacing:.02em"><?= $sc['judul'] ?></div>
            <div style="font-size:13px;color:var(--text-2);margin-top:4px"><?= $sc['sub'] ?></div>
            <div style="margin-top:14px;padding:12px;background:rgba(255,255,255,.6);border-radius:var(--radius);font-size:13.5px;color:var(--text-1);line-height:1.6">
                <?= e($hasil['pesan']) ?>
            </div>
        </div>

        <?php if (!empty($hasil['detail'])): ?>
        <div class="card" style="box-shadow:none">
            <div class="card-header" style="padding:12px 16px"><span class="card-title" style="font-size:13px">Detail Respons</span></div>
            <div class="card-body" style="padding:14px 16px">
                <?php foreach ($hasil['detail'] as $k => $v): ?>
                <?php if (is_string($v) || is_numeric($v)): ?>
                <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:7px 0;border-bottom:1px solid var(--border);font-size:13px">
                    <span style="color:var(--text-3);font-weight:500"><?= e(str_replace('_',' ',ucfirst($k))) ?></span>
                    <span style="font-weight:600;text-align:right;max-width:240px;word-break:break-word"><?= e($v) ?></span>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- Panduan -->
        <div class="card" style="box-shadow:none">
            <div class="card-header" style="padding:12px 16px"><span class="card-title" style="font-size:13px">Cara Membaca NIK</span></div>
            <div class="card-body" style="font-size:13px;line-height:1.7">
                <div style="font-family:monospace;font-size:16px;letter-spacing:3px;text-align:center;padding:12px;background:var(--bg);border-radius:var(--radius);margin-bottom:14px;color:var(--brand);font-weight:700">
                    XX XX XX DDMMYY XXXX
                </div>
                <?php foreach ([
                    ['Digit 1-2',  'Kode Provinsi (sesuai Kemendagri)'],
                    ['Digit 3-4',  'Kode Kabupaten/Kota'],
                    ['Digit 5-6',  'Kode Kecamatan'],
                    ['Digit 7-12', 'Tgl lahir DDMMYY (perempuan: tgl+40)'],
                    ['Digit 13-16','Nomor urut penduduk'],
                ] as [$d, $k]): ?>
                <div style="display:flex;gap:12px;padding:6px 0;border-bottom:1px solid var(--border)">
                    <span style="font-family:monospace;font-weight:700;color:var(--brand);min-width:80px"><?= $d ?></span>
                    <span style="color:var(--text-2)"><?= $k ?></span>
                </div>
                <?php endforeach; ?>
                <div style="margin-top:14px;padding:12px;background:var(--brand-light);border-radius:var(--radius);font-size:12.5px;color:var(--brand-dark)">
                    💡 Mode Simulasi memvalidasi kode wilayah dari 6 digit pertama NIK menggunakan referensi Kemendagri yang sudah diunduh ke database lokal.
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Analisis NIK saat diketik
function updateNIKInfo(nik) {
    const el = document.getElementById('nikInfo');
    if (nik.length < 6) { el.textContent = ''; return; }
    const prov   = nik.substring(0, 2);
    const kab    = nik.substring(0, 4);
    const kec    = nik.substring(0, 6);
    const dd     = parseInt(nik.substring(6, 8));
    const mm     = parseInt(nik.substring(8, 10));
    const yy     = parseInt(nik.substring(10, 12));
    const isPer  = dd > 40;
    const ddAsli = isPer ? dd - 40 : dd;
    const thn    = yy <= parseInt(new Date().getFullYear().toString().slice(-2)) ? '20'+String(yy).padStart(2,'0') : '19'+String(yy).padStart(2,'0');
    let info = `Kode wilayah: ${prov}.${kab.slice(2)}.${kec.slice(4)}`;
    if (nik.length >= 12) {
        const tgl = `${String(ddAsli).padStart(2,'0')}/${String(mm).padStart(2,'0')}/${thn}`;
        info += ` | Tgl lahir: ${tgl} | JK: ${isPer ? 'Perempuan' : 'Laki-laki'}`;
    }
    el.textContent = info;
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
