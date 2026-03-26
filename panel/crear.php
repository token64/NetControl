<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_auth();
require_once __DIR__ . '/funciones.php';

$pageTitle = 'Nuevo cliente — NetControl';
$errors = [];
$routers = list_mikrotiks();
$plans = plan_limits();

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

        $lat = $latRaw === '' ? null : $latRaw;
        $lon = $lonRaw === '' ? null : $lonRaw;

        if ($nombre === '') {
            $errors[] = 'El nombre es obligatorio.';
        }
        if ($mikrotikId <= 0) {
            $errors[] = 'Selecciona un MikroTik.';
        }
        if (! isset($plans[$plan])) {
            $errors[] = 'Plan no válido.';
        }
        if ($tipo !== 'pppoe' && $tipo !== 'ip') {
            $errors[] = 'Tipo de conexión no válido.';
        }
        if ($tipo === 'pppoe') {
            if ($user === '' || $pass === '') {
                $errors[] = 'PPPoE requiere usuario y contraseña.';
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

            $limits = $plans[$plan];
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
                        $pool = IP_POOL_DEFAULT;
                        $ipAssign = ip_libre($mikrotikId, $pool);
                        if ($ipAssign === null) {
                            throw new RuntimeException('No hay IPs libres en el pool configurado.');
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
    <div class="alert alert-warning nc-flash">No hay routers en la base de datos. Importa <code>sql/schema.sql</code> y configura la tabla <code>mikrotiks</code>.</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="nc-card p-4">
            <form method="post" novalidate>
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nombre</label>
                        <input class="form-control" name="nombre" required value="<?= esc((string) ($_POST['nombre'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Cédula / ID</label>
                        <input class="form-control" name="cedula" value="<?= esc((string) ($_POST['cedula'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Teléfono</label>
                        <input class="form-control" name="telefono" value="<?= esc((string) ($_POST['telefono'] ?? '')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Dirección</label>
                        <input class="form-control" name="direccion" value="<?= esc((string) ($_POST['direccion'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Latitud</label>
                        <input class="form-control" id="lat" name="lat" value="<?= esc((string) ($_POST['lat'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Longitud</label>
                        <input class="form-control" id="lon" name="lon" value="<?= esc((string) ($_POST['lon'] ?? '')) ?>">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-outline-secondary btn-sm" type="button" id="btnGeo">Usar mi ubicación</button>
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
                        <select class="form-select" name="mikrotik" required>
                            <?php foreach ($routers as $r): ?>
                                <option value="<?= (int) $r['id'] ?>" <?= (string) (int) ($_POST['mikrotik'] ?? 0) === (string) (int) $r['id'] ? 'selected' : '' ?>>
                                    <?= esc((string) $r['nombre']) ?> (<?= esc((string) $r['ip']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
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
                            <?php foreach ($plans as $k => $label): ?>
                                <option value="<?= esc($k) ?>" <?= ($_POST['plan'] ?? 'plan_10m') === $k ? 'selected' : '' ?>><?= esc($k . ' — ' . $label) ?></option>
                            <?php endforeach; ?>
                        </select>
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
  const ppp = document.querySelectorAll('.pppoe-only');
  function sync() {
    const on = tipo.value === 'pppoe';
    ppp.forEach(function (el) { el.classList.toggle('d-none', !on); });
  }
  tipo.addEventListener('change', sync);
  sync();
  document.getElementById('btnGeo').addEventListener('click', function () {
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(function (pos) {
      document.getElementById('lat').value = pos.coords.latitude;
      document.getElementById('lon').value = pos.coords.longitude;
    });
  });
})();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
