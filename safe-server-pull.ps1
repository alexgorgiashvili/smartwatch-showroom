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

$configPath = Join-Path $PSScriptRoot ".deploy.local.ps1"
if (Test-Path $configPath) {
    . $configPath
}

$hostName = if ([string]::IsNullOrWhiteSpace($DEPLOY_SSH_HOST)) { $env:DEPLOY_SSH_HOST } else { $DEPLOY_SSH_HOST }
$userName = if ([string]::IsNullOrWhiteSpace($DEPLOY_SSH_USER)) { $env:DEPLOY_SSH_USER } else { $DEPLOY_SSH_USER }
$deployPath = if ([string]::IsNullOrWhiteSpace($DEPLOY_SSH_PATH)) { $env:DEPLOY_SSH_PATH } else { $DEPLOY_SSH_PATH }
$portValue = if ([string]::IsNullOrWhiteSpace($DEPLOY_SSH_PORT)) { $env:DEPLOY_SSH_PORT } else { $DEPLOY_SSH_PORT }
$branchValue = if ([string]::IsNullOrWhiteSpace($DEPLOY_BRANCH)) { $env:DEPLOY_BRANCH } else { $DEPLOY_BRANCH }
$port = if ([string]::IsNullOrWhiteSpace($portValue)) { "22" } else { $portValue }
$branch = if ([string]::IsNullOrWhiteSpace($branchValue)) { "main" } else { $branchValue }

if ([string]::IsNullOrWhiteSpace($hostName) -or [string]::IsNullOrWhiteSpace($userName) -or [string]::IsNullOrWhiteSpace($deployPath)) {
    Write-Host "Missing deploy configuration." -ForegroundColor Yellow
    Write-Host "Create .deploy.local.ps1 from .deploy.local.example.ps1 or set DEPLOY_SSH_HOST, DEPLOY_SSH_USER, and DEPLOY_SSH_PATH." -ForegroundColor Yellow
    exit 1
}

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
