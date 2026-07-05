# Safe production pull shortcut
# This script only performs a fast-forward git pull on the server.
# It does NOT run migrations, cache clears, builds, composer, or queue restarts.
#
# Required environment variables:
#   DEPLOY_SSH_HOST
#   DEPLOY_SSH_USER
#   DEPLOY_SSH_PATH
#
# Optional environment variables:
#   DEPLOY_SSH_PORT (default: 22)
#   DEPLOY_BRANCH   (default: main)
#
# Usage:
#   .\safe-server-pull.ps1

$ErrorActionPreference = "Stop"

$defaultHostName = "142.132.203.78"
$defaultUserName = "mytechn1"
$defaultDeployPath = "/home/mytechn1/public_html/smartwatch-showroom"
$defaultPort = "22"

$hostName = if ([string]::IsNullOrWhiteSpace($env:DEPLOY_SSH_HOST)) { $defaultHostName } else { $env:DEPLOY_SSH_HOST }
$userName = if ([string]::IsNullOrWhiteSpace($env:DEPLOY_SSH_USER)) { $defaultUserName } else { $env:DEPLOY_SSH_USER }
$deployPath = if ([string]::IsNullOrWhiteSpace($env:DEPLOY_SSH_PATH)) { $defaultDeployPath } else { $env:DEPLOY_SSH_PATH }
$port = if ([string]::IsNullOrWhiteSpace($env:DEPLOY_SSH_PORT)) { $defaultPort } else { $env:DEPLOY_SSH_PORT }
$branch = if ([string]::IsNullOrWhiteSpace($env:DEPLOY_BRANCH)) { "main" } else { $env:DEPLOY_BRANCH }

Write-Host ""
Write-Host "Safe server pull check..." -ForegroundColor Cyan
Write-Host "Host: $userName@$hostName" -ForegroundColor Gray
Write-Host "Path: $deployPath" -ForegroundColor Gray
Write-Host "Branch: $branch" -ForegroundColor Gray
Write-Host ""

$remoteCommand = @"
set -e
cd '$deployPath'
git status --short
git pull --ff-only origin $branch
"@

ssh -p $port "$userName@$hostName" $remoteCommand

if ($LASTEXITCODE -ne 0) {
    throw "Safe server pull failed."
}

Write-Host ""
Write-Host "Safe server pull completed." -ForegroundColor Green
Write-Host ""
