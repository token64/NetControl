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
    cliente_delete_mikrotik($c);
} catch (Throwable $e) {
    flash_set('error', 'MikroTik: ' . $e->getMessage());
    redirect('index.php');
}

$st = db()->prepare('DELETE FROM clientes WHERE id = ?');
$st->bind_param('i', $id);
$st->execute();

flash_set('success', 'Cliente eliminado.');
redirect('index.php');
