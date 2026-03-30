#!/bin/bash
# En la VM (como usuario con sudo): muestra credenciales o qué falta.
#   bash deploy/show-netcontrol-credentials.sh
#   curl -fsSL https://raw.githubusercontent.com/token64/NetControl/main/deploy/show-netcontrol-credentials.sh | sudo bash
set -euo pipefail

CREDS="/root/netcontrol-credentials.txt"
CFG="/var/www/NetControl/panel/config.php"

if [[ -f "$CREDS" ]]; then
  echo "=== $CREDS ==="
  if [[ -r "$CREDS" ]]; then cat "$CREDS"; else sudo cat "$CREDS"; fi
  exit 0
fi

echo "No existe: $CREDS"
echo "(Nombre correcto: netcontrol-credentials.txt — con «control».)"
echo ""

if [[ -f "$CFG" ]]; then
  echo "Hay panel instalado. Extraído de $CFG (MySQL + nombre BD):"
  if [[ -r "$CFG" ]]; then
    grep -E "^const (DB_USER|DB_NAME|DB_PASS)" "$CFG" || true
  else
    sudo grep -E "^const (DB_USER|DB_NAME|DB_PASS)" "$CFG" || true
  fi
  echo ""
  echo "Panel web: http://$(hostname -I | awk '{print $1}')/netcontrol/"
  exit 0
fi

echo "No hay $CFG. Corré el bootstrap como root:"
echo "  curl -fsSL https://raw.githubusercontent.com/token64/NetControl/main/deploy/lxc-bootstrap.sh | sudo bash"
exit 1
