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
        $enlaceTipo = mikrotik_enlace_normalizado(trim((string) ($_POST['enlace_tipo'] ?? 'directo')));
        $notas = trim((string) ($_POST['notas'] ?? ''));
        if (strlen($notas) > 500) {
            $errors[] = 'Las notas no pueden superar 500 caracteres.';
        }

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
                    'INSERT INTO mikrotiks (nombre, ip, api_port, use_ssl, usuario, password, enlace_tipo, notas) VALUES (?,?,?,?,?,?,?,?)'
                );
                $st->bind_param('ssiissss', $nombre, $ip, $port, $useSsl, $usuario, $password, $enlaceTipo, $notas);
                $st->execute();
                flash_set('success', 'Router creado. Añadí redes IPv4 en el menú lateral si usás clientes con IP fija.');
                redirect('routers.php');
            } else {
                if ($password !== '') {
                    $st = $db->prepare(
                        'UPDATE mikrotiks SET nombre=?, ip=?, api_port=?, use_ssl=?, usuario=?, password=?, enlace_tipo=?, notas=? WHERE id=?'
                    );
                    $st->bind_param('ssiissssi', $nombre, $ip, $port, $useSsl, $usuario, $password, $enlaceTipo, $notas, $id);
                } else {
                    $st = $db->prepare(
                        'UPDATE mikrotiks SET nombre=?, ip=?, api_port=?, use_ssl=?, usuario=?, enlace_tipo=?, notas=? WHERE id=?'
                    );
                    $st->bind_param('ssiisssi', $nombre, $ip, $port, $useSsl, $usuario, $enlaceTipo, $notas, $id);
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
        'enlace_tipo' => mikrotik_enlace_normalizado(trim((string) ($_POST['enlace_tipo'] ?? ($row['enlace_tipo'] ?? 'directo')))),
        'notas' => trim((string) ($_POST['notas'] ?? ($row['notas'] ?? ''))),
    ]);
}

$r = $row ?? [];
$enlaceTipoVal = mikrotik_enlace_normalizado(trim((string) ($r['enlace_tipo'] ?? 'directo')));
$notasVal = trim((string) ($r['notas'] ?? ''));

require __DIR__ . '/partials/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h1 class="h3 mb-0"><?= $id > 0 ? 'Editar router' : 'Nuevo router' ?></h1>
        <p class="text-secondary small mb-0">Credenciales de la API RouterOS — la IP es la que <strong>alcanza el servidor PHP</strong>, no siempre la WAN del sitio.</p>
    </div>
    <a class="btn btn-outline-secondary" href="routers.php">Volver</a>
</div>

<?php if ($errors !== []): ?>
    <div class="alert alert-danger nc-flash">
        <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="nc-card p-4">
            <form method="post" novalidate>
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Nombre</label>
                        <input class="form-control" name="nombre" required value="<?= esc((string) ($r['nombre'] ?? '')) ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">IP o host (visto desde el panel)</label>
                        <input class="form-control font-monospace" name="ip" required value="<?= esc((string) ($r['ip'] ?? '')) ?>"
                               placeholder="ej. 192.168.88.1 o 10.64.0.4 (VPN hub)">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Puerto API</label>
                        <input class="form-control" type="number" name="api_port" min="1" max="65535" value="<?= (int) ($r['api_port'] ?? 8728) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Tipo de enlace</label>
                        <select class="form-select" name="enlace_tipo">
                            <?php foreach (mikrotik_enlace_opciones() as $k => $label): ?>
                                <option value="<?= esc($k) ?>" <?= $enlaceTipoVal === $k ? 'selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notas <span class="text-secondary fw-normal">(opcional)</span></label>
                        <textarea class="form-control font-monospace small" name="notas" rows="3" maxlength="500" placeholder="Ej. WireGuard peer hub — IP tunel 10.50.0.14; firewall: allow 8728 desde IP del panel"><?= esc($notasVal) ?></textarea>
                        <div class="form-text">Sirve para operación: no cambia la conexión API, solo documenta el escenario (túnel, Starlink, etc.).</div>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="use_ssl" value="1" id="use_ssl" <?= ! empty($r['use_ssl']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="use_ssl">API sobre TLS (puerto suele ser 8729)</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Usuario API</label>
                        <input class="form-control" name="usuario" required value="<?= esc((string) ($r['usuario'] ?? '')) ?>" autocomplete="off">
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
    <div class="col-lg-5">
        <?php require __DIR__ . '/partials/mikrotik_ayuda_enlaces.php'; ?>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php';
