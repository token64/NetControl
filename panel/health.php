<?php
declare(strict_types=1);

/**
 * Salud del servicio (monitoring / balanceadores). No expone datos sensibles.
 * GET → JSON: {"ok":true,"db":true} o 503 si falla config/DB
 */
header('Content-Type: application/json; charset=UTF-8');

$config = __DIR__ . '/config.php';
if (! is_readable($config)) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'db' => false, 'error' => 'config'], JSON_THROW_ON_ERROR);
    exit;
}

require $config;
require_once __DIR__ . '/db.php';

try {
    db()->query('SELECT 1');
    echo json_encode(['ok' => true, 'db' => true], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'db' => false], JSON_THROW_ON_ERROR);
}
