<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requirePeran('superadmin', 'admin_desa');

$db     = getDB();
$id     = (int)($_GET['id'] ?? 0);
$idDesa = getIdDesaAktif();

$s = $db->prepare("SELECT judul, foto_utama, id_desa FROM berita WHERE id=?");
$s->execute([$id]);
$berita = $s->fetch();

if ($berita) {
    // Cek akses
    if (!isSuperadmin() && $berita['id_desa'] != $idDesa) {
        http_response_code(403); die('Akses ditolak.');
    }
    // Hapus foto fisik
    if (!empty($berita['foto_utama'])) {
        $path = UPLOAD_DIR . 'berita/' . $berita['foto_utama'];
        if (file_exists($path)) unlink($path);
    }
    $db->prepare("DELETE FROM berita WHERE id=?")->execute([$id]);
    logAktivitas('Hapus berita', 'berita', $id, $berita['judul']);
    setFlash('success', "Berita \"{$berita['judul']}\" berhasil dihapus.");
} else {
    setFlash('error', 'Berita tidak ditemukan.');
}
header('Location: index.php'); exit;
