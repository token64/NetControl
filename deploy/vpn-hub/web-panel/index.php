<?php
declare(strict_types=1);
/**
 * Panel WireGuard hub — alta/baja de peers, listado desde `wg show … dump`.
 */
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$configFile = __DIR__ . '/config.php';
if (! is_readable($configFile)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Falta config.php. Copiá config.example.php → config.php y editá.\n");
}
require_once $configFile;

if (! defined('WG_REMOVE_PEER_SCRIPT')) {
    define('WG_REMOVE_PEER_SCRIPT', dirname(WG_ADD_PEER_SCRIPT) . '/wg-server-remove-peer.sh');
}
if (! defined('WG_CLI')) {
    define('WG_CLI', '/usr/bin/wg');
}

function wg_resolve_remove_script(): ?string
{
    $seen = [];
    $try = [
        WG_REMOVE_PEER_SCRIPT,
        dirname(WG_ADD_PEER_SCRIPT) . '/wg-server-remove-peer.sh',
        '/usr/local/sbin/wg-server-remove-peer.sh',
    ];
    foreach ($try as $p) {
        $p = (string) $p;
        if ($p === '' || isset($seen[$p])) {
            continue;
        }
        $seen[$p] = true;
        if (is_readable($p)) {
            return $p;
        }
    }

    return null;
}

function wg_remove_script_missing_hint(): string
{
    return ' En la VM vpn-hub: sudo install -m 755 deploy/vpn-hub/wg-server-remove-peer.sh /usr/local/sbin/'
        . ' y sudo bash deploy/vpn-hub/apply-nc-vpn-panel-sudoers.sh';
}

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function valid_peer_name(string $n): bool
{
    return $n !== '' && (bool) preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $n);
}

function valid_wg_pubkey(string $k): bool
{
    $k = trim($k);
    if (strlen($k) < 40 || strlen($k) > 50) {
        return false;
    }

    return (bool) preg_match('#^[A-Za-z0-9+/]+=*$#', $k);
}

function valid_allowed_ip(string $ip): bool
{
    if (! preg_match('#^10\.64\.0\.(\d{1,3})/32$#', trim($ip), $m)) {
        return false;
    }
    $n = (int) $m[1];

    return $n >= 2 && $n <= 254;
}

/** @return array{ok: bool, error: string, iface: ?array<string, string>, peers: list<array<string, mixed>>} */
function nc_wg_fetch_dump(): array
{
    $if = escapeshellarg(WG_INTERFACE);
    $wg = escapeshellarg(WG_CLI);
    exec("sudo -n {$wg} show {$if} dump 2>&1", $lines, $code);
    $raw = implode("\n", $lines);
    if ($code !== 0
        || str_contains(strtolower($raw), 'password')
        || str_contains($raw, 'Permission denied')
        || str_contains($raw, 'Unable to access interface')) {
        return ['ok' => false, 'error' => $raw, 'iface' => null, 'peers' => []];
    }
    $parsed = nc_wg_parse_dump_lines($lines);

    return array_merge(['ok' => true, 'error' => ''], $parsed);
}

/**
 * @param list<string> $lines
 * @return array{iface: ?array<string, string>, peers: list<array<string, mixed>>}
 */
function nc_wg_parse_dump_lines(array $lines): array
{
    $iface = null;
    $peers = [];
    $first = true;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $p = explode("\t", $line);
        if ($first) {
            $first = false;
            $iface = [
                'private_key' => (string) ($p[0] ?? ''),
                'public_key' => (string) ($p[1] ?? ''),
                'listen_port' => (string) ($p[2] ?? ''),
                'fwmark' => (string) ($p[3] ?? ''),
            ];

            continue;
        }
        $ep = (string) ($p[2] ?? '');
        if ($ep === '(none)') {
            $ep = '—';
        }
        $ips = (string) ($p[3] ?? '');
        $ips = str_replace(',', ', ', $ips);
        $peers[] = [
            'public_key' => (string) ($p[0] ?? ''),
            'endpoint' => $ep,
            'allowed_ips' => $ips,
            'latest_handshake' => (int) ($p[4] ?? 0),
            'transfer_rx' => (int) ($p[5] ?? 0),
            'transfer_tx' => (int) ($p[6] ?? 0),
        ];
    }

    return ['iface' => $iface, 'peers' => $peers];
}

