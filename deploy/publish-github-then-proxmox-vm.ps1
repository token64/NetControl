#Requires -Version 5.1
<#
.SYNOPSIS
  Flujo recomendado: subir commits a GitHub y actualizar la VM del panel vía API Proxmox (guest agent).

.DESCRIPTION
  1) git push origin <rama> desde la raíz del repo (tu copia queda reflejada en GitHub).
  2) Ejecuta en la VM (vmid 109 por defecto) fetch + reset --hard a origin/<rama> vía
     deploy/proxmox-vm-guest-git-deploy.ps1 (sin SSH a la VM).

  Por defecto exige árbol de trabajo limpio (sin cambios sin commitear), para que GitHub
  y la VM coincidan con lo que realmente versionaste.

.EXAMPLE
  cd C:\xampp\htdocs\NetControl
  .\deploy\publish-github-then-proxmox-vm.ps1

.EXAMPLE
  Solo refrescar la VM desde GitHub (sin push desde esta PC):
  .\deploy\publish-github-then-proxmox-vm.ps1 -SkipGitPush

.EXAMPLE
  Empujar aunque queden archivos sin commitear (no van a GitHub hasta commitear):
  .\deploy\publish-github-then-proxmox-vm.ps1 -AllowDirty
#>
param(
    [string]$LocalRoot = "",
    [string]$Branch = "",
    [switch]$SkipGitPush,
    [switch]$AllowDirty
)

$ErrorActionPreference = "Stop"
if ($LocalRoot -eq "") {
    $LocalRoot = Split-Path -Parent $PSScriptRoot
}
if (-not (Test-Path (Join-Path $LocalRoot ".git"))) {
    throw "No parece un repo git (falta .git): $LocalRoot"
}

if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
    throw "git no está en PATH."
}

$deploy = Join-Path $PSScriptRoot "proxmox-vm-guest-git-deploy.ps1"
if (-not (Test-Path $deploy)) {
    throw "No existe: $deploy"
}

Push-Location $LocalRoot
try {
    if ($Branch -eq "") {
        $Branch = (git rev-parse --abbrev-ref HEAD).Trim()
        if ($Branch -eq "HEAD") {
            throw "HEAD detached; indicá -Branch explícitamente (ej. main)."
        }
    }

    if (-not $SkipGitPush) {
        if (-not $AllowDirty) {
            $dirty = git status --porcelain
            if ($dirty) {
                throw "Hay cambios sin commitear. Hacé commit (o stash) antes del push, o usá -AllowDirty.`n$dirty"
            }
        }
        Write-Host "git push origin $Branch ..." -ForegroundColor Cyan
        git push origin $Branch
        if ($LASTEXITCODE -ne 0) {
            throw "git push falló (código $LASTEXITCODE)."
        }
    } else {
        Write-Host "Omitiendo git push (-SkipGitPush)." -ForegroundColor Yellow
    }
}
finally {
    Pop-Location
}

Write-Host "VM: fetch + reset --hard origin/$Branch (Proxmox guest agent) ..." -ForegroundColor Cyan
& $deploy -Branch $Branch
if ($LASTEXITCODE -ne 0) {
    throw "proxmox-vm-guest-git-deploy.ps1 terminó con código $LASTEXITCODE."
}
Write-Host "Publicación lista (GitHub + VM alineados en rama $Branch)." -ForegroundColor Green
