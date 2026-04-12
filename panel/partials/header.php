<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? 'NetControl WISP';
$navSection = $navSection ?? '';
$ncHeadExtra = $ncHeadExtra ?? '';
$__ncCss = dirname(__DIR__) . '/assets/app.css';
$__ncCssV = is_readable($__ncCss) ? (string) filemtime($__ncCss) : '1';
$__ncBridge = dirname(__DIR__) . '/assets/nc-adminlte-bridge.css';
$__ncBridgeV = is_readable($__ncBridge) ? (string) filemtime($__ncBridge) : '1';
$__logged = auth_logged_in();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($pageTitle) ?></title>
<?php if ($__logged): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-rc7/dist/css/adminlte.min.css" crossorigin="anonymous">
<?php else: ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Comic+Neue:wght@400;700&family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<?php endif; ?>
    <link href="assets/app.css?v=<?= esc($__ncCssV) ?>" rel="stylesheet">
<?php if ($__logged): ?>
    <link href="assets/nc-adminlte-bridge.css?v=<?= esc($__ncBridgeV) ?>" rel="stylesheet">
<?php endif; ?>
    <?= $ncHeadExtra ?>
</head>
<body class="<?= $__logged ? 'layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary nc-adminlte' : 'nc-body' ?>">
<?php if ($__logged): ?>
<div class="app-wrapper">
    <nav class="app-header navbar navbar-expand bg-body border-bottom">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="Menú">
                        <i class="bi bi-list"></i>
                    </a>
                </li>
                <li class="nav-item d-none d-md-block"><a href="dashboard.php" class="nav-link">Inicio</a></li>
                <li class="nav-item d-none d-md-block"><a href="index.php" class="nav-link">Clientes</a></li>
            </ul>
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item d-none d-sm-block">
                    <span class="nav-link disabled py-1 small text-secondary" title="Búsqueda global (próximamente)"><i class="bi bi-search me-1"></i>Buscar…</span>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#" data-lte-toggle="fullscreen" aria-label="Pantalla completa">
                        <i data-lte-icon="maximize" class="bi bi-arrows-fullscreen"></i>
                        <i data-lte-icon="minimize" class="bi bi-fullscreen-exit" style="display: none"></i>
                    </a>
                </li>
                <li class="nav-item dropdown user-menu">
                    <a href="#" class="nav-link dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="user-avatar rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" style="width:2rem;height:2rem;font-size:0.85rem;"><?= esc(nc_initials(nc_admin_display_name())) ?></span>
                        <span class="d-none d-md-inline"><?= esc(nc_admin_display_name()) ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                        <li class="user-header text-bg-primary p-3 rounded-top-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center" style="width:2.5rem;height:2.5rem;font-size:1rem;"><?= esc(nc_initials(nc_admin_display_name())) ?></span>
                                <div>
                                    <div class="fw-semibold"><?= esc(nc_admin_display_name()) ?></div>
                                    <small class="opacity-75"><?= esc(ADMIN_USER) ?></small>
                                </div>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="dashboard.php"><i class="bi bi-grid-1x2 me-2"></i>Panel</a></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Salir</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>
    <?php require __DIR__ . '/sidebar.php'; ?>
    <main class="app-main">
        <div class="app-content-header">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6">
                        <h3 class="mb-0"><?= esc($pageTitle) ?></h3>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-end mb-0">
                            <li class="breadcrumb-item"><a href="dashboard.php">Inicio</a></li>
                            <li class="breadcrumb-item active" aria-current="page"><?= esc($pageTitle) ?></li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <?php require __DIR__ . '/quicknav.php'; ?>
        <div class="app-content">
            <div class="container-fluid px-3 px-lg-4 py-3">
<?php else: ?>
<nav class="navbar navbar-expand-lg nc-navbar border-bottom border-secondary-subtle">
    <div class="container-fluid px-lg-4">
        <a class="navbar-brand fw-bold nc-brand" href="login.php">NetControl</a>
    </div>
</nav>
<main class="container-fluid px-lg-4 py-4">
<?php endif; ?>
