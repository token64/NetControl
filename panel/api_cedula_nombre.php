<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_auth();
require_once __DIR__ . '/includes/cedula_lookup.php';

header('Content-Type: application/json; charset=UTF-8');

if (! nc_cedula_lookup_enabled()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Consulta por cédula deshabilitada en config.php.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = isset($_GET['cedula']) ? (string) $_GET['cedula'] : '';
$d = nc_cedula_digits_rd($raw);
if ($d === null) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Usá la cédula dominicana con 11 dígitos (sin guiones).'], JSON_UNESCAPED_UNICODE);
    exit;
}

$res = nc_lookup_nombre_por_cedula($d);
if (! $res['ok']) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => (string) $res['error']], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'ok' => true,
    'nombre' => (string) $res['nombre'],
    'fuente' => nc_cedula_lookup_etiqueta(),
], JSON_UNESCAPED_UNICODE);
