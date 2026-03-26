<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_auth();

$pageTitle = 'Clientes — NetControl';
require_once __DIR__ . '/funciones.php';

$flash = flash_get();
$res = db()->query('SELECT c.*, m.nombre AS mikrotik_nombre FROM clientes c JOIN mikrotiks m ON m.id = c.mikrotik_id ORDER BY c.nombre');

require __DIR__ . '/partials/header.php';

if ($flash): ?>
    <div class="alert alert-<?= esc($flash['type'] === 'error' ? 'danger' : ($flash['type'] === 'warning' ? 'warning' : 'success')) ?> nc-flash alert-dismissible fade show">
        <?= esc($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h1 class="h3 mb-0">Clientes</h1>
        <p class="text-secondary small mb-0">Gestión PPPoE y colas simples (IP fija)</p>
    </div>
    <a class="btn btn-nc-primary" href="crear.php">Nuevo cliente</a>
</div>

<div class="nc-card p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table nc-table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>Nombre</th>
                <th>Tipo</th>
                <th>Plan</th>
                <th>MikroTik</th>
                <th>Pago / promesa</th>
                <th>Estado</th>
                <th class="text-end">Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($res && $res->num_rows > 0): ?>
                <?php while ($c = $res->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <strong><?= esc((string) $c['nombre']) ?></strong>
                            <?php if ($c['tipo_conexion'] === 'pppoe' && $c['usuario']): ?>
                                <div class="small text-secondary"><?= esc((string) $c['usuario']) ?></div>
                            <?php elseif ($c['ip_fija']): ?>
                                <div class="small text-secondary"><?= esc((string) $c['ip_fija']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge text-bg-secondary"><?= esc(strtoupper((string) $c['tipo_conexion'])) ?></span></td>
                        <td><?= esc((string) $c['plan']) ?></td>
                        <td><?= esc((string) $c['mikrotik_nombre']) ?></td>
                        <td class="small">
                            <div>Vence: <strong><?= esc(formatear_fecha($c['fecha_pago'] ? (string) $c['fecha_pago'] : null)) ?></strong></div>
                            <?php if (promesa_vigente($c['promesa_hasta'] ?? null)): ?>
                                <div class="mt-1"><span class="badge text-bg-info">Promesa <?= esc(formatear_fecha((string) $c['promesa_hasta'])) ?></span></div>
                            <?php elseif (!empty($c['promesa_hasta'])): ?>
                                <div class="mt-1 text-secondary">Promesa vencida (<?= esc(formatear_fecha((string) $c['promesa_hasta'])) ?>)</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($c['estado'] === 'activo'): ?>
                                <span class="badge badge-nc-ok">Activo</span>
                            <?php else: ?>
                                <span class="badge badge-nc-off">Suspendido</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                <?php if ($c['estado'] === 'activo'): ?>
                                    <form class="d-inline" method="post" action="cortar.php" onsubmit="return confirm('¿Suspender este cliente?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                        <button type="submit" class="btn btn-outline-warning btn-sm">Suspender</button>
                                    </form>
                                <?php else: ?>
                                    <form class="d-inline" method="post" action="pagar.php">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                        <button type="submit" class="btn btn-outline-success btn-sm">Reactivar</button>
                                    </form>
                                <?php endif; ?>
                                    <form class="d-inline" method="post" action="eliminar.php" onsubmit="return confirm('¿Eliminar definitivamente? Esta acción no se puede deshacer.');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar</button>
                                    </form>
                                    <a class="btn btn-outline-info btn-sm" href="promesa.php?id=<?= (int) $c['id'] ?>">Promesa</a>
                                </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center text-secondary py-5">No hay clientes. Crea el primero.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
