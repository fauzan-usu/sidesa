<?php
/**
 * PANDUAN PENGAJUAN MOU SIAK KEMENDAGRI
 * FNA & Kawan-kawan — 2025
 */
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requirePeran('superadmin', 'admin_desa');

$pageTitle      = 'Panduan MOU SIAK Kemendagri';
$pageBreadcrumb = ['Dashboard'=>APP_URL.'/index.php','Integrasi SIAK'=>APP_URL.'/modules/siak/index.php','Panduan MOU'=>null];
$pageSub        = 'Langkah-langkah pengajuan hak akses ke SIAK Terpusat Ditjen Dukcapil Kemendagri';
require_once __DIR__ . '/../../includes/header.php';
?>

<div style="max-width:860px">

<!-- HERO PANDUAN -->
<div style="background:linear-gradient(135deg,#131b2e 0%,#1e3a6e 100%);border-radius:var(--radius-lg);padding:28px 32px;margin-bottom:24px;color:#fff">
    <div style="font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.5);margin-bottom:8px">DASAR HUKUM</div>
    <div style="font-size:18px;font-weight:800;margin-bottom:6px">Permendagri No. 102 Tahun 2019</div>
    <div style="font-size:13.5px;color:rgba(255,255,255,.75);line-height:1.7">
        Tentang Pemberian Hak Akses dan Pemanfaatan Data Kependudukan. Berdasarkan regulasi ini, instansi pemerintah desa yang memiliki kebutuhan verifikasi data kependudukan berhak mengajukan akses ke SIAK Terpusat melalui mekanisme perjanjian kerja sama (MOU) dengan Ditjen Dukcapil Kemendagri.
    </div>
    <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap">
        <a href="https://dukcapil.kemendagri.go.id/page/read/integrasi-data" target="_blank"
           style="padding:8px 16px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);color:#fff;border-radius:var(--radius);font-size:13px;font-weight:600;display:flex;align-items:center;gap:6px">
            🔗 Portal Integrasi Dukcapil
        </a>
        <a href="https://layananonline.dukcapil.kemendagri.go.id/" target="_blank"
           style="padding:8px 16px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);color:#fff;border-radius:var(--radius);font-size:13px;font-weight:600;display:flex;align-items:center;gap:6px">
            🔗 Portal SIAK Terpusat
        </a>
    </div>
</div>

<!-- HAL PENTING SEBELUM MULAI -->
<div class="alert alert-warning mb-3" style="align-items:flex-start;gap:12px;padding:16px 20px">
    <div>
        <strong>⚠️ Penting Dipahami Sebelum Memulai</strong><br>
        Kemendagri <strong>tidak memberikan data penduduk</strong> kepada lembaga pengguna. Yang diberikan hanya hak akses untuk <strong>verifikasi</strong> — sistem Anda mengirim NIK + nama + tanggal lahir, dan Dukcapil hanya menjawab <strong>"SESUAI" atau "TIDAK SESUAI"</strong>. Bukan mengambil data penduduk dari Kemendagri.
    </div>
</div>

