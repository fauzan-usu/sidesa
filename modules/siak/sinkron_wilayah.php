<?php
/**
 * SINKRONISASI WILAYAH KEMENDAGRI
 * Mengunduh data wilayah resmi dari API Kemendagri ke database lokal
 * Data: Provinsi → Kabupaten → Kecamatan → Desa/Kelurahan
 * Sumber: emsifa.com (mirror resmi kode wilayah Kemendagri)
 * FNA & Kawan-kawan — 2025
 */
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requirePeran('superadmin', 'admin_desa');
require_once __DIR__ . '/../../includes/SiakClient.php';

$db   = getDB();
$siak = new SiakClient(['mode'=>'simulasi'], $db);

// AJAX endpoint untuk proses sinkronisasi bertahap
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $aksi = $_GET['aksi'] ?? '';

    try {
        switch ($aksi) {
            case 'provinsi':
                $data = $siak->getProvinsi();
                echo json_encode(['ok'=>true,'jumlah'=>count($data),'pesan'=>count($data).' provinsi berhasil dimuat']);
                break;

            case 'kabupaten':
                $kodeProv = $_GET['kode'] ?? '';
                if (!$kodeProv) { echo json_encode(['ok'=>false,'pesan'=>'Kode provinsi diperlukan']); break; }
                $data = $siak->getKabupaten($kodeProv);
                echo json_encode(['ok'=>true,'jumlah'=>count($data),'pesan'=>count($data).' kabupaten/kota dimuat untuk kode '.$kodeProv]);
                break;

            case 'kecamatan':
                $kodeKab = $_GET['kode'] ?? '';
                if (!$kodeKab) { echo json_encode(['ok'=>false,'pesan'=>'Kode kabupaten diperlukan']); break; }
                $data = $siak->getKecamatan($kodeKab);
                echo json_encode(['ok'=>true,'jumlah'=>count($data),'pesan'=>count($data).' kecamatan dimuat untuk kode '.$kodeKab]);
                break;

            case 'desa':
                $kodeKec = $_GET['kode'] ?? '';
                if (!$kodeKec) { echo json_encode(['ok'=>false,'pesan'=>'Kode kecamatan diperlukan']); break; }
                $data = $siak->getDesaKelurahan($kodeKec);
                echo json_encode(['ok'=>true,'jumlah'=>count($data),'pesan'=>count($data).' desa/kelurahan dimuat untuk kecamatan '.$kodeKec]);
                break;

            case 'list_provinsi':
                $rows = $db->query("SELECT kode, nama FROM wilayah_provinsi ORDER BY nama")->fetchAll();
                echo json_encode(['ok'=>true,'data'=>$rows]);
                break;

            case 'list_kabupaten':
                $kp   = $_GET['kode'] ?? '';
                $stmt = $db->prepare("SELECT kode, nama FROM wilayah_kabupaten WHERE kode_provinsi=? ORDER BY nama");
                $stmt->execute([$kp]);
                echo json_encode(['ok'=>true,'data'=>$stmt->fetchAll()]);
                break;

            case 'list_kecamatan':
                $kk   = $_GET['kode'] ?? '';
                $stmt = $db->prepare("SELECT kode, nama FROM wilayah_kecamatan WHERE kode_kabupaten=? ORDER BY nama");
                $stmt->execute([$kk]);
                echo json_encode(['ok'=>true,'data'=>$stmt->fetchAll()]);
                break;

            case 'list_desa':
                $kec  = $_GET['kode'] ?? '';
                $stmt = $db->prepare("SELECT kode, nama FROM wilayah_desa WHERE kode_kecamatan=? ORDER BY nama");
                $stmt->execute([$kec]);
                echo json_encode(['ok'=>true,'data'=>$stmt->fetchAll()]);
                break;

            case 'stat':
                echo json_encode(['ok'=>true,'data'=> [
                    'provinsi'  => (int)$db->query("SELECT COUNT(*) FROM wilayah_provinsi")->fetchColumn(),
                    'kabupaten' => (int)$db->query("SELECT COUNT(*) FROM wilayah_kabupaten")->fetchColumn(),
                    'kecamatan' => (int)$db->query("SELECT COUNT(*) FROM wilayah_kecamatan")->fetchColumn(),
                    'desa'      => (int)$db->query("SELECT COUNT(*) FROM wilayah_desa")->fetchColumn(),
                ]]);
                break;

            default:
                echo json_encode(['ok'=>false,'pesan'=>'Aksi tidak dikenal']);
        }
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'pesan'=>$e->getMessage()]);
    }
    exit;
}

