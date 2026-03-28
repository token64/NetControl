<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_auth();

$navSection = 'red-redes';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$preMik = isset($_GET['mikrotik_id']) ? (int) $_GET['mikrotik_id'] : 0;

$row = $id > 0 ? red_by_id($id) : null;
if ($id > 0 && ! $row) {
    flash_set('error', 'Red no encontrada.');
    redirect('redes.php');
}

$pageTitle = ($id > 0 ? 'Editar red' : 'Nueva red') . ' — NetControl';
$routers = list_mikrotiks_admin();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! csrf_verify()) {
        $errors[] = 'Token de seguridad inválido.';
    } else {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $mikrotikId = (int) ($_POST['mikrotik_id'] ?? 0);
        $rango = trim((string) ($_POST['rango'] ?? ''));
        $activo = ! empty($_POST['activo']) ? 1 : 0;

        if ($nombre === '') {
            $errors[] = 'El nombre es obligatorio.';
        }
        if ($mikrotikId <= 0) {
            $errors[] = 'Seleccioná un router.';
        }
        $parts = explode('-', $rango);
        if (count($parts) !== 2) {
            $errors[] = 'El rango debe ser dos IPv4 separadas por un guion: inicio-fin.';
        } else {
            $a = trim($parts[0]);
            $b = trim($parts[1]);
            if (ip2long($a) === false || ip2long($b) === false) {
                $errors[] = 'IPs del rango no válidas.';
            }
        }

        if ($errors === []) {
            $db = db();
            if ($id === 0) {
                $st = $db->prepare('INSERT INTO redes (nombre, mikrotik_id, rango, activo) VALUES (?,?,?,?)');
                $st->bind_param('sisi', $nombre, $mikrotikId, $rango, $activo);
                $st->execute();
                flash_set('success', 'Red creada.');
            } else {
                $st = $db->prepare('UPDATE redes SET nombre=?, mikrotik_id=?, rango=?, activo=? WHERE id=?');
                $st->bind_param('sisii', $nombre, $mikrotikId, $rango, $activo, $id);
                $st->execute();
                flash_set('success', 'Red actualizada.');
            }
            redirect('redes.php');
        }
    }
}

$defaults = [
    'nombre' => '',
    'mikrotik_id' => $preMik > 0 ? $preMik : 0,
    'rango' => '',
    'activo' => 1,
];
if ($row) {
    $defaults = array_merge($defaults, $row);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors !== []) {
    $defaults = array_merge($defaults, [
        'nombre' => trim((string) ($_POST['nombre'] ?? '')),
        'mikrotik_id' => (int) ($_POST['mikrotik_id'] ?? 0),
        'rango' => trim((string) ($_POST['rango'] ?? '')),
        'activo' => ! empty($_POST['activo']) ? 1 : 0,
    ]);
}

require __DIR__ . '/partials/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h1 class="h3 mb-0"><?= $id > 0 ? 'Editar red IPv4' : 'Nueva red IPv4' ?></h1>
        <p class="text-secondary small mb-0">Se usa al crear clientes con IP fija y cola simple</p>
    </div>
    <a class="btn btn-outline-secondary" href="redes.php">Volver</a>
</div>

<?php if ($errors !== []): ?>
    <div class="alert alert-danger nc-flash">
        <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php if ($routers === []): ?>
    <div class="alert alert-warning nc-flash">Primero debés <a href="router_edit.php">dar de alta un router</a>.</div>
<?php else: ?>
    <div class="row">
        <div class="col-lg-7">
            <div class="nc-card p-4">
                <form method="post" novalidate>
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Nombre</label>
                            <input class="form-control" name="nombre" required value="<?= esc((string) $defaults['nombre']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Router</label>
                            <select class="form-select" name="mikrotik_id" required>
                                <?php foreach ($routers as $r): ?>
                                    <option value="<?= (int) $r['id'] ?>" <?= (int) $defaults['mikrotik_id'] === (int) $r['id'] ? 'selected' : '' ?>>
                                        <?= esc((string) $r['nombre']) ?> (<?= esc((string) $r['ip']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Rango (inicio-fin)</label>
                            <input class="form-control font-monospace" name="rango" required placeholder="192.168.10.2-192.168.10.254" value="<?= esc((string) $defaults['rango']) ?>">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="activo" value="1" id="activo" <?= ! empty($defaults['activo']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="activo">Activa (visible al crear clientes)</label>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-nc-primary">Guardar</button>
                        <a class="btn btn-outline-secondary" href="redes.php">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php';
