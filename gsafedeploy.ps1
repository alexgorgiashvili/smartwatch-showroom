# Safe deploy shortcut that behaves like gdeploy, but keeps the
# production step limited to git push + ff-only pull.
#
# Usage:
#   .\gsafedeploy.ps1 "chatbot fix"
#   gsafedeploy "chatbot fix"

param(
    [Parameter(Mandatory = $true, Position = 0)]
    [string]$Message,
    [string]$Branch
)

$ErrorActionPreference = "Stop"

$resolvedBranch = if ([string]::IsNullOrWhiteSpace($Branch)) {
    git rev-parse --abbrev-ref HEAD
} else {
    $Branch
}

if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($resolvedBranch)) {
    throw "Could not detect current branch."
}

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
git add -A -- . ":(exclude)dump_*.sql"

if ($LASTEXITCODE -ne 0) {
    throw "git add failed."
}

git diff --cached --quiet
if ($LASTEXITCODE -eq 0) {
    Write-Host "No deployable code changes detected." -ForegroundColor Yellow
    exit 1
}

Write-Host "Creating commit..." -ForegroundColor Cyan
git commit -m $Message

if ($LASTEXITCODE -ne 0) {
    throw "git commit failed."
}

& "$PSScriptRoot\safe-sync.ps1" -Branch $resolvedBranch