<!-- LANGKAH-LANGKAH -->
<?php
$langkah = [
    [
        'no'   => '01',
        'judul'=> 'Siapkan Surat Permohonan dari Kepala Desa',
        'warna'=> '#5B4FCF',
        'isi'  => 'Surat permohonan hak akses data kependudukan harus ditandatangani oleh Kepala Desa dan diketahui oleh Camat. Surat ditujukan kepada Kepala Dinas Kependudukan dan Pencatatan Sipil (Disdukcapil) Kabupaten/Kota setempat.',
        'item' => [
            'Surat permohonan bermaterai dari Kepala Desa',
            'Diketahui/ditembuskan ke Camat',
            'Sertakan profil singkat sistem informasi desa yang akan diintegrasikan',
            'Jelaskan tujuan penggunaan akses (verifikasi data warga baru, validasi NIK, dll)',
        ],
    ],
    [
        'no'   => '02',
        'judul'=> 'Ajukan ke Disdukcapil Kabupaten/Kota',
        'warna'=> '#0D9B8A',
        'isi'  => 'Pengajuan pertama dilakukan ke Disdukcapil Kabupaten/Kota, bukan langsung ke Kemendagri pusat. Disdukcapil Kabupaten/Kota yang akan meneruskan ke Ditjen Dukcapil pusat jika diperlukan, atau memberikan akses langsung sesuai kewenangan.',
        'item' => [
            'Kunjungi kantor Disdukcapil Kabupaten/Kota Anda',
            'Serahkan surat permohonan beserta dokumen pendukung',
            'Ikuti proses evaluasi dan verifikasi dari Disdukcapil',
            'Proses biasanya memakan waktu 1–4 minggu kerja',
        ],
    ],
    [
        'no'   => '03',
        'judul'=> 'Penandatanganan Perjanjian Kerja Sama (MOU)',
        'warna'=> '#C4731A',
        'isi'  => 'Jika permohonan disetujui, Disdukcapil akan menyiapkan naskah Perjanjian Kerja Sama (PKS/MOU). Dokumen ini mengatur hak, kewajiban, dan batasan penggunaan data kependudukan.',
        'item' => [
            'Pelajari isi PKS/MOU dengan seksama',
            'Pastikan terdapat klausul kerahasiaan data dan sanksi pelanggaran',
            'Penandatanganan oleh Kepala Desa dan Kepala Disdukcapil',
            'Simpan salinan MOU dengan baik — diperlukan untuk audit',
        ],
    ],
    [
        'no'   => '04',
        'judul'=> 'Terima Kredensial API (Client ID & Secret)',
        'warna'=> '#1A7A4A',
        'isi'  => 'Setelah MOU ditandatangani, Disdukcapil akan memberikan kredensial teknis berupa Client ID dan Client Secret untuk mengakses Web Service SIAK. Simpan kredensial ini dengan sangat aman.',
        'item' => [
            'Client ID — identitas aplikasi Anda di sistem Dukcapil',
            'Client Secret — kunci rahasia, jangan pernah dibagikan',
            'URL endpoint API yang akan digunakan',
            'Dokumentasi teknis penggunaan Web Service dari Dukcapil',
        ],
    ],
    [
        'no'   => '05',
        'judul'=> 'Konfigurasi di SiDesa dan Uji Coba',
        'warna'=> '#C0392B',
        'isi'  => 'Setelah mendapat kredensial, masukkan ke pengaturan SIAK di SiDesa, ubah mode dari "Simulasi" ke "Aktif", dan lakukan pengujian verifikasi NIK pertama.',
        'item' => [
            'Buka menu Integrasi SIAK → Pengaturan',
            'Isi Client ID dan Client Secret dari Disdukcapil',
            'Ubah status MOU menjadi "Disetujui" dan mode menjadi "Aktif"',
            'Uji verifikasi dengan NIK yang diketahui valid terlebih dahulu',
            'Aktifkan verifikasi otomatis jika hasil uji memuaskan',
        ],
    ],
];
foreach ($langkah as $l): ?>
<div style="display:flex;gap:20px;margin-bottom:20px;align-items:flex-start">
    <div style="width:48px;height:48px;border-radius:14px;background:<?= $l['warna'] ?>;color:#fff;font-size:18px;font-weight:900;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <?= $l['no'] ?>
    </div>
    <div class="card" style="flex:1;box-shadow:none;border-color:<?= $l['warna'] ?>22">
        <div class="card-header" style="background:<?= $l['warna'] ?>08;border-color:<?= $l['warna'] ?>22">
            <span class="card-title" style="color:<?= $l['warna'] ?>"><?= $l['judul'] ?></span>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:10px">
            <p style="font-size:13.5px;color:var(--text-2);line-height:1.7"><?= $l['isi'] ?></p>
            <div style="background:var(--bg);border-radius:var(--radius);padding:12px 16px">
                <?php foreach ($l['item'] as $item): ?>
                <div style="display:flex;gap:8px;font-size:13px;padding:4px 0;color:var(--text-2)">
                    <span style="color:<?= $l['warna'] ?>;font-weight:700;flex-shrink:0">✓</span>
                    <?= htmlspecialchars($item) ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- KONTAK DUKCAPIL -->
