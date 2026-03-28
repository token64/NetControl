#!/bin/bash
# Alinear el código del CT con GitHub main cuando "git pull" falla por cambios locales.
# NO borra panel/config.php (suele estar en .gitignore).
# Ejecutar como root:
#   bash /var/www/NetControl/deploy/reset-to-origin-main.sh
set -euo pipefail
TARGET="${NETCONTROL_DIR:-/var/www/NetControl}"
cd "$TARGET"

if [[ -f panel/config.php ]]; then
  cp -a panel/config.php /root/netcontrol-config.php.bak.$(date +%Y%m%d%H%M%S)
  echo "Copia de respaldo: /root/netcontrol-config.php.bak.*"
fi

git fetch origin
# Quita archivos sin trackear que impiden traer los nuevos del repo (cuidado: solo cosas sin commit en este clon).
git clean -fd
# Descarta modificaciones en archivos trackeados y avanza a main remoto.
git reset --hard origin/main

chown -R www-data:www-data "$TARGET/panel"
systemctl reload apache2
echo "Listo: igual que origin/main. Probar: http://<IP>/netcontrol/nc_version.php (nc_panel_ui_rev=2)"
