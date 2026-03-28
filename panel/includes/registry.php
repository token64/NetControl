<?php
declare(strict_types=1);

/**
 * Catálogo operativo: planes y redes IPv4 (sustituye plan_limits() fijo de config).
 */
/**
 * Mapa slug de plan → límite cola MikroTik (lee tabla `planes`; no uses plan_limits() en config.php).
 * @return array<string, string>
 */
/** @return list<array<string, mixed>> Filas para selectores (slug, nombre, max_limit). */
function list_planes_select(): array
{
    try {
        $res = db()->query(
            'SELECT slug, nombre, max_limit FROM planes WHERE activo = 1 ORDER BY sort_order ASC, id ASC'
        );
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    } catch (Throwable $e) {
        return [];
    }
}

function nc_plan_limits(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $res = db()->query(
            'SELECT slug, max_limit FROM planes WHERE activo = 1 ORDER BY sort_order ASC, id ASC'
        );
        if ($res && $res->num_rows > 0) {
            $cache = [];
            while ($row = $res->fetch_assoc()) {
                $cache[(string) $row['slug']] = (string) $row['max_limit'];
            }
            return $cache;
        }
    } catch (Throwable $e) {
    }
    $cache = [
        'plan_5m'  => '5M/5M',
        'plan_10m' => '10M/10M',
        'plan_20m' => '20M/20M',
    ];
    return $cache;
}

/** @return list<array<string, mixed>> */
function list_planes_admin(): array
{
    try {
        $res = db()->query(
            'SELECT id, slug, nombre, max_limit, activo, sort_order FROM planes ORDER BY sort_order ASC, id ASC'
        );
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    } catch (Throwable $e) {
        return [];
    }
}

/** @return list<array<string, mixed>> */
function list_redes_admin(): array
{
    try {
        $res = db()->query(
            'SELECT r.*, m.nombre AS mikrotik_nombre FROM redes r
             JOIN mikrotiks m ON m.id = r.mikrotik_id
             ORDER BY m.nombre, r.nombre'
        );
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    } catch (Throwable $e) {
        return [];
    }
}

/** @return list<array<string, mixed>> */
function list_redes_activas(): array
{
    try {
        $res = db()->query(
            'SELECT r.id, r.nombre, r.mikrotik_id, r.rango FROM redes r
             WHERE r.activo = 1 ORDER BY r.nombre'
        );
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    } catch (Throwable $e) {
        return [];
    }
}

/** @return array<string, mixed>|null */
function red_by_id(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM redes WHERE id = ? LIMIT 1');
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    return $row ?: null;
}

function count_clientes_mikrotik(int $mikrotikId): int
{
    $st = db()->prepare('SELECT COUNT(*) AS c FROM clientes WHERE mikrotik_id = ?');
    $st->bind_param('i', $mikrotikId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    return (int) ($row['c'] ?? 0);
}

function count_clientes_plan_slug(string $slug): int
{
    $st = db()->prepare('SELECT COUNT(*) AS c FROM clientes WHERE plan = ?');
    $st->bind_param('s', $slug);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    return (int) ($row['c'] ?? 0);
}

/** @return list<array<string, mixed>> */
function list_mikrotiks_admin(): array
{
    $res = db()->query(
        'SELECT id, nombre, ip, api_port, use_ssl, usuario FROM mikrotiks ORDER BY nombre'
    );
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

/** @return array<string, mixed>|null */
function mikrotik_by_id(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM mikrotiks WHERE id = ? LIMIT 1');
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    return $row ?: null;
}

function count_redes_mikrotik(int $mikrotikId): int
{
    try {
        $st = db()->prepare('SELECT COUNT(*) AS c FROM redes WHERE mikrotik_id = ?');
        $st->bind_param('i', $mikrotikId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        return (int) ($row['c'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

/** @return array<string, mixed>|null */
function plan_by_id(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM planes WHERE id = ? LIMIT 1');
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    return $row ?: null;
}

/** max_limit para cola MikroTik aunque el plan esté inactivo en catálogo. */
function plan_max_limit_by_slug(string $slug): ?string
{
    try {
        $st = db()->prepare('SELECT max_limit FROM planes WHERE slug = ? LIMIT 1');
        $st->bind_param('s', $slug);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        return $row ? (string) $row['max_limit'] : null;
    } catch (Throwable $e) {
        return null;
    }
}
