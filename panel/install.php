<?php
declare(strict_types=1);

/**
 * Instalación única de base de datos (ejecuta sql/schema.sql).
 * 1. En config.php define INSTALL_TOKEN (ver config.example.php).
 * 2. Abre: install.php?token=TU_TOKEN
 * 3. Vacía INSTALL_TOKEN o borra este archivo después.
 */
header('Content-Type: text/html; charset=UTF-8');

function esc_html(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$config = __DIR__ . '/config.php';
if (! is_readable($config)) {
    http_response_code(500);
    exit('Falta panel/config.php — copia config.example.php');
}

require $config;

if (! defined('INSTALL_TOKEN') || INSTALL_TOKEN === '') {
    http_response_code(403);
    exit('Definí INSTALL_TOKEN en config.php y pasá ?token=... en la URL.');
}

$given = isset($_GET['token']) ? (string) $_GET['token'] : '';
if (! hash_equals(INSTALL_TOKEN, $given)) {
    http_response_code(403);
    exit('Token incorrecto.');
}

$schema = dirname(__DIR__) . '/sql/schema.sql';
if (! is_readable($schema)) {
    http_response_code(500);
    exit('No se encontró sql/schema.sql');
}

$sql = file_get_contents($schema);
if ($sql === false) {
    http_response_code(500);
    exit('No se pudo leer el esquema SQL.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS);
    $mysqli->set_charset('utf8mb4');
    $mysqli->multi_query($sql);
    do {
        if ($result = $mysqli->store_result()) {
            $result->free();
        }
    } while ($mysqli->more_results() && $mysqli->next_result());
    $mysqli->close();
} catch (Throwable $e) {
    http_response_code(500);
    echo '<pre>' . esc_html($e->getMessage()) . '</pre>';
    exit;
}

echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Instalación OK</title></head><body>';
echo '<h1>Base de datos creada</h1>';
echo '<p>Se aplicó <code>sql/schema.sql</code> sobre <strong>' . htmlspecialchars(DB_HOST, ENT_QUOTES, 'UTF-8') . '</strong>.</p>';
echo '<p><a href="login.php">Ir al login</a> · Dejá INSTALL_TOKEN vacío en config.php y borrá <code>install.php</code> por seguridad.</p>';
echo '</body></html>';
