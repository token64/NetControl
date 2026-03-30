<?php
declare(strict_types=1);
/**
 * Panel web mínimo — alta de peers WireGuard en la VM hub (NetControl).
 * La clave del MIKROTIK debe ser la PÚBLICA (Interface → WireGuard → Keys).
 * Nunca subas la clave privada del router al servidor.
 */
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Strict']);

$configFile = __DIR__ . '/config.php';
if (! is_readable($configFile)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Falta config.php. Copiá config.example.php → config.php y editá.\n");
}
require_once $configFile;

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

$err = '';
$out = '';
$authed = ! empty($_SESSION['nc_vpn_panel']);

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: ./');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $pw = (string) ($_POST['password'] ?? '');
    if (hash_equals(NC_VPN_PANEL_PASSWORD, $pw)) {
        $_SESSION['nc_vpn_panel'] = 1;
        header('Location: ./');
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

if ($authed) {
    $status = '';
    exec('sudo -n wg show ' . escapeshellarg(WG_INTERFACE) . ' 2>&1', $wgLines, $wgCode);
    $status = implode("\n", $wgLines);
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>VPN hub — Peers WireGuard</title>
    <style>
        :root { --bg:#0f1419; --card:#1a222d; --text:#e7edf4; --muted:#8b9cAd; --acc:#22c59e; --err:#f6a0a0; }
        * { box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 1.5rem; line-height: 1.5; }
        .wrap { max-width: 640px; margin: 0 auto; }
        h1 { font-size: 1.35rem; margin: 0 0 .5rem; }
        .sub { color: var(--muted); font-size: .9rem; margin-bottom: 1.25rem; }
        .card { background: var(--card); border-radius: 12px; padding: 1.25rem; margin-bottom: 1rem; border: 1px solid rgba(34,197,158,.15); }
        label { display: block; font-size: .82rem; color: var(--muted); margin: .75rem 0 .25rem; }
        input, textarea { width: 100%; padding: .55rem .7rem; border-radius: 8px; border: 1px solid #2a3544; background: #0f1419; color: var(--text); font-family: ui-monospace, monospace; font-size: .9rem; }
        textarea { min-height: 4rem; resize: vertical; }
        button { background: var(--acc); color: #04130f; border: 0; padding: .6rem 1.1rem; border-radius: 8px; font-weight: 600; cursor: pointer; margin-top: 1rem; }
        button:hover { filter: brightness(1.08); }
        .err { color: var(--err); margin: .75rem 0; font-size: .9rem; }
        pre { background: #0a0e12; padding: 1rem; border-radius: 8px; overflow: auto; font-size: .78rem; white-space: pre-wrap; word-break: break-all; border: 1px solid #2a3544; }
        .warn { background: rgba(246,160,160,.12); border: 1px solid rgba(246,160,160,.35); padding: .75rem; border-radius: 8px; font-size: .85rem; margin-bottom: 1rem; }
        a { color: var(--acc); }
    </style>
</head>
<body>
<div class="wrap">
    <h1>MikroTik — Alta de peer (WireGuard hub)</h1>
    <p class="sub">VM hub · interfaz <code><?= h(WG_INTERFACE) ?></code></p>

    <?php if (! $authed): ?>
        <div class="card">
            <p class="sub" style="margin-top:0">Contraseña configurada en <code>config.php</code></p>
            <?php if ($err !== ''): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
            <form method="post">
                <input type="hidden" name="login" value="1">
                <label>Contraseña</label>
                <input type="password" name="password" required autocomplete="current-password">
                <button type="submit">Entrar</button>
            </form>
        </div>
    <?php else: ?>
        <p><a href="?logout=1">Salir</a></p>
        <div class="warn">
            Pegá la <strong>clave pública</strong> del MikroTik (WinBox: <em>WireGuard → Keys → Public</em> o terminal <code>/interface wireguard print keys</code>).
            <strong>No</strong> uses la clave privada en este formulario.
        </div>
        <div class="card">
            <?php if ($err !== ''): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
            <form method="post">
                <input type="hidden" name="add_peer" value="1">
                <label>Nombre del router (sin espacios; usa _ si hace falta)</label>
                <input name="nombre" required placeholder="mk_5009_pop" value="<?= h((string) ($_POST['nombre'] ?? '')) ?>" pattern="[a-zA-Z0-9_-]{1,64}">
                <label>Descripción (opcional)</label>
                <textarea name="descripcion" placeholder="Torre X, enlace de prueba…"><?= h((string) ($_POST['descripcion'] ?? '')) ?></textarea>
                <label>Clave pública WireGuard del MikroTik</label>
                <input name="clave_publica" required autocomplete="off" placeholder="x3mK9…=" value="<?= h((string) ($_POST['clave_publica'] ?? '')) ?>">
                <label>IP en el túnel (10.64.0.2–254 con /32; el .1 es el hub)</label>
                <input name="tunnel_ip" value="<?= h((string) ($_POST['tunnel_ip'] ?? '10.64.0.16/32')) ?>" placeholder="10.64.0.16/32" required>
                <button type="submit">Agregar peer</button>
            </form>
        </div>
        <?php if ($out !== ''): ?>
            <div class="card">
                <strong>Salida del script</strong>
                <pre><?= h($out) ?></pre>
            </div>
        <?php endif; ?>
        <div class="card">
            <strong>Estado <code>wg show</code></strong>
            <pre><?= h($status) ?></pre>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
