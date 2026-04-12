<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_auth();
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/includes/nc_build.php';
require_once __DIR__ . '/includes/finanzas.php';

$navSection = 'dashboard';
$pageTitle = 'Inicio — NetControl';
$flash = flash_get();

$db = db();
$counts = ['total' => 0, 'activos' => 0, 'suspendidos' => 0];
$r = $db->query('SELECT COUNT(*) AS c FROM clientes');
if ($r) {
    $counts['total'] = (int) ($r->fetch_assoc()['c'] ?? 0);
}
$r = $db->query("SELECT COUNT(*) AS c FROM clientes WHERE estado = 'activo'");
if ($r) {
    $counts['activos'] = (int) ($r->fetch_assoc()['c'] ?? 0);
}
$r = $db->query("SELECT COUNT(*) AS c FROM clientes WHERE estado = 'suspendido'");
if ($r) {
    $counts['suspendidos'] = (int) ($r->fetch_assoc()['c'] ?? 0);
}

$r = $db->query(
    "SELECT COUNT(*) AS c FROM clientes WHERE estado = 'activo' AND promesa_hasta IS NOT NULL AND promesa_hasta >= CURDATE()"
);
$promesasActivas = $r ? (int) ($r->fetch_assoc()['c'] ?? 0) : 0;

$r = $db->query(
    "SELECT COUNT(*) AS c FROM clientes WHERE estado = 'activo' AND fecha_pago IS NOT NULL AND fecha_pago < CURDATE()"
);
$vencidosCount = $r ? (int) ($r->fetch_assoc()['c'] ?? 0) : 0;

$r = $db->query(
    "SELECT COUNT(*) AS c FROM clientes WHERE estado = 'activo' AND fecha_pago IS NOT NULL
     AND fecha_pago >= CURDATE() AND fecha_pago <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
);
$proximos7Count = $r ? (int) ($r->fetch_assoc()['c'] ?? 0) : 0;

$altasPorDia = [];
$res = $db->query(
    "SELECT DATE(creado_en) AS d, COUNT(*) AS c FROM clientes
     WHERE creado_en >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(creado_en) ORDER BY d ASC"
);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $altasPorDia[(string) $row['d']] = (int) $row['c'];
    }
}

$labelsAltas = [];
$dataAltas = [];
$today = new DateTimeImmutable('today');
for ($i = 6; $i >= 0; $i--) {
    $day = $today->modify('-' . $i . ' days');
    $d = $day->format('Y-m-d');
    $labelsAltas[] = $day->format('d/m');
    $dataAltas[] = $altasPorDia[$d] ?? 0;
}

$vencidos = [];
$res = $db->query(
    "SELECT id, nombre, fecha_pago, mikrotik_id FROM clientes
     WHERE estado = 'activo' AND fecha_pago IS NOT NULL AND fecha_pago < CURDATE()
     ORDER BY fecha_pago ASC LIMIT 15"
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
     ORDER BY fecha_pago ASC LIMIT 15"
);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $proximos[] = $row;
    }
}

$ultimosClientes = [];
$res = $db->query(
    'SELECT id, nombre, estado, creado_en FROM clientes ORDER BY creado_en DESC LIMIT 10'
);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $ultimosClientes[] = $row;
    }
}

$routers = list_mikrotiks_admin();
$routerStatus = [];
foreach ($routers as $mr) {
    $routerStatus[(int) $mr['id']] = mikrotik_probe((int) $mr['id']);
}
$routersTotal = count($routers);
$routersOk = 0;
foreach ($routerStatus as $st) {
    if (! empty($st['ok'])) {
        $routersOk++;
    }
}
$routersDown = max(0, $routersTotal - $routersOk);

