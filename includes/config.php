<?php
/**
 * KONFIGURASI KONEKSI DATABASE
 * File: includes/config.php
 * Sesuaikan DB_HOST, DB_USER, DB_PASS jika berbeda di XAMPP Anda
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sistem_desa');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME',    'SiDesa — Sistem Informasi Warga Desa');
define('APP_VERSION', '1.0.0');
define('APP_URL',     'http://localhost/sistem_desa');
define('UPLOAD_DIR',  __DIR__ . '/../uploads/');
define('UPLOAD_URL',  APP_URL . '/uploads/');

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Error reporting (set 0 untuk produksi)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// -------------------------------------------------------
//  FUNGSI KONEKSI PDO
// -------------------------------------------------------
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;padding:2rem;color:#c00">
                <h2>Koneksi Database Gagal</h2>
                <p>' . htmlspecialchars($e->getMessage()) . '</p>
                <p>Pastikan XAMPP berjalan dan database <strong>' . DB_NAME . '</strong> sudah diimpor.</p>
            </div>');
        }
    }
    return $pdo;
}

// -------------------------------------------------------
//  FUNGSI HELPER
// -------------------------------------------------------

/** Sanitasi output HTML */
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/** Hitung umur dari tanggal lahir */
function hitungUmur(string $tanggalLahir): int {
    return (int) (new DateTime($tanggalLahir))->diff(new DateTime())->y;
}

/** Format tanggal ke Bahasa Indonesia */
function formatTanggal(string $tanggal, string $format = 'd F Y'): string {
    $bulan = [
        '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April',
        '05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus',
        '09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
    ];
    $d = new DateTime($tanggal);
    if ($format === 'd F Y') {
        return $d->format('d') . ' ' . $bulan[$d->format('m')] . ' ' . $d->format('Y');
    }
    return $d->format($format);
}

/** Kelompok usia untuk statistik */
function kelompokUsia(int $umur): string {
    if ($umur < 5)  return 'Balita (0-4)';
    if ($umur < 15) return 'Anak (5-14)';
    if ($umur < 26) return 'Remaja (15-25)';
    if ($umur < 46) return 'Dewasa (26-45)';
    if ($umur < 60) return 'Madya (46-59)';
    return 'Lansia (60+)';
}

/** Catat log aktivitas */
function logAktivitas(string $aksi, string $tabel = '', int $idRecord = 0, string $ket = ''): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO log_aktivitas (id_pengguna, aksi, tabel_terkait, id_record, keterangan, ip_address) VALUES (?,?,?,?,?,?)");
        $stmt->execute([
            $_SESSION['id_pengguna'] ?? null,
            $aksi, $tabel, $idRecord ?: null, $ket,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Exception $e) { /* log gagal tidak boleh hentikan proses */ }
}

/** Flash message */
function setFlash(string $tipe, string $pesan): void {
    $_SESSION['flash'] = ['tipe' => $tipe, 'pesan' => $pesan];
}

function getFlash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}
