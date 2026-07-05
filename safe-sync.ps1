# Safe sync shortcut
# This script pushes the current branch only when the local tree is clean,
# then performs a fast-forward-only pull on the live server.
#
# It does NOT run migrations, cache clears, builds, composer, or queue restarts.
#
# Required environment variables:
#   DEPLOY_SSH_HOST
#   DEPLOY_SSH_USER
#   DEPLOY_SSH_PATH
#
# Optional environment variables:
#   DEPLOY_SSH_PORT (default: 22)
#
# Usage:
#   .\safe-sync.ps1
#   .\safe-sync.ps1 -Branch main

param(
    [string]$Branch = "main"
)

$ErrorActionPreference = "Stop"

. "$PSScriptRoot\deploy-config.ps1"
$config = Initialize-DeployConfig -ProjectRoot $PSScriptRoot
$hostName = $config.HostName
$userName = $config.UserName
$deployPath = $config.DeployPath
$port = $config.Port

Write-Host ""
Write-Host "Safe sync started..." -ForegroundColor Cyan
Write-Host "Branch: $Branch" -ForegroundColor Gray
Write-Host "Server: $userName@$hostName" -ForegroundColor Gray
Write-Host "Path: $deployPath" -ForegroundColor Gray
Write-Host ""

$status = git status --short
if ($LASTEXITCODE -ne 0) {
    throw "git status failed."
}

if (-not [string]::IsNullOrWhiteSpace($status)) {
    Write-Host "Uncommitted changes detected. Safe sync stopped." -ForegroundColor Yellow
    Write-Host ""
    Write-Host $status
    Write-Host ""
    Write-Host "Commit only the files you really want, then run safe sync again." -ForegroundColor Yellow
    exit 1
}

$currentBranch = git rev-parse --abbrev-ref HEAD
if ($LASTEXITCODE -ne 0) {
    throw "Could not detect current branch."
}

if ($currentBranch -ne $Branch) {
    Write-Host "Current branch is '$currentBranch', expected '$Branch'." -ForegroundColor Yellow
    exit 1
}

Write-Host "Pushing '$Branch'..." -ForegroundColor Green
git push origin $Branch

if ($LASTEXITCODE -ne 0) {
    throw "git push failed."
}

$remoteCommand = @"
set -e
cd '$deployPath'
git status --short
git pull --ff-only origin $Branch
"@

Write-Host ""
Write-Host "Pulling latest commit on the server..." -ForegroundColor Green
ssh -p $port "$userName@$hostName" $remoteCommand

if ($LASTEXITCODE -ne 0) {
    throw "Safe server pull failed."
}

Write-Host ""
Write-Host "Safe sync completed." -ForegroundColor Green
Write-Host ""
