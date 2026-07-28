# Canlı deploy: lokal commit + GitHub push + sunucuda git pull
# Kullanım:
#   .\tools\deploy.ps1 -Message "Sipariş formu düzeltildi"
#   .\tools\deploy.ps1 -Message "Hotfix" -SkipCommit   # sadece push + pull (commit zaten varsa)

param(
    [Parameter(Mandatory = $true)]
    [string]$Message,

    [switch]$SkipCommit
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$configPath = Join-Path $PSScriptRoot "deploy.config.local.json"
$examplePath = Join-Path $PSScriptRoot "deploy.config.example.json"

if (-not (Test-Path $configPath)) {
    Write-Host ""
    Write-Host "HATA: deploy.config.local.json bulunamadi." -ForegroundColor Red
    Write-Host "Once sunucu bilgilerini girin:" -ForegroundColor Yellow
    Write-Host "  copy tools\deploy.config.example.json tools\deploy.config.local.json" -ForegroundColor Cyan
    Write-Host "  notepad tools\deploy.config.local.json" -ForegroundColor Cyan
    Write-Host ""
    exit 1
}

$config = Get-Content $configPath -Raw | ConvertFrom-Json
$branch = if ($config.branch) { $config.branch } else { "develop" }
$remote = if ($config.remote) { $config.remote } else { "origin" }
$hostName = $config.server.host
$sshUser = $config.server.user
$sshPort = if ($config.server.port) { $config.server.port } else { 22 }
$remotePath = $config.server.path

if ([string]::IsNullOrWhiteSpace($hostName) -or $hostName -eq "SUNUCU_IP_VEYA_HOST") {
    Write-Host "HATA: deploy.config.local.json icinde server.host doldurulmali." -ForegroundColor Red
    exit 1
}

Set-Location $root

Write-Host ""
Write-Host "=== 1/3 LOKAL (Git) ===" -ForegroundColor Green

$status = git status --porcelain
if ($status -and -not $SkipCommit) {
    git add -A
    git commit -m $Message
    Write-Host "Commit olusturuldu." -ForegroundColor Gray
} elseif ($status -and $SkipCommit) {
    Write-Host "UYARI: Commit edilmemis degisiklik var (-SkipCommit kullanildi)." -ForegroundColor Yellow
} else {
    Write-Host "Yeni commit yok, mevcut branch push edilecek." -ForegroundColor Gray
}

Write-Host ""
Write-Host "=== 2/3 GITHUB (push) ===" -ForegroundColor Green
git push $remote $branch
Write-Host "GitHub guncellendi: $remote/$branch" -ForegroundColor Gray

Write-Host ""
Write-Host "=== 3/3 CANLI SUNUCU (git pull) ===" -ForegroundColor Green

$pullCmd = "cd '$remotePath' && if [ -d .git ]; then git fetch $remote && git checkout $branch && git pull $remote $branch; else echo 'HATA: .git yok - once git clone yapin'; exit 1; fi"

if ($config.post_pull_commands) {
    foreach ($cmd in $config.post_pull_commands) {
        if (-not [string]::IsNullOrWhiteSpace($cmd)) {
            $pullCmd += " && $cmd"
        }
    }
}

ssh -p $sshPort "${sshUser}@${hostName}" $pullCmd

if ($LASTEXITCODE -ne 0) {
    Write-Host ""
    Write-Host "HATA: Sunucu guncellemesi basarisiz (SSH veya git pull)." -ForegroundColor Red
    exit $LASTEXITCODE
}

Write-Host ""
Write-Host "CANLI DEPLOY TAMAMLANDI." -ForegroundColor Green
Write-Host "Branch: $branch | Sunucu: ${sshUser}@${hostName}:$remotePath" -ForegroundColor Gray
Write-Host ""
