#Requires -Version 5.1
<#
.SYNOPSIS
  Sube tu copia local de NetControl al servidor (VM/CT) vía SSH, sin depender de git push.

.DESCRIPTION
  Genera un .tgz en tu PC (excluye .git y .cursor), lo copia a /tmp del remoto y lo descomprime
  en /var/www/NetControl con tar. Por defecto NO sobrescribe panel/config.php del servidor.

.NOTAS
  Requisitos: OpenSSH (scp/ssh) en el PATH de Windows; usuario remoto con sudo sin contraseña
  o que podás introducir la contraseña cuando ssh lo pida (varias veces si no hay clave SSH).

.EXAMPLE
  cd C:\xampp\htdocs\NetControl
  .\deploy\sync-local-to-remote.ps1 -RemoteHost 192.168.13.73 -RemoteUser jose

.EXAMPLE
  Sobrescribir también config.php (cuidado: pisás la config del servidor con la de tu PC):
  .\deploy\sync-local-to-remote.ps1 -RemoteHost 192.168.13.50 -RemoteUser jose -IncludePanelConfig
#>
param(
    [Parameter(Mandatory = $false)]
    [string]$RemoteHost = "192.168.13.73",

    [Parameter(Mandatory = $false)]
    [string]$RemoteUser = "jose",

    [Parameter(Mandatory = $false)]
    [string]$RemotePath = "/var/www/NetControl",

    [string]$LocalRoot = "",

    [switch]$IncludePanelConfig
)

$ErrorActionPreference = "Stop"
if ($LocalRoot -eq "") {
    $LocalRoot = Split-Path -Parent $PSScriptRoot
}
if (-not (Test-Path (Join-Path $LocalRoot "panel"))) {
    throw "No parece la raíz del repo NetControl (falta panel\): $LocalRoot"
}

$remote = "${RemoteUser}@${RemoteHost}"
$tmp = Join-Path $env:TEMP ("netcontrol-sync-{0}.tgz" -f ([Guid]::NewGuid().ToString("N").Substring(0, 8)))

Write-Host "Empaquetando desde: $LocalRoot" -ForegroundColor Cyan
$excludes = @(
    "--exclude=.git",
    "--exclude=.cursor",
    "--exclude=*.tgz"
)
if (-not $IncludePanelConfig) {
    $excludes += "--exclude=panel/config.php"
    Write-Host "Excluyendo panel/config.php (usá -IncludePanelConfig para incluirlo)." -ForegroundColor Yellow
}

Push-Location $LocalRoot
try {
    & tar.exe -czf $tmp @excludes .
}
finally {
    Pop-Location
}

if (-not (Test-Path $tmp) -or (Get-Item $tmp).Length -lt 100) {
    throw "No se generó el tarball o quedó vacío: $tmp"
}

Write-Host "Subiendo a ${remote}:/tmp/netcontrol-sync.tgz ..." -ForegroundColor Cyan
& scp.exe $tmp "${remote}:/tmp/netcontrol-sync.tgz"

$extract = @"
set -e
sudo mkdir -p '$RemotePath'
sudo tar -xzf /tmp/netcontrol-sync.tgz -C '$RemotePath'
sudo chown -R www-data:www-data '$RemotePath/panel'
sudo systemctl reload apache2 || true
rm -f /tmp/netcontrol-sync.tgz
echo OK
"@

Write-Host "Extrayendo en $RemotePath y recargando Apache..." -ForegroundColor Cyan
$extract | & ssh.exe -t $remote "bash -s"

Remove-Item -Force $tmp -ErrorAction SilentlyContinue
Write-Host "Listo. Probar: http://${RemoteHost}/netcontrol/nc_version.php" -ForegroundColor Green
Write-Host "Alternativa estable: git push desde tu PC + git pull en el servidor (deploy/server-git-pull.sh)." -ForegroundColor DarkGray
