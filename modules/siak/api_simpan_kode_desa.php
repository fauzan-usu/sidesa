<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requirePeran('superadmin', 'admin_desa');

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);
$idDesa = (int)($input['id_desa'] ?? 0);
$kode   = trim($input['kode'] ?? '');

if (!$idDesa || !$kode) {
    echo json_encode(['ok'=>false,'pesan'=>'Parameter tidak lengkap']); exit;
}

try {
    $db = getDB();
    $db->prepare("UPDATE siak_config SET kode_desa_kemendagri=? WHERE id_desa=?")->execute([$kode, $idDesa]);
    logAktivitas('Pemetaan kode Kemendagri', 'siak_config', $idDesa, $kode);
    echo json_encode(['ok'=>true,'pesan'=>'Berhasil disimpan']);
} catch (Exception $e) {
    echo json_encode(['ok'=>false,'pesan'=>$e->getMessage()]);
}
