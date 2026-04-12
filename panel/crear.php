<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_auth();
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/includes/cedula_lookup.php';

$ncCedulaLookup = nc_cedula_lookup_enabled();
$navSection = 'clientes-crear';
$pageTitle = 'Nuevo cliente — NetControl';
$errors = [];
$routers = list_mikrotiks();
$planesRows = list_planes_select();
if ($planesRows === []) {
    foreach (nc_plan_limits() as $slug => $lim) {
        $planesRows[] = ['slug' => $slug, 'nombre' => $slug, 'max_limit' => $lim];
    }
}
$redesActivas = list_redes_activas();
$planLimits = nc_plan_limits();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! csrf_verify()) {
        $errors[] = 'Token de seguridad inválido. Recarga la página.';
    } else {
        $nombre     = trim((string) ($_POST['nombre'] ?? ''));
        $cedula     = trim((string) ($_POST['cedula'] ?? ''));
        $telefono   = trim((string) ($_POST['telefono'] ?? ''));
        $direccion  = trim((string) ($_POST['direccion'] ?? ''));
        $latRaw     = trim((string) ($_POST['lat'] ?? ''));
        $lonRaw     = trim((string) ($_POST['lon'] ?? ''));
        $tipo       = (string) ($_POST['tipo'] ?? 'pppoe');
        $mikrotikId = (int) ($_POST['mikrotik'] ?? 0);
        $plan       = (string) ($_POST['plan'] ?? '');
        $user       = trim((string) ($_POST['user'] ?? ''));
        $pass       = (string) ($_POST['pass'] ?? '');
        $redId      = (int) ($_POST['red_id'] ?? 0);

        $lat = $latRaw === '' ? null : $latRaw;
        $lon = $lonRaw === '' ? null : $lonRaw;

        if ($nombre === '') {
            $errors[] = 'El nombre es obligatorio.';
        }
        if ($mikrotikId <= 0) {
            $errors[] = 'Selecciona un MikroTik.';
        }
        if (! isset($planLimits[$plan])) {
            $errors[] = 'Plan no válido o inactivo.';
        }
        if ($tipo !== 'pppoe' && $tipo !== 'ip') {
            $errors[] = 'Tipo de conexión no válido.';
        }
        if ($tipo === 'pppoe') {
            if ($user === '' || $pass === '') {
                $errors[] = 'PPPoE requiere usuario y contraseña.';
            }
        }

        $pool = IP_POOL_DEFAULT;
        if ($tipo === 'ip') {
            if ($redId > 0) {
                $redRow = red_by_id($redId);
                if (! $redRow || empty($redRow['activo'])) {
                    $errors[] = 'La red IPv4 elegida no existe o está inactiva.';
                } elseif ((int) $redRow['mikrotik_id'] !== $mikrotikId) {
                    $errors[] = 'La red debe pertenecer al mismo router seleccionado.';
                } else {
                    $pool = (string) $redRow['rango'];
                }
            }
        }

        if ($errors === []) {
            $ipFija = null;
            $usuarioDb = null;
            $passwordDb = null;

            if ($tipo === 'pppoe') {
                $usuarioDb = $user;
                $passwordDb = $pass;
            }

            $limits = $planLimits[$plan];
            $fechaPago = (new DateTimeImmutable('today'))->modify('+30 days')->format('Y-m-d');

            $db = db();
            $db->begin_transaction();
            try {
                $st = $db->prepare(
                    'INSERT INTO clientes (nombre,cedula,telefono,direccion,latitud,longitud,usuario,password,plan,tipo_conexion,ip_fija,mikrotik_id,estado,fecha_pago)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?, "activo", ?)'
                );
                $st->bind_param(
                    'sssssssssssis',
                    $nombre,
                    $cedula,
                    $telefono,
                    $direccion,
                    $lat,
                    $lon,
                    $usuarioDb,
                    $passwordDb,
                    $plan,
                    $tipo,
                    $ipFija,
                    $mikrotikId,
                    $fechaPago
                );
                $st->execute();
                $newId = (int) $db->insert_id;

                $API = mikrotik_connect($mikrotikId);
                try {
                    if ($tipo === 'pppoe') {
                        $API->comm('/ppp/secret/add', [
                            'name'     => $user,
                            'password' => $pass,
                            'service'  => 'pppoe',
                            'profile'  => $plan,
                            'comment'  => 'NetControl-' . $newId,
                        ]);
                    } else {
                        $ipAssign = ip_libre($mikrotikId, $pool);
                        if ($ipAssign === null) {
                            throw new RuntimeException('No hay IPs libres en el rango seleccionado.');
                        }
                        $API->comm('/queue/simple/add', [
                            'name'      => 'nc-' . $newId,
                            'target'    => $ipAssign . '/32',
                            'max-limit' => $limits,
                            'comment'   => netcontrol_queue_comment($newId),
                        ]);
                        $u = $db->prepare('UPDATE clientes SET ip_fija = ? WHERE id = ?');
                        $u->bind_param('si', $ipAssign, $newId);
                        $u->execute();
                    }
                } finally {
                    $API->disconnect();
                }

                $db->commit();
                flash_set('success', 'Cliente creado correctamente.');
                redirect('index.php');
            } catch (Throwable $e) {
                $db->rollback();
                $errors[] = $e->getMessage();
            }
        }
    }
}

