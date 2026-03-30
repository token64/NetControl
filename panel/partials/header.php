<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? 'NetControl WISP';
$navSection = $navSection ?? '';
$ncHeadExtra = $ncHeadExtra ?? '';
$__ncCss = dirname(__DIR__) . '/assets/app.css';
$__ncCssV = is_readable($__ncCss) ? (string) filemtime($__ncCss) : '1';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Comic+Neue:wght@400;700&family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/app.css?v=<?= esc($__ncCssV) ?>" rel="stylesheet">
    <?= $ncHeadExtra ?>
</head>
<body class="nc-body">
<?php if (auth_logged_in()): ?>
<div class="nc-app d-flex min-vh-100">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <div class="nc-main flex-grow-1 d-flex flex-column min-w-0">
        <header class="nc-topbar nc-topbar-pro border-bottom border-secondary-subtle d-flex align-items-center flex-wrap gap-2 justify-content-between px-3 px-lg-4">
            <div class="d-flex align-items-center gap-3 min-w-0">
                <button class="btn btn-outline-secondary border-secondary d-lg-none btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#ncSideMenu" aria-controls="ncSideMenu"><i class="bi bi-list"></i></button>
                <div class="nc-welcome text-truncate">Bienvenido, <strong><?= esc(nc_admin_display_name()) ?></strong><br><span class="d-none d-sm-inline">Panel NetControl · WISP</span></div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-grow-1 flex-lg-grow-0 justify-content-lg-end">
                <div class="nc-search-fake d-none d-md-block flex-grow-1 flex-lg-grow-0" title="Próximamente: búsqueda global"><i class="bi bi-search me-2 opacity-50"></i>Buscar cliente…</div>
                <span class="badge rounded-pill text-bg-secondary d-none d-sm-inline">v2</span>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-person-circle me-1"></i><?= esc(ADMIN_USER) ?></button>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark">
                        <li><a class="dropdown-item" href="dashboard.php"><i class="bi bi-grid-1x2 me-2"></i>Inicio</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Salir</a></li>
                    </ul>
                </div>
            </div>
        </header>
        <?php require __DIR__ . '/quicknav.php'; ?>
        <main class="nc-main-inner container-fluid px-3 px-lg-4 py-4 flex-grow-1">
<?php else: ?>
<nav class="navbar navbar-expand-lg nc-navbar border-bottom border-secondary-subtle">
    <div class="container-fluid px-lg-4">
        <a class="navbar-brand fw-bold nc-brand" href="login.php">NetControl</a>
    </div>
</nav>
<main class="container-fluid px-lg-4 py-4">
<?php endif; ?>
