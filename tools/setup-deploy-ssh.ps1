# SSH anahtari olustur ve sunucuya ekle (TEK SEFERLIK)
# Kullanim: .\tools\setup-deploy-ssh.ps1
# Sifre bir kez sorulur, sonra deploy sifresiz calisir.

$ErrorActionPreference = "Stop"
$toolsDir = $PSScriptRoot
$sshDir = Join-Path $toolsDir ".ssh"
$keyPath = Join-Path $sshDir "deploy_key"
$pubPath = "$keyPath.pub"
$configPath = Join-Path $toolsDir "deploy.config.local.json"

if (-not (Test-Path $configPath)) {
    Write-Host "HATA: once deploy.config.local.json olusturun." -ForegroundColor Red
    exit 1
}

$config = Get-Content $configPath -Raw | ConvertFrom-Json
$hostName = $config.server.host
$sshUser = $config.server.user
$sshPort = if ($config.server.port) { $config.server.port } else { 22 }

if ([string]::IsNullOrWhiteSpace($hostName)) {
    Write-Host "HATA: deploy.config.local.json icinde server.host bos." -ForegroundColor Red
    exit 1
}

New-Item -ItemType Directory -Force -Path $sshDir | Out-Null

if (-not (Test-Path $keyPath)) {
    Write-Host "SSH anahtari olusturuluyor..." -ForegroundColor Cyan
    ssh-keygen -t ed25519 -f $keyPath -N '""' -C "kosarvantilator-deploy"
} else {
    Write-Host "SSH anahtari zaten var: $keyPath" -ForegroundColor Gray
}

Write-Host ""
Write-Host "Sunucuya anahtar ekleniyor (sifre BIR KEZ sorulacak)..." -ForegroundColor Yellow
Write-Host "Hedef: ${sshUser}@${hostName}" -ForegroundColor Gray
Write-Host ""

$pubKey = Get-Content $pubPath -Raw
$remoteCmd = "mkdir -p ~/.ssh && chmod 700 ~/.ssh && grep -qxF '$($pubKey.Trim())' ~/.ssh/authorized_keys 2>/dev/null || echo '$($pubKey.Trim())' >> ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys && echo SSH_KEY_OK"

ssh -p $sshPort -o StrictHostKeyChecking=accept-new "${sshUser}@${hostName}" $remoteCmd

if ($LASTEXITCODE -ne 0) {
    Write-Host ""
    Write-Host "HATA: Anahtar sunucuya eklenemedi." -ForegroundColor Red
    exit $LASTEXITCODE
}

Write-Host ""
Write-Host "BASARILI: SSH anahtari kuruldu. Artik sifre sorulmaz." -ForegroundColor Green
Write-Host ""
Write-Host "=== GitHub Secrets (bir kez ekleyin) ===" -ForegroundColor Cyan
Write-Host "Repo > Settings > Secrets and variables > Actions > New repository secret"
Write-Host ""
Write-Host "SERVER_HOST       = $hostName"
Write-Host "SERVER_USER       = $sshUser"
Write-Host "SERVER_PORT       = $sshPort"
Write-Host "SERVER_PATH       = $($config.server.path)"
Write-Host "SSH_PRIVATE_KEY   = asagidaki dosyanin TAM icerigi:"
Write-Host "                    $keyPath"
Write-Host ""
Write-Host "Test: ssh -i `"$keyPath`" -p $sshPort ${sshUser}@${hostName} `"echo OK`""
