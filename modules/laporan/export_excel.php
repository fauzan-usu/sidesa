<?php
/**
 * EXPORT DATA WARGA KE EXCEL (format CSV UTF-8 BOM)
 * Dibuka dengan Excel menghasilkan tampilan tabel yang rapi
 */
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db     = getDB();
$idDesa = getIdDesaAktif();
$fDesa  = (int) ($_GET['desa'] ?? $idDesa ?? 0);
$fJK    = $_GET['jk'] ?? '';
$fStatus= $_GET['status'] ?? 'Hidup';
$cari   = trim($_GET['q'] ?? '');

// Bangun query
$clauses = [];
$params  = [];
if (!isSuperadmin() && $idDesa) { $clauses[] = "w.id_desa=?"; $params[] = $idDesa; }
elseif ($fDesa > 0)             { $clauses[] = "w.id_desa=?"; $params[] = $fDesa; }
if ($fJK)    { $clauses[] = "w.jenis_kelamin=?"; $params[] = $fJK; }
if ($fStatus){ $clauses[] = "w.status_hidup=?";  $params[] = $fStatus; }
if ($cari)   { $clauses[] = "(w.nama_lengkap LIKE ? OR w.nik LIKE ?)"; $params[] = "%$cari%"; $params[] = "%$cari%"; }

$whereStr = $clauses ? "WHERE " . implode(" AND ", $clauses) : "";

$stmt = $db->prepare("SELECT w.*, d.nama_desa, d.kecamatan, d.kabupaten 
    FROM warga w JOIN desa d ON w.id_desa=d.id 
    $whereStr ORDER BY d.nama_desa, w.nama_lengkap");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Nama file
$namaFile = 'data_warga_' . date('Ymd_His') . '.csv';

// Header HTTP untuk download
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $namaFile . '"');
header('Cache-Control: no-cache');

$out = fopen('php://output', 'w');

// BOM UTF-8 agar Excel membaca karakter Indonesia dengan benar
fputs($out, "\xEF\xBB\xBF");

// Header kolom
fputcsv($out, [
    'No', 'Desa', 'Kecamatan', 'Kabupaten',
    'NIK', 'No. KK', 'Nama Lengkap',
    'Tempat Lahir', 'Tanggal Lahir', 'Umur',
    'Jenis Kelamin', 'Agama', 'Status Kawin',
    'Pendidikan', 'Pekerjaan',
    'Alamat', 'RT', 'RW',
    'No. Telepon', 'Status Hidup', 'DTKS',
    'Tgl Daftar'
]);

$no = 1;
foreach ($rows as $w) {
    fputcsv($out, [
        $no++,
        $w['nama_desa'],
        $w['kecamatan'],
        $w['kabupaten'],
        $w['nik'],
        $w['no_kk'],
        $w['nama_lengkap'],
        $w['tempat_lahir'],
        $w['tanggal_lahir'],
        hitungUmur($w['tanggal_lahir']),
        $w['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan',
        $w['agama'],
        $w['status_kawin'],
        $w['pendidikan'],
        $w['pekerjaan'],
        $w['alamat'],
        $w['rt'],
        $w['rw'],
        $w['no_telepon'],
        $w['status_hidup'],
        $w['status_dtks'] ? 'Ya' : 'Tidak',
        date('d/m/Y', strtotime($w['created_at']))
    ]);
}

fclose($out);
exit;
