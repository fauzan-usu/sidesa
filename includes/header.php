<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Dashboard') ?> — SiDesa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
        </div>
        <div>
            <div class="brand-name">SiDesa</div>
            <div class="brand-sub">v1.0</div>
        </div>
    </div>

    <!-- Pilih Desa (hanya superadmin) -->
    <?php if (isSuperadmin()): ?>
    <div class="sidebar-desa-picker">
        <form method="POST" action="<?= APP_URL ?>/index.php">
            <input type="hidden" name="action" value="set_filter_desa">
            <select name="filter_desa" onchange="this.form.submit()" class="desa-select">
                <option value="">— Semua Desa —</option>
                <?php
                $desas = getDB()->query("SELECT id, nama_desa FROM desa WHERE aktif=1 ORDER BY nama_desa")->fetchAll();
                foreach ($desas as $d):
                    $sel = ($_SESSION['filter_desa'] ?? '') == $d['id'] ? 'selected' : '';
                ?>
                <option value="<?= $d['id'] ?>" <?= $sel ?>><?= e($d['nama_desa']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php else: ?>
    <div class="sidebar-desa-label">
        <?php
        $namaDesa = getDB()->prepare("SELECT nama_desa FROM desa WHERE id=?");
        $namaDesa->execute([$_SESSION['id_desa']]);
        $nd = $namaDesa->fetchColumn();
        ?>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?= e($nd ?: 'Desa Anda') ?>
    </div>
    <?php endif; ?>

    <nav class="sidebar-nav">
        <a href="<?= APP_URL ?>/index.php"           class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            Dashboard
        </a>
        <a href="<?= APP_URL ?>/modules/warga/index.php" class="nav-item <?= (strpos($_SERVER['PHP_SELF'], '/warga/') !== false ? 'active' : '') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
            Data Warga
        </a>
        <?php if (isSuperadmin()): ?>
        <a href="<?= APP_URL ?>/modules/desa/index.php" class="nav-item <?= (strpos($_SERVER['PHP_SELF'], '/desa/') !== false ? 'active' : '') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            Manajemen Desa
        </a>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/modules/laporan/index.php" class="nav-item <?= (strpos($_SERVER['PHP_SELF'], '/laporan/') !== false ? 'active' : '') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            Laporan & Statistik
        </a>

        <div class="nav-divider"></div>

        <a href="<?= APP_URL ?>/modules/siak/index.php" class="nav-item <?= (strpos($_SERVER['PHP_SELF'], '/siak/') !== false ? 'active' : '') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/><path d="M9 8h1"/><path d="M14 8h1"/><circle cx="9.5" cy="11" r="1.5"/><circle cx="14.5" cy="11" r="1.5"/></svg>
            Integrasi SIAK
        </a>

        <a href="<?= APP_URL ?>/modules/berita/index.php" class="nav-item <?= (strpos($_SERVER['PHP_SELF'], '/berita/') !== false ? 'active' : '') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            Berita & Portal
        </a>
        <?php
        $_pid = getIdDesaAktif();
        if (!$_pid && isSuperadmin()) {
            try { $_pid = getDB()->query("SELECT id FROM desa WHERE aktif=1 LIMIT 1")->fetchColumn() ?: 1; } catch(Exception $e) { $_pid = 1; }
        }
        ?>
        <a href="<?= APP_URL ?>/portal.php<?= $_pid ? '?id='.$_pid : '' ?>" target="_blank" class="nav-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg>
            Portal Publik
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-left:auto;opacity:.4"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        </a>

        <div class="nav-divider"></div>

        <a href="<?= APP_URL ?>/logout.php" class="nav-item nav-item-danger">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Keluar
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="user-badge">
            <div class="user-avatar"><?= strtoupper(substr($_SESSION['nama_lengkap'] ?? 'U', 0, 1)) ?></div>
            <div>
                <div class="user-name"><?= e($_SESSION['nama_lengkap'] ?? '') ?></div>
                <div class="user-role"><?= ucfirst(str_replace('_', ' ', $_SESSION['peran'] ?? '')) ?></div>
            </div>
        </div>
    </div>
</aside>

<!-- OVERLAY MOBILE -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- KONTEN UTAMA -->
<main class="main-content">
    <header class="top-bar">
        <button class="hamburger" onclick="toggleSidebar()" aria-label="Toggle menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <div class="page-title-wrap">
            <h1 class="page-title"><?= e($pageTitle ?? 'Dashboard') ?></h1>
            <?php if (!empty($pageBreadcrumb)): ?>
            <nav class="breadcrumb">
                <?php foreach ($pageBreadcrumb as $label => $url): ?>
                    <?php if ($url): ?>
                        <a href="<?= $url ?>"><?= e($label) ?></a> <span>/</span>
                    <?php else: ?>
                        <span><?= e($label) ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
            <?php endif; ?>
        </div>
    </header>

    <!-- Flash Message -->
    <?php $flash = getFlash(); if ($flash): ?>
    <div class="alert alert-<?= $flash['tipe'] ?>">
        <?= e($flash['pesan']) ?>
        <button onclick="this.parentElement.remove()" class="alert-close">×</button>
    </div>
    <?php endif; ?>

    <div class="content-wrap">
