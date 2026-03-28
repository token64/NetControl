# Encuentra el CT NetControl por IP en la config y muestra el comando exacto para actualizar código.
# Lee token de %USERPROFILE%\.cursor\proxmox.credentials.json (no subir ese archivo a git).
#
# Uso (PowerShell): .\deploy\pve-sync-netcontrol.ps1
#
# Nota PVE 9.x: la API NO implementa POST .../lxc/{vmid}/exec. Hay que ejecutar el "pct exec" en una
# consola del nodo Proxmox (Shell del nodo en la web de PVE) o con SSH al hipervisor.
param(
    [string]$CredentialsPath = "$env:USERPROFILE\.cursor\proxmox.credentials.json",
    [string]$TargetIp = "192.168.13.73"
)

$ErrorActionPreference = "Stop"
if (-not (Test-Path $CredentialsPath)) { throw "No existe: $CredentialsPath" }

$c = Get-Content $CredentialsPath -Raw -Encoding UTF8 | ConvertFrom-Json
$hostPve = $c.proxmox.host
$port = [int]$c.proxmox.port
$auth = "PVEAPIToken=$($c.auth.user)!$($c.auth.token_name)=$($c.auth.token_value)"
$base = "https://${hostPve}:${port}/api2/json"

function Invoke-Pve {
    param([string]$Path)
    $url = "$base$Path"
    $out = & curl.exe -sk -H "Authorization: $auth" $url 2>$null
    if (-not $out) { throw "Sin respuesta: $url" }
    return $out | ConvertFrom-Json
}

Write-Host "PVE: probando API..."
$ver = Invoke-Pve "/version"
if (-not $ver.data) { throw "API no respondió bien: $($ver | ConvertTo-Json -Compress)" }
Write-Host "  OK - PVE release $($ver.data.release)"

$nodes = (Invoke-Pve "/nodes").data
$found = $null
foreach ($n in $nodes) {
    $node = $n.node
    $list = (Invoke-Pve "/nodes/$node/lxc").data
    foreach ($ct in $list) {
        $vmid = $ct.vmid
        $cfg = (Invoke-Pve "/nodes/$node/lxc/$vmid/config").data
        $blob = ($cfg | ConvertTo-Json -Compress)
        if ($blob -match [regex]::Escape($TargetIp)) {
            $found = [pscustomobject]@{ Node = $node; Vmid = $vmid; Name = $ct.name }
            break
        }
    }
    if ($found) { break }
}

if (-not $found) {
    Write-Host "No encontré un LXC cuya config mencione $TargetIp. Listado rápido de CT:"
    foreach ($n in $nodes) {
        $node = $n.node
        (Invoke-Pve "/nodes/$node/lxc").data | ForEach-Object { Write-Host "  $node / $($_.vmid)  $($_.name)  status=$($_.status)" }
    }
    throw "Ajustá TargetIp o elegí node+vmid a mano."
}

Write-Host "CT candidato: nodo=$($found.Node) vmid=$($found.Vmid) name=$($found.Name)"

$cmd = 'cd /var/www/NetControl && git fetch origin && git reset --hard origin/main && chown -R www-data:www-data panel && systemctl reload apache2'

Write-Host ""
Write-Host "=== Copiá y pegá esto en la Shell del nodo Proxmox (interfaz web: nodo -> Shell), como root ==="
Write-Host "pct exec $($found.Vmid) -- bash -lc '$cmd'"
Write-Host "============================================================================"
Write-Host ""
Write-Host "O desde tu PC, si tenés SSH al hipervisor sin contraseña:"
Write-Host "ssh root@$hostPve `"pct exec $($found.Vmid) -- bash -lc '$cmd'`""
