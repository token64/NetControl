<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_auth();

$navSection = 'cat-planes';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$row = $id > 0 ? plan_by_id($id) : null;
if ($id > 0 && ! $row) {
    flash_set('error', 'Plan no encontrado.');
    redirect('planes.php');
}

$pageTitle = ($id > 0 ? 'Editar plan' : 'Nuevo plan') . ' — NetControl';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! csrf_verify()) {
        $errors[] = 'Token de seguridad inválido.';
    } else {
        $slug = trim((string) ($_POST['slug'] ?? ''));
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $maxLimit = trim((string) ($_POST['max_limit'] ?? ''));
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);
        $activo = ! empty($_POST['activo']) ? 1 : 0;

        if ($id === 0 && ($slug === '' || ! preg_match('/^[a-z][a-z0-9_]*$/', $slug))) {
            $errors[] = 'Slug obligatorio: solo minúsculas, números y guion bajo; debe empezar con letra.';
        }
        if ($nombre === '') {
            $errors[] = 'El nombre es obligatorio.';
        }
        if ($maxLimit === '') {
            $errors[] = 'El límite (max-limit) es obligatorio.';
        }

        if ($errors === []) {
            $db = db();
            if ($id === 0) {
                $st = $db->prepare(
                    'INSERT INTO planes (slug, nombre, max_limit, activo, sort_order) VALUES (?,?,?,?,?)'
                );
                $st->bind_param('sssii', $slug, $nombre, $maxLimit, $activo, $sortOrder);
                try {
                    $st->execute();
                    flash_set('success', 'Plan creado.');
                    redirect('planes.php');
                } catch (Throwable $e) {
                    $errors[] = 'No se pudo crear (¿slug duplicado?).';
                }
            } else {
                $st = $db->prepare(
                    'UPDATE planes SET nombre=?, max_limit=?, activo=?, sort_order=? WHERE id=?'
                );
                $st->bind_param('ssiii', $nombre, $maxLimit, $activo, $sortOrder, $id);
                $st->execute();
                flash_set('success', 'Plan actualizado.');
                redirect('planes.php');
            }
        }
    }
}

$defaults = [
    'slug' => '',
    'nombre' => '',
    'max_limit' => '10M/10M',
    'activo' => 1,
    'sort_order' => 10,
];
if ($row) {
    $defaults = array_merge($defaults, $row);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors !== []) {
    $defaults = array_merge($defaults, [
        'slug' => trim((string) ($_POST['slug'] ?? '')),
        'nombre' => trim((string) ($_POST['nombre'] ?? '')),
        'max_limit' => trim((string) ($_POST['max_limit'] ?? '')),
        'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        'activo' => ! empty($_POST['activo']) ? 1 : 0,
    ]);
}

require __DIR__ . '/partials/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h1 class="h3 mb-0"><?= $id > 0 ? 'Editar plan' : 'Nuevo plan' ?></h1>
        <p class="text-secondary small mb-0">Perfil PPPoE = slug · Cola simple = max-limit</p>
    </div>
    <a class="btn btn-outline-secondary" href="planes.php">Volver</a>
</div>

<?php if ($errors !== []): ?>
    <div class="alert alert-danger nc-flash">
        <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-7">
        <div class="nc-card p-4">
            <form method="post" novalidate>
                <?= csrf_field() ?>
                <div class="row g-3">
                    <?php if ($id > 0): ?>
                        <div class="col-12">
                            <label class="form-label">Slug (solo lectura)</label>
                            <input class="form-control font-monospace" readonly value="<?= esc((string) $defaults['slug']) ?>">
                            <p class="small text-secondary mb-0">Para renombrar el perfil en clientes existentes habría que migrar datos en MikroTik y en esta tabla.</p>
                        </div>
                    <?php else: ?>
                        <div class="col-12">
                            <label class="form-label">Slug (nombre perfil PPPoE)</label>
                            <input class="form-control font-monospace" name="slug" required pattern="[a-z][a-z0-9_]*" placeholder="plan_10m" value="<?= esc((string) $defaults['slug']) ?>">
                        </div>
                    <?php endif; ?>
                    <div class="col-12">
                        <label class="form-label">Nombre visible</label>
                        <input class="form-control" name="nombre" required value="<?= esc((string) $defaults['nombre']) ?>">
                    </div>
                    <div class="col-md-7">
                        <label class="form-label">Max-limit (cola simple)</label>
                        <input class="form-control font-monospace" name="max_limit" required placeholder="10M/10M" value="<?= esc((string) $defaults['max_limit']) ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Orden</label>
                        <input class="form-control" type="number" name="sort_order" value="<?= (int) $defaults['sort_order'] ?>">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="activo" value="1" id="activo" <?= ! empty($defaults['activo']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="activo">Activo (se ofrece al crear clientes)</label>
                        </div>
                    </div>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-nc-primary">Guardar</button>
                    <a class="btn btn-outline-secondary" href="planes.php">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php';
