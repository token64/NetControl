#!/bin/bash
# Actualiza solo /panel desde el ZIP de GitHub (no toca config.php del servidor).
# Ejecutar en el CT como root si git pull no está disponible o el repo está desactualizado:
#   bash /var/www/NetControl/deploy/download-panel-from-github.sh
set -euo pipefail
TARGET="${NETCONTROL_DIR:-/var/www/NetControl}"
REPO_ZIP="${NETCONTROL_ZIP_URL:-https://github.com/token64/NetControl/archive/refs/heads/main.zip}"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

echo "Descargando último main…"
curl -fsSL -o "$TMP/nc.zip" "$REPO_ZIP"
unzip -quiet "$TMP/nc.zip" -d "$TMP"
SRC="$TMP/NetControl-main/panel"
if [[ ! -d "$SRC" ]]; then
  echo "Error: no se encontró NetControl-main/panel dentro del zip."
  exit 1
fi

echo "Copiando a ${TARGET}/panel …"
cp -a "$SRC"/* "$TARGET/panel/"
chown -R www-data:www-data "$TARGET/panel"
systemctl reload apache2
echo "Listo. Comprobar en el navegador: …/netcontrol/nc_version.php (debe mostrar nc_panel_ui_rev=3)"