function nc_wg_fmt_handshake(int $ts): string
{
    if ($ts <= 0) {
        return 'Nunca';
    }
    $ago = time() - $ts;
    if ($ago < 90) {
        return 'hace ' . $ago . ' s';
    }
    if ($ago < 3600) {
        return 'hace ' . (int) ($ago / 60) . ' min';
    }
    if ($ago < 86400) {
        return 'hace ' . (int) ($ago / 3600) . ' h';
    }

    return date('d M H:i', $ts);
}

function nc_wg_fmt_bytes(int $b): string
{
    if ($b < 1024) {
        return $b . ' B';
    }
    if ($b < 1048576) {
        return round($b / 1024, 1) . ' KiB';
    }
    if ($b < 1073741824) {
        return round($b / 1048576, 1) . ' MiB';
    }

    return round($b / 1073741824, 2) . ' GiB';
}

/** @param array<string, mixed>|null $iface */
function nc_wg_is_server_pubkey(?array $iface, string $pk): bool
{
    if ($iface === null) {
        return false;
    }
    $k = trim($pk);
    $ik = trim((string) ($iface['public_key'] ?? ''));

    return $ik !== '' && hash_equals($ik, $k);
}

/** @param list<array<string, mixed>> $peers */
function nc_wg_peer_exists(array $peers, string $pk): bool
{
    $pk = trim($pk);
    foreach ($peers as $row) {
        if (hash_equals((string) $row['public_key'], $pk)) {
            return true;
        }
    }

    return false;
}

/** @return array{out: string, code: int, err_msg: string} */
function nc_wg_exec_remove(string $pubRm): array
{
    $rmPath = wg_resolve_remove_script();
    if ($rmPath === null) {
        return [
            'out' => '',
            'code' => 1,
            'err_msg' => 'No hay wg-server-remove-peer.sh legible para www-data.' . wg_remove_script_missing_hint(),
        ];
    }
    $rm = realpath($rmPath) ?: $rmPath;
    $cmdRm = sprintf(
        'sudo -n /bin/bash %s %s 2>&1',
        escapeshellarg($rm),
        escapeshellarg(trim($pubRm))
    );
    exec($cmdRm, $linesRm, $codeRm);
    $out = implode("\n", $linesRm);

    return [
        'out' => $out,
        'code' => $codeRm,
        'err_msg' => $codeRm !== 0 ? 'El script de baja devolvió error. Revisá la salida abajo.' : '',
    ];
}

$err = '';
$out = '';
$authed = ! empty($_SESSION['nc_vpn_panel']);
$wgDump = ['ok' => false, 'error' => '', 'iface' => null, 'peers' => []];

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: ./');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $pw = trim((string) ($_POST['password'] ?? ''));
    $expected = trim((string) NC_VPN_PANEL_PASSWORD);
    if (hash_equals($expected, $pw)) {
        session_regenerate_id(true);
        $_SESSION['nc_vpn_panel'] = 1;
        session_write_close();
        header('Location: ./', true, 303);
        exit;
    }
    $err = 'Contraseña incorrecta.';
}

