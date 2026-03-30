#Requires -Version 5.1
<#
.SYNOPSIS
  Copia tu clave SSH al servidor y ejecuta el bootstrap de NetControl (desde GitHub).
.NOTES
  Ejecutalo en TU PC (PowerShell). Te pedirá contraseña SSH la primera vez, y luego sudo si hace falta.
.EXAMPLE
  cd C:\xampp\htdocs\NetControl
  .\deploy\netcontrol-remote-bootstrap.ps1
  .\deploy\netcontrol-remote-bootstrap.ps1 -HostName "192.168.13.50" -User "jose"
#>
param(
    [string]$HostName = "192.168.13.50",
    [string]$User = "jose",
    [string]$RepoUrl = "https://github.com/token64/NetControl.git"
)

$ErrorActionPreference = "Stop"
$pub = Join-Path $env:USERPROFILE ".ssh\id_ed25519.pub"
if (-not (Test-Path $pub)) {
    $pub = Join-Path $env:USERPROFILE ".ssh\id_rsa.pub"
}
if (-not (Test-Path $pub)) {
    throw "No se encontró id_ed25519.pub ni id_rsa.pub en $($env:USERPROFILE)\.ssh\"
}

$remote = "${User}@${HostName}"
Write-Host "=== 1/2 Copiando clave pública a $remote ===" -ForegroundColor Cyan
Write-Host "Si es la primera vez, ingresá la contraseña SSH de $User.`n"
Get-Content -Raw $pub | ssh $remote "mkdir -p ~/.ssh && chmod 700 ~/.ssh && touch ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys && cat >> ~/.ssh/authorized_keys"

# Script remoto: RepoUrl via base64 para no romper comillas.
$repoB64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($RepoUrl))
$remoteSh = @'
set -euo pipefail
export NETCONTROL_REPO_URL="$(echo REPO_B64_PLACEHOLDER | base64 -d)"
apt-get update -qq
apt-get install -y -qq curl ca-certificates
curl -fsSL https://raw.githubusercontent.com/token64/NetControl/main/deploy/lxc-bootstrap.sh | bash
'@.Replace('REPO_B64_PLACEHOLDER', $repoB64)
$scriptB64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($remoteSh))

Write-Host "`n=== 2/2 Bootstrap (apt + lxc-bootstrap.sh desde GitHub) ===" -ForegroundColor Cyan
Write-Host "Si sudo pide clave, ingresala.`n"
ssh -t $remote "echo $scriptB64 | base64 -d | sudo bash"

Write-Host "`nHecho. En el servidor: sudo cat /root/netcontrol-credentials.txt" -ForegroundColor Green
Write-Host "Panel: http://${HostName}/netcontrol/" -ForegroundColor Green
