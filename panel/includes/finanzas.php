<?php
declare(strict_types=1);

function nc_pagos_table_exists(): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    $r = db()->query("SHOW TABLES LIKE 'pagos'");
    $ok = $r && $r->num_rows > 0;
    return $ok;
}

/** Suma cobros del día (moneda DOP mezclada; mejora futura: por moneda). */
function nc_pagos_total_hoy(): float
{
    if (! nc_pagos_table_exists()) {
        return 0.0;
    }
    $r = db()->query("SELECT COALESCE(SUM(monto),0) AS t FROM pagos WHERE DATE(creado_en) = CURDATE()");
    if (! $r) {
        return 0.0;
    }
    return (float) ($r->fetch_assoc()['t'] ?? 0);
}

/**
 * @return list<array<string, mixed>>
 */
function nc_pagos_recientes(int $limit = 15): array
{
    if (! nc_pagos_table_exists()) {
        return [];
    }
    $limit = max(1, min(200, $limit));
    $sql = "SELECT p.id, p.monto, p.moneda, p.metodo, p.referencia, p.creado_en, p.operador, c.nombre AS cliente_nombre
            FROM pagos p INNER JOIN clientes c ON c.id = p.cliente_id
            ORDER BY p.creado_en DESC LIMIT {$limit}";
    $res = db()->query($sql);
    $out = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $out[] = $row;
        }
    }
    return $out;
}
