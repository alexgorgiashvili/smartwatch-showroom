# Simple safe deploy shortcut
# Usage:
#   .\gdeploy.ps1 "update chatbot fallback"
#   .\gdeploy.ps1 "fix pricing copy" -Branch main
#
# This script stages local changes, creates a commit, pushes it,
# then performs a fast-forward-only pull on the live server.
#
# It does NOT run migrations, cache clears, builds, composer, or queue restarts.

param(
    [Parameter(Mandatory = $true, Position = 0)]
    [string]$Message,
    [string]$Branch = "main"
)

$ErrorActionPreference = "Stop"

$status = git status --short
if ($LASTEXITCODE -ne 0) {
    throw "git status failed."
}

if ([string]::IsNullOrWhiteSpace($status)) {
    Write-Host "No local changes detected. Nothing to deploy." -ForegroundColor Yellow
    exit 1
}

Write-Host ""
Write-Host "Staging current changes..." -ForegroundColor Cyan
git add -A

if ($LASTEXITCODE -ne 0) {
    throw "git add failed."
}

Write-Host "Creating commit..." -ForegroundColor Cyan
git commit -m $Message

if ($LASTEXITCODE -ne 0) {
    throw "git commit failed."
}

& "$PSScriptRoot\safe-sync.ps1" -Branch $Branch