<div class="card mb-3">
    <div class="card-header"><span class="card-title">📞 Kontak Ditjen Dukcapil Kemendagri</span></div>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <?php foreach ([
                ['🌐 Website Resmi',     'dukcapil.kemendagri.go.id',  'https://dukcapil.kemendagri.go.id'],
                ['🔗 Portal SIAK',       'layananonline.dukcapil.kemendagri.go.id', 'https://layananonline.dukcapil.kemendagri.go.id'],
                ['🗺️ GIS Dukcapil',     'gis.dukcapil.kemendagri.go.id', 'https://gis.dukcapil.kemendagri.go.id'],
                ['📊 E-Database',        'e-database.kemendagri.go.id', 'https://e-database.kemendagri.go.id'],
            ] as [$lbl, $domain, $url]): ?>
            <a href="<?= $url ?>" target="_blank"
               style="padding:14px 16px;border:1px solid var(--border);border-radius:var(--radius-lg);display:flex;flex-direction:column;gap:4px;transition:border-color .15s,background .15s;text-decoration:none"
               onmouseover="this.style.borderColor='var(--brand)';this.style.background='var(--brand-light)'"
               onmouseout="this.style.borderColor='var(--border)';this.style.background=''">
                <span style="font-size:14px;font-weight:700;color:var(--text-1)"><?= $lbl ?></span>
                <span style="font-size:12px;color:var(--brand)"><?= $domain ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- FAQ -->
<div class="card mb-3">
    <div class="card-header"><span class="card-title">❓ Pertanyaan yang Sering Diajukan</span></div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:0">
        <?php foreach ([
            ['Apakah desa bisa langsung mengajukan tanpa melalui Disdukcapil Kabupaten?',
             'Tidak. Alur pengajuan harus melalui Disdukcapil Kabupaten/Kota setempat terlebih dahulu. Disdukcapil yang berwenang untuk memberikan akses atau meneruskan permohonan ke Ditjen Dukcapil pusat.'],
            ['Apakah penggunaan akses SIAK dikenakan biaya?',
             'Untuk instansi pemerintah desa, akses verifikasi data kependudukan umumnya tidak dikenakan biaya. Namun kebijakan ini dapat berbeda setiap daerah, konfirmasi langsung ke Disdukcapil setempat.'],
            ['Berapa lama proses persetujuan MOU?',
             'Rata-rata 2–6 minggu kerja tergantung antrian dan kelengkapan dokumen. Sebaiknya ajukan jauh sebelum sistem siap digunakan secara penuh.'],
            ['Apa saja yang boleh dan tidak boleh dilakukan dengan akses SIAK?',
             'Boleh: verifikasi kesesuaian NIK dengan nama dan tanggal lahir. Tidak boleh: mengambil/menyimpan data penduduk dari Dukcapil, menjual atau membagikan data kepada pihak lain, menggunakan akses di luar cakupan MOU. Pelanggaran dikenakan pidana 2 tahun penjara sesuai Pasal 95A UU No.24/2013.'],
            ['Apakah mode simulasi SiDesa aman untuk digunakan selama menunggu MOU?',
             'Ya, mode simulasi sepenuhnya berjalan di server lokal Anda sendiri. Tidak ada data yang dikirim ke server Kemendagri maupun pihak mana pun. Validasi dilakukan berdasarkan struktur NIK dan referensi kode wilayah yang sudah diunduh ke database lokal.'],
        ] as [$q, $a]): ?>
        <div style="padding:16px 0;border-bottom:1px solid var(--border)">
            <div style="font-size:14px;font-weight:700;color:var(--text-1);margin-bottom:6px">Q: <?= htmlspecialchars($q) ?></div>
            <div style="font-size:13.5px;color:var(--text-2);line-height:1.7"><?= htmlspecialchars($a) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- TOMBOL AKSI -->
<div style="display:flex;gap:10px;flex-wrap:wrap">
    <a href="pengaturan.php" class="btn btn-primary">⚙️ Buka Pengaturan SIAK</a>
    <a href="index.php" class="btn btn-secondary">← Kembali ke Dashboard SIAK</a>
    <a href="https://dukcapil.kemendagri.go.id/page/read/integrasi-data" target="_blank" class="btn btn-secondary">🔗 Halaman Integrasi Dukcapil</a>
</div>

</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
