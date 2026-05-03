<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

// Load SiakClient untuk verifikasi NIK otomatis
require_once __DIR__ . '/../../includes/SiakClient.php';

$db     = getDB();
$idDesa = getIdDesaAktif();
$id     = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;

// Ambil konfigurasi SIAK desa ini
$siakConfig = null;
$siakClient = null;
if ($idDesa) {
    $sc = $db->prepare("SELECT * FROM siak_config WHERE id_desa=?");
    $sc->execute([$idDesa]);
    $siakConfig = $sc->fetch();
    if ($siakConfig) {
        $siakClient = new SiakClient($siakConfig, $db);
    }
}
$siakAktif = $siakConfig && ($siakConfig['verifikasi_aktif'] ?? 0) == 1;

// Ambil data jika edit
$warga = [];
if ($isEdit) {
    $stmt = $db->prepare("SELECT * FROM warga WHERE id = ?");
    $stmt->execute([$id]);
    $warga = $stmt->fetch();
    if (!$warga) { setFlash('error', 'Data warga tidak ditemukan.'); header('Location: index.php'); exit; }
    if (!isSuperadmin() && $idDesa && $warga['id_desa'] != $idDesa) {
        http_response_code(403); die('Akses ditolak.');
    }
}


// Proses form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'id_desa'       => (int) $_POST['id_desa'],
        'nik'           => trim($_POST['nik']),
        'no_kk'         => trim($_POST['no_kk']),
        'nama_lengkap'  => trim($_POST['nama_lengkap']),
        'tempat_lahir'  => trim($_POST['tempat_lahir']),
        'tanggal_lahir' => $_POST['tanggal_lahir'],
        'jenis_kelamin' => $_POST['jenis_kelamin'],
        'agama'         => $_POST['agama'],
        'status_kawin'  => $_POST['status_kawin'],
        'pendidikan'    => $_POST['pendidikan'],
        'pekerjaan'     => trim($_POST['pekerjaan']),
        'alamat'        => trim($_POST['alamat']),
        'rt'            => trim($_POST['rt']),
        'rw'            => trim($_POST['rw']),
        'no_telepon'    => trim($_POST['no_telepon']),
        'status_hidup'  => $_POST['status_hidup'],
        'status_dtks'   => isset($_POST['status_dtks']) ? 1 : 0,
    ];

    // Validasi
    $errors = [];
    if (strlen($data['nik']) !== 16 || !ctype_digit($data['nik'])) $errors[] = "NIK harus 16 digit angka.";
    if (strlen($data['no_kk']) !== 16 || !ctype_digit($data['no_kk'])) $errors[] = "No. KK harus 16 digit angka.";
    if (empty($data['nama_lengkap'])) $errors[] = "Nama lengkap wajib diisi.";
    if (empty($data['tanggal_lahir'])) $errors[] = "Tanggal lahir wajib diisi.";
    if (empty($data['alamat'])) $errors[] = "Alamat wajib diisi.";

    // Cek NIK unik (kecuali diri sendiri saat edit)
    if (empty($errors)) {
        $qNik = $db->prepare("SELECT id FROM warga WHERE nik=? AND id != ?");
        $qNik->execute([$data['nik'], $isEdit ? $id : 0]);
        if ($qNik->fetch()) $errors[] = "NIK {$data['nik']} sudah terdaftar untuk warga lain.";
    }

    // ── VERIFIKASI NIK KE SIAK (jika aktif) ──────────────────
    $siakHasil       = null;
    $siakPesan       = '';
    $siakStatusBadge = '';
    if (empty($errors) && $siakAktif && $siakClient && !$isEdit) {
        $siakHasil = $siakClient->verifikasiNIK(
            $data['nik'],
            $data['nama_lengkap'],
            $data['tanggal_lahir'],
            (int)($siakConfig['id_desa'] ?? $idDesa),
            (int)($_SESSION['id_pengguna'] ?? 0)
        );
        // Catat status verifikasi untuk disimpan bersama data warga
        $data['siak_verified']    = $siakHasil['status'] === 'sesuai' ? 1 : 0;
        $data['siak_verified_at'] = $siakHasil['status'] === 'sesuai' ? date('Y-m-d H:i:s') : null;
    }

    if (empty($errors)) {
        if ($isEdit) {
            $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
            $stmt = $db->prepare("UPDATE warga SET $sets WHERE id = ?");
            $stmt->execute([...array_values($data), $id]);
            logAktivitas('Edit warga', 'warga', $id, $data['nama_lengkap']);
            setFlash('success', "Data warga {$data['nama_lengkap']} berhasil diperbarui.");
        } else {
            $cols = implode(', ', array_keys($data));
            $phs  = implode(', ', array_fill(0, count($data), '?'));
            $stmt = $db->prepare("INSERT INTO warga ($cols) VALUES ($phs)");
            $stmt->execute(array_values($data));
            $newId = (int)$db->lastInsertId();
            logAktivitas('Tambah warga', 'warga', $newId, $data['nama_lengkap']);

            // Pesan sukses + info SIAK
            if ($siakHasil) {
                $statusTeks = $siakHasil['status'] === 'sesuai'
                    ? '✅ NIK terverifikasi SIAK Dukcapil.'
                    : '⚠️ NIK tidak dapat diverifikasi SIAK (' . ($siakHasil['kode'] ?? '') . ').';
                setFlash('success', "Warga {$data['nama_lengkap']} berhasil ditambahkan. $statusTeks");
            } else {
                setFlash('success', "Warga {$data['nama_lengkap']} berhasil ditambahkan.");
            }
        }
        header('Location: index.php'); exit;
    }
}

