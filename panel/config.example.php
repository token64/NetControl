<?php
/**
 * Copiar este archivo como config.php y completar valores.
 * Contraseña panel por defecto en ejemplo: CambiarClave123!
 * Generar hash: php -r "echo password_hash('tu_clave', PASSWORD_DEFAULT);"
 */
declare(strict_types=1);

const DB_HOST = 'localhost';
const DB_USER = 'root';
/** En XAMPP, si configuraste contraseña para root (phpMyAdmin), debe ir aquí; si no, dejá ''. */
const DB_PASS = '';
const DB_NAME = 'panel_wisp';

/** Usuario del panel (login web) */
const ADMIN_USER = 'admin';
/** Nombre visible en el dashboard (sidebar). Opcional; si vacío se usa ADMIN_USER. */
const ADMIN_DISPLAY_NAME = '';
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
/** @deprecated El mapa usa Leaflet + OpenStreetMap; no hace falta clave. Dejá vacío. */
const GOOGLE_MAPS_KEY = '';

/** Centro del mapa (mapa.php) cuando no hay clientes con coordenadas o para vista inicial vacía. */
const MAP_DEFAULT_LAT = 18.5;
const MAP_DEFAULT_LNG = -69.9;
const MAP_DEFAULT_ZOOM = 12;

/**
 * Rango respaldo si no eliges “Red IPv4” al crear cliente IP fija.
 * Preferí crear redes en el menú Redes IPv4 (tabla `redes`).
 */
const IP_POOL_DEFAULT = '192.168.10.2-192.168.10.254';

/** Si tu config.php antiguo define plan_limits(), bórrala: ahora viene de BD (includes/registry.php). */