if ($authed && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_peer'])) {
    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $nombre = preg_replace('/\s+/', '_', $nombre) ?? '';
    $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
    $descripcion = preg_replace('/[\r\n]+/', ' ', $descripcion);
    if (strlen($descripcion) > 240) {
        $descripcion = substr($descripcion, 0, 240);
    }
    $pub = trim((string) ($_POST['clave_publica'] ?? ''));
    $tun = trim((string) ($_POST['tunnel_ip'] ?? '10.64.0.16/32'));

    if (! valid_peer_name($nombre)) {
        $err = 'Nombre: solo letras, números, guiones y guión bajo; 1–64 caracteres.';
    } elseif (! valid_wg_pubkey($pub)) {
        $err = 'Clave pública inválida (copiá la Public key del MikroTik, no la private).';
    } elseif (! valid_allowed_ip($tun)) {
        $err = 'IP túnel: usá 10.64.0.2 a 10.64.0.254 con /32 (el .1 es el servidor).';
    } elseif (! is_readable(WG_ADD_PEER_SCRIPT)) {
        $err = 'No existe o no se puede leer: ' . WG_ADD_PEER_SCRIPT;
    } else {
        $script = realpath(WG_ADD_PEER_SCRIPT) ?: WG_ADD_PEER_SCRIPT;
        $cmd = sprintf(
            'sudo -n /bin/bash %s %s %s %s %s 2>&1',
            escapeshellarg($script),
            escapeshellarg($nombre),
            escapeshellarg($pub),
            escapeshellarg($tun),
            $descripcion !== '' ? escapeshellarg($descripcion) : escapeshellarg('')
        );
        exec($cmd, $lines, $code);
        $out = implode("\n", $lines);
        if ($code !== 0) {
            $err = 'El script devolvió error. Revisá la salida abajo.';
        }
    }
}

if ($authed && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_peer'])) {
    $pubRm = trim((string) ($_POST['clave_publica_quitar'] ?? ''));
    $wgDump = nc_wg_fetch_dump();
    if (! valid_wg_pubkey($pubRm)) {
        $err = 'Clave pública inválida.';
    } elseif (nc_wg_is_server_pubkey($wgDump['iface'], $pubRm)) {
        $err = 'Esa es la clave pública del servidor (interfaz wg), no de un peer. Elegí un peer de la tabla o pegá la clave del MikroTik.';
    } elseif ($wgDump['ok'] && ! nc_wg_peer_exists($wgDump['peers'], $pubRm)) {
        $err = 'Esa clave no está en la lista de peers activos (o el dump no se pudo leer — revisá sudo para `wg show ' . h(WG_INTERFACE) . ' dump`).';
    } else {
        $r = nc_wg_exec_remove($pubRm);
        $out = $r['out'];
        if ($r['err_msg'] !== '') {
            $err = $r['err_msg'];
        }
    }
}

if ($authed && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_peer_row'])) {
    $pubRm = trim((string) ($_POST['peer_pubkey'] ?? ''));
    $wgDump = nc_wg_fetch_dump();
    if (! valid_wg_pubkey($pubRm)) {
        $err = 'Clave inválida.';
    } elseif (nc_wg_is_server_pubkey($wgDump['iface'], $pubRm)) {
        $err = 'No se puede borrar la clave del servidor.';
    } elseif (! $wgDump['ok'] || ! nc_wg_peer_exists($wgDump['peers'], $pubRm)) {
        $err = 'Peer no encontrado en el estado actual.';
    } else {
        $r = nc_wg_exec_remove($pubRm);
        $out = $r['out'];
        if ($r['err_msg'] !== '') {
            $err = $r['err_msg'];
        }
    }
}

