#!/bin/bash
# Agrega un [Peer] a /etc/wireguard/wg0.conf y recarga.
# Uso: sudo bash wg-server-add-peer.sh chavon_mikrotik 'BASE64_PUBLICKEY_CLIENT' 10.64.0.16
set -euo pipefail

WG_IF="${WG_IF:-wg0}"
CONF="/etc/wireguard/${WG_IF}.conf"

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Corré con sudo."
  exit 1
fi

if [[ $# -lt 3 ]]; then
  echo "Uso: $0 <comentario_sin_espacios> <clave_publica_cliente_wg> <ip_tunnel_cliente/32>"
  echo "Ej: $0 mk_chavon 'hOQoR8…=' 10.64.0.16/32"
  exit 1
fi

COMMENT="$1"
PUB="$2"
TUN_IP="$3"

if [[ ! -f "${CONF}" ]]; then
  echo "No existe ${CONF}. Primero ejecutá prepare-wireguard-hub.sh"
  exit 1
fi

if grep -q "PublicKey = ${PUB}" "${CONF}"; then
  echo "Esa clave pública ya está en ${CONF}."
  exit 1
fi

{
  echo ""
  echo "# ${COMMENT}"
  echo "[Peer]"
  echo "PublicKey = ${PUB}"
  echo "AllowedIPs = ${TUN_IP}"
} >> "${CONF}"

chmod 600 "${CONF}"

if systemctl is-active --quiet "wg-quick@${WG_IF}.service" 2>/dev/null; then
  wg syncconf "${WG_IF}" <(wg-quick strip "${CONF}")
else
  systemctl start "wg-quick@${WG_IF}.service"
fi

echo "Peer '${COMMENT}' agregado (${TUN_IP})."
echo "Estado: wg show ${WG_IF}"
