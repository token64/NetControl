#!/bin/bash
# Quita un bloque [Peer] de /etc/wireguard/wg0.conf por su clave PÚBLICA del cliente.
# Si la clave no está en el archivo pero sí en el kernel: wg set peer remove (evita syncconf roto).
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

TMP="$(mktemp)"
export CONF PUB TMP WG_IF

set +e
python3 <<'PY'
import os, re, subprocess, sys

conf = os.environ["CONF"]
want = os.environ["PUB"].strip()
path_tmp = os.environ["TMP"]
wg_if = os.environ["WG_IF"]


def parse_pubkey_token(line: str):
    s = line.strip()
    m = re.match(r"^PublicKey\s*=\s*(.+)$", s, re.I)
    if not m:
        return None
    tok = m.group(1).strip().split()[0]
    return tok.replace("\r", "")


def peer_in_dump() -> bool:
    r = subprocess.run(
        ["wg", "show", wg_if, "dump"],
        capture_output=True,
        text=True,
        check=False,
    )
    if r.returncode != 0:
        print((r.stderr or r.stdout or "wg dump falló").strip(), file=sys.stderr)
        sys.exit(1)
    first = True
    for line in r.stdout.splitlines():
        line = line.strip()
        if not line:
            continue
        parts = line.split("\t")
        if first:
            first = False
            continue
        if parts and parts[0] == want:
            return True
    return False


with open(conf, "r", encoding="utf-8") as f:
    lines = f.read().splitlines(keepends=True)

pk_line = None
for i, line in enumerate(lines):
    got = parse_pubkey_token(line)
    if got is not None and got == want:
        pk_line = i
        break

if pk_line is not None:
    if pk_line < 1 or lines[pk_line - 1].strip() != "[Peer]":
        print("Bloque mal formado: falta [Peer] antes de PublicKey.", file=sys.stderr)
        sys.exit(1)

    peer_line = pk_line - 1
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
        break

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
    sys.exit(0)

if not peer_in_dump():
    print(
        f"No hay peer con esa clave en {conf} ni en la interfaz {wg_if} (quizá ya fue quitado).",
        file=sys.stderr,
    )
    sys.exit(2)

print(
    f"Nota: la clave no está en {conf} pero sí en {wg_if}; "
    "se quitará solo ese peer del kernel (wg set … remove), sin reparsear todo el .conf.",
    file=sys.stderr,
)
sys.exit(3)
PY
code=$?
set -e

if [[ "${code}" -eq 0 ]]; then
  mv -f "$TMP" "${CONF}"
  chmod 600 "${CONF}"
elif [[ "${code}" -eq 2 ]]; then
  rm -f "$TMP"
  echo "Listo (peer ya no figuraba; nada que aplicar)."
  exit 0
elif [[ "${code}" -eq 3 ]]; then
  rm -f "$TMP"
  if ! wg set "${WG_IF}" peer "${PUB}" remove; then
    echo "wg set peer remove falló (¿interfaz ${WG_IF} caída?)." >&2
    exit 1
  fi
  echo "Listo — peer huérfano quitado del kernel (${CONF} sin cambios). Últimos 6 de la clave: …${PUB: -6}"
  exit 0
else
  rm -f "$TMP"
  exit "${code}"
fi

if systemctl is-active --quiet "wg-quick@${WG_IF}.service" 2>/dev/null; then
  if ! sync_err=$(wg syncconf "${WG_IF}" <(wg-quick strip "${CONF}") 2>&1); then
    echo "${sync_err}" >&2
    echo "Nota: wg syncconf falló (revisá ${CONF}: no pongas PublicKey en [Interface], solo PrivateKey). Se aplica wg set peer remove." >&2
    wg set "${WG_IF}" peer "${PUB}" remove
  fi
else
  systemctl start "wg-quick@${WG_IF}.service"
fi

echo "Listo (últimos 6 de la clave: …${PUB: -6}). Estado: wg show ${WG_IF}"
