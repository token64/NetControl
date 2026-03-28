<?php
declare(strict_types=1);

function db(): mysqli
{
    static $mysqli = null;
    if ($mysqli instanceof mysqli) {
        return $mysqli;
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    } catch (mysqli_sql_exception $e) {
        if (PHP_SAPI === 'cli') {
            throw $e;
        }
        http_response_code(503);
        $hint = '';
        $msg = $e->getMessage();
        if (str_contains($msg, 'Access denied')) {
            $hint = '<p><strong>Suele pasar en XAMPP</strong> si la cuenta MySQL tiene contraseña y en <code>panel/config.php</code> dejaste <code>DB_PASS</code> vacío. Editá ese archivo y poné la misma contraseña que usás en phpMyAdmin para <code>root</code> (o el usuario que corresponda).</p>';
        }
        if (str_contains($msg, 'Unknown database')) {
            $hint = '<p>La base <code>' . htmlspecialchars(DB_NAME, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code> no existe. Importá <code>sql/schema.sql</code> o ejecutá <code>install.php</code> según la guía del proyecto.</p>';
        }
        $path = htmlspecialchars(__DIR__ . '/../config.php', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        exit('<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>NetControl — Base de datos</title>'
            . '<style>body{font-family:system-ui,sans-serif;background:#0f1419;color:#e8eef4;max-width:40rem;margin:2rem auto;padding:0 1rem;line-height:1.5}code{background:#1a222c;padding:0 .25rem;border-radius:4px}</style></head><body>'
            . '<h1>No hay conexión a MySQL</h1><p>' . htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
            . $hint
            . '<p>Revisá <code>' . $path . '</code> → <code>DB_HOST</code>, <code>DB_USER</code>, <code>DB_PASS</code>, <code>DB_NAME</code>.</p></body></html>');
    }
    $mysqli->set_charset('utf8mb4');
    return $mysqli;
}

/**
 * @return array<string, mixed>|null
 */
function fetch_client_by_id(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM clientes WHERE id = ? LIMIT 1');
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    return $row ?: null;
}

/**
 * @return list<array<string, mixed>>
 */
function list_mikrotiks(): array
{
    $res = db()->query('SELECT id, nombre, ip FROM mikrotiks ORDER BY nombre');
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}
