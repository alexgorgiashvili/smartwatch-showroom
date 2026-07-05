# Safe push shortcut
# Usage:
#   .\safe-push.ps1
#   .\safe-push.ps1 -Branch main

param(
    [string]$Branch = "main"
)

$ErrorActionPreference = "Stop"

Write-Host ""
Write-Host "Safe push check..." -ForegroundColor Cyan
Write-Host ""

$status = git status --short
if ($LASTEXITCODE -ne 0) {
    throw "git status failed."
}

if (-not [string]::IsNullOrWhiteSpace($status)) {
    Write-Host "Uncommitted changes detected. Safe push stopped." -ForegroundColor Yellow
    Write-Host ""
    Write-Host $status
    Write-Host ""
    Write-Host "First review and commit only the files you really want to deploy." -ForegroundColor Yellow
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

Write-Host ""
Write-Host "Safe push completed." -ForegroundColor Green
Write-Host ""
