<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_auth();
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/includes/nc_build.php';

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

$kpiPctActivos = $counts['total'] > 0
    ? (int) round(100 * $counts['activos'] / $counts['total'])
    : 0;
$kpiPctRouters = $routersTotal > 0
    ? (int) round(100 * $routersOk / $routersTotal)
    : 0;

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
        <h1 class="h4 mb-1">Panel operador</h1>
        <p class="text-secondary small mb-0">Resumen operativo: clientes, cobros en ventana, vencidos y routers. <span class="opacity-75">· UI rev <?= (int) NC_PANEL_UI_REV ?></span></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-nc-primary btn-sm" href="crear.php"><i class="bi bi-person-plus me-1"></i>Nuevo cliente</a>
        <a class="btn btn-outline-secondary btn-sm" href="index.php">Lista completa</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-xl-3">
        <div class="nc-kpi-card nc-kpi-card--teal h-100">
            <div class="nc-kpi-label">Clientes activos</div>
            <div class="nc-kpi-value"><?= (int) $counts['activos'] ?></div>
            <div class="nc-kpi-meta">Registrados en cartera: <?= (int) $counts['total'] ?></div>
            <div class="progress">
                <div class="progress-bar bg-white opacity-75" style="width: <?= $kpiPctActivos ?>%"></div>
            </div>
            <a class="nc-kpi-link" href="index.php">Ver lista de clientes</a>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="nc-kpi-card nc-kpi-card--blue h-100">
            <div class="nc-kpi-label">Ventana de cobro (7 días)</div>
            <div class="nc-kpi-value"><?= $proximos7Count ?></div>
            <div class="nc-kpi-meta">Pagos programados en la próxima semana · Promesas vigentes: <?= $promesasActivas ?></div>
            <div class="progress">
                <div class="progress-bar bg-white opacity-75" style="width: <?= $counts['activos'] > 0 ? min(100, (int) round(100 * $proximos7Count / max(1, $counts['activos']))) : 0 ?>%"></div>
            </div>
            <a class="nc-kpi-link" href="index.php">Ver calendario en listado</a>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="nc-kpi-card nc-kpi-card--violet h-100">
            <div class="nc-kpi-label">Pagos vencidos (activos)</div>
            <div class="nc-kpi-value"><?= $vencidosCount ?></div>
            <div class="nc-kpi-meta">Clientes con fecha de pago &lt; hoy · Requiere seguimiento</div>
            <div class="progress">
                <div class="progress-bar bg-danger opacity-90" style="width: <?= $counts['activos'] > 0 ? min(100, (int) round(100 * $vencidosCount / max(1, $counts['activos']))) : 0 ?>%"></div>
            </div>
            <a class="nc-kpi-link" href="finanzas.php">Ver finanzas / vencidos</a>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="nc-kpi-card nc-kpi-card--slate h-100">
            <div class="nc-kpi-label">Routers — API</div>
            <div class="nc-kpi-value"><?= $routersOk ?><span class="fs-5 fw-semibold opacity-75"> / <?= $routersTotal ?></span></div>
            <div class="nc-kpi-meta"><?= $routersDown ?> sin respuesta API · Diagnóstico detallado disponible</div>
            <div class="progress">
                <div class="progress-bar bg-success opacity-90" style="width: <?= $kpiPctRouters ?>%"></div>
            </div>
            <a class="nc-kpi-link" href="mikrotik_diag.php">Ver diagnóstico API</a>
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
            borderColor: '#38bdf8',
            backgroundColor: 'rgba(56, 189, 248, 0.14)',
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
            grid: { color: 'rgba(124, 143, 163, 0.12)' },
            ticks: { color: '#8fa3b8', maxRotation: 0 },
          },
          y: {
            beginAtZero: true,
            grid: { color: 'rgba(124, 143, 163, 0.12)' },
            ticks: { color: '#8fa3b8', precision: 0 },
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
          backgroundColor: ['#1ecdb2', 'rgba(232, 179, 57, 0.88)'],
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
            labels: { color: '#8fa3b8', boxWidth: 12 },
          },
        },
      },
    });
  }
})();
</script>

<?php require __DIR__ . '/partials/footer.php';
