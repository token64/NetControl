<?php
declare(strict_types=1);

require_once __DIR__ . '/mikrotik.php';

function netcontrol_queue_comment(int $clientId): string
{
    return 'NetControl-' . $clientId;
}

function mikrotik_connect(int $mikrotikId, int $timeout = 6, int $attempts = 3, int $delay = 1): RouterosAPI
{
    $st = db()->prepare('SELECT ip, api_port, COALESCE(use_ssl, 0) AS use_ssl, usuario, password FROM mikrotiks WHERE id = ? LIMIT 1');
    $st->bind_param('i', $mikrotikId);
    $st->execute();
    $m = $st->get_result()->fetch_assoc();
    if (!$m) {
        throw new RuntimeException('MikroTik no encontrado.');
    }

    $API = new RouterosAPI();
    $API->port = (int) ($m['api_port'] ?: 8728);
    $API->ssl = (bool) $m['use_ssl'];
    $API->timeout = $timeout;
    $API->attempts = $attempts;
    $API->delay = $delay;

    if (!$API->connect($m['ip'], $m['usuario'], $m['password'])) {
        $prt = (int) ($m['api_port'] ?: 8728);
        throw new RuntimeException('No se pudo conectar a la API del MikroTik (' . $m['ip'] . ':' . $prt . ').');
    }

    return $API;
}

/**
 * @return list<string>
 */
function ip_libre_addresses(int $mikrotikId): array
{
    $API = mikrotik_connect($mikrotikId);
    try {
        $rows = $API->comm('/ip/address/print');
    } finally {
        $API->disconnect();
    }

    $ips = [];
    foreach ((array) $rows as $row) {
        if (is_array($row) && isset($row['address'])) {
            $ips[] = explode('/', (string) $row['address'])[0];
        }
    }
    return $ips;
}

function ip_libre(int $mikrotikId, string $rango): ?string
{
    $usadas = ip_libre_addresses($mikrotikId);
    $parts = explode('-', $rango);
    if (count($parts) !== 2) {
        return null;
    }
    $start = ip2long(trim($parts[0]));
    $end   = ip2long(trim($parts[1]));
    if ($start === false || $end === false || $start > $end) {
        return null;
    }
    for ($i = $start; $i <= $end; $i++) {
        $ip = long2ip($i);
        if (! in_array($ip, $usadas, true)) {
            return $ip;
        }
    }
    return null;
}

/**
 * @return array<string, mixed>|null primera fila
 */
function mikrotik_first_row(array $response): ?array
{
    if ($response === []) {
        return null;
    }
    $first = $response[0] ?? null;
    return is_array($first) ? $first : null;
}

function cliente_suspend_mikrotik(array $cliente): void
{
    $API = mikrotik_connect((int) $cliente['mikrotik_id']);
    try {
        if (($cliente['tipo_conexion'] ?? '') === 'pppoe') {
            $user = (string) ($cliente['usuario'] ?? '');
            if ($user === '') {
                throw new RuntimeException('Cliente PPPoE sin usuario.');
            }
            $rows = $API->comm('/ppp/secret/print', ['?name' => $user]);
            $row = mikrotik_first_row($rows);
            if ($row && isset($row['.id'])) {
                $API->comm('/ppp/secret/set', ['.id' => $row['.id'], 'disabled' => 'yes']);
            }
            return;
        }

        $comment = netcontrol_queue_comment((int) $cliente['id']);
        $rows = $API->comm('/queue/simple/print', ['?comment' => $comment]);
        $row = mikrotik_first_row($rows);
        if ($row && isset($row['.id'])) {
            $API->comm('/queue/simple/set', ['.id' => $row['.id'], 'disabled' => 'yes']);
        }
    } finally {
        $API->disconnect();
    }
}

function cliente_activate_mikrotik(array $cliente): void
{
    $API = mikrotik_connect((int) $cliente['mikrotik_id']);
    try {
        if (($cliente['tipo_conexion'] ?? '') === 'pppoe') {
            $user = (string) ($cliente['usuario'] ?? '');
            if ($user === '') {
                throw new RuntimeException('Cliente PPPoE sin usuario.');
            }
            $rows = $API->comm('/ppp/secret/print', ['?name' => $user]);
            $row = mikrotik_first_row($rows);
            if ($row && isset($row['.id'])) {
                $API->comm('/ppp/secret/set', ['.id' => $row['.id'], 'disabled' => 'no']);
            }
            return;
        }

        $comment = netcontrol_queue_comment((int) $cliente['id']);
        $rows = $API->comm('/queue/simple/print', ['?comment' => $comment]);
        $row = mikrotik_first_row($rows);
        if ($row && isset($row['.id'])) {
            $API->comm('/queue/simple/set', ['.id' => $row['.id'], 'disabled' => 'no']);
        }
    } finally {
        $API->disconnect();
    }
}

