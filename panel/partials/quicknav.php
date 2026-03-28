<?php
declare(strict_types=1);
/** @var string $navSection */
$qn = static function (string $s) use ($navSection): string {
    return $s === $navSection ? 'nc-qnav-link active' : 'nc-qnav-link';
};
?>
<nav class="nc-quicknav border-bottom border-secondary-subtle px-3 px-lg-4 py-2" aria-label="Módulos del panel">
    <div class="d-flex flex-wrap align-items-center gap-1 gap-md-2 small">
        <span class="text-secondary me-1 d-none d-sm-inline">Ir a:</span>
        <a class="<?= $qn('dashboard') ?>" href="dashboard.php">Inicio</a>
        <span class="nc-qnav-sep text-secondary d-none d-md-inline">·</span>
        <a class="<?= $qn('clientes') ?>" href="index.php">Clientes</a>
        <span class="nc-qnav-sep text-secondary d-none d-md-inline">·</span>
        <a class="<?= $qn('finanzas') ?>" href="finanzas.php">Finanzas</a>
        <span class="nc-qnav-sep text-secondary d-none d-md-inline">·</span>
        <a class="<?= $qn('ops-diag') ?>" href="mikrotik_diag.php">API</a>
        <span class="nc-qnav-sep text-secondary d-none d-md-inline">·</span>
        <a class="<?= $qn('clientes-crear') ?>" href="crear.php">Nuevo cliente</a>
        <span class="nc-qnav-sep text-secondary d-none d-md-inline">·</span>
        <a class="<?= $qn('clientes-mapa') ?>" href="mapa.php">Mapa</a>
        <span class="nc-qnav-sep text-secondary d-none d-md-inline">|</span>
        <a class="<?= $qn('red-routers') ?>" href="routers.php">Routers</a>
        <span class="nc-qnav-sep text-secondary d-none d-md-inline">·</span>
        <a class="<?= $qn('red-redes') ?>" href="redes.php">Redes IPv4</a>
        <span class="nc-qnav-sep text-secondary d-none d-md-inline">|</span>
        <a class="<?= $qn('cat-planes') ?>" href="planes.php">Planes</a>
    </div>
</nav>
