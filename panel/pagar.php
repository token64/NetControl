<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_auth();
require_post();
require_once __DIR__ . '/funciones.php';

if (! csrf_verify()) {
    flash_set('error', 'Token inválido.');
    redirect('index.php');
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    flash_set('error', 'Cliente no válido.');
    redirect('index.php');
}

$c = fetch_client_by_id($id);
if (! $c) {
    flash_set('error', 'Cliente no encontrado.');
    redirect('index.php');
}

try {
    cliente_activate_mikrotik($c);
    $fecha = (new DateTimeImmutable('today'))->modify('+30 days')->format('Y-m-d');
    $st = db()->prepare('UPDATE clientes SET estado = "activo", fecha_pago = ?, promesa_hasta = NULL, promesa_notas = NULL WHERE id = ?');
    $st->bind_param('si', $fecha, $id);
    $st->execute();
    flash_set('success', 'Cliente reactivado; nueva fecha de pago aplicada.');
} catch (Throwable $e) {
    flash_set('error', $e->getMessage());
}

redirect('index.php');