function cliente_delete_mikrotik(array $cliente): void
{
    $API = mikrotik_connect((int) $cliente['mikrotik_id']);
    try {
        if (($cliente['tipo_conexion'] ?? '') === 'pppoe') {
            $user = (string) ($cliente['usuario'] ?? '');
            if ($user === '') {
                return;
            }
            $rows = $API->comm('/ppp/secret/print', ['?name' => $user]);
            $row = mikrotik_first_row($rows);
            if ($row && isset($row['.id'])) {
                $API->comm('/ppp/secret/remove', ['.id' => $row['.id']]);
            }
            return;
        }

        $comment = netcontrol_queue_comment((int) $cliente['id']);
        $rows = $API->comm('/queue/simple/print', ['?comment' => $comment]);
        $row = mikrotik_first_row($rows);
        if ($row && isset($row['.id'])) {
            $API->comm('/queue/simple/remove', ['.id' => $row['.id']]);
        }
    } finally {
        $API->disconnect();
    }
}

function mikrotik_ppp_secret_id(RouterosAPI $API, string $user): ?string
{
    if ($user === '') {
        return null;
    }
    $rows = $API->comm('/ppp/secret/print', ['?name' => $user]);
    $row = mikrotik_first_row($rows);
    if ($row && isset($row['.id'])) {
        return (string) $row['.id'];
    }
    return null;
}

function mikrotik_simple_queue_id_by_comment(RouterosAPI $API, string $comment): ?string
{
    $rows = $API->comm('/queue/simple/print', ['?comment' => $comment]);
    $row = mikrotik_first_row($rows);
    if ($row && isset($row['.id'])) {
        return (string) $row['.id'];
    }
    return null;
}

/**
 * Tras editar fila en BD: aplica cambios en MikroTik (plan, router, usuario PPPoE, IP, contraseña).
 * No cambia tipo_conexion (pppoe/ip); debe ser el mismo que $old.
 *
 * @param array<string, mixed> $old Fila anterior (clientes.*)
 * @param array<string, mixed> $new Fila nueva misma estructura
 * @param ?string $plainPassword Si no null y no vacío, contraseña PPPoE a aplicar; si null, se usa (string)$new['password']
 */
