#!/bin/bash
# NetControl — preparar VM Ubuntu como hub WireGuard (red 10.64.0.0/24; no reutilizar rangos de otras VPN).
# Ejecutar EN la VM vpn-hub tras instalar Ubuntu Server:
#   sudo bash prepare-wireguard-hub.sh
set -euo pipefail

WG_IF="wg0"
WG_PORT="${WG_PORT:-51820}"
WG_NET="10.64.0.0/24"
SRV_TUN_IP="10.64.0.1/24"

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Corré con sudo."
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq wireguard wireguard-tools iptables

WAN_IF="$(ip -4 route show default | awk '{print $5; exit}')"
if [[ -z "${WAN_IF}" ]]; then
  echo "No se detectó interfaz WAN (ruta default). Configurá red y reintentá."
  exit 1
fi
echo "WAN detectada: ${WAN_IF}"

install -d -m 700 /etc/wireguard
cd /etc/wireguard

if [[ ! -f server_private.key ]]; then
  umask 077
  wg genkey | tee server_private.key | wg pubkey > server_public.key
fi
SERV_PRIV="$(cat server_private.key)"
SERV_PUB="$(cat server_public.key)"

cat >"/etc/wireguard/${WG_IF}.conf" <<EOF
# Hub NetControl — agregá [Peer] con wg-server-add-peer.sh
[Interface]
PrivateKey = ${SERV_PRIV}
Address = ${SRV_TUN_IP}
ListenPort = ${WG_PORT}

# NAT: clientes VPN salen a internet por esta VM (opcional; comentá si no querés)
PostUp = iptables -A FORWARD -i ${WG_IF} -j ACCEPT; iptables -A FORWARD -o ${WG_IF} -j ACCEPT; iptables -t nat -A POSTROUTING -o ${WAN_IF} -j MASQUERADE
PostDown = iptables -D FORWARD -i ${WG_IF} -j ACCEPT; iptables -D FORWARD -o ${WG_IF} -j ACCEPT; iptables -t nat -D POSTROUTING -o ${WAN_IF} -j MASQUERADE
EOF
chmod 600 "/etc/wireguard/${WG_IF}.conf"

sysctl -w net.ipv4.ip_forward=1
grep -q '^net.ipv4.ip_forward=' /etc/sysctl.d/99-netcontrol-vpn.conf 2>/dev/null || \
  echo 'net.ipv4.ip_forward=1' > /etc/sysctl.d/99-netcontrol-vpn.conf

if command -v ufw >/dev/null 2>&1; then
  ufw allow "${WG_PORT}/udp" comment 'WireGuard hub'
  # Permitir reenvío (si UFW está activo con default deny forward, activar forward)
  sed -i 's/^DEFAULT_FORWARD_POLICY=.*/DEFAULT_FORWARD_POLICY="ACCEPT"/' /etc/default/ufw 2>/dev/null || true
  echo "Revisá UFW: si bloquea, ejecutá 'ufw route allow in on ${WG_IF}' y 'ufw reload'."
fi

systemctl enable --now "wg-quick@${WG_IF}"

echo ""
echo "=== Hub WireGuard listo ==="
echo "Puerto UDP: ${WG_PORT}  (abrilo en firewall externo / router hacia esta VM si aplica)"
echo "Red túnel:  ${WG_NET}  (servidor = 10.64.0.1)"
echo "Clave pública del servidor (pegala en el cliente MikroTik / Linux):"
echo "${SERV_PUB}"
echo ""
echo "Agregar un cliente (clave pública del remote + IP fija 10.64.0.x):"
echo "  sudo bash wg-server-add-peer.sh NOMBRE CLIENT_PUBKEY 10.64.0.16"
echo "En NetControl → Routers usá la IP del túnel del MK (ej. 10.64.0.16) y API."
