#!/bin/bash
# Ejecutar en el CT como root: arregla hash corrupto de admin (bootstrap bash comía $2…).
php -r '$f="/var/www/NetControl/panel/config.php"; $c=file_get_contents($f); $h=password_hash("admin", PASSWORD_DEFAULT); $line="const ADMIN_PASSWORD_HASH = ".var_export($h,true).";"; $c=preg_replace("/^const ADMIN_PASSWORD_HASH = .*$/m", $line, $c, 1); file_put_contents($f,$c); echo (password_verify("admin",$h)?"OK":"BAD"), "\n";'