$cobradoHoy = nc_pagos_total_hoy();

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
        <p class="text-secondary small mb-0">Resumen operativo: clientes, cobros en ventana, vencidos y routers. <span class="opacity-75">· UI rev <?= (int) NC_PANEL_UI_REV ?></span></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-primary btn-sm" href="crear.php"><i class="bi bi-person-plus me-1"></i>Nuevo cliente</a>
        <a class="btn btn-outline-secondary btn-sm" href="index.php">Lista completa</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-3 col-6">
        <div class="small-box text-bg-primary">
            <div class="inner">
                <h3><?= (int) $counts['activos'] ?></h3>
                <p>Clientes activos</p>
            </div>
            <svg class="small-box-icon" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M6.25 6.375a4.125 4.125 0 118.25 0 4.125 4.125 0 01-8.25 0zM3.25 19.125a7.125 7.125 0 0114.25 0v.003l-.001.119a.75.75 0 01-.363.63 13.067 13.067 0 01-6.761 1.873c-2.472 0-4.786-.684-6.76-1.873a.75.75 0 01-.364-.63l-.001-.122zM19.75 7.5a.75.75 0 00-1.5 0v2.25H16a.75.75 0 000 1.5h2.25v2.25a.75.75 0 001.5 0v-2.25H22a.75.75 0 000-1.5h-2.25V7.5z"/></svg>
            <a href="index.php" class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover">Cartera: <?= (int) $counts['total'] ?> · Ver lista <i class="bi bi-link-45deg"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box text-bg-success">
            <div class="inner">
                <h3><?= $proximos7Count ?></h3>
                <p>Cobros en 7 días</p>
            </div>
            <svg class="small-box-icon" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/></svg>
            <a href="finanzas_transacciones.php" class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover">Promesas: <?= $promesasActivas ?> · Hoy <?= esc(number_format($cobradoHoy, 2, '.', ',')) ?> <i class="bi bi-link-45deg"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box text-bg-warning">
            <div class="inner">
                <h3><?= $vencidosCount ?></h3>
                <p>Pagos vencidos</p>
            </div>
            <svg class="small-box-icon" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
            <a href="finanzas.php" class="small-box-footer link-dark link-underline-opacity-0 link-underline-opacity-50-hover">Activos con fecha &lt; hoy <i class="bi bi-link-45deg"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box text-bg-danger">
            <div class="inner">
                <h3><?= $routersOk ?><span class="fs-4">/</span><?= $routersTotal ?></h3>
                <p>Routers API OK</p>
            </div>
            <svg class="small-box-icon" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
            <a href="mikrotik_diag.php" class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover"><?= $routersDown ?> sin API <i class="bi bi-link-45deg"></i></a>
        </div>
    </div>
</div>

