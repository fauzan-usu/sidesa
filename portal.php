<?php
/**
 * PORTAL PUBLIK DESA — HALAMAN BERANDA
 * Dapat diakses siapa saja tanpa login
 * URL: http://localhost/sistem_desa/portal.php?desa=DSA-001
 *      atau http://localhost/sistem_desa/portal.php (tampil semua desa)
 * FNA & Kawan-kawan — Universitas Sumatera Utara — 2025
 */

require_once __DIR__ . '/includes/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$db = getDB();

// ─── Tentukan desa yang ditampilkan ──────────────────────────
// Prioritas: ?kode= → ?id= → desa pertama yang aktif & portal_aktif
$kodeDesa = trim($_GET['kode'] ?? '');
$idDesa   = (int) ($_GET['id'] ?? 0);

if ($kodeDesa) {
    $q = $db->prepare("SELECT d.*, p.* FROM desa d LEFT JOIN profil_desa p ON d.id=p.id_desa WHERE d.kode_desa=? AND d.aktif=1");
    $q->execute([$kodeDesa]);
} elseif ($idDesa) {
    $q = $db->prepare("SELECT d.*, p.* FROM desa d LEFT JOIN profil_desa p ON d.id=p.id_desa WHERE d.id=? AND d.aktif=1");
    $q->execute([$idDesa]);
} else {
    // Tanpa parameter: tampilkan halaman pilih desa
    $desas = $db->query("SELECT d.*, p.warna_tema, p.slogan, p.foto_sampul, p.portal_aktif FROM desa d LEFT JOIN profil_desa p ON d.id=p.id_desa WHERE d.aktif=1 AND (p.portal_aktif IS NULL OR p.portal_aktif=1) ORDER BY d.nama_desa")->fetchAll();
    require __DIR__ . '/portal_pilih_desa.php';
    exit;
}

$desa = $q->fetch();
if (!$desa || ($desa['portal_aktif'] ?? 1) == 0) {
    // Portal dinonaktifkan atau desa tidak ditemukan
    header('Location: ' . APP_URL . '/portal.php');
    exit;
}

$idDesaAktif  = (int) $desa['id'];
$warnaTema    = $desa['warna_tema'] ?? '#5B4FCF';
$namaDesaE    = htmlspecialchars($desa['nama_desa']);

