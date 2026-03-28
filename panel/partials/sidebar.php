<?php
declare(strict_types=1);
/** @var string $navSection */
$navSection = $navSection ?? '';
$is = static function (string $s) use ($navSection): string {
    return $s === $navSection ? 'active' : '';
};
$ncName = nc_admin_display_name();
$ncIni = nc_initials($ncName);

$__finSub = $finanzasSub ?? '';
$__finOpen = ($navSection ?? '') === 'finanzas';
$__finLinks = [
    ['cobros', 'finanzas.php', 'bi-cash-stack', 'Cobros y promesas'],
    ['facturas', 'finanzas_place.php?p=facturas', 'bi-receipt', 'Facturas'],
    ['registrar-pago', 'finanzas_registrar_pago.php', 'bi-plus-circle', 'Registrar pago'],
    ['pagos-masivos', 'finanzas_place.php?p=pagos-masivos', 'bi-upload', 'Registrar pagos masivos'],
    ['transacciones', 'finanzas_transacciones.php', 'bi-arrow-left-right', 'Transacciones'],
    ['ingresos-egresos', 'finanzas_place.php?p=ingresos-egresos', 'bi-graph-up-arrow', 'Otros ingresos y egresos'],
    ['reportes-pago', 'finanzas_place.php?p=reportes-pago', 'bi-person-badge', 'Reportes de pago (portal cliente)'],
    ['facturacion-electronica', 'finanzas_place.php?p=facturacion-electronica', 'bi-file-earmark-pdf', 'Facturación electrónica'],
    ['estadisticas', 'finanzas_place.php?p=estadisticas', 'bi-pie-chart', 'Estadísticas'],
];
?>
<aside class="nc-sidebar nc-sidebar-pro d-none d-lg-flex flex-column sticky-top h-100 align-self-start">
    <div class="nc-sidebar-profile">
        <div class="nc-sidebar-profile-inner">
            <div class="nc-avatar"><?= esc($ncIni) ?></div>
            <div class="min-w-0">
                <div class="fw-semibold text-truncate small"><?= esc($ncName) ?></div>
                <div class="text-secondary" style="font-size: 0.75rem;">Administrador</div>
            </div>
        </div>
    </div>
    <div class="nc-sidebar-brand-compact px-3 pb-2">
        <a href="dashboard.php" class="text-decoration-none text-reset fw-bold nc-brand">NetControl</a>
    </div>
    <nav class="nc-sidebar-scroll nc-sidebar-nav flex-grow-1">
        <a class="nc-snav-link <?= $is('dashboard') ?>" href="dashboard.php"><i class="bi bi-grid-1x2-fill"></i>Inicio</a>
        <a class="nc-snav-link <?= $is('ops-diag') ?>" href="mikrotik_diag.php"><i class="bi bi-hdd-network"></i>Diagnóstico API</a>

        <div class="nc-nav-label mt-2">Clientes</div>
        <a class="nc-snav-link <?= $is('clientes') ?>" href="index.php"><i class="bi bi-people-fill"></i>Lista de clientes</a>
        <a class="nc-snav-link <?= $is('clientes-crear') ?>" href="crear.php"><i class="bi bi-person-plus"></i>Nuevo cliente</a>
        <a class="nc-snav-link <?= $is('clientes-mapa') ?>" href="mapa.php"><i class="bi bi-geo-alt"></i>Mapa</a>

        <div class="nc-nav-label mt-3">Red · MikroTik</div>
        <a class="nc-snav-link <?= $is('red-routers') ?>" href="routers.php"><i class="bi bi-reception-4"></i>Routers (API)</a>
        <a class="nc-snav-link <?= $is('red-redes') ?>" href="redes.php"><i class="bi bi-diagram-3"></i>Redes IPv4</a>

        <div class="nc-nav-label mt-3">Catálogo</div>
        <a class="nc-snav-link <?= $is('cat-planes') ?>" href="planes.php"><i class="bi bi-speedometer2"></i>Planes</a>

        <div class="nc-snav-group mt-3">
            <button class="nc-snav-toggle d-flex align-items-center w-100 text-start <?= $__finOpen ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#ncFinDesk" aria-expanded="<?= $__finOpen ? 'true' : 'false' ?>" aria-controls="ncFinDesk">
                <i class="bi bi-cash-coin"></i>
                <span class="flex-grow-1">Finanzas</span>
                <i class="bi bi-chevron-down nc-snav-chevron"></i>
            </button>
            <div class="collapse nc-snav-sub<?= $__finOpen ? ' show' : '' ?>" id="ncFinDesk">
                <?php foreach ($__finLinks as $row): ?>
                    <a class="nc-snav-sublink<?= $__finSub === $row[0] ? ' active' : '' ?>" href="<?= esc($row[1]) ?>"><i class="bi <?= esc($row[2]) ?>"></i><?= esc($row[3]) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="nc-nav-label mt-3 text-secondary small fw-normal">Tickets y tráfico SNMP: roadmap.</div>
    </nav>
