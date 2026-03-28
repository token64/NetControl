<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_auth();

$navSection = 'red-routers';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$row = $id > 0 ? mikrotik_by_id($id) : null;
if ($id > 0 && ! $row) {
    flash_set('error', 'Router no encontrado.');
    redirect('routers.php');
}

$pageTitle = ($id > 0 ? 'Editar router' : 'Nuevo router') . ' — NetControl';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! csrf_verify()) {
        $errors[] = 'Token de seguridad inválido.';
    } else {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $ip = trim((string) ($_POST['ip'] ?? ''));
        $port = (int) ($_POST['api_port'] ?? 8728);
        $useSsl = ! empty($_POST['use_ssl']) ? 1 : 0;
        $usuario = trim((string) ($_POST['usuario'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($nombre === '') {
            $errors[] = 'El nombre es obligatorio.';
        }
        if ($ip === '') {
            $errors[] = 'La IP o host es obligatorio.';
        }
        if ($usuario === '') {
            $errors[] = 'El usuario API es obligatorio.';
        }
        if ($port < 1 || $port > 65535) {
            $errors[] = 'Puerto API no válido.';
        }
        if ($id === 0 && $password === '') {
            $errors[] = 'La contraseña API es obligatoria al crear.';
        }

        if ($errors === []) {
            $db = db();
            if ($id === 0) {
                $st = $db->prepare(
                    'INSERT INTO mikrotiks (nombre, ip, api_port, use_ssl, usuario, password) VALUES (?,?,?,?,?,?)'
                );
                $st->bind_param('ssiiss', $nombre, $ip, $port, $useSsl, $usuario, $password);
                $st->execute();
                $newId = (int) $db->insert_id;
                flash_set('success', 'Router creado. Añadí redes IPv4 en el menú lateral si usás clientes con IP fija.');
                redirect('routers.php');
            } else {
                if ($password !== '') {
                    $st = $db->prepare(
                        'UPDATE mikrotiks SET nombre=?, ip=?, api_port=?, use_ssl=?, usuario=?, password=? WHERE id=?'
                    );
                    $st->bind_param('ssiissi', $nombre, $ip, $port, $useSsl, $usuario, $password, $id);
                } else {
                    $st = $db->prepare(
                        'UPDATE mikrotiks SET nombre=?, ip=?, api_port=?, use_ssl=?, usuario=? WHERE id=?'
                    );
                    $st->bind_param('ssiisi', $nombre, $ip, $port, $useSsl, $usuario, $id);
                }
                $st->execute();
                flash_set('success', 'Router actualizado.');
                redirect('routers.php');
            }
        }
    }
}

// Re-lectura tras error
if ($row && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $row = array_merge($row, [
        'nombre' => trim((string) ($_POST['nombre'] ?? $row['nombre'])),
        'ip' => trim((string) ($_POST['ip'] ?? $row['ip'])),
        'api_port' => (int) ($_POST['api_port'] ?? $row['api_port']),
        'use_ssl' => ! empty($_POST['use_ssl']) ? 1 : 0,
        'usuario' => trim((string) ($_POST['usuario'] ?? $row['usuario'])),
    ]);
}

require __DIR__ . '/partials/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h1 class="h3 mb-0"><?= $id > 0 ? 'Editar router' : 'Nuevo router' ?></h1>
        <p class="text-secondary small mb-0">Credenciales de la API RouterOS (puerto típico 8728)</p>
    </div>
    <a class="btn btn-outline-secondary" href="routers.php">Volver</a>
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
                    <div class="col-12">
                        <label class="form-label">Nombre</label>
                        <input class="form-control" name="nombre" required value="<?= esc((string) ($row['nombre'] ?? '')) ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">IP o host</label>
                        <input class="form-control font-monospace" name="ip" required value="<?= esc((string) ($row['ip'] ?? '')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Puerto API</label>
                        <input class="form-control" type="number" name="api_port" min="1" max="65535" value="<?= (int) ($row['api_port'] ?? 8728) ?>">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="use_ssl" value="1" id="use_ssl" <?= ! empty($row['use_ssl']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="use_ssl">API sobre TLS (puerto suele ser 8729)</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Usuario API</label>
                        <input class="form-control" name="usuario" required value="<?= esc((string) ($row['usuario'] ?? '')) ?>" autocomplete="off">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Contraseña API</label>
                        <input class="form-control" type="password" name="password" value="" autocomplete="new-password" placeholder="<?= $id > 0 ? 'Dejar vacío para no cambiar' : '' ?>">
                    </div>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-nc-primary">Guardar</button>
                    <a class="btn btn-outline-secondary" href="routers.php">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php';
