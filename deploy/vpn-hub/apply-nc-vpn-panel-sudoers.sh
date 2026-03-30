#!/bin/bash
# Escribe /etc/sudoers.d/nc-vpn-panel para que www-data pueda ejecutar los scripts del panel
# sin contraseña. Incluye líneas con y sin '*' (sudo no matchea * cuando no hay argumentos).
#
# Uso EN la VM vpn-hub:
#   cd ~/NetControl && git pull
#   sudo bash deploy/vpn-hub/apply-nc-vpn-panel-sudoers.sh
#
# Rutas alternativas (si los .sh no están en /usr/local/sbin):
#   sudo ADD_SCRIPT=/ruta/add.sh REMOVE_SCRIPT=/ruta/remove.sh bash deploy/vpn-hub/apply-nc-vpn-panel-sudoers.sh
set -euo pipefail

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Ejecutá con sudo: sudo bash $0" >&2
  exit 1
fi

ADD_SCRIPT="${ADD_SCRIPT:-/usr/local/sbin/wg-server-add-peer.sh}"
REMOVE_SCRIPT="${REMOVE_SCRIPT:-/usr/local/sbin/wg-server-remove-peer.sh}"
WG_BIN="${WG_BIN:-$(command -v wg || true)}"
if [[ -z "${WG_BIN}" ]]; then
  WG_BIN=/usr/bin/wg
fi

# Interfaz por defecto del hub (solo afecta la línea "wg show")
WG_IF="${WG_IF:-wg0}"

TMP="$(mktemp)"
{
  echo "# NetControl vpn-panel — generado por apply-nc-vpn-panel-sudoers.sh"
  echo "www-data ALL=(root) NOPASSWD: /bin/bash ${ADD_SCRIPT}"
  echo "www-data ALL=(root) NOPASSWD: /bin/bash ${ADD_SCRIPT} *"
  echo "www-data ALL=(root) NOPASSWD: /bin/bash ${REMOVE_SCRIPT}"
  echo "www-data ALL=(root) NOPASSWD: /bin/bash ${REMOVE_SCRIPT} *"
  echo "www-data ALL=(root) NOPASSWD: ${WG_BIN} show ${WG_IF}"
} >"${TMP}"

install -m 0440 -o root -g root "${TMP}" /etc/sudoers.d/nc-vpn-panel
rm -f "${TMP}"

if ! visudo -c -f /etc/sudoers.d/nc-vpn-panel; then
  echo "Error de sintaxis en nc-vpn-panel; restaurá backup si hace falta." >&2
  exit 1
fi

echo "OK: /etc/sudoers.d/nc-vpn-panel"
echo "Probar: sudo -u www-data sudo -n /bin/bash ${REMOVE_SCRIPT}"
visudo -c >/dev/null
echo "visudo -c: OK"
