<?php
/**
 * CETAK DAFTAR WARGA — FORMAT PRINT/PDF
 * FNA & Kawan-kawan © 2025
 */
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db     = getDB();
$idDesa = getIdDesaAktif();
$fDesa  = (int) ($_GET['desa'] ?? $idDesa ?? 0);
$fJK    = $_GET['jk']     ?? '';
$fStatus= $_GET['status'] ?? 'Hidup';
$cari   = trim($_GET['q'] ?? '');

$clauses = []; $params = [];
if (!isSuperadmin() && $idDesa) { $clauses[] = "w.id_desa=?"; $params[] = $idDesa; }
elseif ($fDesa > 0)             { $clauses[] = "w.id_desa=?"; $params[] = $fDesa; }
if ($fJK)    { $clauses[] = "w.jenis_kelamin=?"; $params[] = $fJK; }
if ($fStatus){ $clauses[] = "w.status_hidup=?";  $params[] = $fStatus; }
if ($cari)   { $clauses[] = "(w.nama_lengkap LIKE ? OR w.nik LIKE ?)"; $params[] = "%$cari%"; $params[] = "%$cari%"; }

$whereStr = $clauses ? "WHERE ".implode(" AND ",$clauses) : "";

$stmt = $db->prepare("SELECT w.*, d.nama_desa, d.kecamatan FROM warga w JOIN desa d ON w.id_desa=d.id $whereStr ORDER BY d.nama_desa, w.nama_lengkap");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$judulFilter = $fDesa
    ? ($db->prepare("SELECT nama_desa FROM desa WHERE id=?")->execute([$fDesa]) ? $db->query("SELECT nama_desa FROM desa WHERE id=$fDesa")->fetchColumn() : 'Desa')
    : 'Semua Desa';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Daftar Warga — <?= e($judulFilter) ?></title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: Arial, sans-serif; font-size: 10pt; color: #111; }
  .page { width:210mm; padding:15mm 18mm; margin:0 auto; }
  .kop { display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #1A1830; padding-bottom:8px; margin-bottom:12px; }
  .kop h1 { font-size:13pt; font-weight:bold; color:#1A1830; }
  .kop h2 { font-size:10pt; font-weight:normal; color:#444; margin-top:2px; }
  .kop-fna { text-align:right; font-size:8.5pt; color:#5B4FCF; font-style:italic; }

  h3.judul { text-align:center; font-size:12pt; text-transform:uppercase; margin-bottom:4px; }
  p.sub    { text-align:center; font-size:10pt; margin-bottom:12px; color:#444; }

  table { width:100%; border-collapse:collapse; font-size:8.5pt; }
  thead th { background:#1A1830; color:#fff; padding:5px 6px; text-align:left; font-size:8pt; }
  tbody td { padding:4px 6px; border-bottom:0.5px solid #ddd; vertical-align:top; }
  tbody tr:nth-child(even) td { background:#f5f4ff; }

  .footer { margin-top:16px; font-size:8.5pt; color:#666; display:flex; justify-content:space-between; }
  .watermark { text-align:center; font-size:8pt; color:#aaa; margin-top:12px; font-style:italic; }

  @media print { .no-print { display:none !important; } }
  @page { size:A4 landscape; margin:0; }
</style>
</head>
<body>
<div class="page">
  <div class="kop">
    <div>
      <h1>SiDesa — Sistem Informasi Warga Desa</h1>
      <h2>Daftar Warga: <?= e($judulFilter) ?></h2>
    </div>
    <div class="kop-fna">FNA &amp; Kawan-kawan<br><span style="font-size:7.5pt;color:#aaa">Open Source · Gratis</span></div>
  </div>
  <h3 class="judul">Daftar Data Warga</h3>
  <p class="sub"><?= e($judulFilter) ?> | Dicetak: <?= date('d F Y, H:i') ?> WIB | Total: <?= number_format(count($rows)) ?> jiwa</p>

  <table>
    <thead>
      <tr>
        <th style="width:28px">#</th>
        <th style="min-width:120px">Nama Lengkap</th>
        <th style="width:130px">NIK</th>
        <th style="width:80px">TTL</th>
        <th style="width:30px">JK</th>
        <th style="width:60px">Agama</th>
        <th style="width:70px">Pendidikan</th>
        <th style="width:70px">Pekerjaan</th>
        <th>Alamat</th>
        <th style="width:70px">Desa</th>
        <th style="width:40px">Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
      <tr><td colspan="11" style="text-align:center;padding:20px;color:#888">Tidak ada data.</td></tr>
      <?php else: ?>
      <?php foreach ($rows as $i => $w): ?>
      <tr>
        <td><?= $i+1 ?></td>
        <td><strong><?= e($w['nama_lengkap']) ?></strong></td>
        <td style="font-family:monospace;font-size:7.5pt"><?= e($w['nik']) ?></td>
        <td style="font-size:7.5pt"><?= e($w['tempat_lahir']) ?>, <?= date('d/m/Y', strtotime($w['tanggal_lahir'])) ?></td>
        <td><?= $w['jenis_kelamin'] ?></td>
        <td style="font-size:7.5pt"><?= e($w['agama']) ?></td>
        <td style="font-size:7.5pt"><?= e($w['pendidikan']) ?></td>
        <td style="font-size:7.5pt"><?= e($w['pekerjaan'] ?: '-') ?></td>
        <td style="font-size:7.5pt"><?= e($w['alamat']) ?><?= $w['rt'] ? " RT {$w['rt']}/RW {$w['rw']}" : '' ?></td>
        <td style="font-size:7.5pt"><?= e($w['nama_desa']) ?></td>
        <td style="font-size:7.5pt"><?= e($w['status_hidup']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="footer">
    <span>Total: <?= number_format(count($rows)) ?> warga terdaftar</span>
    <span>Dicetak oleh: <?= e($_SESSION['nama_lengkap'] ?? '-') ?></span>
  </div>
  <div class="watermark">
    Dibuat oleh FNA &amp; Kawan-kawan (Fauzan Nur Ahmadi &amp; Kawan-kawan) — Universitas Sumatera Utara — Open Source, bebas digunakan — <?= date('Y') ?>
  </div>
</div>

<div class="no-print" style="text-align:center;padding:16px;background:#f0f0f0;font-family:sans-serif">
  <button onclick="window.print()" style="padding:10px 28px;background:#5B4FCF;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;margin-right:8px">🖨️ Cetak / Simpan PDF</button>
  <button onclick="window.close()" style="padding:10px 20px;background:#eee;color:#333;border:none;border-radius:8px;font-size:14px;cursor:pointer">Tutup</button>
</div>
</body>
</html>
