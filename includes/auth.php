<?php
/**
 * AUTENTIKASI & MANAJEMEN SESI
 * File: includes/auth.php
 */

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Cek apakah pengguna sudah login; jika tidak redirect ke halaman login */
function requireLogin(): void {
    if (empty($_SESSION['id_pengguna'])) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

/** Cek peran minimum yang dibutuhkan */
function requirePeran(string ...$peranDiizinkan): void {
    requireLogin();
    if (!in_array($_SESSION['peran'] ?? '', $peranDiizinkan, true)) {
        http_response_code(403);
        die('<p style="font-family:sans-serif;padding:2rem">Akses ditolak. Peran Anda tidak memiliki izin untuk halaman ini.</p>');
    }
}

/** Apakah pengguna adalah superadmin? */
function isSuperadmin(): bool {
    return ($_SESSION['peran'] ?? '') === 'superadmin';
}

/** ID desa yang sedang aktif (null jika superadmin tanpa filter) */
function getIdDesaAktif(): ?int {
    if (isSuperadmin()) {
        return isset($_SESSION['filter_desa']) ? (int) $_SESSION['filter_desa'] : null;
    }
    return (int) ($_SESSION['id_desa'] ?? 0) ?: null;
}

/** Proses login */
function prosesLogin(string $username, string $password): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM pengguna WHERE username = ? AND aktif = 1 LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }

    // Update last_login
    $db->prepare("UPDATE pengguna SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

    $_SESSION['id_pengguna']  = $user['id'];
    $_SESSION['username']     = $user['username'];
    $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
    $_SESSION['peran']        = $user['peran'];
    $_SESSION['id_desa']      = $user['id_desa'];

    return true;
}

/** Logout */
function logout(): void {
    session_destroy();
    header('Location: ' . APP_URL . '/login.php');
    exit;
}
