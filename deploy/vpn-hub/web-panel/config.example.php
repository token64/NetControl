<?php
declare(strict_types=1);

/**
 * Copiar a config.php y editar. En la VM: subir esta carpeta a /var/www/html/vpn-panel
 * o al vhost que uses (solo HTTPS en producción).
 *
 * Sudo sin contraseña (ejemplo; rutas en /usr/local/sbin recomendadas):
 *
 *   sudo visudo -f /etc/sudoers.d/nc-vpn-panel
 *
 *   www-data ALL=(root) NOPASSWD: /bin/bash /usr/local/sbin/wg-server-add-peer.sh *
 *   www-data ALL=(root) NOPASSWD: /bin/bash /usr/local/sbin/wg-server-remove-peer.sh *
 *   www-data ALL=(root) NOPASSWD: /usr/bin/wg show wg0
 *   www-data ALL=(root) NOPASSWD: /usr/bin/wg show wg0 dump
 *
 * El panel usa la ruta absoluta WG_CLI (como sudo); debe coincidir con una de las líneas anteriores.
 */
const NC_VPN_PANEL_PASSWORD = 'cambiar_por_clave_larga';
const WG_ADD_PEER_SCRIPT = '/usr/local/sbin/wg-server-add-peer.sh';
const WG_REMOVE_PEER_SCRIPT = '/usr/local/sbin/wg-server-remove-peer.sh';
const WG_INTERFACE = 'wg0';
/** Salida de `command -v wg` en el hub (típ. /usr/bin/wg o /bin/wg). */
const WG_CLI = '/usr/bin/wg';
