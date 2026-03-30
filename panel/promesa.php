<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_auth();

$navSection = 'clientes';
$pageTitle = 'Promesa de pago — NetControl';

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    flash_set('error', 'Cliente no especificado.');
    redirect('index.php');
}

$cliente = fetch_client_by_id($id);
if (! $cliente) {
    flash_set('error', 'Cliente no encontrado.');
    redirect('index.php');
}

$hoy = (new DateTimeImmutable('today'))->format('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! csrf_verify()) {
        flash_set('error', 'Token de seguridad inválido.');
        redirect('promesa.php?id=' . $id);
    }

    $action = (string) ($_POST['action'] ?? 'save');

    if ($action === 'clear') {
        $st = db()->prepare('UPDATE clientes SET promesa_hasta = NULL, promesa_notas = NULL WHERE id = ?');
        $st->bind_param('i', $id);
        $st->execute();
        flash_set('success', 'Promesa de pago eliminada para este cliente.');
        redirect('index.php');
    }

    $fecha = trim((string) ($_POST['promesa_hasta'] ?? ''));
    $notas = trim((string) ($_POST['promesa_notas'] ?? ''));

    if ($fecha === '') {
        flash_set('error', 'Indica la fecha límite del compromiso.');
        redirect('promesa.php?id=' . $id);
    }

    $lim = DateTimeImmutable::createFromFormat('Y-m-d', $fecha);
    if (! $lim instanceof DateTimeImmutable) {
        flash_set('error', 'Fecha no válida.');
        redirect('promesa.php?id=' . $id);
    }

    if ($lim < new DateTimeImmutable('today')) {
        flash_set('error', 'La fecha de promesa debe ser hoy o una fecha futura.');
        redirect('promesa.php?id=' . $id);
    }

    $notasDb = $notas === '' ? null : mb_substr($notas, 0, 500);

    $st = db()->prepare('UPDATE clientes SET promesa_hasta = ?, promesa_notas = ? WHERE id = ?');
    $st->bind_param('ssi', $fecha, $notasDb, $id);
    $st->execute();

    flash_set('success', 'Promesa registrada: el servicio no se cortará por mora hasta el ' . formatear_fecha($fecha) . ' (si sigue activo).');
    redirect('index.php');
}

$flash = flash_get();

$valorFecha = $cliente['promesa_hasta'] ? (string) $cliente['promesa_hasta'] : '';
$valorNotas = isset($cliente['promesa_notas']) ? (string) $cliente['promesa_notas'] : '';

require __DIR__ . '/partials/header.php';

if ($flash): ?>
    <div class="alert alert-<?= esc($flash['type'] === 'error' ? 'danger' : ($flash['type'] === 'warning' ? 'warning' : 'success')) ?> nc-flash alert-dismissible fade show">
        <?= esc($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="mb-3">
            <a href="index.php" class="text-secondary text-decoration-none small">← Clientes</a>
        </div>
        <h1 class="h3 mb-2">Promesa de pago</h1>
        <p class="text-secondary small">
            Compromiso de pago con <strong><?= esc((string) $cliente['nombre']) ?></strong>:
            amplía la tolerancia ante mora hasta la fecha que elijas (el corte automático no aplica hasta entonces si está vigente).
        </p>

        <div class="nc-card p-4 mb-4">
            <dl class="row small mb-0">
                <dt class="col-sm-4 text-secondary">Fecha de pago actual</dt>
                <dd class="col-sm-8"><?= esc(formatear_fecha($cliente['fecha_pago'] ? (string) $cliente['fecha_pago'] : null)) ?></dd>
                <dt class="col-sm-4 text-secondary">Promesa vigente</dt>
                <dd class="col-sm-8">
                    <?php if (promesa_vigente($cliente['promesa_hasta'] ?? null)): ?>
                        <span class="badge text-bg-info">Hasta <?= esc(formatear_fecha((string) $cliente['promesa_hasta'])) ?></span>
                    <?php else: ?>
                        <span class="text-secondary">Ninguna</span>
                    <?php endif; ?>
                </dd>
            </dl>
        </div>

        <div class="nc-card p-4">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="action" value="save">

                <div class="mb-3">
                    <label class="form-label">Fecha límite del compromiso</label>
                    <input type="date" name="promesa_hasta" class="form-control" required
                           min="<?= esc($hoy) ?>"
                           value="<?= esc($valorFecha) ?>">
                    <div class="form-text">El corte por mora (tarea programada) no se aplicará mientras hoy sea ≤ esta fecha.</div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Nota interna (opcional)</label>
                    <textarea name="promesa_notas" class="form-control" rows="2" maxlength="500" placeholder="Ej. acuerdo verbal, referencia de recibo..."><?= esc($valorNotas) ?></textarea>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-nc-primary">Guardar promesa</button>
                    <a class="btn btn-outline-secondary" href="index.php">Cancelar</a>
                </div>
            </form>

            <?php if ($valorFecha !== ''): ?>
                <hr class="border-secondary my-4">
                <form method="post" onsubmit="return confirm('¿Quitar la promesa de pago de este cliente?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="action" value="clear">
                    <button type="submit" class="btn btn-outline-warning btn-sm">Quitar promesa</button>
                </form>
            <?php endif; ?>
        </div>

        <p class="small text-secondary mt-3 mb-0">
            Referencia: en gestión ISP la promesa suele fijar una <em>fecha de compromiso</em> antes de aplicar suspensión por deuda vencida
            (concepto habitual en facturación y morosidad).
        </p>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