<?php if ($vencidosCount > 0): ?>
    <div class="alert alert-danger nc-flash mb-4 py-2">
        Hay <strong><?= $vencidosCount ?></strong> cliente(s) activo(s) con fecha de pago vencida.
    </div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="nc-dash-widget h-100">
            <div class="nc-dash-widget-h d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>Altas — últimos 7 días</span>
                <span class="text-secondary fw-normal" style="font-size:0.65rem;">Nuevos registros en el panel</span>
            </div>
            <div class="row g-0">
                <div class="col-lg-8 nc-dash-chart-split">
                    <div class="nc-chart-box">
                        <canvas id="ncChartAltas" aria-label="Gráfico de altas"></canvas>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="nc-chart-box d-flex flex-column justify-content-center">
                        <canvas id="ncChartMix" style="max-height:200px" aria-label="Activos vs suspendidos"></canvas>
                        <p class="text-center small text-secondary mb-0 mt-2">Distribución de estados</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="nc-dash-widget h-100">
            <div class="nc-dash-widget-h">Resumen del sistema</div>
            <div class="nc-sys-list py-1">
                <div class="nc-sys-row"><span>Routers API OK</span><span class="nc-sys-badge nc-sys-badge--ok"><?= $routersOk ?></span></div>
                <div class="nc-sys-row"><span>Routers sin API</span><span class="nc-sys-badge nc-sys-badge--bad"><?= $routersDown ?></span></div>
                <div class="nc-sys-row"><span>Clientes activos</span><span class="nc-sys-badge nc-sys-badge--ok"><?= (int) $counts['activos'] ?></span></div>
                <div class="nc-sys-row"><span>Clientes suspendidos</span><span class="nc-sys-badge nc-sys-badge--muted"><?= (int) $counts['suspendidos'] ?></span></div>
                <div class="nc-sys-row"><span>Total en cartera</span><span class="nc-sys-badge"><?= (int) $counts['total'] ?></span></div>
                <div class="nc-sys-row"><span>Promesas vigentes</span><span class="nc-sys-badge nc-sys-badge--muted"><?= $promesasActivas ?></span></div>
                <div class="nc-sys-row"><span>Pagos próximos (7 d.)</span><span class="nc-sys-badge"><?= $proximos7Count ?></span></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="nc-dash-widget h-100">
            <div class="nc-dash-widget-h">Últimos clientes registrados</div>
            <div class="table-responsive">
                <table class="table table-borderless nc-mini-table nc-table mb-0">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Estado</th>
                            <th class="text-end">Alta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($ultimosClientes === []): ?>
                            <tr><td colspan="3" class="text-secondary px-3 py-3">Sin registros.</td></tr>
                        <?php else: ?>
                            <?php foreach ($ultimosClientes as $u): ?>
                                <tr>
                                    <td class="ps-3">
                                        <a href="cliente_edit.php?id=<?= (int) $u['id'] ?>" class="text-reset text-decoration-none"><?= esc((string) $u['nombre']) ?></a>
                                    </td>
                                    <td>
                                        <?php if (($u['estado'] ?? '') === 'activo'): ?>
                                            <span class="nc-pill-activo">Activo</span>
                                        <?php else: ?>
                                            <span class="nc-pill-susp">Suspendido</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-3 text-secondary small"><?= esc((string) $u['creado_en']) ?></td>
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
            <div class="nc-dash-widget-h">Pagos vencidos y próximos 7 días</div>
            <div class="row g-0">
                <div class="col-md-6 nc-dash-pay-split">
                    <div class="px-3 py-2 small fw-semibold text-secondary text-uppercase" style="font-size:0.65rem;">Vencidos</div>
                    <ul class="list-group list-group-flush">
                        <?php if ($vencidos === []): ?>
                            <li class="list-group-item bg-transparent text-secondary border-0 py-2 small">Ninguno.</li>
                        <?php else: ?>
                            <?php foreach (array_slice($vencidos, 0, 6) as $v): ?>
                                <li class="list-group-item bg-transparent border-secondary-subtle py-2 small d-flex justify-content-between gap-2">
                                    <span class="text-truncate"><?= esc((string) $v['nombre']) ?></span>
                                    <a class="text-nowrap" href="cliente_edit.php?id=<?= (int) $v['id'] ?>">Editar</a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="col-md-6">
                    <div class="px-3 py-2 small fw-semibold text-secondary text-uppercase" style="font-size:0.65rem;">Próximos</div>
                    <ul class="list-group list-group-flush">
                        <?php if ($proximos === []): ?>
                            <li class="list-group-item bg-transparent text-secondary border-0 py-2 small">Ninguno en ventana.</li>
                        <?php else: ?>
                            <?php foreach (array_slice($proximos, 0, 6) as $p): ?>
                                <li class="list-group-item bg-transparent border-secondary-subtle py-2 small d-flex justify-content-between gap-2">
                                    <span class="text-truncate"><?= esc((string) $p['nombre']) ?></span>
                                    <span class="text-secondary text-nowrap"><?= esc(formatear_fecha((string) $p['fecha_pago'])) ?></span>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="nc-dash-widget">
    <div class="nc-dash-widget-h d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>Routers — última prueba API</span>
        <a class="small text-decoration-none link-secondary" href="mikrotik_diag.php">Diagnóstico detallado</a>
    </div>
    <ul class="list-group list-group-flush">
        <?php foreach ($routers as $mr): ?>
            <?php
            $rid = (int) $mr['id'];
            $st = $routerStatus[$rid] ?? ['ok' => false, 'message' => 'Sin datos'];
            ?>
            <li class="list-group-item bg-transparent d-flex justify-content-between align-items-start flex-wrap gap-2 border-secondary-subtle">
                <div>
                    <strong><?= esc((string) $mr['nombre']) ?></strong>
                    <div class="small font-monospace text-secondary"><?= esc((string) $mr['ip']) ?></div>
                    <?php if (! empty($st['identity'])): ?>
                        <div class="small text-secondary"><?= esc((string) $st['identity']) ?><?= ! empty($st['resource']) ? ' · ' . esc((string) $st['resource']) : '' ?></div>
                    <?php endif; ?>
                </div>
                <?php if (! empty($st['ok'])): ?>
                    <span class="badge badge-nc-ok">API OK</span>
                <?php else: ?>
                    <span class="badge text-bg-danger" title="<?= esc((string) $st['message']) ?>">Sin API</span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
  const labels = <?= json_encode($labelsAltas, JSON_THROW_ON_ERROR) ?>;
  const dataAltas = <?= json_encode($dataAltas, JSON_THROW_ON_ERROR) ?>;
  const activos = <?= (int) $counts['activos'] ?>;
  const suspendidos = <?= (int) $counts['suspendidos'] ?>;

  const lineEl = document.getElementById('ncChartAltas');
  if (lineEl) {
    new Chart(lineEl, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Altas',
            data: dataAltas,
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13, 110, 253, 0.12)',
            fill: true,
            tension: 0.35,
            pointRadius: 3,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
        },
        scales: {
          x: {
            grid: { color: 'rgba(0, 0, 0, 0.06)' },
            ticks: { color: '#6c757d', maxRotation: 0 },
          },
          y: {
            beginAtZero: true,
            grid: { color: 'rgba(0, 0, 0, 0.06)' },
            ticks: { color: '#6c757d', precision: 0 },
          },
        },
      },
    });
  }

  const mixEl = document.getElementById('ncChartMix');
  if (mixEl) {
    new Chart(mixEl, {
      type: 'doughnut',
      data: {
        labels: ['Activos', 'Suspendidos'],
        datasets: [{
          data: [activos, suspendidos],
          backgroundColor: ['#198754', '#ffc107'],
          borderWidth: 0,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        cutout: '68%',
        plugins: {
          legend: {
            position: 'bottom',
            labels: { color: '#6c757d', boxWidth: 12 },
          },
        },
      },
    });
  }
})();
</script>

<?php require __DIR__ . '/partials/footer.php';
