<?php
// ============================================================
// SIMDESA — Halaman Login
// File: login.php
// ============================================================
require_once __DIR__ . '/includes/init.php';
startSession();

// Redirect jika sudah login
if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi.';
    } elseif (!login($username, $password)) {
        $error = 'Username atau password salah, atau akun tidak aktif.';
        // Catat percobaan login gagal
        error_log("Login gagal untuk username: $username dari IP: " . ($_SERVER['REMOTE_ADDR'] ?? ''));
    } else {
        header('Location: ' . APP_URL . '/dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — SIMDESA</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Source+Sans+3:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body class="login-page">

<div class="login-card">
    <div class="login-header">
        <div class="login-logo">🏛️</div>
        <h1>SIMDESA</h1>
        <p>Sistem Informasi Manajemen Data Desa</p>
    </div>

    <div class="login-body">
        <?php if ($error): ?>
        <div class="alert alert-error" style="margin-bottom:20px;">
            ❌ <?= clean($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control"
                    placeholder="Masukkan username" autocomplete="username" required
                    value="<?= clean($_POST['username'] ?? '') ?>">
            </div>

            <div class="form-group" style="margin-top:14px;">
                <label for="password">Password</label>
                <div style="position:relative;">
                    <input type="password" id="password" name="password" class="form-control"
                        placeholder="Masukkan password" autocomplete="current-password" required
                        style="padding-right:44px;">
                    <button type="button" onclick="togglePassword()" 
                        style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9aabb8;font-size:1rem;"
                        title="Tampilkan/sembunyikan password">👁</button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100" style="margin-top:22px;justify-content:center;padding:12px;">
                🔐 Masuk ke Sistem
            </button>
        </form>

        <div style="margin-top:22px;padding-top:18px;border-top:1px solid #eee;font-size:0.82rem;color:#9aabb8;text-align:center;">
            <strong>Akun Demo:</strong><br>
            superadmin / password &nbsp;|&nbsp; admin_simpangbaru / password
            <br><br>
            <em>SIMDESA v<?= APP_VERSION ?> — <?= APP_SUBTITLE ?></em>
        </div>
    </div>
</div>

<script>
function togglePassword() {
    const input = document.getElementById('password');
    input.type = input.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
