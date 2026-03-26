<?php
/**
 * Copiar este archivo como config.php y completar valores.
 * Contraseña panel por defecto en ejemplo: CambiarClave123!
 * Generar hash: php -r "echo password_hash('tu_clave', PASSWORD_DEFAULT);"
 */
declare(strict_types=1);

const DB_HOST = 'localhost';
const DB_USER = 'root';
const DB_PASS = '';
const DB_NAME = 'panel_wisp';

/** Usuario del panel (login web) */
const ADMIN_USER = 'admin';
/** Sustituir por hash propio (nunca guardar contraseña en texto plano) */
const ADMIN_PASSWORD_HASH = '$2y$10$d6tQmUT14HsaA34bd3.EteyxjZJSV/i7AvePtHuWwnIeVkR2nyG7G';

/**
 * Clave para auto_corte.php vía cron (?key=...)
 * Ejemplo cron Windows: php C:\xampp\htdocs\NetControl\panel\auto_corte.php clave_secreta
 */
const CRON_SECRET = 'genera_una_clave_larga_aleatoria';

/** Primera instalación: definí un valor y abrí panel/install.php?token=... Luego vaciá esta constante. */
const INSTALL_TOKEN = 'cambia-esto-antes-de-instalar';

const SMAROLT_API_KEY = '';
const GOOGLE_MAPS_KEY = '';

/** Rango para asignar IP fija: "inicio-fin" */
const IP_POOL_DEFAULT = '192.168.10.2-192.168.10.254';

/**
 * Perfiles MikroTik (deben existir en el router) => límite cola simple
 * @return array<string, string>
 */
function plan_limits(): array
{
    return [
        'plan_5m'  => '5M/5M',
        'plan_10m' => '10M/10M',
        'plan_20m' => '20M/20M',
    ];
}
