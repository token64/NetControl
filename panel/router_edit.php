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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_router']) && $id > 0) {
    if (! csrf_verify()) {
        flash_set('error', 'Token de seguridad inválido.');
    } else {
        $delId = (int) ($_POST['delete_router'] ?? 0);
        if ($delId !== $id) {
            flash_set('error', 'Solicitud no válida.');
        } elseif (count_clientes_mikrotik($id) > 0) {
            flash_set('error', 'No se puede borrar: hay clientes usando este router. Reasignalos antes.');
        } elseif (count_redes_mikrotik($id) > 0) {
            flash_set('error', 'No se puede borrar: eliminá o reasigná las redes IPv4 de este router primero.');
        } else {
            $st = db()->prepare('DELETE FROM mikrotiks WHERE id = ?');
            $st->bind_param('i', $id);
            $st->execute();
            flash_set('success', 'Router eliminado.');
            redirect('routers.php');
        }
    }
    redirect('router_edit.php?id=' . $id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ! isset($_POST['delete_router'])) {
    if (! csrf_verify()) {
        $errors[] = 'Token de seguridad inválido.';
    } else {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $tipoEquipo = mikrotik_tipo_equipo_normalizado(trim((string) ($_POST['tipo_equipo'] ?? 'mikrotik')));
        $latRaw = trim((string) ($_POST['latitud'] ?? ''));
        $lngRaw = trim((string) ($_POST['longitud'] ?? ''));
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

        $latitud = $latRaw === '' ? null : (float) str_replace(',', '.', $latRaw);
        $longitud = $lngRaw === '' ? null : (float) str_replace(',', '.', $lngRaw);
        if (($latRaw === '') !== ($lngRaw === '')) {
            $errors[] = 'Ubicación: completá latitud y longitud, o dejá ambas vacías.';
        }
        if ($latitud !== null && ($latitud < -90.0 || $latitud > 90.0)) {
            $errors[] = 'Latitud fuera de rango (-90 … 90).';
        }
        if ($longitud !== null && ($longitud < -180.0 || $longitud > 180.0)) {
            $errors[] = 'Longitud fuera de rango (-180 … 180).';
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

        $latBind = $latitud === null ? null : number_format($latitud, 7, '.', '');
        $lngBind = $longitud === null ? null : number_format($longitud, 7, '.', '');

        if ($errors === []) {
            $db = db();
            if ($id === 0) {
                $st = $db->prepare(
                    'INSERT INTO mikrotiks (nombre, tipo_equipo, latitud, longitud, ip, api_port, use_ssl, usuario, password, enlace_tipo, notas) VALUES (?,?,?,?,?,?,?,?,?,?,?)'
                );
                $st->bind_param(
                    'sssssiiissss',
                    $nombre,
                    $tipoEquipo,
                    $latBind,
                    $lngBind,
                    $ip,
                    $port,
                    $useSsl,
                    $usuario,
                    $password,
                    $enlaceTipo,
                    $notas
                );
                $st->execute();
                flash_set('success', 'Router creado. Añadí redes IPv4 en el menú lateral si usás clientes con IP fija.');
                redirect('routers.php');
            } else {
                if ($password !== '') {
                    $st = $db->prepare(
                        'UPDATE mikrotiks SET nombre=?, tipo_equipo=?, latitud=?, longitud=?, ip=?, api_port=?, use_ssl=?, usuario=?, password=?, enlace_tipo=?, notas=? WHERE id=?'
                    );
                    $st->bind_param(
                        'sssssiiissssi',
                        $nombre,
                        $tipoEquipo,
                        $latBind,
                        $lngBind,
                        $ip,
                        $port,
                        $useSsl,
                        $usuario,
                        $password,
                        $enlaceTipo,
                        $notas,
                        $id
                    );
                } else {
                    $st = $db->prepare(
                        'UPDATE mikrotiks SET nombre=?, tipo_equipo=?, latitud=?, longitud=?, ip=?, api_port=?, use_ssl=?, usuario=?, enlace_tipo=?, notas=? WHERE id=?'
                    );
                    $st->bind_param(
                        'sssssiiisssi',
                        $nombre,
                        $tipoEquipo,
                        $latBind,
                        $lngBind,
                        $ip,
                        $port,
                        $useSsl,
                        $usuario,
                        $enlaceTipo,
                        $notas,
                        $id
                    );
                }
                $st->execute();
                flash_set('success', 'Router actualizado.');
                redirect('routers.php');
            }
        }
    }
}

// Re-lectura tras error
if ($row && $_SERVER['REQUEST_METHOD'] === 'POST' && ! isset($_POST['delete_router'])) {
    $row = array_merge($row, [
        'nombre' => trim((string) ($_POST['nombre'] ?? $row['nombre'])),
        'tipo_equipo' => mikrotik_tipo_equipo_normalizado(trim((string) ($_POST['tipo_equipo'] ?? ($row['tipo_equipo'] ?? 'mikrotik')))),
        'latitud' => trim((string) ($_POST['latitud'] ?? '')) === '' ? null : ($_POST['latitud'] ?? $row['latitud']),
        'longitud' => trim((string) ($_POST['longitud'] ?? '')) === '' ? null : ($_POST['longitud'] ?? $row['longitud']),
        'ip' => trim((string) ($_POST['ip'] ?? $row['ip'])),
        'api_port' => (int) ($_POST['api_port'] ?? $row['api_port']),
        'use_ssl' => ! empty($_POST['use_ssl']) ? 1 : 0,
        'usuario' => trim((string) ($_POST['usuario'] ?? $row['usuario'])),
        'enlace_tipo' => mikrotik_enlace_normalizado(trim((string) ($_POST['enlace_tipo'] ?? ($row['enlace_tipo'] ?? 'directo')))),
        'notas' => trim((string) ($_POST['notas'] ?? ($row['notas'] ?? ''))),
    ]);
}

$r = $row ?? [];
$tipoEquipoVal = mikrotik_tipo_equipo_normalizado(trim((string) ($r['tipo_equipo'] ?? 'mikrotik')));
$latStr = isset($r['latitud']) && $r['latitud'] !== null && $r['latitud'] !== '' ? (string) $r['latitud'] : '';
$lngStr = isset($r['longitud']) && $r['longitud'] !== null && $r['longitud'] !== '' ? (string) $r['longitud'] : '';
$enlaceTipoVal = mikrotik_enlace_normalizado(trim((string) ($r['enlace_tipo'] ?? 'directo')));
$notasVal = trim((string) ($r['notas'] ?? ''));
$nClientesMk = $id > 0 ? count_clientes_mikrotik($id) : 0;
$nRedesMk = $id > 0 ? count_redes_mikrotik($id) : 0;
$puedeBorrar = $id > 0 && $nClientesMk === 0 && $nRedesMk === 0;

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
        <h1 class="h3 mb-0"><?= $id > 0 ? 'Editar router' : 'Nuevo router' ?></h1>
        <p class="text-secondary small mb-0">Misma lógica que en fichas ISP habituales: <strong>IP/Host</strong> = la que <strong>alcanza este panel</strong> (LAN, túnel u otra); usuario/clave API con permisos para <strong>PPP</strong> y <strong>colas simples</strong>.</p>
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
                <div class="row g-4">
                    <div class="col-lg-6">
                        <h2 class="h6 text-secondary text-uppercase border-bottom border-secondary border-opacity-25 pb-2 mb-3">Datos del equipo</h2>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Nombre del router</label>
                                <input class="form-control" name="nombre" required value="<?= esc((string) ($r['nombre'] ?? '')) ?>"
                                       placeholder="Ej. MK central, POP norte…">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Tipo de equipo</label>
                                <select class="form-select" name="tipo_equipo">
                                    <?php foreach (mikrotik_tipo_equipo_opciones() as $k => $label): ?>
                                        <option value="<?= esc($k) ?>" <?= $tipoEquipoVal === $k ? 'selected' : '' ?>><?= esc($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-2">
                                    <label class="form-label mb-0">Ubicación <span class="text-secondary fw-normal">(opcional)</span></label>
                                    <div class="d-flex align-items-center gap-2">
                                        <button type="button" class="btn btn-outline-light border-secondary btn-sm px-2 py-1" data-nc-geobtn
                                                data-nc-lat="#router_latitud" data-nc-lon="#router_longitud" data-nc-msg="#routerGeoMsg"
                                                title="Rellenar con la posición actual (el navegador pedirá permiso; HTTPS o localhost)">
                                            <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                                        </button>
                                        <span id="routerGeoMsg" class="small text-secondary" role="status"></span>
                                    </div>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label small text-secondary mb-1">Latitud</label>
                                        <input id="router_latitud" class="form-control font-monospace" name="latitud" inputmode="decimal" value="<?= esc($latStr) ?>"
                                               placeholder="18.4321">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-secondary mb-1">Longitud</label>
                                        <input id="router_longitud" class="form-control font-monospace" name="longitud" inputmode="decimal" value="<?= esc($lngStr) ?>"
                                               placeholder="-69.9464">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">IP / Host <span class="text-secondary fw-normal">(visto desde el panel)</span></label>
                                <input class="form-control font-monospace" name="ip" required value="<?= esc((string) ($r['ip'] ?? '')) ?>"
                                       placeholder="Igual que “IP/Host” en otros paneles — ej. túnel o LAN">
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
                                <div class="form-text">Solo documenta el escenario; no cambia cómo conecta la API.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notas <span class="text-secondary fw-normal">(opcional)</span></label>
                                <textarea class="form-control font-monospace small" name="notas" rows="3" maxlength="500" placeholder="Perfil OVPN, VLAN, quien instaló, etc."><?= esc($notasVal) ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <h2 class="h6 text-secondary text-uppercase border-bottom border-secondary border-opacity-25 pb-2 mb-3">API RouterOS</h2>
                        <p class="small text-secondary mb-3">Equivalente al modo <strong>PPP / API</strong> de otros sistemas: usuario con <code>read,write,api</code> (o grupo propio) para que el panel pueda gestionar <strong>PPPoE</strong> (<code>/ppp/secret</code>) y <strong>colas simples</strong>.</p>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Usuario API</label>
                                <input class="form-control" name="usuario" required value="<?= esc((string) ($r['usuario'] ?? '')) ?>" autocomplete="off">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Contraseña API</label>
                                <input class="form-control" type="password" name="password" value="" autocomplete="new-password" placeholder="<?= $id > 0 ? 'Vacío = no cambiar' : 'Obligatoria al crear' ?>">
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="use_ssl" value="1" id="use_ssl" <?= ! empty($r['use_ssl']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="use_ssl">API sobre TLS (puerto típico 8729)</label>
                                </div>
                            </div>
                        </div>
                        <dl class="small text-secondary mt-4 mb-0">
                            <dt class="text-light">Control de velocidad</dt>
                            <dd>NetControl usa <strong>colas simples estáticas</strong> (comentario <code>NetControl-{id}</code>), como “colas simples estáticas” en otros paneles.</dd>
                            <dt class="text-light mt-2">Traffic Flow / IP visitadas</dt>
                            <dd>Opcional en RouterOS; si lo activás, es en el propio MikroTik. El panel <strong>no</strong> consulta esos módulos hoy.</dd>
                        </dl>
                    </div>
                </div>
                <div class="mt-4 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-nc-primary">Guardar</button>
                    <a class="btn btn-outline-secondary" href="routers.php">Cancelar</a>
                </div>
            </form>
            <?php if ($id > 0): ?>
                <hr class="border-secondary border-opacity-25 my-4">
                <h2 class="h6 text-secondary text-uppercase small">Zona de peligro</h2>
                <?php if ($puedeBorrar): ?>
                    <p class="small text-secondary mb-2">Eliminar quita el router del panel (no borra nada en el MikroTik).</p>
                    <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este router del panel?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="delete_router" value="<?= (int) $id ?>">
                        <button type="submit" class="btn btn-outline-danger">Eliminar router</button>
                    </form>
                <?php else: ?>
                    <?php
                    $bloqueos = [];
                    if ($nClientesMk > 0) {
                        $bloqueos[] = (int) $nClientesMk . ' cliente(s) asignados a este router';
                    }
                    if ($nRedesMk > 0) {
                        $bloqueos[] = (int) $nRedesMk . ' red(es) IPv4 enlazadas';
                    }
                    ?>
                    <p class="small text-warning mb-0">
                        No se puede eliminar: <strong><?= esc(implode(' y ', $bloqueos)) ?></strong>.
                        Reasigná o borrá esos datos desde <a href="routers.php" class="link-light">Routers</a>, <a href="index.php" class="link-light">Clientes</a> o <a href="redes.php" class="link-light">Redes IPv4</a>.
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-5">
        <?php require __DIR__ . '/partials/mikrotik_ayuda_enlaces.php'; ?>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php';
