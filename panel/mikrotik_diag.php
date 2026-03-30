<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_auth();
require_once __DIR__ . '/funciones.php';

$navSection = 'ops-diag';
$pageTitle = 'Diagnóstico MikroTik — NetControl';

$routers = list_mikrotiks_admin();
$selectedId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($selectedId <= 0 && $routers !== []) {
    $selectedId = (int) $routers[0]['id'];
}

/** Incluir conteos solo si el operador lo pide (evita listar miles de PPP/colas). */
$includeCounts = (string) ($_GET['mode'] ?? 'fast') === 'counts';

$result = null;
$errorExtra = null;
if ($selectedId > 0) {
    try {
        $API = mikrotik_connect($selectedId);
        try {
            $result = [
                'identity' => $API->comm('/system/identity/print'),
                'resource' => $API->comm('/system/resource/print'),
                'address_count' => null,
                'ppp_count' => null,
                'queue_count' => null,
                'counts_note' => null,
            ];
            if ($includeCounts) {
                $result['address_count'] = mikrotik_print_count_only($API, '/ip/address/print');
                $result['ppp_count'] = mikrotik_print_count_only($API, '/ppp/secret/print');
                $result['queue_count'] = mikrotik_print_count_only($API, '/queue/simple/print');
                if ($result['address_count'] === null && $result['ppp_count'] === null && $result['queue_count'] === null) {
                    $result['counts_note'] = 'Tu RouterOS no devolvió conteos (count-only). Actualizá el equipo o ignorá esta sección.';
                }
            }
        } finally {
            $API->disconnect();
        }
    } catch (Throwable $e) {
        $errorExtra = $e->getMessage();
    }
}

require __DIR__ . '/partials/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h1 class="h3 mb-0">Diagnóstico API RouterOS</h1>
        <p class="text-secondary small mb-0">Vista rápida por defecto; los conteos son opcionales y usan <code>count-only</code> (sin bajar miles de filas).</p>
        <p class="small mb-0"><a href="routers.php#ayuda-enlaces-mikrotik">Guía: IP alcanzable, VPN y CGNAT</a></p>
    </div>
    <a class="btn btn-outline-secondary" href="dashboard.php">Inicio</a>
</div>

<div class="nc-card p-4 mb-4">
    <form method="get" class="row g-3 align-items-end" id="diagForm">
        <input type="hidden" name="mode" id="formMode" value="<?= $includeCounts ? 'counts' : 'fast' ?>">
        <div class="col-md-6">
            <label class="form-label">Router</label>
            <select class="form-select" name="id" id="routerSel">
                <?php foreach ($routers as $r): ?>
                    <option value="<?= (int) $r['id'] ?>" <?= $selectedId === (int) $r['id'] ? 'selected' : '' ?>>
                        <?= esc((string) $r['nombre']) ?> — <?= esc((string) $r['ip']) ?>:<?= (int) $r['api_port'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <button type="button" class="btn btn-nc-primary w-100" id="btnFast">Actualizar (rápido)</button>
        </div>
        <div class="col-md-3">
            <button type="button" class="btn btn-outline-info w-100" id="btnCounts">Incluir conteos</button>
        </div>
    </form>
    <p class="small text-secondary mb-0 mt-3">
        <strong>Actualizar (rápido):</strong> solo identidad y carga del sistema (~2 peticiones).<br>
        <strong>Incluir conteos:</strong> tres consultas <code>print count-only</code> (suele tardar poco aun con muchos clientes).
    </p>
</div>

<?php if ($routers === []): ?>
    <div class="alert alert-warning nc-flash">No hay routers en la base de datos.</div>
<?php elseif ($errorExtra !== null): ?>
    <div class="alert alert-danger nc-flash">
        <strong>Error de conexión o API:</strong> <?= esc($errorExtra) ?>
        <p class="small mb-0 mt-2"><a class="alert-link" href="routers.php">Ir a Routers</a> y editá el equipo: el fallo usa la IP/host guardada en la base, no una definida por el software.</p>
    </div>
<?php elseif ($result !== null): ?>
    <?php
    $idRow = mikrotik_first_row((array) $result['identity']);
    $resRow = mikrotik_first_row((array) $result['resource']);
    ?>
    <div class="row g-3">
        <div class="col-md-6">
            <div class="nc-card p-4">
                <h2 class="h6 text-secondary">Identidad</h2>
                <p class="mb-0"><strong><?= esc((string) ($idRow['name'] ?? '—')) ?></strong></p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="nc-card p-4">
                <h2 class="h6 text-secondary">Recurso</h2>
                <ul class="small mb-0">
                    <li>Versión: <?= esc((string) ($resRow['version'] ?? '—')) ?></li>
                    <li>Board: <?= esc((string) ($resRow['board-name'] ?? '—')) ?></li>
                    <li>Uptime: <?= esc((string) ($resRow['uptime'] ?? '—')) ?></li>
                    <li>CPU load: <?= esc((string) ($resRow['cpu-load'] ?? '—')) ?></li>
                </ul>
            </div>
        </div>
        <div class="col-12">
            <div class="nc-card p-4">
                <h2 class="h6 text-secondary">Conteos</h2>
                <?php if (! $includeCounts): ?>
                    <p class="small text-secondary mb-0">No cargados en esta vista. Pulsá <strong>Incluir conteos</strong> arriba si los necesitás.</p>
                <?php elseif (! empty($result['counts_note'])): ?>
                    <p class="small text-warning mb-0"><?= esc((string) $result['counts_note']) ?></p>
                <?php else: ?>
                    <p class="mb-0 small">
                        Direcciones IP: <strong><?= $result['address_count'] !== null ? (int) $result['address_count'] : '—' ?></strong> ·
                        Secretos PPP: <strong><?= $result['ppp_count'] !== null ? (int) $result['ppp_count'] : '—' ?></strong> ·
                        Colas simples: <strong><?= $result['queue_count'] !== null ? (int) $result['queue_count'] : '—' ?></strong>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
(function () {
  var form = document.getElementById('diagForm');
  var mode = document.getElementById('formMode');
  var sel = document.getElementById('routerSel');
  if (!form || !mode) return;
  var bf = document.getElementById('btnFast');
  var bc = document.getElementById('btnCounts');
  if (bf) bf.addEventListener('click', function () {
    mode.value = 'fast';
    form.submit();
  });
  if (bc) bc.addEventListener('click', function () {
    mode.value = 'counts';
    form.submit();
  });
  if (sel) {
    sel.addEventListener('change', function () {
      mode.value = 'fast';
      form.submit();
    });
  }
})();
</script>

<?php require __DIR__ . '/partials/footer.php';