$defaultPlan = $planesRows[0]['slug'] ?? 'plan_10m';

require __DIR__ . '/partials/header.php';
?>

<h1 class="h3 mb-4">Nuevo cliente</h1>

<?php if ($errors !== []): ?>
    <div class="alert alert-danger nc-flash">
        <ul class="mb-0">
            <?php foreach ($errors as $e): ?>
                <li><?= esc($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($routers === []): ?>
    <div class="alert alert-warning nc-flash">No hay routers en la base de datos. <a href="router_edit.php">Dar de alta un router</a> o importá <code>sql/schema.sql</code>.</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="nc-card p-4">
            <form method="post" novalidate>
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label d-flex align-items-center gap-2 flex-wrap">
                            Nombre
                            <?php if ($ncCedulaLookup): ?>
                                <span id="ncNombreFuente" class="badge rounded-pill text-bg-light border small fw-normal" style="display:none;"></span>
                            <?php endif; ?>
                        </label>
                        <input class="form-control" name="nombre"<?= $ncCedulaLookup ? ' id="nc_nombre"' : '' ?> required value="<?= esc((string) ($_POST['nombre'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Cédula / ID</label>
                        <?php if ($ncCedulaLookup): ?>
                            <div class="input-group" data-nc-cedula-root data-endpoint="api_cedula_nombre.php" data-cedula="#nc_cedula" data-nombre="#nc_nombre" data-msg="#ncCedulaMsg" data-fuente="#ncNombreFuente">
                                <input class="form-control" name="cedula" id="nc_cedula" inputmode="numeric" autocomplete="off" placeholder="11 dígitos" value="<?= esc((string) ($_POST['cedula'] ?? '')) ?>">
                                <button type="button" class="btn btn-outline-secondary" data-nc-cedula-btn title="Consultar nombre según registro configurado">Obtener datos</button>
                            </div>
                            <span id="ncCedulaMsg" class="small text-secondary d-block mt-1" role="status"></span>
                        <?php else: ?>
                            <input class="form-control" name="cedula" value="<?= esc((string) ($_POST['cedula'] ?? '')) ?>">
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Teléfono</label>
                        <input class="form-control" name="telefono" value="<?= esc((string) ($_POST['telefono'] ?? '')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Dirección</label>
                        <input class="form-control" name="direccion" value="<?= esc((string) ($_POST['direccion'] ?? '')) ?>">
                    </div>
                    <div class="col-12">
                        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-2">
                            <label class="form-label mb-0">Ubicación</label>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <button class="btn btn-outline-light border-secondary btn-sm px-2 py-1" type="button" id="btnGeo" data-nc-geobtn
                                        data-nc-lat="#lat" data-nc-lon="#lon" data-nc-msg="#crearGeoMsg"
                                        title="Rellenar con la posición actual">
                                    <i class="bi bi-geo-alt-fill me-1" aria-hidden="true"></i>Usar mi ubicación
                                </button>
                                <span id="crearGeoMsg" class="small text-secondary" role="status"></span>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small text-secondary mb-1">Latitud</label>
                                <input class="form-control" id="lat" name="lat" value="<?= esc((string) ($_POST['lat'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-secondary mb-1">Longitud</label>
                                <input class="form-control" id="lon" name="lon" value="<?= esc((string) ($_POST['lon'] ?? '')) ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tipo</label>
                        <select class="form-select" name="tipo" id="tipo">
                            <option value="pppoe" <?= ($_POST['tipo'] ?? '') === 'ip' ? '' : 'selected' ?>>PPPoE</option>
                            <option value="ip" <?= ($_POST['tipo'] ?? '') === 'ip' ? 'selected' : '' ?>>IP fija (cola simple)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">MikroTik</label>
                        <select class="form-select" name="mikrotik" id="mikrotik" required>
                            <?php foreach ($routers as $r): ?>
                                <option value="<?= (int) $r['id'] ?>" <?= (string) (int) ($_POST['mikrotik'] ?? 0) === (string) (int) $r['id'] ? 'selected' : '' ?>>
                                    <?= esc((string) $r['nombre']) ?> (<?= esc((string) $r['ip']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 ip-only d-none">
                        <label class="form-label">Red IPv4 (pool)</label>
                        <select class="form-select" name="red_id" id="red_id">
                            <option value="0" data-mikrotik="">Predeterminado (<?= esc(IP_POOL_DEFAULT) ?>)</option>
                            <?php foreach ($redesActivas as $net): ?>
                                <option value="<?= (int) $net['id'] ?>" data-mikrotik="<?= (int) $net['mikrotik_id'] ?>">
                                    <?= esc((string) $net['nombre']) ?> — <?= esc((string) $net['rango']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="small text-secondary mb-0 mt-1">Gestioná rangos en <a href="redes.php">Redes IPv4</a>.</p>
                    </div>
                    <div class="col-md-6 pppoe-only">
                        <label class="form-label">Usuario PPPoE</label>
                        <input class="form-control" name="user" value="<?= esc((string) ($_POST['user'] ?? '')) ?>" autocomplete="off">
                    </div>
                    <div class="col-md-6 pppoe-only">
                        <label class="form-label">Contraseña PPPoE</label>
                        <input class="form-control" type="password" name="pass" value="" autocomplete="new-password">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Plan (perfil / cola)</label>
                        <select class="form-select" name="plan">
                            <?php foreach ($planesRows as $p): ?>
                                <?php
                                $slug = (string) $p['slug'];
                                $postPlan = (string) ($_POST['plan'] ?? $defaultPlan);
                                ?>
                                <option value="<?= esc($slug) ?>" <?= $postPlan === $slug ? 'selected' : '' ?>>
                                    <?= esc((string) $p['nombre']) ?> (<?= esc($slug) ?>) — <?= esc((string) $p['max_limit']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="small text-secondary mb-0 mt-1">Editá catálogo en <a href="planes.php">Planes</a>.</p>
                    </div>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-nc-primary">Guardar cliente</button>
                    <a class="btn btn-outline-secondary" href="index.php">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
  const tipo = document.getElementById('tipo');
  const mik = document.getElementById('mikrotik');
  const red = document.getElementById('red_id');
  const ppp = document.querySelectorAll('.pppoe-only');
  const ipOnly = document.querySelectorAll('.ip-only');

  function syncTipo() {
    const isPpp = tipo.value === 'pppoe';
    ppp.forEach(function (el) { el.classList.toggle('d-none', !isPpp); });
    ipOnly.forEach(function (el) { el.classList.toggle('d-none', isPpp); });
    filterRedes();
  }

  function filterRedes() {
    if (!red || !mik) return;
    const mid = mik.value;
    let firstVisible = null;
    Array.prototype.forEach.call(red.options, function (opt) {
      const om = opt.getAttribute('data-mikrotik');
      if (opt.value === '0') {
        opt.hidden = false;
        firstVisible = opt;
        return;
      }
      const show = om === mid;
      opt.hidden = !show;
      if (show && firstVisible === null) firstVisible = opt;
    });
    if (red.selectedOptions.length && red.selectedOptions[0].hidden) {
      red.value = firstVisible ? firstVisible.value : '0';
    }
  }

  tipo.addEventListener('change', syncTipo);
  mik.addEventListener('change', filterRedes);
  syncTipo();
})();
</script>
<?php if ($ncCedulaLookup): ?>
    <?php
    $__ncCed = __DIR__ . '/assets/nc-cedula-nombre.js';
    $__ncCedV = is_readable($__ncCed) ? (string) filemtime($__ncCed) : '1';
    ?>
    <script src="assets/nc-cedula-nombre.js?v=<?= esc($__ncCedV) ?>"></script>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
