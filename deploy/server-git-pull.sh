#!/bin/bash
# Ejecutar EN el servidor / CT como root (consola Proxmox o SSH):
#   bash /var/www/NetControl/deploy/server-git-pull.sh
set -euo pipefail
TARGET="${NETCONTROL_DIR:-/var/www/NetControl}"
cd "$TARGET"
git pull --ff-only
chown -R www-data:www-data "$TARGET/panel"
systemctl reload apache2
echo "NetControl: código actualizado y Apache recargado."
echo "Comprobar: http://<IP>/netcontrol/nc_version.php → nc_panel_ui_rev=4"
