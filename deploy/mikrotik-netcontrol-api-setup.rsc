# =============================================================================
# NetControl — preparar API RouterOS para el panel PHP
# Pegá este bloque en Terminal (Winbox → Terminal) o importá el .rsc
#
# ANTES: editá en los bloques :local … los valores reales:
#   panelIp = IP desde la cual el MikroTik VE al servidor NetControl
#             (LAN del panel, o IP del servidor en la VPN — MikroWisp suele usar 10.8.0.x;
#              hub WireGuard de este repo: 10.64.0.0/24, ej. 10.64.0.16 en NetControl)
#   apiUser / apiPass = mismo usuario/clave que cargás en NetControl → Routers
# =============================================================================

:local panelIp "192.168.1.100"
:local apiUser "netcontrol"
:local apiPass "CambiarEstaClaveSegura"

# Servicio API plano (NetControl suele usar puerto 8728; si usás TLS, activá api-ssl y certificado aparte)
/ip service set [find name=api] disabled=no port=8728

# Grupo solo para API / lectura-escritura que pide el panel (PPP secrets, colas, etc.)
:if ([:len [/user group find name=netcontrol-api]] = 0) do={
  /user group add name=netcontrol-api policy=read,write,api,test
}

# Usuario API (si ya existe, solo actualiza grupo y password)
:if ([:len [/user find name=$apiUser]] = 0) do={
  /user add name=$apiUser password=$apiPass group=netcontrol-api comment="NetControl panel WISP"
} else={
  /user set [find name=$apiUser] password=$apiPass group=netcontrol-api comment="NetControl panel WISP"
}

# Firewall input: permitir API solo desde el panel (recomendado)
# Si no usás firewall en input, esta regla no molesta; si tenés un drop final,
# mové esta regla ARRIBA del drop en IP → Firewall → Filter.
:local fwComment "NetControl API desde panel"
:if ([:len [/ip firewall filter find comment=$fwComment]] = 0) do={
  /ip firewall filter add chain=input action=accept protocol=tcp dst-port=8728 src-address=$panelIp comment=$fwComment place-before=0
}

:put "Listo. En NetControl: Routers + Diagnóstico API con esta IP/host y usuario configurados."
:put ("API usuario=" . $apiUser . " puerto=8728 src permitido en input=" . $panelIp)
