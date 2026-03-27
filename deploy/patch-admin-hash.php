<?php
declare(strict_types=1);
/**
 * Regenera const ADMIN_PASSWORD_HASH en panel/config.php sin corromper bcrypt.
 * (preg_replace interpreta $2 en el reemplazo como backreference; por eso se usa callback.)
 *
 * Uso en el servidor: php /ruta/patch-admin-hash.php
 */
$f = '/var/www/NetControl/panel/config.php';
if (!is_readable($f)) {
    fwrite(STDERR, "No existe $f\n");
    exit(1);
}
$c = file_get_contents($f);
$h = password_hash('admin', PASSWORD_DEFAULT);
$c2 = preg_replace_callback(
    '/^const ADMIN_PASSWORD_HASH = .*$/m',
    static function () use ($h): string {
        return 'const ADMIN_PASSWORD_HASH = ' . var_export($h, true) . ';';
    },
    $c,
    1
);
if ($c2 === null || $c2 === $c) {
    fwrite(STDERR, "No se pudo reemplazar la línea ADMIN_PASSWORD_HASH\n");
    exit(1);
}
file_put_contents($f, $c2);
require $f;
if (!password_verify('admin', ADMIN_PASSWORD_HASH)) {
    fwrite(STDERR, "password_verify falló tras escribir config\n");
    exit(1);
}
echo "OK admin/admin\n";
