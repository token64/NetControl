<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_auth();
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/includes/cedula_lookup.php';

$ncCedulaLookup = nc_cedula_lookup_enabled();
$navSection = 'clientes';
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    flash_set('error', 'Cliente no especificado.');
    redirect('index.php');
}

$old = fetch_client_by_id($id);
if (! $old) {
    flash_set('error', 'Cliente no encontrado.');
    redirect('index.php');
}

$routers = list_mikrotiks();
$planesRows = list_planes_select();
if ($planesRows === []) {
    foreach (nc_plan_limits() as $slug => $lim) {
        $planesRows[] = ['slug' => $slug, 'nombre' => $slug, 'max_limit' => $lim];
    }
}
$slugsOpts = array_map(static fn ($r) => (string) $r['slug'], $planesRows);
$oldPlan = (string) $old['plan'];
if (! in_array($oldPlan, $slugsOpts, true)) {
    $planesRows[] = [
        'slug' => $oldPlan,
        'nombre' => '(Catálogo) ' . $oldPlan,
        'max_limit' => nc_plan_limits()[$oldPlan] ?? '',
    ];
}
$planLimits = nc_plan_limits();
$pageTitle = 'Editar cliente — NetControl';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! csrf_verify()) {
        $errors[] = 'Token de seguridad inválido.';
    } else {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $cedula = trim((string) ($_POST['cedula'] ?? ''));
        $telefono = trim((string) ($_POST['telefono'] ?? ''));
        $direccion = trim((string) ($_POST['direccion'] ?? ''));
        $latRaw = trim((string) ($_POST['lat'] ?? ''));
        $lonRaw = trim((string) ($_POST['lon'] ?? ''));
        $mikrotikId = (int) ($_POST['mikrotik'] ?? 0);
        $plan = (string) ($_POST['plan'] ?? '');
        $user = trim((string) ($_POST['user'] ?? ''));
        $passPost = (string) ($_POST['pass'] ?? '');
        $ipFija = trim((string) ($_POST['ip_fija'] ?? ''));

        $lat = $latRaw === '' ? null : $latRaw;
        $lon = $lonRaw === '' ? null : $lonRaw;

        if ($nombre === '') {
            $errors[] = 'El nombre es obligatorio.';
        }
        if ($mikrotikId <= 0) {
            $errors[] = 'Seleccioná un MikroTik.';
        }
        $planOk = isset($planLimits[$plan]) || plan_max_limit_by_slug($plan) !== null;
        if (! $planOk) {
            $errors[] = 'Plan no válido. Crear el slug en Planes o elige otro.';
        }

        $tipo = (string) $old['tipo_conexion'];
        if ($tipo === 'pppoe') {
            if ($user === '') {
                $errors[] = 'PPPoE requiere usuario.';
            }
            if ($passPost === '' && trim((string) ($old['password'] ?? '')) === '') {
                $errors[] = 'No hay contraseña guardada; ingresá una contraseña PPPoE.';
            }
        } else {
            if ($ipFija === '' || ! filter_var($ipFija, FILTER_VALIDATE_IP)) {
                $errors[] = 'IP fija no válida.';
            }
        }

        $plainPassForMk = $passPost !== '' ? $passPost : null;

        $new = $old;
        $new['nombre'] = $nombre;
        $new['cedula'] = $cedula === '' ? null : $cedula;
        $new['telefono'] = $telefono === '' ? null : $telefono;
        $new['direccion'] = $direccion === '' ? null : $direccion;
        $new['latitud'] = $lat;
        $new['longitud'] = $lon;
        $new['plan'] = $plan;
        $new['mikrotik_id'] = $mikrotikId;
        if ($tipo === 'pppoe') {
            $new['usuario'] = $user;
            if ($passPost !== '') {
                $new['password'] = $passPost;
            }
            $new['ip_fija'] = null;
        } else {
            $new['ip_fija'] = $ipFija;
        }

        if ($errors === []) {
            $db = db();
            $db->begin_transaction();
            try {
                if ($tipo === 'pppoe' && $passPost !== '') {
                    $st = $db->prepare(
                        'UPDATE clientes SET nombre=?, cedula=?, telefono=?, direccion=?, latitud=?, longitud=?, usuario=?, password=?, plan=?, mikrotik_id=? WHERE id=?'
                    );
                    $st->bind_param(
                        'sssssssssii',
                        $nombre,
                        $new['cedula'],
                        $new['telefono'],
                        $new['direccion'],
                        $lat,
                        $lon,
                        $user,
                        $passPost,
                        $plan,
                        $mikrotikId,
                        $id
                    );
                } elseif ($tipo === 'pppoe') {
                    $st = $db->prepare(
                        'UPDATE clientes SET nombre=?, cedula=?, telefono=?, direccion=?, latitud=?, longitud=?, usuario=?, plan=?, mikrotik_id=? WHERE id=?'
                    );
                    $st->bind_param(
                        'ssssssssii',
                        $nombre,
                        $new['cedula'],
                        $new['telefono'],
                        $new['direccion'],
                        $lat,
                        $lon,
                        $user,
                        $plan,
                        $mikrotikId,
                        $id
                    );
                } else {
                    $st = $db->prepare(
                        'UPDATE clientes SET nombre=?, cedula=?, telefono=?, direccion=?, latitud=?, longitud=?, plan=?, mikrotik_id=?, ip_fija=? WHERE id=?'
                    );
                    $st->bind_param(
                        'sssssssisi',
                        $nombre,
                        $new['cedula'],
                        $new['telefono'],
                        $new['direccion'],
                        $lat,
                        $lon,
                        $plan,
                        $mikrotikId,
                        $ipFija,
                        $id
                    );
                }
                $st->execute();

                cliente_reconcile_mikrotik_after_edit($old, $new, $plainPassForMk);

                $db->commit();
                flash_set('success', 'Cliente actualizado y sincronizado con MikroTik.');
                redirect('index.php');
            } catch (Throwable $e) {
                $db->rollback();
                $errors[] = $e->getMessage();
            }
        }
    }
}

