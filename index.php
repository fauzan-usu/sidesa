<?php
// ============================================================
// SIMDESA — Root redirect
// File: index.php
// ============================================================
require_once __DIR__ . '/includes/init.php';
startSession();

if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/dashboard.php');
} else {
    header('Location: ' . APP_URL . '/login.php');
}
exit;
