<?php
declare(strict_types=1);

/**
 * Copiar a config.php y editar. En la VM: subir esta carpeta a /var/www/html/vpn-panel
 * o al vhost que uses (solo HTTPS en producción).
 *
 * Sudo sin contraseña (ejemplo; ajustar ruta del script):
 *
 *   sudo visudo -f /etc/sudoers.d/nc-vpn-panel
 *
 *   www-data ALL=(root) NOPASSWD: /bin/bash /ruta/completa/wg-server-add-peer.sh
 *   www-data ALL=(root) NOPASSWD: /usr/bin/wg show wg0
 *
 * Sin la segunda línea, el formulario funciona pero el bloque “wg show” fallará.
 */
const NC_VPN_PANEL_PASSWORD = 'cambiar_por_clave_larga';
const WG_ADD_PEER_SCRIPT = '/home/jose/vpn-hub/wg-server-add-peer.sh';
const WG_INTERFACE = 'wg0';