// ─── Hitung statistik ringkas ─────────────────────────────────
$stat = $db->prepare("SELECT
    COUNT(*) as total,
    SUM(jenis_kelamin='L') as laki,
    SUM(jenis_kelamin='P') as perempuan,
    SUM(status_dtks=1) as dtks
    FROM warga WHERE id_desa=? AND status_hidup='Hidup'");
$stat->execute([$idDesaAktif]);
$statistik = $stat->fetch();

// ─── Berita featured (tampil di depan) ───────────────────────
$featured = $db->prepare("SELECT * FROM berita WHERE id_desa=? AND status='terbit' AND tampil_di_depan=1 ORDER BY tgl_terbit DESC LIMIT 3");
$featured->execute([$idDesaAktif]);
$beritaFeatured = $featured->fetchAll();

// ─── Semua berita terbaru ─────────────────────────────────────
$perHal = 6;
$halaman = max(1, (int)($_GET['hal'] ?? 1));
$offset  = ($halaman - 1) * $perHal;
$kat     = $_GET['kat'] ?? '';
$katWhere = $kat ? "AND kategori=?" : "";

$pTotal = $db->prepare("SELECT COUNT(*) FROM berita WHERE id_desa=? AND status='terbit' $katWhere");
$pTotal->execute($kat ? [$idDesaAktif, $kat] : [$idDesaAktif]);
$totalBerita = (int) $pTotal->fetchColumn();
$totalHal    = max(1, ceil($totalBerita / $perHal));

$pBerita = $db->prepare("SELECT b.*, pg.nama_lengkap as penulis FROM berita b LEFT JOIN pengguna pg ON b.id_penulis=pg.id WHERE b.id_desa=? AND b.status='terbit' $katWhere ORDER BY b.tgl_terbit DESC LIMIT $perHal OFFSET $offset");
$pBerita->execute($kat ? [$idDesaAktif, $kat] : [$idDesaAktif]);
$beritaList = $pBerita->fetchAll();

// ─── Update view count kalau buka detail berita ───────────────
$detailSlug = trim($_GET['berita'] ?? '');
$detailBerita = null;
if ($detailSlug) {
    $pDetail = $db->prepare("SELECT b.*, pg.nama_lengkap as penulis FROM berita b LEFT JOIN pengguna pg ON b.id_penulis=pg.id WHERE b.id_desa=? AND b.slug=? AND b.status='terbit'");
    $pDetail->execute([$idDesaAktif, $detailSlug]);
    $detailBerita = $pDetail->fetch();
    if ($detailBerita) {
        $db->prepare("UPDATE berita SET views=views+1 WHERE id=?")->execute([$detailBerita['id']]);
    }
}

// Kategori unik yang ada
$kats = $db->prepare("SELECT DISTINCT kategori, COUNT(*) as n FROM berita WHERE id_desa=? AND status='terbit' GROUP BY kategori ORDER BY n DESC");
$kats->execute([$idDesaAktif]);
$kategoriList = $kats->fetchAll();

// Warna derivatif dari warna tema
// Kita buat versi lebih gelap secara sederhana (tambah 20 hex)
$warnaGelap = $warnaTema; // Fallback, tidak perlu library

// Label kategori
$labelKat = ['berita'=>'Berita','pengumuman'=>'Pengumuman','agenda'=>'Agenda','pembangunan'=>'Pembangunan','sosial'=>'Sosial','lainnya'=>'Lainnya'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Portal resmi <?= $namaDesaE ?> — Informasi, berita, dan layanan publik desa">
<title><?= $detailBerita ? htmlspecialchars($detailBerita['judul']).' — ' : '' ?>Portal <?= $namaDesaE ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
<style>
/* ─── RESET & BASE ────────────────────────────────────── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --tema:    <?= $warnaTema ?>;
  --tema-dk: color-mix(in srgb, <?= $warnaTema ?> 80%, #000);
  --tema-lt: color-mix(in srgb, <?= $warnaTema ?> 12%, #fff);
  --tema-md: color-mix(in srgb, <?= $warnaTema ?> 25%, #fff);
  --text-1: #1A1830;
  --text-2: #4A4769;
  --text-3: #8884A8;
  --bg:     #F7F6F3;
  --white:  #FFFFFF;
  --border: #E2DFF5;
  --shadow: 0 2px 8px rgba(0,0,0,.07), 0 1px 2px rgba(0,0,0,.05);
  --shadow-md: 0 8px 24px rgba(0,0,0,.10);
  --r:      10px;
  --r-lg:   16px;
}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text-1);font-size:15px;line-height:1.6}
a{color:inherit;text-decoration:none}
img{max-width:100%;height:auto}

/* ─── TOPBAR ──────────────────────────────────────────── */
.topbar{
  background:var(--tema);
  color:#fff;
  display:flex;align-items:center;justify-content:space-between;
  padding:0 5%;
  height:64px;
  position:sticky;top:0;z-index:100;
  box-shadow:0 2px 12px rgba(0,0,0,.15);
}
.topbar-brand{display:flex;align-items:center;gap:12px}
.topbar-logo{
  width:40px;height:40px;background:rgba(255,255,255,.2);
  border-radius:10px;display:flex;align-items:center;justify-content:center;
  font-size:20px;flex-shrink:0;
}
.topbar-title{font-size:16px;font-weight:800;color:#fff;line-height:1.2}
.topbar-sub{font-size:11px;color:rgba(255,255,255,.7)}
.topbar-nav{display:flex;align-items:center;gap:6px}
.topbar-nav a{
  padding:7px 14px;border-radius:var(--r);
  font-size:13px;font-weight:600;color:rgba(255,255,255,.85);
  transition:background .15s;
}
.topbar-nav a:hover{background:rgba(255,255,255,.15);color:#fff}
.topbar-nav a.active{background:rgba(255,255,255,.2);color:#fff}
.btn-login-top{
  background:rgba(255,255,255,.18);border:1.5px solid rgba(255,255,255,.4);
  color:#fff;padding:7px 18px;border-radius:var(--r);
  font-size:13px;font-weight:700;cursor:pointer;
  transition:background .15s,border-color .15s;
  display:flex;align-items:center;gap:6px;
}
.btn-login-top:hover{background:rgba(255,255,255,.3);border-color:rgba(255,255,255,.7)}
.hamburger-pub{display:none;background:none;border:none;cursor:pointer;color:#fff;padding:4px}

/* ─── HERO ────────────────────────────────────────────── */
.hero{
  background:linear-gradient(135deg, var(--tema) 0%, var(--tema-dk) 100%);
  padding:72px 5% 64px;
  color:#fff;
  position:relative;
  overflow:hidden;
}
.hero::before{
  content:'';position:absolute;right:-5%;top:-20%;
  width:55%;height:140%;
  background:rgba(255,255,255,.04);
  border-radius:50%;
}
.hero::after{
  content:'';position:absolute;right:10%;bottom:-30%;
  width:30%;height:80%;
  background:rgba(255,255,255,.04);
  border-radius:50%;
}
.hero-inner{position:relative;z-index:1;max-width:700px}
.hero-badge{
  display:inline-flex;align-items:center;gap:6px;
  background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);
  padding:5px 14px;border-radius:999px;
  font-size:12px;font-weight:700;letter-spacing:.04em;
  margin-bottom:20px;
}
.hero h1{font-size:clamp(28px,4vw,48px);font-weight:900;line-height:1.15;letter-spacing:-.02em;margin-bottom:14px}
.hero h1 span{color:rgba(255,255,255,.75)}
.hero p{font-size:16px;color:rgba(255,255,255,.8);max-width:560px;line-height:1.7;margin-bottom:28px}
.hero-actions{display:flex;gap:10px;flex-wrap:wrap}
.btn-hero-primary{
  background:#fff;color:var(--tema);
  padding:11px 24px;border-radius:var(--r);font-weight:800;font-size:14px;
  transition:box-shadow .15s,transform .1s;
}
.btn-hero-primary:hover{box-shadow:0 6px 20px rgba(0,0,0,.2);transform:translateY(-1px)}
.btn-hero-ghost{
  background:rgba(255,255,255,.15);border:1.5px solid rgba(255,255,255,.35);color:#fff;
  padding:11px 24px;border-radius:var(--r);font-weight:700;font-size:14px;
  transition:background .15s;
}
.btn-hero-ghost:hover{background:rgba(255,255,255,.25)}

/* ─── STAT STRIP ──────────────────────────────────────── */
.stat-strip{
  background:var(--white);
  border-bottom:1px solid var(--border);
  padding:20px 5%;
  display:flex;gap:0;
  box-shadow:var(--shadow);
}
.stat-item{
  flex:1;text-align:center;
  padding:10px 16px;
  border-right:1px solid var(--border);
}
.stat-item:last-child{border-right:none}
.stat-item .num{font-size:28px;font-weight:800;color:var(--tema);line-height:1}
.stat-item .lbl{font-size:12px;color:var(--text-3);margin-top:4px;font-weight:600;text-transform:uppercase;letter-spacing:.04em}

/* ─── MAIN LAYOUT ─────────────────────────────────────── */
.main-portal{max-width:1200px;margin:0 auto;padding:40px 5%;display:flex;gap:28px;align-items:flex-start}
.content-col{flex:1;min-width:0}
.sidebar-col{width:300px;flex-shrink:0}

/* ─── SECTION HEADER ──────────────────────────────────── */
.sec-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px}
.sec-title{font-size:20px;font-weight:800;color:var(--text-1);display:flex;align-items:center;gap:8px}
.sec-title::before{content:'';display:block;width:4px;height:22px;background:var(--tema);border-radius:2px}
.sec-link{font-size:13px;font-weight:700;color:var(--tema)}
.sec-link:hover{text-decoration:underline}

/* ─── FEATURED SLIDER ─────────────────────────────────── */
.featured-grid{display:grid;grid-template-columns:1.8fr 1fr;gap:16px;margin-bottom:40px}
.featured-main{position:relative;border-radius:var(--r-lg);overflow:hidden;background:var(--tema);height:320px;cursor:pointer}
.featured-main:hover .featured-img{transform:scale(1.03)}
.featured-img{width:100%;height:100%;object-fit:cover;transition:transform .4s;display:block}
.featured-main .no-img{
  width:100%;height:100%;
  background:linear-gradient(135deg, var(--tema) 0%, var(--tema-dk) 100%);
  display:flex;align-items:center;justify-content:center;
  font-size:64px;
}
.featured-overlay{
  position:absolute;bottom:0;left:0;right:0;
  padding:24px;
  background:linear-gradient(0deg,rgba(0,0,0,.75) 0%,transparent 100%);
}
.featured-overlay .kat-badge{display:inline-block;padding:3px 10px;border-radius:999px;background:var(--tema);font-size:11px;font-weight:700;color:#fff;margin-bottom:8px;text-transform:uppercase;letter-spacing:.04em}
.featured-overlay h2{font-size:18px;font-weight:800;color:#fff;line-height:1.35;margin-bottom:6px}
.featured-overlay .meta{font-size:12px;color:rgba(255,255,255,.7)}

.featured-side{display:flex;flex-direction:column;gap:12px}
.featured-side-item{
  background:var(--white);border:1px solid var(--border);border-radius:var(--r);
  overflow:hidden;display:flex;gap:0;cursor:pointer;
  transition:box-shadow .15s,border-color .15s;
  flex:1;
}
.featured-side-item:hover{box-shadow:var(--shadow-md);border-color:var(--tema)}
.side-item-img{width:90px;flex-shrink:0;background:var(--tema-lt);display:flex;align-items:center;justify-content:center;font-size:28px}
.side-item-img img{width:90px;height:100%;object-fit:cover}
.side-item-body{padding:12px;flex:1;min-width:0}
.side-item-kat{font-size:10.5px;font-weight:700;color:var(--tema);text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px}
.side-item-body h3{font-size:13px;font-weight:700;line-height:1.4;color:var(--text-1);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.side-item-body .meta{font-size:11px;color:var(--text-3);margin-top:4px}

/* ─── BERITA GRID ─────────────────────────────────────── */
.berita-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;margin-bottom:28px}
.berita-card{
  background:var(--white);border:1px solid var(--border);border-radius:var(--r-lg);
  overflow:hidden;cursor:pointer;
  transition:box-shadow .2s,transform .15s,border-color .2s;
  display:flex;flex-direction:column;
}
.berita-card:hover{box-shadow:var(--shadow-md);transform:translateY(-2px);border-color:var(--tema)}
.berita-card-img{
  height:180px;background:var(--tema-lt);
  display:flex;align-items:center;justify-content:center;font-size:48px;
  position:relative;overflow:hidden;
}
.berita-card-img img{width:100%;height:100%;object-fit:cover;position:absolute;inset:0}
.berita-card-body{padding:16px;flex:1;display:flex;flex-direction:column}
.berita-kat{display:inline-flex;margin-bottom:8px}
.kat-chip{display:inline-block;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;background:var(--tema-lt);color:var(--tema)}
.berita-card-body h3{font-size:15px;font-weight:700;line-height:1.4;color:var(--text-1);margin-bottom:8px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.berita-card-body p{font-size:13px;color:var(--text-2);line-height:1.6;flex:1;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.berita-card-footer{display:flex;align-items:center;justify-content:space-between;margin-top:12px;padding-top:12px;border-top:1px solid var(--border);font-size:12px;color:var(--text-3)}
.berita-card-footer .views{display:flex;align-items:center;gap:4px}

/* ─── FILTER KATEGORI ─────────────────────────────────── */
.kat-filter{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px}
.kat-btn{
  padding:6px 14px;border-radius:999px;font-size:12.5px;font-weight:600;
  border:1.5px solid var(--border);background:var(--white);color:var(--text-2);
  cursor:pointer;transition:background .15s,color .15s,border-color .15s;
}
.kat-btn:hover,.kat-btn.active{background:var(--tema);color:#fff;border-color:var(--tema)}

/* ─── PAGINATION ──────────────────────────────────────── */
.pagination{display:flex;gap:4px;justify-content:center;margin-top:12px;flex-wrap:wrap}
.page-btn{padding:7px 14px;border:1.5px solid var(--border);border-radius:var(--r);font-size:13px;font-weight:600;color:var(--text-2);background:var(--white);cursor:pointer;transition:background .15s}
.page-btn:hover{background:var(--tema-lt);border-color:var(--tema);color:var(--tema)}
.page-btn.active{background:var(--tema);color:#fff;border-color:var(--tema)}
.page-btn.disabled{opacity:.35;pointer-events:none}

/* ─── SIDEBAR WIDGET ──────────────────────────────────── */
.widget{background:var(--white);border:1px solid var(--border);border-radius:var(--r-lg);overflow:hidden;margin-bottom:20px}
.widget-header{padding:14px 18px;border-bottom:1px solid var(--border);font-size:14px;font-weight:800;color:var(--text-1);display:flex;align-items:center;gap:8px}
.widget-header::before{content:'';width:3px;height:16px;background:var(--tema);border-radius:2px;display:block}
.widget-body{padding:18px}

.profil-item{display:flex;justify-content:space-between;align-items:flex-start;padding:8px 0;border-bottom:1px solid var(--border);font-size:13px}
.profil-item:last-child{border-bottom:none}
.profil-label{color:var(--text-3);font-weight:500;flex-shrink:0;margin-right:12px;min-width:80px}
.profil-val{color:var(--text-1);font-weight:600;text-align:right}

.berita-mini{display:flex;gap:10px;padding:10px 0;border-bottom:1px solid var(--border);cursor:pointer}
.berita-mini:last-child{border-bottom:none;padding-bottom:0}
.berita-mini:hover .mini-judul{color:var(--tema)}
.mini-num{width:28px;height:28px;border-radius:8px;background:var(--tema-lt);color:var(--tema);font-size:12px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.mini-judul{font-size:13px;font-weight:600;color:var(--text-1);line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;transition:color .15s}
.mini-tanggal{font-size:11px;color:var(--text-3);margin-top:3px}

.login-widget{background:linear-gradient(135deg, var(--tema) 0%, var(--tema-dk) 100%);border:none}
.login-widget .widget-header{border-bottom-color:rgba(255,255,255,.2);color:#fff}
.login-widget .widget-header::before{background:rgba(255,255,255,.6)}
.login-widget .widget-body{color:#fff}
.login-widget p{font-size:13px;color:rgba(255,255,255,.8);margin-bottom:14px;line-height:1.6}
.btn-login-widget{
  width:100%;padding:10px;background:rgba(255,255,255,.2);
  border:1.5px solid rgba(255,255,255,.4);color:#fff;
  border-radius:var(--r);font-family:inherit;font-size:13.5px;font-weight:700;
  cursor:pointer;transition:background .15s;
  display:block;text-align:center;
}
.btn-login-widget:hover{background:rgba(255,255,255,.32)}

/* ─── DETAIL BERITA ───────────────────────────────────── */
.detail-wrap{background:var(--white);border:1px solid var(--border);border-radius:var(--r-lg);overflow:hidden}
.detail-header{padding:28px 32px;border-bottom:1px solid var(--border)}
.detail-header .kat-chip{margin-bottom:12px}
.detail-header h1{font-size:clamp(20px,3vw,30px);font-weight:900;line-height:1.25;letter-spacing:-.02em;color:var(--text-1);margin-bottom:14px}
.detail-meta{display:flex;align-items:center;gap:16px;flex-wrap:wrap;font-size:13px;color:var(--text-3)}
.detail-meta .dot{color:var(--border)}
.detail-img{width:100%;max-height:400px;object-fit:cover;display:block}
.detail-isi{padding:28px 32px;line-height:1.85;font-size:15.5px;color:var(--text-2)}
.detail-isi p{margin-bottom:18px}
.detail-isi ul,
.detail-isi ol{padding-left:24px;margin-bottom:18px}
.detail-isi li{margin-bottom:6px}
.detail-isi strong{color:var(--text-1)}
.back-btn{display:inline-flex;align-items:center;gap:6px;font-size:13.5px;font-weight:700;color:var(--tema);margin-bottom:20px}
.back-btn:hover{text-decoration:underline}
.back-btn svg{flex-shrink:0}

/* ─── FOOTER ──────────────────────────────────────────── */
.footer-portal{
  background:var(--text-1);color:rgba(255,255,255,.7);
  padding:40px 5% 24px;margin-top:60px;
}
.footer-inner{max-width:1200px;margin:0 auto;display:flex;gap:40px;flex-wrap:wrap;margin-bottom:32px}
.footer-col{flex:1;min-width:200px}
.footer-col h4{font-size:14px;font-weight:800;color:#fff;margin-bottom:14px}
.footer-col p,.footer-col a{font-size:13px;line-height:1.8;color:rgba(255,255,255,.6)}
.footer-col a:hover{color:#fff}
.footer-bottom{max-width:1200px;margin:0 auto;border-top:1px solid rgba(255,255,255,.1);padding-top:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;font-size:12px;color:rgba(255,255,255,.4)}
.fna-badge{color:rgba(255,255,255,.5);font-style:italic}

/* ─── RESPONSIVE ──────────────────────────────────────── */
@media(max-width:900px){
  .featured-grid{grid-template-columns:1fr}
  .featured-side{flex-direction:row;overflow-x:auto}
  .featured-side-item{min-width:220px}
  .main-portal{flex-direction:column}
  .sidebar-col{width:100%}
  .topbar-nav{display:none}
  .hamburger-pub{display:block}
}
@media(max-width:600px){
  .stat-strip{flex-wrap:wrap}
  .stat-item{flex:0 0 50%;border-bottom:1px solid var(--border)}
  .stat-item:nth-child(2n){border-right:none}
  .hero{padding:48px 5% 40px}
  .detail-header,.detail-isi{padding:20px}
}
</style>
</head>
<body>

<!-- TOPBAR -->
<header class="topbar">
  <div class="topbar-brand">
    <div class="topbar-logo">🏘️</div>
    <div>
      <div class="topbar-title"><?= $namaDesaE ?></div>
      <div class="topbar-sub"><?= htmlspecialchars($desa['kecamatan'] . ', ' . $desa['kabupaten']) ?></div>
    </div>
  </div>
  <nav class="topbar-nav">
    <a href="?id=<?= $idDesaAktif ?>" class="<?= !$detailSlug && !$kat ? 'active' : '' ?>">Beranda</a>
    <a href="?id=<?= $idDesaAktif ?>&kat=pengumuman" class="<?= $kat==='pengumuman' ? 'active' : '' ?>">Pengumuman</a>
    <a href="?id=<?= $idDesaAktif ?>&kat=pembangunan" class="<?= $kat==='pembangunan' ? 'active' : '' ?>">Pembangunan</a>
    <a href="?id=<?= $idDesaAktif ?>&kat=agenda" class="<?= $kat==='agenda' ? 'active' : '' ?>">Agenda</a>
    <a href="portal.php">🏠 Ganti Desa</a>
  </nav>
  <a href="<?= APP_URL ?>/login.php" class="btn-login-top">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
    Masuk Admin
  </a>
</header>

<?php if ($detailBerita): ?>
<!-- ═══════════ HALAMAN DETAIL BERITA ═══════════ -->
<div class="main-portal">
  <div class="content-col">
    <a href="?id=<?= $idDesaAktif ?>" class="back-btn">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
      Kembali ke Beranda
    </a>

    <div class="detail-wrap">
      <div class="detail-header">
        <div class="kat-chip"><?= htmlspecialchars($labelKat[$detailBerita['kategori']] ?? $detailBerita['kategori']) ?></div>
        <h1><?= htmlspecialchars($detailBerita['judul']) ?></h1>
        <div class="detail-meta">
          <span>📅 <?= $detailBerita['tgl_terbit'] ? htmlspecialchars(formatTanggal($detailBerita['tgl_terbit'])) : '-' ?></span>
          <span class="dot">·</span>
          <span>✍️ <?= htmlspecialchars($detailBerita['penulis'] ?? 'Redaksi') ?></span>
          <span class="dot">·</span>
          <span>👁 <?= number_format($detailBerita['views'] + 1) ?> tayangan</span>
        </div>
      </div>
      <?php if ($detailBerita['foto_utama']): ?>
      <img src="<?= UPLOAD_URL ?>berita/<?= htmlspecialchars($detailBerita['foto_utama']) ?>" alt="<?= htmlspecialchars($detailBerita['judul']) ?>" class="detail-img">
      <?php endif; ?>
      <div class="detail-isi">
        <?= $detailBerita['isi'] /* HTML sudah disanitasi saat input */ ?>
      </div>
    </div>
  </div>

  <?php include __DIR__ . '/portal_sidebar.php'; ?>
</div>

<?php else: ?>
<!-- ═══════════ HALAMAN BERANDA PORTAL ═══════════ -->

<!-- HERO -->
<section class="hero">
  <div class="hero-inner">
    <div class="hero-badge">🏘️ Portal Resmi Desa</div>
    <h1>
      Selamat Datang di<br>
      <span><?= $namaDesaE ?></span>
    </h1>
    <?php if (!empty($desa['slogan'])): ?>
    <p><?= htmlspecialchars($desa['slogan']) ?></p>
    <?php else: ?>
    <p>Informasi resmi, berita terkini, dan layanan publik <?= $namaDesaE ?> tersedia untuk seluruh warga dan masyarakat luas.</p>
    <?php endif; ?>
    <div class="hero-actions">
      <a href="#berita" class="btn-hero-primary">Baca Berita Terbaru</a>
      <a href="<?= APP_URL ?>/login.php" class="btn-hero-ghost">Masuk sebagai Admin</a>
    </div>
  </div>
</section>

<!-- STATISTIK STRIP -->
<div class="stat-strip">
  <div class="stat-item">
    <div class="num"><?= number_format($statistik['total'] ?? 0) ?></div>
    <div class="lbl">Total Warga</div>
  </div>
  <div class="stat-item">
    <div class="num"><?= number_format($statistik['laki'] ?? 0) ?></div>
    <div class="lbl">Laki-laki</div>
  </div>
  <div class="stat-item">
    <div class="num"><?= number_format($statistik['perempuan'] ?? 0) ?></div>
    <div class="lbl">Perempuan</div>
  </div>
  <div class="stat-item">
    <div class="num"><?= number_format($totalBerita) ?></div>
    <div class="lbl">Total Berita</div>
  </div>
</div>

<!-- KONTEN UTAMA -->
<div class="main-portal">
  <div class="content-col">

    <!-- FEATURED -->
    <?php if (!empty($beritaFeatured)): ?>
    <div class="featured-grid">
      <?php $f0 = $beritaFeatured[0]; ?>
      <a href="?id=<?= $idDesaAktif ?>&berita=<?= htmlspecialchars($f0['slug']) ?>" class="featured-main">
        <?php if ($f0['foto_utama']): ?>
        <img src="<?= UPLOAD_URL ?>berita/<?= htmlspecialchars($f0['foto_utama']) ?>" alt="" class="featured-img">
        <?php else: ?>
        <div class="no-img">🏘️</div>
        <?php endif; ?>
        <div class="featured-overlay">
          <div class="kat-badge"><?= htmlspecialchars($labelKat[$f0['kategori']] ?? $f0['kategori']) ?></div>
          <h2><?= htmlspecialchars($f0['judul']) ?></h2>
          <div class="meta">📅 <?= $f0['tgl_terbit'] ? htmlspecialchars(formatTanggal($f0['tgl_terbit'])) : '-' ?> · 👁 <?= number_format($f0['views']) ?></div>
        </div>
      </a>
      <div class="featured-side">
        <?php foreach (array_slice($beritaFeatured, 1) as $fi): ?>
        <a href="?id=<?= $idDesaAktif ?>&berita=<?= htmlspecialchars($fi['slug']) ?>" class="featured-side-item">
          <div class="side-item-img">
            <?php if ($fi['foto_utama']): ?>
            <img src="<?= UPLOAD_URL ?>berita/<?= htmlspecialchars($fi['foto_utama']) ?>" alt="">
            <?php else: ?>
            🗞️
            <?php endif; ?>
          </div>
          <div class="side-item-body">
            <div class="side-item-kat"><?= htmlspecialchars($labelKat[$fi['kategori']] ?? $fi['kategori']) ?></div>
            <h3><?= htmlspecialchars($fi['judul']) ?></h3>
            <div class="meta">📅 <?= $fi['tgl_terbit'] ? htmlspecialchars(formatTanggal($fi['tgl_terbit'])) : '-' ?></div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- FILTER & DAFTAR BERITA -->
    <div id="berita">
      <div class="sec-header">
        <div class="sec-title">Berita & Informasi</div>
      </div>

      <?php if (!empty($kategoriList)): ?>
      <div class="kat-filter">
        <a href="?id=<?= $idDesaAktif ?>" class="kat-btn <?= !$kat ? 'active' : '' ?>">Semua</a>
        <?php foreach ($kategoriList as $k): ?>
        <a href="?id=<?= $idDesaAktif ?>&kat=<?= $k['kategori'] ?>" class="kat-btn <?= $kat===$k['kategori'] ? 'active' : '' ?>"><?= htmlspecialchars($labelKat[$k['kategori']] ?? $k['kategori']) ?> <span style="opacity:.6">(<?= $k['n'] ?>)</span></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if (empty($beritaList)): ?>
      <div style="text-align:center;padding:60px 20px;color:var(--text-3)">
        <div style="font-size:48px;margin-bottom:12px">📭</div>
        <p style="font-size:15px">Belum ada berita yang diterbitkan.</p>
      </div>
      <?php else: ?>
      <div class="berita-grid">
        <?php foreach ($beritaList as $b): ?>
        <a href="?id=<?= $idDesaAktif ?>&berita=<?= htmlspecialchars($b['slug']) ?>" class="berita-card">
          <div class="berita-card-img">
            <?php if ($b['foto_utama']): ?>
            <img src="<?= UPLOAD_URL ?>berita/<?= htmlspecialchars($b['foto_utama']) ?>" alt="">
            <?php else: ?>
            <?= ['berita'=>'🗞️','pengumuman'=>'📢','agenda'=>'📅','pembangunan'=>'🏗️','sosial'=>'🤝','lainnya'=>'📄'][$b['kategori']] ?? '📄' ?>
            <?php endif; ?>
          </div>
          <div class="berita-card-body">
            <div class="berita-kat"><span class="kat-chip"><?= htmlspecialchars($labelKat[$b['kategori']] ?? $b['kategori']) ?></span></div>
            <h3><?= htmlspecialchars($b['judul']) ?></h3>
            <p><?= htmlspecialchars($b['ringkasan'] ?? '') ?></p>
            <div class="berita-card-footer">
              <span>📅 <?= $b['tgl_terbit'] ? htmlspecialchars(formatTanggal($b['tgl_terbit'])) : '-' ?></span>
              <span class="views">👁 <?= number_format($b['views']) ?></span>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>

      <?php if ($totalHal > 1): ?>
      <div class="pagination">
        <a href="?id=<?= $idDesaAktif ?>&<?= $kat?"kat=$kat&":'' ?>hal=<?= max(1,$halaman-1) ?>" class="page-btn <?= $halaman<=1?'disabled':'' ?>">‹</a>
        <?php for ($p=max(1,$halaman-2); $p<=min($totalHal,$halaman+2); $p++): ?>
        <a href="?id=<?= $idDesaAktif ?>&<?= $kat?"kat=$kat&":'' ?>hal=<?= $p ?>" class="page-btn <?= $p===$halaman?'active':'' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <a href="?id=<?= $idDesaAktif ?>&<?= $kat?"kat=$kat&":'' ?>hal=<?= min($totalHal,$halaman+1) ?>" class="page-btn <?= $halaman>=$totalHal?'disabled':'' ?>">›</a>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php include __DIR__ . '/portal_sidebar.php'; ?>
</div>
<?php endif; ?>

<!-- FOOTER -->
<footer class="footer-portal">
  <div class="footer-inner">
    <div class="footer-col">
      <h4>🏘️ <?= $namaDesaE ?></h4>
      <p><?= htmlspecialchars($desa['alamat'] ?? $desa['kecamatan'].', '.$desa['kabupaten']) ?></p>
      <?php if (!empty($desa['telepon'])): ?><p>☎ <?= htmlspecialchars($desa['telepon']) ?></p><?php endif; ?>
      <?php if (!empty($desa['email'])): ?><p>✉ <?= htmlspecialchars($desa['email']) ?></p><?php endif; ?>
      <?php if (!empty($desa['jam_layanan'])): ?><p>🕐 <?= htmlspecialchars($desa['jam_layanan']) ?></p><?php endif; ?>
    </div>
    <div class="footer-col">
      <h4>Tautan Cepat</h4>
      <a href="?id=<?= $idDesaAktif ?>">Beranda</a><br>
      <a href="?id=<?= $idDesaAktif ?>&kat=pengumuman">Pengumuman</a><br>
      <a href="?id=<?= $idDesaAktif ?>&kat=pembangunan">Pembangunan</a><br>
      <a href="?id=<?= $idDesaAktif ?>&kat=agenda">Agenda</a><br>
      <a href="portal.php">Portal Desa Lainnya</a>
    </div>
    <div class="footer-col">
      <h4>Sistem Informasi</h4>
      <a href="<?= APP_URL ?>/login.php">Masuk sebagai Admin</a><br>
      <p style="margin-top:10px">Dikelola menggunakan SiDesa — Sistem Informasi Warga Desa</p>
    </div>
  </div>
  <div class="footer-bottom">
    <span>© <?= date('Y') ?> <?= $namaDesaE ?>. Hak Cipta Dilindungi.</span>
    <span class="fna-badge">Powered by SiDesa · FNA &amp; Kawan-kawan (Fauzan Nur Ahmadi) · Universitas Sumatera Utara</span>
  </div>
</footer>

<script>
// Smooth scroll ke #berita
document.querySelectorAll('a[href="#berita"]').forEach(a => {
  a.addEventListener('click', e => {
    e.preventDefault();
    document.getElementById('berita')?.scrollIntoView({behavior:'smooth',block:'start'});
  });
});
</script>
</body>
</html>
