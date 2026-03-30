#!/bin/bash
# Ejecutar EN la VM vpn-hub (con sudo), desde la carpeta donde está web-panel/
# Ej: cd ~/vpn-hub && sudo bash ./install-web-panel-apache.sh
set -euo pipefail

SRC_DIR="$(cd "$(dirname "$0")" && pwd)"
PANEL_SRC="${SRC_DIR}/web-panel"
DOCROOT="/var/www/html/vpn-panel"

if [[ ! -d "${PANEL_SRC}" ]] || [[ ! -f "${PANEL_SRC}/index.php" ]]; then
  echo "No encuentro web-panel/ junto a este script (esperado: ${PANEL_SRC})."
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq apache2 libapache2-mod-php php

a2enmod -q php* 2>/dev/null || true

install -d -m 0755 "${DOCROOT}"
rm -rf "${DOCROOT:?}"/*
cp -a "${PANEL_SRC}/." "${DOCROOT}/"
chown -R root:www-data "${DOCROOT}"
find "${DOCROOT}" -type d -exec chmod 0755 {} \;
find "${DOCROOT}" -type f -exec chmod 0644 {} \;
if [[ -f "${DOCROOT}/config.php" ]]; then
  chmod 0640 "${DOCROOT}/config.php"
  chown root:www-data "${DOCROOT}/config.php"
fi

# Scripts WireGuard junto a web-panel (misma carpeta deploy/vpn-hub)
for hub_script in wg-server-add-peer.sh wg-server-remove-peer.sh; do
  if [[ -f "${SRC_DIR}/${hub_script}" ]]; then
    install -m 755 "${SRC_DIR}/${hub_script}" "/usr/local/sbin/${hub_script}"
    chown root:root "/usr/local/sbin/${hub_script}"
    echo "Instalado /usr/local/sbin/${hub_script}"
  fi
done

systemctl enable --now apache2
systemctl reload apache2

# UFW: si está activo, permitir HTTP desde la LAN (ajustá el origen si hace falta)
if command -v ufw >/dev/null && ufw status 2>/dev/null | grep -q "Status: active"; then
  ufw allow 80/tcp comment 'vpn-panel Apache' || true
  echo "UFW activo: se añadió regla 80/tcp si no existía. Revisá: sudo ufw status"
fi

echo ""
echo "Listo. Abrí en el navegador (reemplazá la IP):"
echo "  http://$(hostname -I 2>/dev/null | awk '{print $1}' || echo 'IP_DE_LA_VM')/vpn-panel/"
echo ""
echo "Siguiente: si aún no existe, sudo cp ${DOCROOT}/config.example.php ${DOCROOT}/config.php"
echo "y configurá sudoers (add + remove + wg show; ver INSTALAR-VPN-HUB.txt sección 9)."