function cliente_reconcile_mikrotik_after_edit(array $old, array $new, ?string $plainPassword): void
{
    $id = (int) $new['id'];
    $tipo = (string) ($new['tipo_conexion'] ?? '');
    if ($tipo !== 'pppoe' && $tipo !== 'ip') {
        throw new RuntimeException('Tipo de conexión no válido.');
    }
    if ((string) ($old['tipo_conexion'] ?? '') !== $tipo) {
        throw new RuntimeException('Cambiar entre PPPoE e IP fija no está soportado; elimine y cree de nuevo.');
    }

    $oldMk = (int) $old['mikrotik_id'];
    $newMk = (int) $new['mikrotik_id'];
    $estado = (string) ($new['estado'] ?? 'activo');
    $disabled = $estado === 'suspendido' ? 'yes' : 'no';

    $planSlug = (string) $new['plan'];
    $planLimits = nc_plan_limits();
    $maxLimit = $planLimits[$planSlug] ?? plan_max_limit_by_slug($planSlug);
    if ($maxLimit === null || $maxLimit === '') {
        throw new RuntimeException('Plan no válido para cola/perfil: ' . $planSlug);
    }

    if ($tipo === 'pppoe') {
        $oldUser = (string) ($old['usuario'] ?? '');
        $newUser = (string) ($new['usuario'] ?? '');
        if ($newUser === '') {
            throw new RuntimeException('PPPoE requiere usuario.');
        }
        $pass = $plainPassword !== null && $plainPassword !== ''
            ? $plainPassword
            : (string) ($new['password'] ?? '');
        if ($pass === '') {
            throw new RuntimeException('Definí contraseña PPPoE o dejá la actual en BD.');
        }

        $movedOrRenamed = ($oldMk !== $newMk) || ($oldUser !== $newUser);

        if ($movedOrRenamed) {
            if ($oldUser !== '') {
                $APIo = mikrotik_connect($oldMk);
                try {
                    $sid = mikrotik_ppp_secret_id($APIo, $oldUser);
                    if ($sid !== null) {
                        $APIo->comm('/ppp/secret/remove', ['.id' => $sid]);
                    }
                } finally {
                    $APIo->disconnect();
                }
            }
            $APIn = mikrotik_connect($newMk);
            try {
                $APIn->comm('/ppp/secret/add', [
                    'name'     => $newUser,
                    'password' => $pass,
                    'service'  => 'pppoe',
                    'profile'  => $planSlug,
                    'comment'  => 'NetControl-' . $id,
                    'disabled' => $disabled,
                ]);
            } finally {
                $APIn->disconnect();
            }
            return;
        }

        $API = mikrotik_connect($newMk);
        try {
            $sid = mikrotik_ppp_secret_id($API, $newUser);
            if ($sid === null) {
                $API->comm('/ppp/secret/add', [
                    'name'     => $newUser,
                    'password' => $pass,
                    'service'  => 'pppoe',
                    'profile'  => $planSlug,
                    'comment'  => 'NetControl-' . $id,
                    'disabled' => $disabled,
                ]);
                return;
            }
            $set = ['.id' => $sid, 'profile' => $planSlug, 'disabled' => $disabled];
            if ($plainPassword !== null && $plainPassword !== '') {
                $set['password'] = $plainPassword;
            }
            $API->comm('/ppp/secret/set', $set);
        } finally {
            $API->disconnect();
        }
        return;
    }

    // IP fija + cola simple
    $ip = trim((string) ($new['ip_fija'] ?? ''));
    if ($ip === '' || ! filter_var($ip, FILTER_VALIDATE_IP)) {
        throw new RuntimeException('IP fija no válida.');
    }
    $comment = netcontrol_queue_comment($id);

    $moved = ($oldMk !== $newMk);
    if ($moved) {
        $APIo = mikrotik_connect($oldMk);
        try {
            $qid = mikrotik_simple_queue_id_by_comment($APIo, $comment);
            if ($qid !== null) {
                $APIo->comm('/queue/simple/remove', ['.id' => $qid]);
            }
        } finally {
            $APIo->disconnect();
        }
        $APIn = mikrotik_connect($newMk);
        try {
            $APIn->comm('/queue/simple/add', [
                'name'      => 'nc-' . $id,
                'target'    => $ip . '/32',
                'max-limit' => $maxLimit,
                'comment'   => $comment,
                'disabled'  => $disabled,
            ]);
        } finally {
            $APIn->disconnect();
        }
        return;
    }

    $API = mikrotik_connect($newMk);
    try {
        $qid = mikrotik_simple_queue_id_by_comment($API, $comment);
        if ($qid === null) {
            $API->comm('/queue/simple/add', [
                'name'      => 'nc-' . $id,
                'target'    => $ip . '/32',
                'max-limit' => $maxLimit,
                'comment'   => $comment,
                'disabled'  => $disabled,
            ]);
            return;
        }
        $API->comm('/queue/simple/set', [
            '.id'        => $qid,
            'target'     => $ip . '/32',
            'max-limit'  => $maxLimit,
            'disabled'   => $disabled,
        ]);
    } finally {
        $API->disconnect();
    }
}

/**
 * Prueba API RouterOS y devuelve datos para UI de diagnóstico.
 *
 * @return array{ok: bool, message: string, identity?: string, resource?: string}
 */
/**
 * Número de filas vía API (count-only); no transfiere cada registro.
 * Devuelve null si el firmware no lo soporta o hay error.
 */
function mikrotik_print_count_only(RouterosAPI $API, string $printCommand): ?int
{
    try {
        $r = $API->comm($printCommand, ['?count-only' => 'yes']);
        if (is_int($r)) {
            return $r;
        }
        if (is_string($r) && is_numeric($r)) {
            return (int) $r;
        }
        if (is_array($r)) {
            if (isset($r['ret']) && is_numeric($r['ret'])) {
                return (int) $r['ret'];
            }
            $first = mikrotik_first_row($r);
            if ($first !== null && isset($first['ret']) && is_numeric($first['ret'])) {
                return (int) $first['ret'];
            }
        }
    } catch (Throwable $e) {
    }

    return null;
}

function mikrotik_probe(int $mikrotikId): array
{
    try {
        // Timeouts cortos: el dashboard prueba todos los routers; no bloquear la página.
        $API = mikrotik_connect($mikrotikId, 2, 1, 0);
        try {
            $ident = $API->comm('/system/identity/print');
            $res = $API->comm('/system/resource/print');
            $irow = mikrotik_first_row((array) $ident);
            $rrow = mikrotik_first_row((array) $res);
            $name = $irow['name'] ?? '';
            $ver = $rrow['version'] ?? ($rrow['board-name'] ?? '');
            return [
                'ok' => true,
                'message' => 'Conexión API correcta.',
                'identity' => (string) $name,
                'resource' => is_string($ver) ? $ver : '',
            ];
        } finally {
            $API->disconnect();
        }
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}
