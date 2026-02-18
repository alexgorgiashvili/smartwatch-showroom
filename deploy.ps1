# KidSIM Watch - Deployment Script
# Usage: .\deploy.ps1

Write-Host ""
Write-Host "🚀 Starting deployment process..." -ForegroundColor Cyan
Write-Host ""

# Check current branch
$currentBranch = git rev-parse --abbrev-ref HEAD
if ($currentBranch -ne "main") {
    Write-Host "⚠️  Warning: You are on branch '$currentBranch', not 'main'" -ForegroundColor Yellow
    $continue = Read-Host "Continue anyway? (y/N)"
    if ($continue -ne "y" -and $continue -ne "Y") {
        exit
    }
}

# Add all changes
Write-Host "📦 Adding files to Git..." -ForegroundColor Green
git add .

# Commit with timestamp
$timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
Write-Host "💾 Committing changes..." -ForegroundColor Green
git commit -m "Deploy: $timestamp"

if ($LASTEXITCODE -ne 0) {
    Write-Host "ℹ️  No changes to commit" -ForegroundColor Gray
}

# Push to remote
Write-Host "🌐 Pushing to remote repository..." -ForegroundColor Green
git push origin main

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "✅ Successfully pushed to Git!" -ForegroundColor Green
    Write-Host ""
    Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Cyan
    Write-Host "📋 Next: Run these commands on your server:" -ForegroundColor Yellow
    Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "ssh user@your-server-ip" -ForegroundColor White
    Write-Host "cd /var/www/smartwatch-showroom" -ForegroundColor White
    Write-Host "git pull origin main" -ForegroundColor White
    Write-Host "composer install --optimize-autoloader --no-dev" -ForegroundColor White
    Write-Host "npm install && npm run build" -ForegroundColor White
    Write-Host "php artisan migrate --force" -ForegroundColor White
    Write-Host "php artisan config:cache" -ForegroundColor White
    Write-Host "php artisan route:cache" -ForegroundColor White
    Write-Host "php artisan view:cache" -ForegroundColor White
    Write-Host ""
    Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "💡 Tip: Save these commands or see DEPLOYMENT.md for full guide" -ForegroundColor Gray
    Write-Host ""
} else {
    Write-Host ""
    Write-Host "❌ Push failed. Check your Git configuration and remote repository." -ForegroundColor Red
    Write-Host ""
    Write-Host "If this is your first push, you may need to:" -ForegroundColor Yellow
    Write-Host "1. Create a GitHub/GitLab repository" -ForegroundColor Yellow
    Write-Host "2. Run: git remote add origin YOUR_REPO_URL" -ForegroundColor Yellow
    Write-Host "3. Run: git push -u origin main" -ForegroundColor Yellow
    Write-Host ""
}
