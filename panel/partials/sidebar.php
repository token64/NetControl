<?php
declare(strict_types=1);
/** @var string $navSection */
$navSection = $navSection ?? '';
$is = static function (string $s) use ($navSection): string {
    return $s === $navSection ? 'active' : '';
};
$ncName = nc_admin_display_name();

$__finSub = $finanzasSub ?? '';
$__finOpen = ($navSection ?? '') === 'finanzas';
$__finLinks = [
    ['cobros', 'finanzas.php', 'bi-cash-stack', 'Cobros y promesas'],
    ['facturas', 'finanzas_place.php?p=facturas', 'bi-receipt', 'Facturas'],
    ['registrar-pago', 'finanzas_registrar_pago.php', 'bi-plus-circle', 'Registrar pago'],
    ['pagos-masivos', 'finanzas_place.php?p=pagos-masivos', 'bi-upload', 'Pagos masivos'],
    ['transacciones', 'finanzas_transacciones.php', 'bi-arrow-left-right', 'Transacciones'],
    ['ingresos-egresos', 'finanzas_place.php?p=ingresos-egresos', 'bi-graph-up-arrow', 'Otros ingresos / egresos'],
    ['reportes-pago', 'finanzas_place.php?p=reportes-pago', 'bi-person-badge', 'Reportes de pago'],
    ['facturacion-electronica', 'finanzas_place.php?p=facturacion-electronica', 'bi-file-earmark-pdf', 'Facturación electrónica'],
    ['estadisticas', 'finanzas_place.php?p=estadisticas', 'bi-pie-chart', 'Estadísticas'],
];
?>
<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
    <div class="sidebar-brand">
        <a href="dashboard.php" class="brand-link">
            <span class="brand-text fw-light">NetControl</span>
        </a>
    </div>
    <div class="sidebar-wrapper">
        <nav class="mt-2">
            <ul
                class="nav sidebar-menu flex-column"
                data-lte-toggle="treeview"
                role="navigation"
                aria-label="Menú principal"
                data-accordion="false"
                id="navigation"
            >
                <li class="nav-item <?= $is('dashboard') ? 'menu-open' : '' ?>">
                    <a href="dashboard.php" class="nav-link <?= $is('dashboard') ?>">
                        <i class="nav-icon bi bi-speedometer2"></i>
                        <p>Panel</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="mikrotik_diag.php" class="nav-link <?= $is('ops-diag') ?>">
                        <i class="nav-icon bi bi-hdd-network"></i>
                        <p>Diagnóstico API</p>
                    </a>
                </li>

                <li class="nav-header text-uppercase small opacity-75">Clientes</li>
                <li class="nav-item">
                    <a href="index.php" class="nav-link <?= $is('clientes') ?>">
                        <i class="nav-icon bi bi-people-fill"></i>
                        <p>Lista de clientes</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="crear.php" class="nav-link <?= $is('clientes-crear') ?>">
                        <i class="nav-icon bi bi-person-plus"></i>
                        <p>Nuevo cliente</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="mapa.php" class="nav-link <?= $is('clientes-mapa') ?>">
                        <i class="nav-icon bi bi-geo-alt"></i>
                        <p>Mapa</p>
                    </a>
                </li>

                <li class="nav-header text-uppercase small opacity-75">Red · MikroTik</li>
                <li class="nav-item">
                    <a href="routers.php" class="nav-link <?= $is('red-routers') ?>">
                        <i class="nav-icon bi bi-reception-4"></i>
                        <p>Routers (API)</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="redes.php" class="nav-link <?= $is('red-redes') ?>">
                        <i class="nav-icon bi bi-diagram-3"></i>
                        <p>Redes IPv4</p>
                    </a>
                </li>

                <li class="nav-header text-uppercase small opacity-75">Catálogo</li>
                <li class="nav-item">
                    <a href="planes.php" class="nav-link <?= $is('cat-planes') ?>">
                        <i class="nav-icon bi bi-speedometer"></i>
                        <p>Planes</p>
                    </a>
                </li>

                <li class="nav-item <?= $__finOpen ? 'menu-open' : '' ?>">
                    <a href="#" class="nav-link <?= $__finOpen ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-cash-coin"></i>
                        <p>
                            Finanzas
                            <i class="nav-arrow bi bi-chevron-right"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php foreach ($__finLinks as $row): ?>
                            <li class="nav-item">
                                <a href="<?= esc($row[1]) ?>" class="nav-link <?= $__finSub === $row[0] ? 'active' : '' ?>">
                                    <i class="nav-icon bi <?= esc($row[2]) ?>"></i>
                                    <p><?= esc($row[3]) ?></p>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>

                <li class="nav-item mt-2">
                    <span class="nav-link text-secondary small py-1">SNMP / tickets: roadmap</span>
                </li>
            </ul>
        </nav>
    </div>
</aside>
