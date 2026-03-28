<?php
/**
 * Copiar en el CT como: /var/www/html/index.php
 * (renombrar o borrar el index.html por defecto de Apache, o asegurar que DirectoryIndex priorice index.php)
 *
 * Al abrir http://IP/ → login (si ya hay sesión, login.php redirige a dashboard).
 */
declare(strict_types=1);

header('Location: /netcontrol/login.php', true, 302);
exit;
