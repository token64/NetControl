#Requires -Version 5.1
<#
.SYNOPSIS
  Sincroniza tu copia local de NetControl hacia la VM del panel en Proxmox (local = fuente de verdad).

.DESCRIPTION
  No hace pull desde el servidor: empaqueta el árbol local (tar), lo sube por SSH y lo extrae en
  /var/www/NetControl. Por defecto NO sobrescribe panel/config.php del remoto.

  IP de referencia en homelab: VM «NetControls» 192.168.13.50 (ajustá -RemoteHost si cambió).

.EXAMPLE
  cd C:\xampp\htdocs\NetControl
  .\deploy\sync-to-proxmox-netcontrol.ps1

.EXAMPLE
  Incluir también tu config.php local (pisar la del servidor):
  .\deploy\sync-to-proxmox-netcontrol.ps1 -IncludePanelConfig
#>
param(
    [string]$RemoteHost = "192.168.13.50",
    [string]$RemoteUser = "jose",
    [string]$RemotePath = "/var/www/NetControl",
    [switch]$IncludePanelConfig
)

$ErrorActionPreference = "Stop"
$sync = Join-Path $PSScriptRoot "sync-local-to-remote.ps1"
if (-not (Test-Path $sync)) { throw "No existe: $sync" }

& $sync -RemoteHost $RemoteHost -RemoteUser $RemoteUser -RemotePath $RemotePath -IncludePanelConfig:$IncludePanelConfig
