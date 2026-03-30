#!/bin/bash
# Quita un bloque [Peer] de /etc/wireguard/wg0.conf por su clave PÚBLICA del cliente.
# Uso: sudo bash wg-server-remove-peer.sh 'BASE64_PUBLICKEY_CLIENTE'
set -euo pipefail

WG_IF="${WG_IF:-wg0}"
CONF="/etc/wireguard/${WG_IF}.conf"

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Corré con sudo."
  exit 1
fi

if [[ $# -lt 1 ]]; then
  echo "Uso: $0 <clave_publica_peer>"
  exit 1
fi

PUB="$(echo "$1" | tr -d '[:space:]')"

if [[ ! -f "${CONF}" ]]; then
  echo "No existe ${CONF}."
  exit 1
fi

if ! grep -q "PublicKey = ${PUB}" "${CONF}"; then
  echo "No hay ningún peer con esa clave pública en ${CONF}."
  exit 1
fi

TMP="$(mktemp)"
export CONF PUB TMP
python3 <<'PY'
import os, sys

conf = os.environ["CONF"]
want = os.environ["PUB"].strip()
path_tmp = os.environ["TMP"]

with open(conf, "r", encoding="utf-8") as f:
    lines = f.read().splitlines(keepends=True)

# índice de la línea PublicKey = want
pk_line = None
for i, line in enumerate(lines):
    s = line.strip()
    if not s.startswith("PublicKey ="):
        continue
    got = s.split("=", 1)[1].strip()
    if got == want:
        pk_line = i
        break
if pk_line is None:
    print("No se encontró el peer (clave pública).", file=sys.stderr)
    sys.exit(1)

if pk_line < 1 or lines[pk_line - 1].strip() != "[Peer]":
    print("Bloque mal formado: falta [Peer] antes de PublicKey.", file=sys.stderr)
    sys.exit(1)

peer_line = pk_line - 1
# Subir comentarios (#…) y una línea en blanco previa (formato de add-peer.sh)
start = peer_line
i = peer_line - 1
while i >= 0:
    t = lines[i].rstrip("\n\r")
    if t.strip().startswith("#"):
        start = i
        i -= 1
        continue
    if t.strip() == "":
        start = i
        i -= 1
        break
    # línea de otro bloque (p.ej. PostDown, AllowedIPs anterior): no consumir
    break

# Bajar hasta AllowedIPs de este peer
end = pk_line
while end < len(lines):
    if lines[end].strip().startswith("AllowedIPs"):
        end += 1
        break
    end += 1
else:
    print("Bloque sin AllowedIPs.", file=sys.stderr)
    sys.exit(1)

new_lines = lines[:start] + lines[end:]
with open(path_tmp, "w", encoding="utf-8") as f:
    f.writelines(new_lines)
PY

mv -f "$TMP" "${CONF}"
chmod 600 "${CONF}"

if systemctl is-active --quiet "wg-quick@${WG_IF}.service" 2>/dev/null; then
  wg syncconf "${WG_IF}" <(wg-quick strip "${CONF}")
else
  systemctl start "wg-quick@${WG_IF}.service"
fi

echo "Peer eliminado (últimos 6 de la clave: …${PUB: -6})."
echo "Estado: wg show ${WG_IF}"
