<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_auth();

$navSection = 'red-redes';
$pageTitle = 'Redes IPv4 — NetControl';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (! csrf_verify()) {
        flash_set('error', 'Token de seguridad inválido.');
    } else {
        $delId = (int) $_POST['delete_id'];
        if ($delId > 0) {
            $st = db()->prepare('DELETE FROM redes WHERE id = ?');
            $st->bind_param('i', $delId);
            $st->execute();
            flash_set('success', 'Red eliminada.');
        }
    }
    redirect('redes.php');
}

$rows = list_redes_admin();
$flash = flash_get();

require __DIR__ . '/partials/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : 'success' ?> nc-flash alert-dismissible fade show">
        <?= esc($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h1 class="h3 mb-0">Redes IPv4 / pools</h1>
        <p class="text-secondary small mb-0">Rango inicio-fin para asignar IPs fijas (ej. <code>10.0.0.2-10.0.0.254</code>)</p>
    </div>
    <a class="btn btn-nc-primary" href="red_edit.php">Nueva red</a>
</div>

<div class="nc-card p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table nc-table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>Nombre</th>
                <th>Router</th>
                <th>Rango</th>
                <th>Estado</th>
                <th class="text-end">Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($rows !== []): ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><strong><?= esc((string) $r['nombre']) ?></strong></td>
                        <td><?= esc((string) $r['mikrotik_nombre']) ?></td>
                        <td class="font-monospace small"><?= esc((string) $r['rango']) ?></td>
                        <td><?= ! empty($r['activo']) ? '<span class="badge text-bg-success">Activa</span>' : '<span class="badge text-bg-secondary">Inactiva</span>' ?></td>
                        <td class="text-end">
                            <a class="btn btn-outline-light btn-sm" href="red_edit.php?id=<?= (int) $r['id'] ?>">Editar</a>
                            <form class="d-inline" method="post" action="redes.php" onsubmit="return confirm('¿Eliminar esta red del catálogo?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="delete_id" value="<?= (int) $r['id'] ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm">Borrar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="text-center text-secondary py-5">No hay redes. Creá una o ejecutá la migración <code>sql/migration_v2_planes_redes.sql</code>.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php';
