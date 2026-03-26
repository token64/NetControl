<?php
declare(strict_types=1);

/**
 * Suspensión automática por fecha de pago vencida.
 * Cron / Programador de tareas:
 *   php "C:\xampp\htdocs\NetControl\panel\auto_corte.php" TU_CRON_SECRET
 * Web (no recomendado sin firewall): auto_corte.php?key=TU_CRON_SECRET
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/funciones.php';

$cliSecret = $argv[1] ?? null;
$getSecret = is_string($_GET['key'] ?? null) ? (string) $_GET['key'] : '';
$secret = is_string($cliSecret) ? $cliSecret : $getSecret;

if (! is_string(CRON_SECRET) || CRON_SECRET === '' || CRON_SECRET === 'cambiar_esta_clave_para_cron') {
    http_response_code(500);
    exit('Define CRON_SECRET en config.php con una clave segura.');
}

if (! hash_equals(CRON_SECRET, $secret)) {
    http_response_code(403);
    exit('Prohibido');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$sql = <<<'SQL'
SELECT * FROM clientes
WHERE fecha_pago IS NOT NULL
  AND fecha_pago < ?
  AND estado = "activo"
  AND (
    promesa_hasta IS NULL
    OR promesa_hasta < ?
  )
SQL;
$st = db()->prepare($sql);
$st->bind_param('ss', $today, $today);
$st->execute();
$r = $st->get_result();

$suspended = 0;
while ($c = $r->fetch_assoc()) {
    try {
        cliente_suspend_mikrotik($c);
        $id = (int) $c['id'];
        $u = db()->prepare('UPDATE clientes SET estado = "suspendido" WHERE id = ?');
        $u->bind_param('i', $id);
        $u->execute();
        $suspended++;
    } catch (Throwable) {
        // Continuar con otros clientes; revisar logs del servidor si falla el API.
    }
}

header('Content-Type: text/plain; charset=UTF-8');
echo 'OK suspendidos: ' . $suspended . PHP_EOL;
