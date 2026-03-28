<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_auth();

$navSection = 'red-routers';
$pageTitle = 'Routers — NetControl';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (! csrf_verify()) {
        flash_set('error', 'Token de seguridad inválido.');
    } else {
        $delId = (int) $_POST['delete_id'];
        if ($delId > 0) {
            if (count_clientes_mikrotik($delId) > 0) {
                flash_set('error', 'No se puede borrar: hay clientes usando este router.');
            } elseif (count_redes_mikrotik($delId) > 0) {
                flash_set('error', 'No se puede borrar: elimina o reasigna primero las redes IPv4 de este router.');
            } else {
                $st = db()->prepare('DELETE FROM mikrotiks WHERE id = ?');
                $st->bind_param('i', $delId);
                $st->execute();
                flash_set('success', 'Router eliminado.');
            }
        }
    }
    redirect('routers.php');
}

$rows = list_mikrotiks_admin();
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
        <h1 class="h3 mb-0">Routers (MikroTik API)</h1>
        <p class="text-secondary small mb-0">Alta de equipos para sincronizar PPPoE y colas simples</p>
    </div>
    <a class="btn btn-nc-primary" href="router_edit.php">Nuevo router</a>
</div>

<div class="nc-card p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table nc-table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>Nombre</th>
                <th>IP / puerto</th>
                <th>SSL</th>
                <th>Usuario API</th>
                <th class="text-end">Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($rows !== []): ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><strong><?= esc((string) $r['nombre']) ?></strong></td>
                        <td class="font-monospace small"><?= esc((string) $r['ip']) ?>:<?= (int) $r['api_port'] ?></td>
                        <td><?= ! empty($r['use_ssl']) ? 'Sí' : 'No' ?></td>
                        <td><?= esc((string) $r['usuario']) ?></td>
                        <td class="text-end">
                            <a class="btn btn-outline-light btn-sm" href="router_edit.php?id=<?= (int) $r['id'] ?>">Editar</a>
                            <?php if (count_clientes_mikrotik((int) $r['id']) === 0 && count_redes_mikrotik((int) $r['id']) === 0): ?>
                                <form class="d-inline" method="post" action="routers.php" onsubmit="return confirm('¿Eliminar este router?');">
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
                    <td colspan="5" class="text-center text-secondary py-5">No hay routers. Crea el primero o ejecutá <code>sql/schema.sql</code>.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php';
