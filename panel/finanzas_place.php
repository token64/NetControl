<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_auth();
require_once __DIR__ . '/includes/helpers.php';

$p = (string) ($_GET['p'] ?? '');
if ($p === 'registrar-pago') {
    redirect('finanzas_registrar_pago.php');
}
if ($p === 'transacciones') {
    redirect('finanzas_transacciones.php');
}

$pages = [
    'facturas' => [
        'title' => 'Facturas',
        'body' => 'Emisión, numeración, estados (pagada / vencida) e impresión. Requiere tablas de facturas y líneas vinculadas a clientes.',
    ],
    'pagos-masivos' => [
        'title' => 'Registrar pagos masivos',
        'body' => 'Importación CSV o carga en lote de pagos. Requiere el módulo de pagos y validaciones.',
    ],
    'ingresos-egresos' => [
        'title' => 'Otros ingresos y egresos',
        'body' => 'Movimientos que no vienen de facturación recurrente (gastos, ajustes). Requiere categorías y permisos.',
    ],
    'reportes-pago' => [
        'title' => 'Reportes de pago',
        'subtitle' => '(Portal cliente)',
        'body' => 'Vista para que el cliente consulte comprobantes en un portal público. Requiere autenticación de abonado y API o sesión aparte.',
    ],
    'facturacion-electronica' => [
        'title' => 'Facturación electrónica',
        'body' => 'Integración DGII / e-CF según normativa local. Requiere certificado, secuencias y proveedor o API oficial.',
    ],
    'estadisticas' => [
        'title' => 'Estadísticas',
        'body' => 'Gráficos de ingresos, mora, churn. Se alimentarán de facturación y pagos cuando existan las tablas.',
    ],
];

if ($p === '' || ! isset($pages[$p])) {
    redirect('finanzas.php');
}

$meta = $pages[$p];
$navSection = 'finanzas';
$finanzasSub = $p;
$pageTitle = $meta['title'] . ' — NetControl';

require __DIR__ . '/funciones.php';
require __DIR__ . '/partials/header.php';
?>

<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h4 mb-1"><?= esc($meta['title']) ?></h1>
        <?php if (! empty($meta['subtitle'])): ?>
            <p class="text-secondary small mb-0"><?= esc($meta['subtitle']) ?></p>
        <?php endif; ?>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="finanzas.php">← Cobros y promesas</a>
</div>

<div class="nc-card p-4">
    <p class="text-secondary mb-3"><?= esc($meta['body']) ?></p>
    <p class="small text-muted mb-0">Módulo en roadmap. Hoy podés gestionar <strong>fechas de pago</strong> y <strong>promesas</strong> desde la lista de clientes y en <a href="finanzas.php">Cobros y promesas</a>.</p>
</div>

<?php require __DIR__ . '/partials/footer.php';
