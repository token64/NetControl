# Actualiza /var/www/NetControl dentro de una VM Linux por la API de Proxmox (QEMU guest agent).
# Credenciales: %USERPROFILE%\.cursor\proxmox.credentials.json (no subir ese archivo a git).
#
# Requisitos en PVE: token con permiso de guest agent (p. ej. QEMUAgent) en esa VM;
# en el invitado: qemu-guest-agent activo.
#
# Uso (PowerShell, desde la raíz del repo):
#   .\deploy\proxmox-vm-guest-git-deploy.ps1
#   .\deploy\proxmox-vm-guest-git-deploy.ps1 -Vmid 109 -Node proxmox
#
# Nota: en PowerShell NO uses la variable $PID para el pid del agente; está reservada.
param(
    [string]$CredentialsPath = "$env:USERPROFILE\.cursor\proxmox.credentials.json",
    [string]$Node = "proxmox",
    [int]$Vmid = 109,
    [string]$RepoPath = "/var/www/NetControl",
    [string]$Branch = "main",
    [int]$StatusPollSeconds = 2,
    [int]$StatusPollMax = 60
)

$ErrorActionPreference = "Stop"
if (-not (Test-Path $CredentialsPath)) { throw "No existe: $CredentialsPath" }

$c = Get-Content $CredentialsPath -Raw -Encoding UTF8 | ConvertFrom-Json
$hostPve = $c.proxmox.host
$port = [int]$c.proxmox.port
$auth = "PVEAPIToken=$($c.auth.user)!$($c.auth.token_name)=$($c.auth.token_value)"
$base = "https://${hostPve}:${port}/api2/json"

$inner = @"
cd $RepoPath && git -c safe.directory=$RepoPath fetch origin $Branch && git -c safe.directory=$RepoPath reset --hard origin/$Branch && chown -R www-data:www-data panel && systemctl reload apache2 && echo OK
"@
$inner = $inner.Trim() -replace "`r`n", " "

$bodyObj = @{ command = @("bash", "-lc", $inner) }
$tmpJson = Join-Path $env:TEMP ("pve-agent-exec-{0}.json" -f [guid]::NewGuid().ToString("n"))
try {
    $json = $bodyObj | ConvertTo-Json -Compress -Depth 5
    $enc = New-Object System.Text.UTF8Encoding $false
    [System.IO.File]::WriteAllText($tmpJson, $json, $enc)
    $execUrl = "$base/nodes/$Node/qemu/$Vmid/agent/exec"
    $raw = & curl.exe -sk -X POST -H "Authorization: $auth" -H "Content-Type: application/json" --data-binary "@$tmpJson" $execUrl 2>$null
    if (-not $raw) { throw "Sin respuesta: POST $execUrl" }
    $exec = $raw | ConvertFrom-Json
    if (-not $exec.data -or $null -eq $exec.data.pid) {
        throw "agent/exec no devolvió pid: $raw"
    }
    $guestPid = $exec.data.pid
    Write-Host "PVE agent/exec pid=$guestPid (VM $Vmid en nodo $Node)"

    $statusUrl = "$base/nodes/$Node/qemu/$Vmid/agent/exec-status"
    $deadline = (Get-Date).AddSeconds($StatusPollSeconds * $StatusPollMax)
    while ((Get-Date) -lt $deadline) {
        Start-Sleep -Seconds $StatusPollSeconds
        $stRaw = & curl.exe -sk -H "Authorization: $auth" "${statusUrl}?pid=$guestPid" 2>$null
        if (-not $stRaw) { continue }
        $st = $stRaw | ConvertFrom-Json
        if ($st.data -and $st.data.exited -eq 1) {
            if ($st.data.'out-data') { Write-Host $st.data.'out-data' }
            if ($st.data.'err-data') { Write-Host $st.data.'err-data' }
            $code = $st.data.exitcode
            if ($code -ne 0) { throw "Comando en el invitado terminó con exitcode=$code" }
            Write-Host "Listo."
            exit 0
        }
    }
    throw "Timeout esperando exec-status para pid=$guestPid"
}
finally {
    if (Test-Path $tmpJson) { Remove-Item -Force $tmpJson }
}