if ($authed && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_remove_peers'])) {
    $selected = $_POST['peer_pubkeys'] ?? [];
    if (! is_array($selected)) {
        $selected = [];
    }
    $selected = array_map('trim', array_map('strval', $selected));
    $selected = array_filter($selected, static fn (string $s): bool => $s !== '');
    if ($selected === []) {
        $err = 'Marcá al menos un peer para eliminar.';
    } else {
        $wgDump = nc_wg_fetch_dump();
        $outs = [];
        foreach ($selected as $pubRm) {
            if (! valid_wg_pubkey($pubRm) || nc_wg_is_server_pubkey($wgDump['iface'], $pubRm)) {
                continue;
            }
            if ($wgDump['ok'] && ! nc_wg_peer_exists($wgDump['peers'], $pubRm)) {
                $outs[] = 'Omitido (no listado): ' . substr($pubRm, 0, 20) . '…';

                continue;
            }
            $r = nc_wg_exec_remove($pubRm);
            $outs[] = $r['out'];
            if ($r['code'] !== 0) {
                $err = 'Al menos una baja falló. Revisá la salida.';
            }
            $wgDump = nc_wg_fetch_dump();
        }
        $out = implode("\n---\n", array_filter($outs));
    }
}

if ($authed) {
    $wgDump = nc_wg_fetch_dump();
    $status = '';
    exec('sudo -n ' . escapeshellarg(WG_CLI) . ' show ' . escapeshellarg(WG_INTERFACE) . ' 2>&1', $wgLines, $wgCode);
    $status = implode("\n", $wgLines);
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hub WireGuard — Panel</title>
    <style>
        :root { --bg:#0b0f14; --card:#141b24; --text:#e8f0f7; --muted:#8b9cAd; --acc:#22c59e; --err:#f6a0a0; --border:#2a3544; }
        * { box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 1.25rem; line-height: 1.5; }
        .wrap { max-width: 1100px; margin: 0 auto; }
        h1 { font-size: 1.4rem; margin: 0 0 .35rem; font-weight: 700; }
        .sub { color: var(--muted); font-size: .9rem; margin-bottom: 1rem; }
        .card { background: var(--card); border-radius: 12px; padding: 1.25rem; margin-bottom: 1rem; border: 1px solid rgba(34,197,158,.12); }
        label { display: block; font-size: .82rem; color: var(--muted); margin: .75rem 0 .25rem; }
        input, textarea { width: 100%; max-width: 100%; padding: .55rem .7rem; border-radius: 8px; border: 1px solid var(--border); background: #0b1018; color: var(--text); font-family: ui-monospace, monospace; font-size: .85rem; }
        textarea { min-height: 4rem; resize: vertical; }
        button { background: var(--acc); color: #04130f; border: 0; padding: .55rem 1rem; border-radius: 8px; font-weight: 600; cursor: pointer; margin-top: .5rem; }
        button:hover { filter: brightness(1.08); }
        .err { color: var(--err); margin: .75rem 0; font-size: .9rem; }
        pre { background: #070a0e; padding: 1rem; border-radius: 8px; overflow: auto; font-size: .75rem; white-space: pre-wrap; word-break: break-all; border: 1px solid var(--border); }
        .warn { background: rgba(246,160,160,.1); border: 1px solid rgba(246,160,160,.35); padding: .85rem; border-radius: 10px; font-size: .88rem; margin-bottom: 1rem; }
        .card-danger { border-color: rgba(198,93,93,.4); }
        .btn-danger { background: #c65d5d !important; color: #fff !important; border: 1px solid #e08080; }
        .btn-sm { padding: .35rem .65rem; font-size: .8rem; margin-top: 0; }
        .top-links { font-size: .9rem; margin: -.15rem 0 1rem; display: flex; flex-wrap: wrap; gap: .75rem 1.25rem; align-items: center; }
        a { color: var(--acc); }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: .75rem; margin-bottom: 1rem; }
        .stat { background: #0e141c; padding: .85rem 1rem; border-radius: 10px; border: 1px solid var(--border); }
        .stat dt { font-size: .72rem; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; margin: 0; }
        .stat dd { margin: .25rem 0 0; font-family: ui-monospace, monospace; font-size: .8rem; word-break: break-all; }
        table.wg { width: 100%; border-collapse: collapse; font-size: .82rem; }
        table.wg th, table.wg td { text-align: left; padding: .55rem .5rem; border-bottom: 1px solid var(--border); vertical-align: top; }
        table.wg th { color: var(--muted); font-weight: 600; font-size: .72rem; text-transform: uppercase; }
        table.wg tr:hover td { background: rgba(34,197,158,.05); }
        .mono { font-family: ui-monospace, monospace; font-size: .78rem; word-break: break-all; }
        .section-title { font-size: 1rem; font-weight: 700; margin: 0 0 .75rem; color: var(--text); }
        .muted { color: var(--muted); font-size: .85rem; }
        details.raw { margin-top: .75rem; }
        details.raw summary { cursor: pointer; color: var(--muted); font-size: .85rem; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Hub WireGuard — Panel</h1>
    <p class="sub">Interfaz <code><?= h(WG_INTERFACE) ?></code> · gestión de peers para NetControl / MikroTik</p>

    <?php if (! $authed): ?>
        <div class="card">
            <p class="sub" style="margin-top:0">Contraseña en <code>config.php</code> → <code>NC_VPN_PANEL_PASSWORD</code></p>
            <?php if ($err !== ''): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
            <form method="post">
                <input type="hidden" name="login" value="1">
                <label>Contraseña</label>
                <input type="password" name="password" required autocomplete="current-password">
                <button type="submit">Entrar</button>
            </form>
        </div>
    <?php else: ?>
        <div class="top-links">
            <a href="?logout=1">Salir</a>
            <a href="#peers">Peers</a>
            <a href="#agregar">Agregar</a>
            <a href="#manual-borrar">Borrar manual</a>
        </div>

        <?php if ($err !== ''): ?><div class="card"><p class="err"><?= h($err) ?></p></div><?php endif; ?>

        <?php if (! $wgDump['ok']): ?>
            <div class="card">
                <p class="err">No se pudo leer <code><?= h(basename((string) WG_CLI)) ?> show <?= h(WG_INTERFACE) ?> dump</code>. Alineá <code>WG_CLI</code> en <code>config.php</code> con <code>command -v wg</code> y volvé a correr <code>apply-nc-vpn-panel-sudoers.sh</code>.</p>
                <pre>www-data ALL=(root) NOPASSWD: <?= h(WG_CLI) ?> show <?= h(WG_INTERFACE) ?> dump</pre>
                <p class="muted">Salida:</p>
                <pre><?= h($wgDump['error']) ?></pre>
            </div>
        <?php else: ?>
            <div class="stats">
                <div class="stat">
                    <dt>Public key (servidor)</dt>
                    <dd title="No es un peer; no se borra desde aquí"><?= h((string) ($wgDump['iface']['public_key'] ?? '—')) ?></dd>
                </div>
                <div class="stat">
                    <dt>Puerto UDP</dt>
                    <dd><?= h((string) ($wgDump['iface']['listen_port'] ?? '—')) ?></dd>
                </div>
                <div class="stat">
                    <dt>Peers activos</dt>
                    <dd><?= count($wgDump['peers']) ?></dd>
                </div>
            </div>

            <div class="card" id="peers">
                <h2 class="section-title">Peers conectados</h2>
                <p class="muted" style="margin-top:0">Marcá uno o varios y eliminá sin copiar la clave a mano. La clave del <strong>servidor</strong> (arriba) no aparece como peer.</p>

                <?php if ($wgDump['peers'] === []): ?>
                    <p class="muted">No hay peers. Usá «Agregar».</p>
                <?php else: ?>
                    <form id="nc-bulk-remove" method="post" onsubmit="return confirm('¿Eliminar los peers marcados del archivo wg0.conf?');" style="margin-bottom:.75rem;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
                        <input type="hidden" name="bulk_remove_peers" value="1">
                        <button type="submit" class="btn-danger btn-sm">Eliminar marcados</button>
                        <span class="muted">Las casillas están ligadas a este botón.</span>
                    </form>
                    <table class="wg">
                        <thead>
                        <tr>
                            <th style="width:2rem;"></th>
                            <th>Allowed IPs</th>
                            <th>Endpoint</th>
                            <th>Handshake</th>
                            <th>↓ / ↑</th>
                            <th>Public key</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($wgDump['peers'] as $row): ?>
                            <tr>
                                <td><input type="checkbox" name="peer_pubkeys[]" value="<?= h((string) $row['public_key']) ?>" form="nc-bulk-remove"></td>
                                <td class="mono"><?= h((string) $row['allowed_ips']) ?></td>
                                <td class="mono"><?= h((string) $row['endpoint']) ?></td>
                                <td><?= h(nc_wg_fmt_handshake((int) $row['latest_handshake'])) ?></td>
                                <td class="mono"><?= h(nc_wg_fmt_bytes((int) $row['transfer_rx'])) ?> / <?= h(nc_wg_fmt_bytes((int) $row['transfer_tx'])) ?></td>
                                <td class="mono" title="<?= h((string) $row['public_key']) ?>"><?= h(substr((string) $row['public_key'], 0, 18)) ?>…</td>
                                <td>
                                    <form method="post" style="margin:0" onsubmit="return confirm('¿Borrar este peer?');">
                                        <input type="hidden" name="remove_peer_row" value="1">
                                        <input type="hidden" name="peer_pubkey" value="<?= h((string) $row['public_key']) ?>">
                                        <button type="submit" class="btn-danger btn-sm">Borrar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="warn">
            Claves: siempre la <strong>pública</strong> del MikroTik (WinBox <em>WireGuard → Keys → Public</em>). Nunca la privada en este panel.
        </div>

        <div class="card" id="agregar">
            <h2 class="section-title">Agregar peer</h2>
            <form method="post">
                <input type="hidden" name="add_peer" value="1">
                <label>Nombre (sin espacios; usá _)</label>
                <input name="nombre" required placeholder="mk_5009_pop" value="<?= h((string) ($_POST['nombre'] ?? '')) ?>" pattern="[a-zA-Z0-9_-]{1,64}">
                <label>Descripción (opcional)</label>
                <textarea name="descripcion" placeholder="Torre, POP…"><?= h((string) ($_POST['descripcion'] ?? '')) ?></textarea>
                <label>Clave pública del MikroTik</label>
                <input name="clave_publica" required autocomplete="off" placeholder="Base64 de la Public key" value="<?= h((string) ($_POST['clave_publica'] ?? '')) ?>">
                <label>IP en el túnel (10.64.0.2–254/32)</label>
                <input name="tunnel_ip" value="<?= h((string) ($_POST['tunnel_ip'] ?? '10.64.0.16/32')) ?>" required>
                <button type="submit">Agregar peer</button>
            </form>
        </div>

        <div class="card card-danger" id="manual-borrar">
            <h2 class="section-title" style="color:#f0b4b4;">Borrar por clave (manual)</h2>
            <p class="muted" style="margin-top:0">Solo si preferís pegar la public key completa. <strong>No</strong> uses la clave del servidor.</p>
            <form method="post">
                <input type="hidden" name="remove_peer" value="1">
                <label>Clave pública del peer</label>
                <input name="clave_publica_quitar" autocomplete="off" placeholder="Igual que en la tabla" value="<?= h((string) ($_POST['clave_publica_quitar'] ?? '')) ?>">
                <button type="submit" class="btn-danger">Borrar</button>
          </form>
        </div>

        <?php if ($out !== ''): ?>
            <div class="card">
                <strong>Salida del script</strong>
                <pre><?= h($out) ?></pre>
            </div>
        <?php endif; ?>

        <?php if ($authed && isset($status)): ?>
            <details class="raw">
                <summary>Texto completo <code>wg show</code> (referencia)</summary>
                <pre><?= h($status) ?></pre>
            </details>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
