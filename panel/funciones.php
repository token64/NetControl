<?php
declare(strict_types=1);

require_once __DIR__ . '/mikrotik.php';

function netcontrol_queue_comment(int $clientId): string
{
    return 'NetControl-' . $clientId;
}

function mikrotik_connect(int $mikrotikId): RouterosAPI
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
    $API->timeout = 6;
    $API->attempts = 3;
    $API->delay = 1;

    if (!$API->connect($m['ip'], $m['usuario'], $m['password'])) {
        throw new RuntimeException('No se pudo conectar a la API del MikroTik (' . esc($m['ip']) . ').');
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
