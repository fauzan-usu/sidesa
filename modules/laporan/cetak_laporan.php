<?php
/**
 * CETAK LAPORAN DEMOGRAFIS — FORMAT PDF/PRINT
 * FNA & Kawan-kawan © 2025 — Fauzan Nur Ahmadi & Kawan-kawan
 */
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db     = getDB();
$idDesa = getIdDesaAktif();
$fDesa  = (int) ($_GET['desa'] ?? $idDesa ?? 0);

$where = $fDesa ? "WHERE w.id_desa=$fDesa AND w.status_hidup='Hidup'" : "WHERE w.status_hidup='Hidup'";

$rowDesa = null;
if ($fDesa) {
    $s = $db->prepare("SELECT * FROM desa WHERE id=?");
    $s->execute([$fDesa]);
    $rowDesa = $s->fetch();
}

$total     = $db->query("SELECT COUNT(*) FROM warga w $where")->fetchColumn();
$lakiLaki  = $db->query("SELECT COUNT(*) FROM warga w $where AND jenis_kelamin='L'")->fetchColumn();
$perempuan = $db->query("SELECT COUNT(*) FROM warga w $where AND jenis_kelamin='P'")->fetchColumn();
$dtks      = $db->query("SELECT COUNT(*) FROM warga w $where AND status_dtks=1")->fetchColumn();

$agama      = $db->query("SELECT agama, COUNT(*) as n FROM warga w $where GROUP BY agama ORDER BY n DESC")->fetchAll();
$pendidikan = $db->query("SELECT pendidikan, COUNT(*) as n FROM warga w $where GROUP BY pendidikan ORDER BY FIELD(pendidikan,'Tidak/Belum Sekolah','SD/Sederajat','SMP/Sederajat','SMA/Sederajat','D1/D2/D3','S1','S2','S3')")->fetchAll();
$pekerjaan  = $db->query("SELECT IFNULL(pekerjaan,'Tidak/Belum Bekerja') as pekerjaan, COUNT(*) as n FROM warga w $where GROUP BY pekerjaan ORDER BY n DESC LIMIT 10")->fetchAll();

$wargaTgl = $db->query("SELECT tanggal_lahir FROM warga w $where")->fetchAll();
$grupUsia = ['Balita (0-4)'=>0,'Anak (5-14)'=>0,'Remaja (15-25)'=>0,'Dewasa (26-45)'=>0,'Madya (46-59)'=>0,'Lansia (60+)'=>0];
foreach ($wargaTgl as $w) { $grupUsia[kelompokUsia(hitungUmur($w['tanggal_lahir']))]++; }

