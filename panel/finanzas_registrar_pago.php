<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_auth();
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/includes/finanzas.php';

$navSection = 'finanzas';
$finanzasSub = 'registrar-pago';
$pageTitle = 'Registrar pago — NetControl';
$errors = [];

if (! nc_pagos_table_exists()) {
    require __DIR__ . '/partials/header.php';
    ?>
    <div class="alert alert-warning nc-flash">
        Falta crear la tabla de pagos. En el servidor ejecutá:
        <code>mysql ... panel_wisp &lt; sql/migration_finanzas_pagos.sql</code>
        (o importá ese archivo desde phpMyAdmin).
    </div>
    <a class="btn btn-outline-secondary" href="finanzas.php">Volver</a>
    <?php
    require __DIR__ . '/partials/footer.php';
    exit;
}

$res = db()->query('SELECT id, nombre FROM clientes ORDER BY nombre ASC');
$clientes = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $clientes[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! csrf_verify()) {
        $errors[] = 'Token de seguridad inválido.';
    } else {
        $cid = (int) ($_POST['cliente_id'] ?? 0);
        $montoRaw = trim((string) ($_POST['monto'] ?? ''));
        $montoRaw = str_replace(',', '.', preg_replace('/[^\d.,]/', '', $montoRaw) ?? '');
        $moneda = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) ($_POST['moneda'] ?? 'DOP')) ?: 'DOP', 0, 3));
        if (strlen($moneda) !== 3) {
            $moneda = 'DOP';
        }
        $metodo = trim((string) ($_POST['metodo'] ?? 'efectivo'));
        if ($metodo === '') {
            $metodo = 'efectivo';
        }
        $metodo = substr($metodo, 0, 40);
        $referencia = substr(trim((string) ($_POST['referencia'] ?? '')), 0, 120);
        $notas = substr(trim((string) ($_POST['notas'] ?? '')), 0, 500);
        $monto = (float) $montoRaw;
        $updFecha = ! empty($_POST['actualizar_fecha_pago']);
        $nuevaFecha = trim((string) ($_POST['nueva_fecha_pago'] ?? ''));

        if ($cid <= 0) {
            $errors[] = 'Elegí un cliente.';
        }
        if ($monto <= 0) {
            $errors[] = 'El monto debe ser mayor a cero.';
        }

        if ($errors === []) {
            $chk = db()->prepare('SELECT id FROM clientes WHERE id = ? LIMIT 1');
            $chk->bind_param('i', $cid);
            $chk->execute();
            if (! $chk->get_result()->fetch_assoc()) {
                $errors[] = 'Cliente no válido.';
            }
        }

        if ($errors === []) {
            $op = ADMIN_USER;
            $st = db()->prepare(
                'INSERT INTO pagos (cliente_id, monto, moneda, metodo, referencia, notas, operador) VALUES (?,?,?,?,?,?,?)'
            );
            $st->bind_param('idsssss', $cid, $monto, $moneda, $metodo, $referencia, $notas, $op);
            $st->execute();

            if ($updFecha && $nuevaFecha !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $nuevaFecha)) {
                $u = db()->prepare('UPDATE clientes SET fecha_pago = ? WHERE id = ?');
                $u->bind_param('si', $nuevaFecha, $cid);
                $u->execute();
            }

            flash_set('success', 'Pago registrado correctamente.');
            require_once __DIR__ . '/includes/helpers.php';
            redirect('finanzas_transacciones.php');
        }
    }
}

require __DIR__ . '/partials/header.php';
?>

<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h4 mb-1">Registrar pago</h1>
        <p class="text-secondary small mb-0">Registro de cobro en el historial. Opcional: actualizar la próxima fecha de pago del cliente.</p>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="finanzas.php">Finanzas</a>
</div>

<?php if ($errors !== []): ?>
    <div class="alert alert-danger nc-flash"><?= esc(implode(' ', $errors)) ?></div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="nc-card p-4">
            <form method="post" class="vstack gap-3">
                <?= csrf_field() ?>
                <div>
                    <label class="form-label">Cliente</label>
                    <select name="cliente_id" class="form-select" required>
                        <option value="">— Elegir —</option>
                        <?php foreach ($clientes as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"><?= esc((string) $c['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Monto</label>
                        <input type="text" name="monto" class="form-control" required placeholder="ej. 1500.00" inputmode="decimal">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Moneda</label>
                        <select name="moneda" class="form-select">
                            <option value="DOP">DOP</option>
                            <option value="USD">USD</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Método</label>
                        <select name="metodo" class="form-select">
                            <option value="efectivo">Efectivo</option>
                            <option value="transferencia">Transferencia</option>
                            <option value="tarjeta">Tarjeta</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="form-label">Referencia (opcional)</label>
                    <input type="text" name="referencia" class="form-control" maxlength="120" placeholder="No. comprobante, banco…">
                </div>
                <div>
                    <label class="form-label">Notas</label>
                    <textarea name="notas" class="form-control" rows="2" maxlength="500"></textarea>
                </div>
                <div class="border border-secondary rounded p-3">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="actualizar_fecha_pago" value="1" id="updFecha">
                        <label class="form-check-label" for="updFecha">Actualizar <strong>fecha de pago</strong> del cliente (próximo vencimiento)</label>
                    </div>
                    <label class="form-label small text-secondary">Nueva fecha de pago</label>
                    <input type="date" name="nueva_fecha_pago" class="form-control form-control-sm" style="max-width:12rem">
                </div>
                <button type="submit" class="btn btn-nc-primary">Guardar pago</button>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php';
