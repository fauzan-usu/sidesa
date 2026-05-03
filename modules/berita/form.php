<?php
/**
 * FORM TAMBAH / EDIT BERITA
 * FNA & Kawan-kawan — 2025
 */
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requirePeran('superadmin', 'admin_desa');

$db     = getDB();
$idDesa = getIdDesaAktif();
$id     = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;

// Ambil data untuk edit
$berita = [];
if ($isEdit) {
    $s = $db->prepare("SELECT * FROM berita WHERE id=?");
    $s->execute([$id]);
    $berita = $s->fetch();
    if (!$berita) { setFlash('error','Berita tidak ditemukan.'); header('Location: index.php'); exit; }
    // Cek akses: admin_desa hanya boleh edit berita desanya sendiri
    if (!isSuperadmin() && $berita['id_desa'] != $idDesa) {
        http_response_code(403); die('Akses ditolak.');
    }
}

// Fungsi buat slug dari judul
function buatSlug(string $judul): string {
    $judul = strtolower(trim($judul));
    $judul = preg_replace('/[^a-z0-9\s\-]/', '', $judul);
    $judul = preg_replace('/[\s\-]+/', '-', $judul);
    return trim($judul, '-');
}

// Proses POST
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $desaPost   = isSuperadmin() ? (int)$_POST['id_desa'] : (int)$idDesa;
    $judul      = trim($_POST['judul'] ?? '');
    $ringkasan  = trim($_POST['ringkasan'] ?? '');
    $isi        = trim($_POST['isi'] ?? '');
    $kategori   = $_POST['kategori'] ?? 'berita';
    $status     = $_POST['status']   ?? 'draft';
    $featured   = isset($_POST['tampil_di_depan']) ? 1 : 0;
    $tglTerbit  = !empty($_POST['tgl_terbit']) ? $_POST['tgl_terbit'] : null;

    // Validasi
    if (empty($judul))    $errors[] = "Judul berita wajib diisi.";
    if (empty($isi))      $errors[] = "Isi berita wajib diisi.";
    if (!$desaPost)       $errors[] = "Desa wajib dipilih.";

    // Buat slug unik
    $slug = buatSlug($judul);
    if (empty($slug)) $slug = 'berita-' . time();

    // Pastikan slug unik di desa ini (kecuali record sendiri saat edit)
    if (empty($errors)) {
        $chkSlug = $db->prepare("SELECT id FROM berita WHERE id_desa=? AND slug=? AND id!=?");
        $chkSlug->execute([$desaPost, $slug, $isEdit ? $id : 0]);
        if ($chkSlug->fetch()) $slug .= '-' . time(); // tambah timestamp jika duplikat
    }

    // Upload foto
    $fotoUtama = $isEdit ? ($berita['foto_utama'] ?? null) : null;
    if (!empty($_FILES['foto_utama']['name'])) {
        $file    = $_FILES['foto_utama'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp','gif'];
        if (!in_array($ext, $allowed)) {
            $errors[] = "Format foto harus JPG, PNG, WEBP, atau GIF.";
        } elseif ($file['size'] > 3 * 1024 * 1024) {
            $errors[] = "Ukuran foto maksimal 3 MB.";
        } else {
            $namaFile = 'berita_' . $desaPost . '_' . time() . '.' . $ext;
            $dir      = UPLOAD_DIR . 'berita/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            if (move_uploaded_file($file['tmp_name'], $dir . $namaFile)) {
                // Hapus foto lama jika edit dan ada foto baru
                if ($isEdit && !empty($berita['foto_utama'])) {
                    $oldFile = $dir . $berita['foto_utama'];
                    if (file_exists($oldFile)) unlink($oldFile);
                }
                $fotoUtama = $namaFile;
            } else {
                $errors[] = "Gagal menyimpan foto. Pastikan folder uploads/berita/ dapat ditulis.";
            }
        }
    }

    if (empty($errors)) {
        $data = [
            'id_desa'        => $desaPost,
            'id_penulis'     => $_SESSION['id_pengguna'],
            'judul'          => $judul,
            'slug'           => $slug,
            'ringkasan'      => $ringkasan ?: null,
            'isi'            => $isi,
            'foto_utama'     => $fotoUtama,
            'kategori'       => $kategori,
            'status'         => $status,
            'tampil_di_depan'=> $featured,
            'tgl_terbit'     => ($status === 'terbit' && !$tglTerbit) ? date('Y-m-d H:i:s') : ($tglTerbit ? date('Y-m-d H:i:s', strtotime($tglTerbit)) : null),
        ];

        if ($isEdit) {
            $sets = implode(', ', array_map(fn($k) => "$k=?", array_keys($data)));
            $db->prepare("UPDATE berita SET $sets WHERE id=?")->execute([...array_values($data), $id]);
            logAktivitas('Edit berita', 'berita', $id, $judul);
            setFlash('success', "Berita \"$judul\" berhasil diperbarui.");
        } else {
            $cols = implode(',', array_keys($data));
            $phs  = implode(',', array_fill(0, count($data), '?'));
            $db->prepare("INSERT INTO berita ($cols) VALUES ($phs)")->execute(array_values($data));
            $newId = (int)$db->lastInsertId();
            logAktivitas('Tambah berita', 'berita', $newId, $judul);
            setFlash('success', "Berita \"$judul\" berhasil ditambahkan.");
        }
        header('Location: index.php'); exit;
    }
}

