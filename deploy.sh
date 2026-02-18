#!/bin/bash

echo "🚀 Starting deployment..."

# Check if on main branch
BRANCH=$(git rev-parse --abbrev-ref HEAD)
if [ "$BRANCH" != "main" ]; then
    echo "⚠️  Warning: You are on branch '$BRANCH', not 'main'"
    read -p "Continue anyway? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Add all changes
echo "📦 Adding files..."
git add .

# Commit with timestamp
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
echo "💾 Committing changes..."
git commit -m "Deploy: $TIMESTAMP" || echo "No changes to commit"

# Push to remote
echo "🌐 Pushing to remote repository..."
git push origin main

echo ""
echo "✅ Local push complete!"
echo ""
echo "📋 Next steps on your server:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "ssh user@your-server-ip"
echo "cd /var/www/smartwatch-showroom"
echo "git pull origin main"
echo "composer install --optimize-autoloader --no-dev"
echo "npm install && npm run build"
echo "php artisan migrate --force"
echo "php artisan config:cache"
echo "php artisan route:cache"
echo "php artisan view:cache"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
