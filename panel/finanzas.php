<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_auth();
require_once __DIR__ . '/funciones.php';

$navSection = 'finanzas';
$finanzasSub = 'cobros';
$pageTitle = 'Finanzas — NetControl';
$flash = flash_get();

$db = db();

$r = $db->query("SELECT COUNT(*) AS c FROM clientes WHERE estado = 'activo' AND fecha_pago IS NOT NULL AND fecha_pago < CURDATE()");
$vencidosCount = $r ? (int) ($r->fetch_assoc()['c'] ?? 0) : 0;

$r = $db->query(
    "SELECT COUNT(*) AS c FROM clientes WHERE estado = 'activo' AND fecha_pago IS NOT NULL
     AND fecha_pago >= CURDATE() AND fecha_pago <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
);
$proximos7Count = $r ? (int) ($r->fetch_assoc()['c'] ?? 0) : 0;

$r = $db->query(
    "SELECT COUNT(*) AS c FROM clientes WHERE estado = 'activo' AND promesa_hasta IS NOT NULL AND promesa_hasta >= CURDATE()"
);
$promesasActivas = $r ? (int) ($r->fetch_assoc()['c'] ?? 0) : 0;

$r = $db->query('SELECT COUNT(*) AS c FROM clientes WHERE estado = "activo"');
$activosCount = $r ? (int) ($r->fetch_assoc()['c'] ?? 0) : 0;

$vencidos = [];
$res = $db->query(
    "SELECT id, nombre, fecha_pago, promesa_hasta, mikrotik_id FROM clientes
     WHERE estado = 'activo' AND fecha_pago IS NOT NULL AND fecha_pago < CURDATE()
     ORDER BY fecha_pago ASC LIMIT 40"
);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $vencidos[] = $row;
    }
}

$proximos = [];
$res = $db->query(
    "SELECT id, nombre, fecha_pago FROM clientes
     WHERE estado = 'activo' AND fecha_pago IS NOT NULL
       AND fecha_pago >= CURDATE() AND fecha_pago <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
     ORDER BY fecha_pago ASC LIMIT 40"
);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $proximos[] = $row;
    }
}

$promesas = [];
$res = $db->query(
    "SELECT id, nombre, promesa_hasta, fecha_pago FROM clientes
     WHERE estado = 'activo' AND promesa_hasta IS NOT NULL AND promesa_hasta >= CURDATE()
     ORDER BY promesa_hasta ASC LIMIT 40"
);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $promesas[] = $row;
    }
}

require __DIR__ . '/partials/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : ($flash['type'] === 'warning' ? 'warning' : 'success') ?> nc-flash alert-dismissible fade show">
        <?= esc($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h4 mb-1">Finanzas</h1>
        <p class="text-secondary small mb-0">Cobros según fechas de pago y promesas en el sistema. Facturación y montos en moneda: pendiente (sin tablas de cobros aún).</p>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="dashboard.php">Volver al inicio</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="nc-kpi-card nc-kpi-card--violet h-100">
            <div class="nc-kpi-label">Pagos vencidos</div>
            <div class="nc-kpi-value"><?= $vencidosCount ?></div>
            <div class="nc-kpi-meta">Activos con fecha de pago &lt; hoy</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="nc-kpi-card nc-kpi-card--blue h-100">
            <div class="nc-kpi-label">Próximos 7 días</div>
            <div class="nc-kpi-value"><?= $proximos7Count ?></div>
            <div class="nc-kpi-meta">Ventana de cobro inmediata</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="nc-kpi-card nc-kpi-card--teal h-100">
            <div class="nc-kpi-label">Promesas vigentes</div>
            <div class="nc-kpi-value"><?= $promesasActivas ?></div>
            <div class="nc-kpi-meta">Acuerdos activos con fecha límite</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="nc-kpi-card nc-kpi-card--slate h-100">
            <div class="nc-kpi-label">Clientes activos</div>
            <div class="nc-kpi-value"><?= $activosCount ?></div>
            <div class="nc-kpi-meta">Base para ratios de mora</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="nc-dash-widget h-100">
            <div class="nc-dash-widget-h">Pagos vencidos (activos)</div>
            <div class="table-responsive">
                <table class="table table-borderless nc-mini-table nc-table mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3">Cliente</th>
                            <th>Venció</th>
                            <th class="text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($vencidos === []): ?>
                            <tr><td colspan="3" class="text-secondary px-3 py-3">Ninguno.</td></tr>
                        <?php else: ?>
                            <?php foreach ($vencidos as $v): ?>
                                <tr>
                                    <td class="ps-3"><?= esc((string) $v['nombre']) ?></td>
                                    <td class="text-warning"><?= esc(formatear_fecha((string) $v['fecha_pago'])) ?></td>
                                    <td class="text-end pe-3 text-nowrap">
                                        <a class="small" href="cliente_edit.php?id=<?= (int) $v['id'] ?>">Editar</a>
                                        · <a class="small" href="promesa.php?id=<?= (int) $v['id'] ?>">Promesa</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="nc-dash-widget h-100">
            <div class="nc-dash-widget-h">Pagos en los próximos 7 días</div>
            <div class="table-responsive">
                <table class="table table-borderless nc-mini-table nc-table mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3">Cliente</th>
                            <th>Fecha pago</th>
                            <th class="text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($proximos === []): ?>
                            <tr><td colspan="3" class="text-secondary px-3 py-3">Ninguno en esta ventana.</td></tr>
                        <?php else: ?>
                            <?php foreach ($proximos as $p): ?>
                                <tr>
                                    <td class="ps-3"><?= esc((string) $p['nombre']) ?></td>
                                    <td><?= esc(formatear_fecha((string) $p['fecha_pago'])) ?></td>
                                    <td class="text-end pe-3">
                                        <a class="small" href="cliente_edit.php?id=<?= (int) $p['id'] ?>">Editar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="nc-dash-widget mt-4">
    <div class="nc-dash-widget-h">Promesas de pago vigentes</div>
    <div class="table-responsive">
        <table class="table table-borderless nc-mini-table nc-table mb-0">
            <thead>
                <tr>
                    <th class="ps-3">Cliente</th>
                    <th>Promesa hasta</th>
                    <th>Fecha pago (plan)</th>
                    <th class="text-end pe-3">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($promesas === []): ?>
                    <tr><td colspan="4" class="text-secondary px-3 py-3">Ninguna promesa vigente.</td></tr>
                <?php else: ?>
                    <?php foreach ($promesas as $p): ?>
                        <tr>
                            <td class="ps-3"><?= esc((string) $p['nombre']) ?></td>
                            <td class="text-info"><?= esc(formatear_fecha((string) $p['promesa_hasta'])) ?></td>
                            <td class="text-secondary"><?= esc(formatear_fecha($p['fecha_pago'] ? (string) $p['fecha_pago'] : null)) ?></td>
                            <td class="text-end pe-3">
                                <a class="small" href="promesa.php?id=<?= (int) $p['id'] ?>">Ver / editar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="alert alert-secondary border-secondary mt-4 mb-0 small">
    <strong>Roadmap:</strong> facturas, montos (RD$ / USD), historial de cobros por operador e integración contable requieren nuevas tablas y pantallas; hoy el panel usa solo <code>fecha_pago</code> y <code>promesa_hasta</code> en clientes.
</div>

<?php require __DIR__ . '/partials/footer.php';
