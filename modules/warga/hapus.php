<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requirePeran('superadmin','admin_desa');

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT nama_lengkap FROM warga WHERE id=?");
$stmt->execute([$id]);
$warga = $stmt->fetch();

if ($warga) {
    $db->prepare("DELETE FROM warga WHERE id=?")->execute([$id]);
    logAktivitas('Hapus warga', 'warga', $id, $warga['nama_lengkap']);
    setFlash('success', "Warga {$warga['nama_lengkap']} berhasil dihapus.");
} else {
    setFlash('error', 'Data warga tidak ditemukan.');
}
header('Location: index.php'); exit;
