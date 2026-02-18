#!/bin/bash
# Server-side deployment script
# Run this on your production server after git pull

echo "🔄 Updating application on server..."

# Pull latest changes
echo "📥 Pulling from Git..."
git pull origin main

# Install/update Composer dependencies
echo "📦 Installing PHP dependencies..."
composer install --optimize-autoloader --no-dev

# Install/update NPM dependencies and build assets
echo "🎨 Building frontend assets..."
npm install
npm run build

# Run database migrations
echo "🗄️  Running database migrations..."
php artisan migrate --force

# Clear old cache
echo "🧹 Clearing old cache..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Optimize for production
echo "⚡ Optimizing for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# Set correct permissions
echo "🔐 Setting permissions..."
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Reload PHP-FPM (adjust version as needed)
echo "🔄 Reloading PHP-FPM..."
sudo systemctl reload php8.1-fpm 2>/dev/null || echo "⚠️  Could not reload PHP-FPM (you may need to do this manually)"

echo ""
echo "✅ Deployment complete!"
echo ""