// Daftar desa untuk dropdown
if (isSuperadmin()) {
    $desas = $db->query("SELECT id, nama_desa FROM desa WHERE aktif=1 ORDER BY nama_desa")->fetchAll();
} else {
    $stmt2 = $db->prepare("SELECT id, nama_desa FROM desa WHERE id=?");
    $stmt2->execute([$idDesa]);
    $desas = $stmt2->fetchAll();
}

$pageTitle      = $isEdit ? 'Edit Warga' : 'Tambah Warga';
$pageBreadcrumb = ['Dashboard' => APP_URL.'/index.php', 'Data Warga' => APP_URL.'/modules/warga/index.php', $pageTitle => null];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <span class="card-title"><?= $isEdit ? 'Edit Data Warga' : 'Tambah Warga Baru' ?></span>
        <a href="index.php" class="btn btn-secondary btn-sm">← Kembali</a>
    </div>
    <div class="card-body">

        <?php if (!empty($errors)): ?>
        <div class="alert alert-error mb-2">
            <div>
                <?php foreach ($errors as $err): ?><div>• <?= e($err) ?></div><?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-grid mb-3">

                <!-- Desa -->
                <div class="form-group full">
                    <label>Desa <span class="req">*</span></label>
                    <select name="id_desa" required <?= !isSuperadmin() ? 'disabled' : '' ?>>
                        <option value="">— Pilih Desa —</option>
                        <?php foreach ($desas as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= (($_POST['id_desa'] ?? $warga['id_desa'] ?? $idDesa) == $d['id']) ? 'selected' : '' ?>><?= e($d['nama_desa']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!isSuperadmin()): ?>
                    <input type="hidden" name="id_desa" value="<?= $idDesa ?>">
                    <?php endif; ?>
                </div>

                <!-- NIK & KK -->
                <div class="form-group">
                    <label>NIK <span class="req">*</span></label>
                    <input type="text" name="nik" maxlength="16" oninput="validasiNIK(this)" value="<?= e($_POST['nik'] ?? $warga['nik'] ?? '') ?>" placeholder="16 digit" required>
                    <span class="form-hint">Nomor Induk Kependudukan, 16 digit</span>
                </div>
                <div class="form-group">
                    <label>No. Kartu Keluarga <span class="req">*</span></label>
                    <input type="text" name="no_kk" maxlength="16" oninput="validasiNIK(this)" value="<?= e($_POST['no_kk'] ?? $warga['no_kk'] ?? '') ?>" placeholder="16 digit" required>
                </div>

                <!-- Nama -->
                <div class="form-group full">
                    <label>Nama Lengkap <span class="req">*</span></label>
                    <input type="text" name="nama_lengkap" value="<?= e($_POST['nama_lengkap'] ?? $warga['nama_lengkap'] ?? '') ?>" placeholder="Sesuai KTP" required>
                </div>

                <!-- TTL -->
                <div class="form-group">
                    <label>Tempat Lahir <span class="req">*</span></label>
                    <input type="text" name="tempat_lahir" value="<?= e($_POST['tempat_lahir'] ?? $warga['tempat_lahir'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Tanggal Lahir <span class="req">*</span></label>
                    <input type="date" name="tanggal_lahir" value="<?= e($_POST['tanggal_lahir'] ?? $warga['tanggal_lahir'] ?? '') ?>" max="<?= date('Y-m-d') ?>" required>
                </div>

                <!-- JK & Agama -->
                <div class="form-group">
                    <label>Jenis Kelamin <span class="req">*</span></label>
                    <select name="jenis_kelamin" required>
                        <option value="L" <?= (($_POST['jenis_kelamin'] ?? $warga['jenis_kelamin'] ?? '') === 'L') ? 'selected' : '' ?>>Laki-laki</option>
                        <option value="P" <?= (($_POST['jenis_kelamin'] ?? $warga['jenis_kelamin'] ?? '') === 'P') ? 'selected' : '' ?>>Perempuan</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Agama <span class="req">*</span></label>
                    <select name="agama" required>
                        <?php foreach (['Islam','Kristen Protestan','Kristen Katolik','Hindu','Budha','Konghucu','Lainnya'] as $ag): ?>
                        <option value="<?= $ag ?>" <?= (($_POST['agama'] ?? $warga['agama'] ?? '') === $ag) ? 'selected' : '' ?>><?= $ag ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status Kawin & Pendidikan -->
                <div class="form-group">
                    <label>Status Perkawinan <span class="req">*</span></label>
                    <select name="status_kawin" required>
                        <?php foreach (['Belum Kawin','Kawin','Cerai Hidup','Cerai Mati'] as $sk): ?>
                        <option value="<?= $sk ?>" <?= (($_POST['status_kawin'] ?? $warga['status_kawin'] ?? 'Belum Kawin') === $sk) ? 'selected' : '' ?>><?= $sk ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Pendidikan Terakhir <span class="req">*</span></label>
                    <select name="pendidikan" required>
                        <?php foreach (['Tidak/Belum Sekolah','SD/Sederajat','SMP/Sederajat','SMA/Sederajat','D1/D2/D3','S1','S2','S3'] as $pend): ?>
                        <option value="<?= $pend ?>" <?= (($_POST['pendidikan'] ?? $warga['pendidikan'] ?? '') === $pend) ? 'selected' : '' ?>><?= $pend ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Pekerjaan & Telepon -->
                <div class="form-group">
                    <label>Pekerjaan</label>
                    <input type="text" name="pekerjaan" value="<?= e($_POST['pekerjaan'] ?? $warga['pekerjaan'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>No. Telepon</label>
                    <input type="tel" name="no_telepon" value="<?= e($_POST['no_telepon'] ?? $warga['no_telepon'] ?? '') ?>" placeholder="08xxxxxxxxxx">
                </div>

                <!-- Alamat -->
                <div class="form-group full">
                    <label>Alamat Lengkap <span class="req">*</span></label>
                    <textarea name="alamat" required><?= e($_POST['alamat'] ?? $warga['alamat'] ?? '') ?></textarea>
                </div>

                <!-- RT/RW -->
                <div class="form-group">
                    <label>RT</label>
                    <input type="text" name="rt" maxlength="5" value="<?= e($_POST['rt'] ?? $warga['rt'] ?? '') ?>" placeholder="001">
                </div>
                <div class="form-group">
                    <label>RW</label>
                    <input type="text" name="rw" maxlength="5" value="<?= e($_POST['rw'] ?? $warga['rw'] ?? '') ?>" placeholder="001">
                </div>

                <!-- Status Hidup -->
                <div class="form-group">
                    <label>Status <span class="req">*</span></label>
                    <select name="status_hidup" required>
                        <?php foreach (['Hidup','Meninggal','Pindah'] as $sh): ?>
                        <option value="<?= $sh ?>" <?= (($_POST['status_hidup'] ?? $warga['status_hidup'] ?? 'Hidup') === $sh) ? 'selected' : '' ?>><?= $sh ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- DTKS -->
                <div class="form-group" style="justify-content:flex-end;flex-direction:row;align-items:center;gap:10px">
                    <input type="checkbox" name="status_dtks" id="dtks" value="1" <?= (($_POST['status_dtks'] ?? $warga['status_dtks'] ?? 0) ? 'checked' : '') ?> style="width:auto">
                    <label for="dtks" style="cursor:pointer;font-weight:500">Termasuk Data Terpadu Kesejahteraan Sosial (DTKS)</label>
                </div>

            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;padding-top:16px;border-top:1px solid var(--border)">
                <a href="index.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    <?= $isEdit ? 'Simpan Perubahan' : 'Simpan Warga' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