$judul = $rowDesa ? "Desa {$rowDesa['nama_desa']}, Kec. {$rowDesa['kecamatan']}, {$rowDesa['kabupaten']}" : "Semua Desa";
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Laporan Demografis — <?= e($judul) ?></title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: 'Times New Roman', serif; font-size: 12pt; color: #111; background: #fff; }
  .page { width: 210mm; min-height: 297mm; padding: 20mm 25mm; margin: 0 auto; }
  .kop { display:flex; align-items:center; gap:16px; border-bottom: 3px solid #1A1830; padding-bottom: 10px; margin-bottom: 6px; }
  .kop-logo { width:70px; height:70px; border:2px solid #1A1830; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:20pt; font-weight:bold; color:#1A1830; flex-shrink:0; }
  .kop-text { flex:1; }
  .kop-text h1 { font-size:16pt; font-weight:bold; color:#1A1830; letter-spacing:1px; text-transform:uppercase; }
  .kop-text h2 { font-size:12pt; font-weight:normal; margin-top:2px; }
  .kop-text p  { font-size:9.5pt; color:#444; margin-top:2px; }
  .kop-fna { text-align:right; font-size:9pt; color:#5B4FCF; font-style:italic; }

  .judul-laporan { text-align:center; margin:14px 0; }
  .judul-laporan h2 { font-size:14pt; font-weight:bold; text-transform:uppercase; letter-spacing:0.5px; }
  .judul-laporan p  { font-size:11pt; margin-top:4px; }
  .judul-laporan .garis { border:none; border-top:1.5px solid #1A1830; margin:8px auto; width:80%; }

  .meta-table { width:100%; margin-bottom:16px; font-size:10.5pt; }
  .meta-table td { padding:2px 8px 2px 0; vertical-align:top; }
  .meta-table td:first-child { width:180px; color:#555; }
  .meta-table td:nth-child(2) { width:12px; }

  .section-title { font-size:12pt; font-weight:bold; border-bottom:1px solid #aaa; padding-bottom:4px; margin:16px 0 8px; text-transform:uppercase; letter-spacing:0.4px; color:#1A1830; }

  .stat-row { display:flex; gap:10px; margin-bottom:14px; }
  .stat-box { flex:1; border:1.5px solid #1A1830; border-radius:6px; padding:10px 14px; text-align:center; }
  .stat-box .num { font-size:22pt; font-weight:bold; color:#1A1830; }
  .stat-box .lbl { font-size:9pt; color:#444; margin-top:2px; }

  table.rep { width:100%; border-collapse:collapse; font-size:10.5pt; margin-bottom:10px; }
  table.rep th { background:#1A1830; color:#fff; padding:6px 10px; text-align:left; font-weight:bold; font-size:10pt; }
  table.rep td { padding:5px 10px; border-bottom:1px solid #ddd; }
  table.rep tr:nth-child(even) td { background:#F5F4FF; }
  table.rep td.num { text-align:right; font-weight:bold; }
  table.rep td.pct { text-align:right; color:#666; }
  .bar { display:inline-block; height:8px; background:#1A1830; border-radius:3px; vertical-align:middle; }

  .footer { margin-top:28px; border-top:2px solid #1A1830; padding-top:10px; display:flex; justify-content:space-between; align-items:flex-end; font-size:9.5pt; color:#555; }
  .ttd-box { text-align:center; }
  .ttd-box .ttd-line { border-bottom:1px solid #333; width:160px; height:50px; margin:0 auto 4px; }
  .ttd-box p { font-size:9pt; }

  .watermark-fna { text-align:center; font-size:9pt; color:#9090c0; font-style:italic; margin-top:10px; }

  @media print {
    .no-print { display:none !important; }
    .page { margin:0; padding:15mm 20mm; }
    body { background:#fff; }
  }
  @page { size: A4; margin: 0; }
</style>
</head>
<body>
<div class="page">

  <!-- KOP SURAT -->
  <div class="kop">
    <div class="kop-logo">SD</div>
    <div class="kop-text">
      <h1>Sistem Informasi Warga Desa (SiDesa)</h1>
      <h2><?= e($judul) ?></h2>
      <p><?= $rowDesa ? "Kode Pos: {$rowDesa['kode_pos']} | Kepala Desa: " . e($rowDesa['kepala_desa'] ?: '-') : 'Laporan Gabungan Semua Desa' ?></p>
    </div>
    <div class="kop-fna">
      FNA &amp; Kawan-kawan<br>
      <span style="font-size:8pt;color:#888">v1.0 — Open Source</span>
    </div>
  </div>
  <div style="text-align:right;font-size:8.5pt;color:#888;margin-bottom:8px">Dicetak: <?= date('d F Y, H:i') ?> WIB</div>

  <!-- JUDUL -->
  <div class="judul-laporan">
    <h2>Laporan Demografis Kependudukan</h2>
    <p><?= e($judul) ?></p>
    <hr class="garis">
  </div>

  <!-- META INFO -->
  <table class="meta-table">
    <tr><td>Tanggal Cetak</td><td>:</td><td><?= date('d F Y') ?></td></tr>
    <tr><td>Operator</td><td>:</td><td><?= e($_SESSION['nama_lengkap'] ?? '-') ?></td></tr>
    <tr><td>Cakupan Data</td><td>:</td><td><?= e($judul) ?></td></tr>
    <tr><td>Status Warga</td><td>:</td><td>Hidup (aktif)</td></tr>
  </table>

  <!-- RINGKASAN -->
  <div class="section-title">Ringkasan Demografis</div>
  <div class="stat-row">
    <div class="stat-box"><div class="num"><?= number_format($total) ?></div><div class="lbl">Total Warga</div></div>
    <div class="stat-box"><div class="num"><?= number_format($lakiLaki) ?></div><div class="lbl">Laki-laki</div></div>
    <div class="stat-box"><div class="num"><?= number_format($perempuan) ?></div><div class="lbl">Perempuan</div></div>
    <div class="stat-box"><div class="num"><?= number_format($dtks) ?></div><div class="lbl">Warga DTKS</div></div>
  </div>
  <?php if ($total > 0): $rLL = round($lakiLaki/$total*100,1); $rP = round($perempuan/$total*100,1); ?>
  <p style="font-size:10pt;color:#555;margin-bottom:14px">
    Rasio jenis kelamin: <?= $rLL ?>% laki-laki : <?= $rP ?>% perempuan.
    Dari total <?= number_format($total) ?> warga aktif, sebanyak <?= number_format($dtks) ?> jiwa (<?= round($dtks/$total*100,1) ?>%) termasuk dalam Data Terpadu Kesejahteraan Sosial (DTKS).
  </p>
  <?php endif; ?>

  <!-- DISTRIBUSI USIA -->
  <div class="section-title">Distribusi Kelompok Usia</div>
  <table class="rep">
    <thead><tr><th>Kelompok Usia</th><th style="text-align:right">Jumlah</th><th style="text-align:right">Persentase</th><th>Grafik</th></tr></thead>
    <tbody>
      <?php foreach ($grupUsia as $label => $count):
        $pct = $total > 0 ? round($count/$total*100,1) : 0;
        $barW = round($pct * 1.2); ?>
      <tr>
        <td><?= e($label) ?></td>
        <td class="num"><?= number_format($count) ?></td>
        <td class="pct"><?= $pct ?>%</td>
        <td><span class="bar" style="width:<?= $barW ?>px"></span></td>
      </tr>
      <?php endforeach; ?>
      <tr style="font-weight:bold;background:#f0f0ff"><td>TOTAL</td><td class="num"><?= number_format($total) ?></td><td class="pct">100%</td><td></td></tr>
    </tbody>
  </table>

  <!-- DISTRIBUSI AGAMA -->
  <div class="section-title">Distribusi Agama</div>
  <table class="rep">
    <thead><tr><th>Agama</th><th style="text-align:right">Jumlah</th><th style="text-align:right">Persentase</th><th>Grafik</th></tr></thead>
    <tbody>
      <?php foreach ($agama as $row):
        $pct = $total > 0 ? round($row['n']/$total*100,1) : 0;
        $barW = round($pct * 1.2); ?>
      <tr>
        <td><?= e($row['agama']) ?></td>
        <td class="num"><?= number_format($row['n']) ?></td>
        <td class="pct"><?= $pct ?>%</td>
        <td><span class="bar" style="width:<?= $barW ?>px"></span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- DISTRIBUSI PENDIDIKAN -->
  <div class="section-title">Tingkat Pendidikan</div>
  <table class="rep">
    <thead><tr><th>Pendidikan</th><th style="text-align:right">Jumlah</th><th style="text-align:right">Persentase</th><th>Grafik</th></tr></thead>
    <tbody>
      <?php foreach ($pendidikan as $row):
        $pct = $total > 0 ? round($row['n']/$total*100,1) : 0;
        $barW = round($pct * 1.2); ?>
      <tr>
        <td><?= e($row['pendidikan']) ?></td>
        <td class="num"><?= number_format($row['n']) ?></td>
        <td class="pct"><?= $pct ?>%</td>
        <td><span class="bar" style="width:<?= $barW ?>px"></span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- DISTRIBUSI PEKERJAAN -->
  <div class="section-title">Jenis Pekerjaan (10 Terbesar)</div>
  <table class="rep">
    <thead><tr><th>Pekerjaan</th><th style="text-align:right">Jumlah</th><th style="text-align:right">Persentase</th><th>Grafik</th></tr></thead>
    <tbody>
      <?php foreach ($pekerjaan as $row):
        $pct = $total > 0 ? round($row['n']/$total*100,1) : 0;
        $barW = round($pct * 1.2); ?>
      <tr>
        <td><?= e($row['pekerjaan']) ?></td>
        <td class="num"><?= number_format($row['n']) ?></td>
        <td class="pct"><?= $pct ?>%</td>
        <td><span class="bar" style="width:<?= $barW ?>px"></span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- TTD -->
  <div class="footer">
    <div>
      <p>Dicetak oleh sistem SiDesa secara otomatis.</p>
      <p style="margin-top:4px">Data bersumber dari entri langsung petugas desa.</p>
    </div>
    <div class="ttd-box">
      <p><?= date('d F Y') ?></p>
      <div class="ttd-line"></div>
      <p><strong><?= e($_SESSION['nama_lengkap'] ?? 'Operator') ?></strong></p>
      <p><?= ucfirst(str_replace('_',' ', $_SESSION['peran'] ?? '')) ?></p>
    </div>
  </div>

  <!-- WATERMARK FNA -->
  <div class="watermark-fna">
    Dibuat dan didistribusikan secara gratis oleh <strong>FNA &amp; Kawan-kawan</strong> (Fauzan Nur Ahmadi &amp; Kawan-kawan) — Open Source, bebas digunakan.
    <br>Universitas Sumatera Utara · Medan · <?= date('Y') ?>
  </div>

</div>

<!-- TOMBOL CETAK -->
<div class="no-print" style="text-align:center;padding:20px;background:#f5f5f5;border-top:1px solid #ddd;font-family:sans-serif">
  <button onclick="window.print()" style="padding:10px 28px;background:#5B4FCF;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;margin-right:10px">
    🖨️ Cetak / Simpan PDF
  </button>
  <button onclick="window.close()" style="padding:10px 20px;background:#eee;color:#333;border:none;border-radius:8px;font-size:14px;cursor:pointer">Tutup</button>
  <p style="margin-top:10px;font-size:12px;color:#888">Tip: Di dialog cetak, pilih "Save as PDF" atau "Microsoft Print to PDF" untuk menyimpan sebagai file PDF.</p>
</div>
</body>
</html>