// Data desa untuk dropdown (superadmin)
$desList = isSuperadmin()
    ? $db->query("SELECT id, nama_desa FROM desa WHERE aktif=1 ORDER BY nama_desa")->fetchAll()
    : [];

$nilaiDesaPost = (int)($_POST['id_desa'] ?? $berita['id_desa'] ?? $idDesa ?? 0);
$labelKat = ['berita'=>'Berita','pengumuman'=>'Pengumuman','agenda'=>'Agenda','pembangunan'=>'Pembangunan','sosial'=>'Sosial','lainnya'=>'Lainnya'];

$pageTitle      = $isEdit ? 'Edit Berita' : 'Tulis Berita Baru';
$pageBreadcrumb = ['Dashboard'=>APP_URL.'/index.php','Berita'=>APP_URL.'/modules/berita/index.php',$pageTitle=>null];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <span class="card-title"><?= $isEdit ? 'Edit Berita' : 'Tulis Berita Baru' ?></span>
        <a href="index.php" class="btn btn-secondary btn-sm">← Kembali</a>
    </div>
    <div class="card-body">

        <?php if (!empty($errors)): ?>
        <div class="alert alert-error mb-2">
            <?php foreach ($errors as $err): ?>
            <div>• <?= e($err) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <!-- KOLOM 2: Konten Kiri + Sidebar Kanan -->
            <div style="display:grid;grid-template-columns:1fr 300px;gap:24px;align-items:flex-start">

                <!-- ── KONTEN UTAMA ── -->
                <div style="display:flex;flex-direction:column;gap:18px">

                    <?php if (isSuperadmin()): ?>
                    <div class="form-group">
                        <label class="field">Desa <span class="req">*</span></label>
                        <select name="id_desa" required>
                            <option value="">— Pilih Desa —</option>
                            <?php foreach ($desList as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= $nilaiDesaPost==$d['id']?'selected':'' ?>><?= e($d['nama_desa']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="id_desa" value="<?= $idDesa ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="field">Judul Berita <span class="req">*</span></label>
                        <input type="text" name="judul" value="<?= e($_POST['judul'] ?? $berita['judul'] ?? '') ?>"
                               placeholder="Tulis judul yang menarik…" required
                               style="font-size:16px;font-weight:700;padding:12px 14px">
                    </div>

                    <div class="form-group">
                        <label class="field">Ringkasan <span style="color:var(--text-3);font-weight:400">(opsional, max 500 karakter)</span></label>
                        <textarea name="ringkasan" maxlength="500" rows="2"
                                  placeholder="Cuplikan singkat yang tampil di kartu berita portal…"><?= e($_POST['ringkasan'] ?? $berita['ringkasan'] ?? '') ?></textarea>
                        <span class="form-hint" id="ringkasanCount">0 / 500 karakter</span>
                    </div>

                    <div class="form-group">
                        <label class="field">Isi Berita <span class="req">*</span></label>
                        <!-- Toolbar editor sederhana -->
                        <div id="editorToolbar" style="display:flex;gap:4px;flex-wrap:wrap;padding:8px;background:var(--bg);border:1px solid var(--border);border-bottom:none;border-radius:var(--radius) var(--radius) 0 0">
                            <?php
                            $tools = [
                                ['bold','<b>B</b>','font-weight:700'],
                                ['italic','<i>I</i>','font-style:italic'],
                                ['underline','<u>U</u>','text-decoration:underline'],
                                ['insertOrderedList','1.',''],
                                ['insertUnorderedList','•',''],
                            ];
                            foreach ($tools as [$cmd, $lbl, $st]): ?>
                            <button type="button" onclick="document.execCommand('<?= $cmd ?>')"
                                    style="padding:5px 10px;border:1px solid var(--border);border-radius:6px;background:#fff;cursor:pointer;font-size:13px;<?= $st ?>"
                                    title="<?= $cmd ?>"><?= $lbl ?></button>
                            <?php endforeach; ?>
                            <button type="button" onclick="insertH2()" style="padding:5px 10px;border:1px solid var(--border);border-radius:6px;background:#fff;cursor:pointer;font-size:13px;font-weight:700">H</button>
                            <button type="button" onclick="insertHR()" style="padding:5px 10px;border:1px solid var(--border);border-radius:6px;background:#fff;cursor:pointer;font-size:12px;color:var(--text-3)">― Garis</button>
                        </div>
                        <div id="isiEditor"
                             contenteditable="true"
                             style="min-height:320px;padding:16px;border:1px solid var(--border);border-radius:0 0 var(--radius) var(--radius);background:#fff;font-size:15px;line-height:1.8;outline:none;color:var(--text-1)"
                             ><?= $_POST['isi'] ?? $berita['isi'] ?? '' ?></div>
                        <textarea name="isi" id="isiHidden" style="display:none" required><?= e($_POST['isi'] ?? $berita['isi'] ?? '') ?></textarea>
                        <span class="form-hint">Gunakan toolbar di atas untuk format teks. Anda juga bisa paste dari Word/Google Docs.</span>
                    </div>

                </div><!-- /.konten-utama -->

                <!-- ── SIDEBAR PENGATURAN ── -->
                <div style="display:flex;flex-direction:column;gap:16px;position:sticky;top:80px">

                    <!-- Status & Aksi -->
                    <div class="card" style="box-shadow:none">
                        <div class="card-header" style="padding:12px 16px">
                            <span class="card-title" style="font-size:13px">Publikasi</span>
                        </div>
                        <div class="card-body" style="padding:14px 16px;display:flex;flex-direction:column;gap:12px">
                            <div class="form-group">
                                <label class="field">Status</label>
                                <select name="status" id="statusSelect">
                                    <?php foreach (['draft'=>'Draft — Belum Terbit','terbit'=>'Terbit — Tampil di Portal','arsip'=>'Arsip — Disembunyikan'] as $v => $lbl): ?>
                                    <option value="<?= $v ?>" <?= ($_POST['status'] ?? $berita['status'] ?? 'draft')===$v?'selected':'' ?>><?= $lbl ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="field">Tanggal Terbit</label>
                                <input type="datetime-local" name="tgl_terbit"
                                       value="<?= e(isset($berita['tgl_terbit']) && $berita['tgl_terbit'] ? date('Y-m-d\TH:i', strtotime($berita['tgl_terbit'])) : '') ?>">
                                <span class="form-hint">Kosongkan untuk langsung terbit saat disimpan</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:10px;padding:10px;background:var(--bg);border-radius:var(--radius);border:1px solid var(--border)">
                                <input type="checkbox" name="tampil_di_depan" id="featured" value="1"
                                       <?= ($_POST['tampil_di_depan'] ?? $berita['tampil_di_depan'] ?? 0) ? 'checked' : '' ?>
                                       style="width:auto;accent-color:var(--brand)">
                                <label for="featured" style="cursor:pointer;font-size:13px;font-weight:600;margin:0">
                                    ★ Tampilkan di Halaman Depan Portal
                                </label>
                            </div>
                            <div style="display:flex;gap:8px">
                                <a href="index.php" class="btn btn-secondary" style="flex:1;justify-content:center">Batal</a>
                                <button type="submit" class="btn btn-primary" style="flex:1">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
                                    <?= $isEdit ? 'Perbarui' : 'Simpan' ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Kategori -->
                    <div class="card" style="box-shadow:none">
                        <div class="card-header" style="padding:12px 16px"><span class="card-title" style="font-size:13px">Kategori</span></div>
                        <div class="card-body" style="padding:14px 16px">
                            <div style="display:flex;flex-direction:column;gap:6px">
                                <?php foreach ($labelKat as $k => $lbl): ?>
                                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13.5px">
                                    <input type="radio" name="kategori" value="<?= $k ?>"
                                           <?= ($_POST['kategori'] ?? $berita['kategori'] ?? 'berita')===$k?'checked':'' ?>
                                           style="width:auto;accent-color:var(--brand)">
                                    <?= $lbl ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Foto Utama -->
                    <div class="card" style="box-shadow:none">
                        <div class="card-header" style="padding:12px 16px"><span class="card-title" style="font-size:13px">Foto Sampul</span></div>
                        <div class="card-body" style="padding:14px 16px">
                            <?php if ($isEdit && !empty($berita['foto_utama'])): ?>
                            <div style="margin-bottom:10px;border-radius:8px;overflow:hidden;position:relative">
                                <img src="<?= UPLOAD_URL ?>berita/<?= e($berita['foto_utama']) ?>" alt="Foto saat ini"
                                     style="width:100%;height:120px;object-fit:cover;display:block">
                                <div style="position:absolute;bottom:6px;left:6px;background:rgba(0,0,0,.6);color:#fff;font-size:10px;padding:2px 7px;border-radius:4px">Foto saat ini</div>
                            </div>
                            <?php endif; ?>
                            <label for="fotoInput" style="display:block;border:2px dashed var(--border);border-radius:var(--radius);padding:20px;text-align:center;cursor:pointer;transition:border-color .15s;font-size:13px;color:var(--text-3)" id="dropZone">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="display:block;margin:0 auto 8px;opacity:.4"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                <span id="dropLabel"><?= ($isEdit && !empty($berita['foto_utama'])) ? 'Klik untuk ganti foto' : 'Klik untuk upload foto' ?></span><br>
                                <span style="font-size:11px">JPG, PNG, WEBP · Maks 3 MB</span>
                            </label>
                            <input type="file" name="foto_utama" id="fotoInput" accept="image/*" style="display:none">
                        </div>
                    </div>

                </div><!-- /.sidebar-pengaturan -->
            </div><!-- /.grid -->
        </form>
    </div>
</div>

<script>
// ── SYNC contenteditable ke hidden textarea ──────────────────
document.querySelector('form').addEventListener('submit', function() {
    document.getElementById('isiHidden').value = document.getElementById('isiEditor').innerHTML;
});
document.getElementById('isiEditor').addEventListener('focus', function() {
    if (this.innerHTML.trim() === '') this.innerHTML = '';
});

// ── Toolbar helper ────────────────────────────────────────────
function insertH2() {
    document.execCommand('formatBlock', false, 'h3');
}
function insertHR() {
    document.execCommand('insertHTML', false, '<hr style="border:none;border-top:1.5px solid #e2e8f0;margin:16px 0">');
}

// ── Counter ringkasan ─────────────────────────────────────────
const ringkasanEl = document.querySelector('textarea[name=ringkasan]');
const countEl     = document.getElementById('ringkasanCount');
function updateCount() {
    countEl.textContent = (ringkasanEl.value.length) + ' / 500 karakter';
}
ringkasanEl.addEventListener('input', updateCount);
updateCount();

// ── Preview foto sebelum upload ────────────────────────────────
document.getElementById('fotoInput').addEventListener('change', function() {
    if (!this.files[0]) return;
    const url  = URL.createObjectURL(this.files[0]);
    const zone = document.getElementById('dropZone');
    zone.style.padding = '0';
    zone.style.border  = '2px solid var(--brand)';
    zone.innerHTML = `<img src="${url}" style="width:100%;height:120px;object-fit:cover;display:block;border-radius:6px">`;
    document.getElementById('dropLabel')?.remove();
});

// ── Drag & drop foto ───────────────────────────────────────────
const dropZone = document.getElementById('dropZone');
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.style.borderColor = 'var(--brand)'; });
dropZone.addEventListener('dragleave', () => { dropZone.style.borderColor = 'var(--border)'; });
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    const file = e.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) {
        document.getElementById('fotoInput').files = e.dataTransfer.files;
        dropZone.style.padding = '0';
        dropZone.innerHTML = `<img src="${URL.createObjectURL(file)}" style="width:100%;height:120px;object-fit:cover;display:block;border-radius:6px">`;
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
