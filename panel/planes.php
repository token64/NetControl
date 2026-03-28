<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_auth();

$navSection = 'cat-planes';
$pageTitle = 'Planes — NetControl';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (! csrf_verify()) {
        flash_set('error', 'Token de seguridad inválido.');
    } else {
        $delId = (int) $_POST['delete_id'];
        if ($delId > 0) {
            $p = plan_by_id($delId);
            if (! $p) {
                flash_set('error', 'Plan no encontrado.');
            } else {
                $slug = (string) $p['slug'];
                if (count_clientes_plan_slug($slug) > 0) {
                    flash_set('error', 'No se puede borrar: hay clientes con este plan.');
                } else {
                    $st = db()->prepare('DELETE FROM planes WHERE id = ?');
                    $st->bind_param('i', $delId);
                    $st->execute();
                    flash_set('success', 'Plan eliminado.');
                }
            }
        }
    }
    redirect('planes.php');
}

$rows = list_planes_admin();
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
        <h1 class="h3 mb-0">Planes de velocidad</h1>
        <p class="text-secondary small mb-0">El <strong>slug</strong> debe coincidir con el perfil PPPoE en MikroTik; <strong>límite</strong> con cola simple (ej. <code>10M/10M</code>)</p>
    </div>
    <a class="btn btn-nc-primary" href="plan_edit.php">Nuevo plan</a>
</div>

<div class="nc-card p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table nc-table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>Slug</th>
                <th>Nombre</th>
                <th>Límite cola</th>
                <th>Orden</th>
                <th>Estado</th>
                <th class="text-end">Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($rows !== []): ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="font-monospace small"><?= esc((string) $r['slug']) ?></td>
                        <td><strong><?= esc((string) $r['nombre']) ?></strong></td>
                        <td class="font-monospace small"><?= esc((string) $r['max_limit']) ?></td>
                        <td><?= (int) $r['sort_order'] ?></td>
                        <td><?= ! empty($r['activo']) ? '<span class="badge text-bg-success">Activo</span>' : '<span class="badge text-bg-secondary">Inactivo</span>' ?></td>
                        <td class="text-end">
                            <a class="btn btn-outline-light btn-sm" href="plan_edit.php?id=<?= (int) $r['id'] ?>">Editar</a>
                            <?php if (count_clientes_plan_slug((string) $r['slug']) === 0): ?>
                                <form class="d-inline" method="post" action="planes.php" onsubmit="return confirm('¿Eliminar este plan?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="delete_id" value="<?= (int) $r['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm">Borrar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="text-center text-secondary py-5">No hay planes en la base de datos.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php';
