<?php
/**
 * Copiar este archivo como config.php y completar valores.
 * Ejemplo de acceso panel: usuario jose · contraseña 123456aaa (cambiá en producción).
 * Generar hash: php -r "echo password_hash('tu_clave', PASSWORD_DEFAULT);"
 */
declare(strict_types=1);

const DB_HOST = 'localhost';
const DB_USER = 'root';
/** En XAMPP, si configuraste contraseña para root (phpMyAdmin), debe ir aquí; si no, dejá ''. */
const DB_PASS = '';
const DB_NAME = 'panel_wisp';

/** Usuario del panel (login web) */
const ADMIN_USER = 'jose';
/** Nombre visible en el dashboard (sidebar). Opcional; si vacío se usa ADMIN_USER. */
const ADMIN_DISPLAY_NAME = '';
/** Sustituir por hash propio (nunca guardar contraseña en texto plano) */
const ADMIN_PASSWORD_HASH = '$2y$10$.kG968GsO5KXMZnvkaaiL.vM.FJczDWdxikIs.6ZrK3Xm7xLQcEBi';

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

/**
 * Rellenar nombre desde una API HTTP al pulsar «Buscar» junto a la cédula (crear / editar cliente).
 * Los datos suelen venir del registro de contribuyentes (DGII u otro proveedor), no de la JCE.
 * Plantilla: un solo %s = cédula con 11 dígitos (se normaliza en el servidor).
 * Ejemplo (terceros, sujeto a sus términos): https://rnc.megaplus.com.do/api/consulta?rnc=%s
 * Si ves bloqueo tipo Imunify360: no es el JavaScript; es la IP desde la que tu PHP sale a Internet.
 * Otro panel que “funciona” suele llamar a su propio PHP en el hosting (misma idea que `api_cedula_nombre.php` aquí).
 */
const CEDULA_LOOKUP_ENABLED = false;
const CEDULA_LOOKUP_URL_TEMPLATE = '';
/** Texto mostrado en pantalla, ej. "DGII (contribuyente)". */
const CEDULA_LOOKUP_ETIQUETA_FUENTE = 'DGII (contribuyente)';
/**
 * Si true, cURL no verifica certificado SSL al consultar (solo para diagnóstico / XAMPP con CA rota).
 * En producción dejá false.
 */
const CEDULA_LOOKUP_INSECURE_SSL = false;
/**
 * Si la IP del servidor está bloqueada (Imunify) y tenés un proxy HTTP saliente permitido:
 * ej. http://127.0.0.1:8888 o tcp://proxy.isp.com:3128 (según lo que acepte tu cURL).
 */
const CEDULA_LOOKUP_HTTP_PROXY = '';
/** Opcional: usuario:contraseña del proxy (Basic). */
const CEDULA_LOOKUP_HTTP_PROXY_USERPWD = '';

/** Si tu config.php antiguo define plan_limits(), bórrala: ahora viene de BD (includes/registry.php). */
