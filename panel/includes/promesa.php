<?php
declare(strict_types=1);

/**
 * Promesa de pago: el cliente compromete pagar antes de promesa_hasta;
 * el corte automático usa esta fecha como gracia adicional sobre fecha_pago.
 */
function promesa_vigente(?string $promesaHasta): bool
{
    if ($promesaHasta === null || $promesaHasta === '') {
        return false;
    }
    $hoy = new DateTimeImmutable('today');
    $lim = DateTimeImmutable::createFromFormat('Y-m-d', $promesaHasta);
    if (! $lim instanceof DateTimeImmutable) {
        return false;
    }
    return $lim >= $hoy;
}

function formatear_fecha(?string $ymd): string
{
    if ($ymd === null || $ymd === '') {
        return '—';
    }
    $d = DateTimeImmutable::createFromFormat('Y-m-d', $ymd);
    return $d ? $d->format('d/m/Y') : '—';
}

/** Activo + fecha_pago: vencido | próximo (≤7 días) | vacío. */
function cliente_etiqueta_vencimiento(?string $fechaPago, string $estado): string
{
    if ($estado !== 'activo' || $fechaPago === null || $fechaPago === '') {
        return '';
    }
    $hoy = new DateTimeImmutable('today');
    $fp = DateTimeImmutable::createFromFormat('Y-m-d', $fechaPago);
    if (! $fp instanceof DateTimeImmutable) {
        return '';
    }
    if ($fp < $hoy) {
        return 'vencido';
    }
    if ($fp <= $hoy->modify('+7 days')) {
        return 'proximo';
    }
    return '';
}