</aside>

<div class="offcanvas offcanvas-start nc-offcanvas d-lg-none" tabindex="-1" id="ncSideMenu">
    <div class="offcanvas-header border-bottom border-secondary align-items-start">
        <div class="d-flex gap-2">
            <div class="nc-avatar" style="width:40px;height:40px;font-size:0.85rem;"><?= esc($ncIni) ?></div>
            <div>
                <div class="fw-semibold small"><?= esc($ncName) ?></div>
                <div class="text-secondary" style="font-size:0.72rem;">Administrador</div>
            </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0">
        <nav class="nc-sidebar-nav p-3">
            <a class="nc-snav-link" href="dashboard.php"><i class="bi bi-grid-1x2-fill"></i>Inicio</a>
            <a class="nc-snav-link" href="mikrotik_diag.php"><i class="bi bi-hdd-network"></i>Diagnóstico</a>
            <div class="nc-nav-label mt-2">Clientes</div>
            <a class="nc-snav-link" href="index.php"><i class="bi bi-people-fill"></i>Lista</a>
            <a class="nc-snav-link" href="crear.php"><i class="bi bi-person-plus"></i>Nuevo</a>
            <a class="nc-snav-link" href="mapa.php"><i class="bi bi-geo-alt"></i>Mapa</a>
            <div class="nc-nav-label mt-3">Red</div>
            <a class="nc-snav-link" href="routers.php"><i class="bi bi-reception-4"></i>Routers</a>
            <a class="nc-snav-link" href="redes.php"><i class="bi bi-diagram-3"></i>Redes IPv4</a>
            <div class="nc-nav-label mt-3">Catálogo</div>
            <a class="nc-snav-link" href="planes.php"><i class="bi bi-speedometer2"></i>Planes</a>
            <div class="nc-snav-group mt-3">
                <button class="nc-snav-toggle d-flex align-items-center w-100 text-start <?= $__finOpen ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#ncFinMob" aria-expanded="<?= $__finOpen ? 'true' : 'false' ?>" aria-controls="ncFinMob">
                    <i class="bi bi-cash-coin"></i>
                    <span class="flex-grow-1">Finanzas</span>
                    <i class="bi bi-chevron-down nc-snav-chevron"></i>
                </button>
                <div class="collapse nc-snav-sub<?= $__finOpen ? ' show' : '' ?>" id="ncFinMob">
                    <?php foreach ($__finLinks as $row): ?>
                        <a class="nc-snav-sublink<?= $__finSub === $row[0] ? ' active' : '' ?>" data-bs-dismiss="offcanvas" href="<?= esc($row[1]) ?>"><i class="bi <?= esc($row[2]) ?>"></i><?= esc($row[3]) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </nav>
    </div>
</div>
