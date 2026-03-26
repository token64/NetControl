<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? 'NetControl WISP';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/app.css" rel="stylesheet">
</head>
<body class="nc-body">
<nav class="navbar navbar-expand-lg nc-navbar border-bottom border-secondary-subtle">
    <div class="container-fluid px-lg-4">
        <a class="navbar-brand fw-bold nc-brand" href="index.php">NetControl</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMain">
            <?php if (auth_logged_in()): ?>
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="index.php">Clientes</a></li>
                    <li class="nav-item"><a class="nav-link" href="crear.php">Nuevo cliente</a></li>
                    <li class="nav-item"><a class="nav-link" href="mapa.php">Mapa</a></li>
                </ul>
                <span class="navbar-text me-3 small text-secondary"><?= esc(ADMIN_USER) ?></span>
                <a class="btn btn-outline-light btn-sm" href="logout.php">Salir</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<main class="container-fluid px-lg-4 py-4">