$pageTitle      = 'Sinkronisasi Wilayah Kemendagri';
$pageBreadcrumb = ['Dashboard'=>APP_URL.'/index.php','Integrasi SIAK'=>APP_URL.'/modules/siak/index.php','Sinkron Wilayah'=>null];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row">
    <!-- PANEL SINKRONISASI -->
    <div class="col-6">
        <div class="card mb-3">
            <div class="card-header"><span class="card-title">🗺️ Unduh Data Wilayah Kemendagri</span></div>
            <div class="card-body">
                <p style="font-size:13.5px;color:var(--text-2);line-height:1.7;margin-bottom:18px">
                    Data wilayah ini berasal dari referensi resmi Kemendagri (kode wilayah administrasi Indonesia).
                    Digunakan untuk validasi kode wilayah pada NIK dan pemetaan desa ke kode Kemendagri.
                    Proses unduh dilakukan bertahap sesuai pilihan wilayah Anda.
                </p>

                <!-- STEP 1: Provinsi -->
                <div id="step1" style="border:1.5px solid var(--border);border-radius:var(--radius-lg);padding:16px;margin-bottom:12px">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                        <div style="font-size:14px;font-weight:700">① Provinsi</div>
                        <span id="statProv" style="font-size:12px;color:var(--text-3)">Memuat…</span>
                    </div>
                    <button onclick="sinkronProvinsi()" id="btnProv" class="btn btn-primary btn-sm">
                        ⬇️ Unduh Semua Provinsi
                    </button>
                </div>

                <!-- STEP 2: Kabupaten -->
                <div id="step2" style="border:1.5px solid var(--border);border-radius:var(--radius-lg);padding:16px;margin-bottom:12px">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                        <div style="font-size:14px;font-weight:700">② Kabupaten/Kota</div>
                        <span id="statKab" style="font-size:12px;color:var(--text-3)">Memuat…</span>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <select id="selectProv" style="flex:1;min-width:200px" onchange="loadKabupaten(this.value)">
                            <option value="">— Pilih Provinsi —</option>
                        </select>
                        <button onclick="sinkronKabupaten()" id="btnKab" class="btn btn-primary btn-sm" disabled>
                            ⬇️ Unduh
                        </button>
                    </div>
                </div>

                <!-- STEP 3: Kecamatan -->
                <div id="step3" style="border:1.5px solid var(--border);border-radius:var(--radius-lg);padding:16px;margin-bottom:12px">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                        <div style="font-size:14px;font-weight:700">③ Kecamatan</div>
                        <span id="statKec" style="font-size:12px;color:var(--text-3)">Memuat…</span>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <select id="selectKab" style="flex:1;min-width:200px" onchange="loadKecamatan(this.value)">
                            <option value="">— Pilih Kabupaten —</option>
                        </select>
                        <button onclick="sinkronKecamatan()" id="btnKec" class="btn btn-primary btn-sm" disabled>
                            ⬇️ Unduh
                        </button>
                    </div>
                </div>

                <!-- STEP 4: Desa -->
                <div id="step4" style="border:1.5px solid var(--border);border-radius:var(--radius-lg);padding:16px">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                        <div style="font-size:14px;font-weight:700">④ Desa/Kelurahan</div>
                        <span id="statDesa" style="font-size:12px;color:var(--text-3)">Memuat…</span>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <select id="selectKec" style="flex:1;min-width:200px">
                            <option value="">— Pilih Kecamatan —</option>
                        </select>
                        <button onclick="sinkronDesa()" id="btnDesa" class="btn btn-primary btn-sm" disabled>
                            ⬇️ Unduh
                        </button>
                    </div>
                </div>

                <!-- Log aktivitas -->
                <div id="logPanel" style="margin-top:16px;background:var(--bg);border-radius:var(--radius);padding:12px;font-family:monospace;font-size:12px;color:var(--text-2);min-height:80px;max-height:200px;overflow-y:auto;display:none">
                </div>
            </div>
        </div>
    </div>

    <!-- PETA KODE DESA -->
    <div class="col-6">
        <div class="card">
            <div class="card-header"><span class="card-title">🔗 Petakan Desa ke Kode Kemendagri</span></div>
            <div class="card-body">
                <p style="font-size:13px;color:var(--text-3);margin-bottom:16px">Setelah data wilayah diunduh, petakan setiap desa di sistem ini ke kode wilayah resmi Kemendagri. Kode ini digunakan sebagai referensi validasi NIK.</p>
                <?php
                $desaList = getDB()->query("SELECT d.id, d.nama_desa, d.kecamatan, d.kabupaten, d.provinsi, sc.kode_desa_kemendagri
                    FROM desa d LEFT JOIN siak_config sc ON d.id=sc.id_desa WHERE d.aktif=1 ORDER BY d.nama_desa")->fetchAll();
                ?>
                <?php foreach ($desaList as $des): ?>
                <div style="padding:14px;border:1px solid var(--border);border-radius:var(--radius-lg);margin-bottom:10px">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px">
                        <div>
                            <div style="font-weight:700;font-size:14px"><?= e($des['nama_desa']) ?></div>
                            <div style="font-size:12px;color:var(--text-3)"><?= e($des['kecamatan']) ?> · <?= e($des['kabupaten']) ?></div>
                        </div>
                        <div>
                            <?php if ($des['kode_desa_kemendagri']): ?>
                            <span style="font-family:monospace;font-size:12px;font-weight:700;padding:4px 10px;background:#d1fae5;color:#065f46;border-radius:6px"><?= e($des['kode_desa_kemendagri']) ?></span>
                            <?php else: ?>
                            <span style="font-size:12px;color:var(--amber);font-weight:600">⚠ Belum dipetakan</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="margin-top:10px" id="form_desa_<?= $des['id'] ?>">
                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                            <select id="kode_desa_<?= $des['id'] ?>" style="flex:1;font-size:12.5px;padding:6px 8px" onchange="simpanKodeDesa(<?= $des['id'] ?>, this.value)">
                                <option value="">— Pilih kode desa Kemendagri —</option>
                            </select>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
const BASE = '<?= APP_URL ?>/modules/siak/sinkron_wilayah.php?ajax=1';

async function apiFetch(aksi, kode='') {
    const url = BASE + '&aksi=' + aksi + (kode ? '&kode='+kode : '');
    const res = await fetch(url);
    return res.json();
}

function log(pesan, ok=true) {
    const panel = document.getElementById('logPanel');
    panel.style.display = 'block';
    const baris = document.createElement('div');
    baris.style.color = ok ? '#065f46' : '#991b1b';
    baris.textContent = '[' + new Date().toLocaleTimeString() + '] ' + pesan;
    panel.appendChild(baris);
    panel.scrollTop = panel.scrollHeight;
}

async function muatStat() {
    const r = await apiFetch('stat');
    if (!r.ok) return;
    document.getElementById('statProv').textContent = r.data.provinsi + ' provinsi tersimpan';
    document.getElementById('statKab').textContent  = r.data.kabupaten + ' kab/kota tersimpan';
    document.getElementById('statKec').textContent  = r.data.kecamatan + ' kecamatan tersimpan';
    document.getElementById('statDesa').textContent = r.data.desa + ' desa tersimpan';
}

async function sinkronProvinsi() {
    document.getElementById('btnProv').disabled = true;
    document.getElementById('btnProv').textContent = '⏳ Mengunduh…';
    log('Mengunduh data provinsi dari Kemendagri…');
    const r = await apiFetch('provinsi');
    log(r.pesan, r.ok);
    document.getElementById('btnProv').textContent = '✅ Selesai';
    await muatStat();
    await isiDropdownProvinsi();
}

async function isiDropdownProvinsi() {
    const r = await apiFetch('list_provinsi');
    if (!r.ok) return;
    const sel = document.getElementById('selectProv');
    sel.innerHTML = '<option value="">— Pilih Provinsi —</option>';
    r.data.forEach(p => {
        sel.innerHTML += `<option value="${p.kode}">${p.nama}</option>`;
    });
}

async function loadKabupaten(kodeProv) {
    const sel = document.getElementById('selectKab');
    sel.innerHTML = '<option value="">— Memuat… —</option>';
    document.getElementById('btnKab').disabled = !kodeProv;
    if (!kodeProv) return;
    // Cek dulu apakah sudah ada di cache
    const r = await apiFetch('list_kabupaten', kodeProv);
    sel.innerHTML = '<option value="">— Pilih Kabupaten —</option>';
    if (r.ok && r.data.length > 0) {
        r.data.forEach(k => sel.innerHTML += `<option value="${k.kode}">${k.nama}</option>`);
    } else {
        sel.innerHTML = '<option value="">Belum diunduh — klik Unduh dulu</option>';
    }
}

async function sinkronKabupaten() {
    const kode = document.getElementById('selectProv').value;
    if (!kode) { alert('Pilih provinsi terlebih dahulu'); return; }
    document.getElementById('btnKab').disabled = true;
    document.getElementById('btnKab').textContent = '⏳ Mengunduh…';
    log('Mengunduh kabupaten untuk kode ' + kode + '…');
    const r = await apiFetch('kabupaten', kode);
    log(r.pesan, r.ok);
    document.getElementById('btnKab').textContent = '✅ Selesai';
    document.getElementById('btnKab').disabled = false;
    await muatStat();
    await loadKabupaten(kode);
}

async function loadKecamatan(kodeKab) {
    const sel = document.getElementById('selectKec');
    sel.innerHTML = '<option value="">— Memuat… —</option>';
    document.getElementById('btnKec').disabled = !kodeKab;
    if (!kodeKab) return;
    const r = await apiFetch('list_kecamatan', kodeKab);
    sel.innerHTML = '<option value="">— Pilih Kecamatan —</option>';
    if (r.ok && r.data.length > 0) {
        r.data.forEach(k => sel.innerHTML += `<option value="${k.kode}">${k.nama}</option>`);
        document.getElementById('btnKec').disabled = false;
    } else {
        sel.innerHTML = '<option value="">Belum diunduh</option>';
    }
}

async function sinkronKecamatan() {
    const kode = document.getElementById('selectKab').value;
    if (!kode) { alert('Pilih kabupaten terlebih dahulu'); return; }
    document.getElementById('btnKec').disabled = true;
    document.getElementById('btnKec').textContent = '⏳ Mengunduh…';
    log('Mengunduh kecamatan untuk kode ' + kode + '…');
    const r = await apiFetch('kecamatan', kode);
    log(r.pesan, r.ok);
    document.getElementById('btnKec').textContent = '✅ Selesai';
    document.getElementById('btnKec').disabled = false;
    await muatStat();
    await loadKecamatan(kode);
}

async function sinkronDesa() {
    const kode = document.getElementById('selectKec').value;
    if (!kode) { alert('Pilih kecamatan terlebih dahulu'); return; }
    document.getElementById('btnDesa').disabled = true;
    document.getElementById('btnDesa').textContent = '⏳ Mengunduh…';
    log('Mengunduh desa/kelurahan untuk kecamatan ' + kode + '…');
    const r = await apiFetch('desa', kode);
    log(r.pesan, r.ok);
    document.getElementById('btnDesa').textContent = '✅ Selesai';
    document.getElementById('btnDesa').disabled = false;
    await muatStat();
}

async function simpanKodeDesa(idDesa, kodeDesa) {
    if (!kodeDesa) return;
    const res = await fetch('<?= APP_URL ?>/modules/siak/api_simpan_kode_desa.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id_desa: idDesa, kode: kodeDesa})
    });
    const r = await res.json();
    if (r.ok) log('Kode desa ID ' + idDesa + ' berhasil dipetakan ke ' + kodeDesa);
    else log('Gagal menyimpan: ' + r.pesan, false);
}

// Init
document.addEventListener('DOMContentLoaded', async () => {
    await muatStat();
    await isiDropdownProvinsi();
    // Isi dropdown untuk pemetaan desa
    <?php foreach ($desaList as $des): ?>
    (async () => {
        // Cek apakah sudah ada kode yang tersimpan dan isi select
        const r = await apiFetch('list_desa', ''); // placeholder
    })();
    <?php endforeach; ?>
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