require __DIR__ . '/partials/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h1 class="h3 mb-0">Editar cliente</h1>
        <p class="text-secondary small mb-0">ID <?= (int) $id ?> · <?= esc(strtoupper((string) $old['tipo_conexion'])) ?> · cambia plan, router, PPPoE o IP (no convierte PPPoE ↔ IP)</p>
    </div>
    <a class="btn btn-outline-secondary" href="index.php">Volver al listado</a>
</div>

<?php if ($errors !== []): ?>
    <div class="alert alert-danger nc-flash">
        <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php if (! $ncCedulaLookup): ?>
    <div class="alert alert-light border py-2 small mb-3" role="note">
        <strong>Consulta por cédula:</strong> desactivada en <code>config.php</code>. Activá <code>CEDULA_LOOKUP_ENABLED</code> y la plantilla de URL (ver <code>config.example.php</code>) para el botón «Obtener datos».
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="nc-card p-4">
            <form method="post" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $id ?>">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label d-flex align-items-center gap-2 flex-wrap">
                            Nombre
                            <?php if ($ncCedulaLookup): ?>
                                <span id="ncNombreFuente" class="badge rounded-pill text-bg-light border small fw-normal" style="display:none;"></span>
                            <?php endif; ?>
                        </label>
                        <input class="form-control" name="nombre"<?= $ncCedulaLookup ? ' id="nc_nombre"' : '' ?> required value="<?= esc((string) ($_POST['nombre'] ?? $old['nombre'])) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Cédula / ID</label>
                        <?php if ($ncCedulaLookup): ?>
                            <div class="input-group" data-nc-cedula-root data-endpoint="api_cedula_nombre.php" data-cedula="#nc_cedula" data-nombre="#nc_nombre" data-msg="#ncCedulaMsg" data-fuente="#ncNombreFuente">
                                <input class="form-control" name="cedula" id="nc_cedula" inputmode="numeric" autocomplete="off" placeholder="11 dígitos" value="<?= esc((string) ($_POST['cedula'] ?? ($old['cedula'] ?? ''))) ?>">
                                <button type="button" class="btn btn-outline-secondary" data-nc-cedula-btn title="Consultar nombre según registro configurado">Obtener datos</button>
                            </div>
                            <span id="ncCedulaMsg" class="small text-secondary d-block mt-1" role="status"></span>
                        <?php else: ?>
                            <input class="form-control" name="cedula" value="<?= esc((string) ($_POST['cedula'] ?? ($old['cedula'] ?? ''))) ?>">
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Teléfono</label>
                        <input class="form-control" name="telefono" value="<?= esc((string) ($_POST['telefono'] ?? ($old['telefono'] ?? ''))) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Dirección</label>
                        <input class="form-control" name="direccion" value="<?= esc((string) (trim((string) ($_POST['direccion'] ?? '')) !== '' ? (string) $_POST['direccion'] : (string) ($old['direccion'] ?? ''))) ?>">
                    </div>
                    <div class="col-12">
                        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-2">
                            <label class="form-label mb-0">Ubicación</label>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm px-2 py-1" data-nc-geobtn
                                        data-nc-lat="#cliente_lat" data-nc-lon="#cliente_lon" data-nc-msg="#clienteGeoMsg"
                                        title="Rellenar con la posición actual (permiso del navegador)">
                                    <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                                </button>
                                <span id="clienteGeoMsg" class="small text-secondary" role="status"></span>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small text-secondary mb-1">Latitud</label>
                                <input id="cliente_lat" class="form-control" name="lat" value="<?= esc((string) ($_POST['lat'] ?? (string) ($old['latitud'] ?? ''))) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-secondary mb-1">Longitud</label>
                                <input id="cliente_lon" class="form-control" name="lon" value="<?= esc((string) ($_POST['lon'] ?? (string) ($old['longitud'] ?? ''))) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Tipo</label>
                        <input class="form-control" value="<?= esc(strtoupper((string) $old['tipo_conexion'])) ?>" disabled>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">MikroTik</label>
                        <select class="form-select" name="mikrotik" required>
                            <?php foreach ($routers as $r): ?>
                                <option value="<?= (int) $r['id'] ?>" <?= (int) ($_POST['mikrotik'] ?? $old['mikrotik_id']) === (int) $r['id'] ? 'selected' : '' ?>>
                                    <?= esc((string) $r['nombre']) ?> (<?= esc((string) $r['ip']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($old['tipo_conexion'] === 'pppoe'): ?>
                        <div class="col-md-6">
                            <label class="form-label">Usuario PPPoE</label>
                            <input class="form-control" name="user" required value="<?= esc((string) ($_POST['user'] ?? ($old['usuario'] ?? ''))) ?>" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contraseña PPPoE</label>
                            <input class="form-control" type="password" name="pass" value="" autocomplete="new-password" placeholder="Vacío = no cambiar">
                        </div>
                    <?php else: ?>
                        <div class="col-md-6">
                            <label class="form-label">IP fija (cola simple)</label>
                            <input class="form-control font-monospace" name="ip_fija" required value="<?= esc((string) ($_POST['ip_fija'] ?? ($old['ip_fija'] ?? ''))) ?>">
                        </div>
                    <?php endif; ?>

                    <div class="col-md-6">
                        <label class="form-label">Plan</label>
                        <select class="form-select" name="plan">
                            <?php foreach ($planesRows as $p): ?>
                                <?php $slug = (string) $p['slug']; ?>
                                <option value="<?= esc($slug) ?>" <?= (string) ($_POST['plan'] ?? $old['plan']) === $slug ? 'selected' : '' ?>>
                                    <?= esc((string) $p['nombre']) ?> (<?= esc($slug) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mt-4 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-nc-primary">Guardar y sincronizar</button>
                    <a class="btn btn-outline-secondary" href="promesa.php?id=<?= (int) $id ?>">Promesa de pago</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php if ($ncCedulaLookup): ?>
    <?php
    $__ncCed = __DIR__ . '/assets/nc-cedula-nombre.js';
    $__ncCedV = is_readable($__ncCed) ? (string) filemtime($__ncCed) : '1';
    ?>
    <script src="assets/nc-cedula-nombre.js?v=<?= esc($__ncCedV) ?>"></script>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php';
