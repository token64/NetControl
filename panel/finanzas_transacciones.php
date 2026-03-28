<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_auth();
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/includes/finanzas.php';

$navSection = 'finanzas';
$finanzasSub = 'transacciones';
$pageTitle = 'Transacciones — NetControl';
$flash = flash_get();

require __DIR__ . '/partials/header.php';

$rows = nc_pagos_recientes(200);
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : 'success' ?> nc-flash alert-dismissible fade show">
        <?= esc($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h4 mb-1">Transacciones</h1>
        <p class="text-secondary small mb-0">Historial de cobros registrados en el panel.</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-nc-primary btn-sm" href="finanzas_registrar_pago.php">Registrar pago</a>
        <a class="btn btn-outline-secondary btn-sm" href="finanzas.php">Resumen finanzas</a>
    </div>
</div>

<?php if (! nc_pagos_table_exists()): ?>
    <div class="alert alert-warning">Ejecutá la migración <code>sql/migration_finanzas_pagos.sql</code> para habilitar esta pantalla.</div>
<?php elseif ($rows === []): ?>
    <div class="nc-card p-4 text-secondary">Aún no hay pagos registrados.</div>
<?php else: ?>
    <div class="nc-card p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table nc-table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th class="text-end">Monto</th>
                        <th>Método</th>
                        <th>Ref.</th>
                        <th>Operador</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td class="text-secondary small"><?= esc((string) $r['creado_en']) ?></td>
                            <td><?= esc((string) $r['cliente_nombre']) ?></td>
                            <td class="text-end font-monospace"><?= esc((string) $r['moneda']) ?> <?= esc(number_format((float) $r['monto'], 2, '.', ',')) ?></td>
                            <td><?= esc((string) $r['metodo']) ?></td>
                            <td class="small text-secondary"><?= esc((string) ($r['referencia'] ?? '')) ?></td>
                            <td class="small"><?= esc((string) $r['operador']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php';
