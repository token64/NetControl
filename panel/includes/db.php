<?php
declare(strict_types=1);

function db(): mysqli
{
    static $mysqli = null;
    if ($mysqli instanceof mysqli) {
        return $mysqli;
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
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
