# KidSIM Watch - Deployment Guide

## Prerequisites
- Git installed on your local machine
- SSH access to your server
- PHP 8.1+ on server
- Composer installed on server
- Node.js & npm on server
- MySQL database on server

## Initial Git Setup (Local)

### 1. Initialize Git Repository
```bash
git init
git add .
git commit -m "Initial commit: KidSIM Watch showroom"
```

### 2. Configure Git User (if not already configured)
```bash
git config --global user.name "Your Name"
git config --global user.email "your.email@example.com"
```

### 3. Create GitHub Repository (Option A - Recommended)
1. Go to https://github.com/new
2. Create a new repository (e.g., `smartwatch-showroom`)
3. Don't initialize with README (we already have code)
4. Copy the repository URL

```bash
git remote add origin https://github.com/YOUR_USERNAME/smartwatch-showroom.git
git branch -M main
git push -u origin main
```

### 4. OR Use GitLab/Bitbucket (Option B)
Similar process - create repo and add remote URL

## Server Setup

### 1. SSH into Your Server
```bash
ssh user@your-server-ip
```

### 2. Clone Repository on Server
```bash
cd /var/www  # or your web root directory
git clone https://github.com/YOUR_USERNAME/smartwatch-showroom.git
cd smartwatch-showroom
```

### 3. Install Dependencies
```bash
# Install PHP dependencies
composer install --optimize-autoloader --no-dev

# Install Node dependencies and build assets
npm install
npm run build
```

### 4. Configure Environment
```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Edit .env file with your server settings
nano .env
```

**Important .env settings to update:**
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=http://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
```

### 5. Set Permissions
```bash
# Set correct ownership
sudo chown -R www-data:www-data /var/www/smartwatch-showroom

# Set correct permissions
sudo chmod -R 755 /var/www/smartwatch-showroom
sudo chmod -R 775 /var/www/smartwatch-showroom/storage
sudo chmod -R 775 /var/www/smartwatch-showroom/bootstrap/cache
```

### 6. Run Database Migrations
```bash
php artisan migrate --force
```

### 7. Create Storage Symlink
```bash
php artisan storage:link
```

### 8. Optimize for Production
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Regular Push/Pull Workflow

### Local Development → Server

#### When you make changes locally:
```bash
# 1. Check what changed
git status

# 2. Add files
git add .

# 3. Commit with message
git commit -m "Description of changes"

# 4. Push to GitHub
git push origin main
```

#### On Server (pull updates):
```bash
# SSH into server
ssh user@your-server-ip
cd /var/www/smartwatch-showroom

# Pull latest changes
git pull origin main

# Install any new dependencies
composer install --optimize-autoloader --no-dev
npm install
npm run build

# Run migrations (if any)
php artisan migrate --force

# Clear and recache
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

## Quick Deploy Script (Local to Server)

Save this as `deploy.sh`:

```bash
#!/bin/bash
echo "🚀 Deploying to server..."

# Local: Push to Git
git add .
git commit -m "Deploy: $(date '+%Y-%m-%d %H:%M:%S')"
git push origin main

# Server: Pull and update
ssh user@your-server-ip << 'EOF'
cd /var/www/smartwatch-showroom
git pull origin main
composer install --optimize-autoloader --no-dev
npm install
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
sudo systemctl reload php8.1-fpm  # Adjust PHP version as needed
echo "✅ Deployment complete!"
EOF
```

Make it executable:
```bash
chmod +x deploy.sh
```

Then deploy with:
```bash
./deploy.sh
```

## For Windows (PowerShell Deploy Script)

Save as `deploy.ps1`:

```powershell
Write-Host "🚀 Deploying to server..." -ForegroundColor Cyan

# Local: Push to Git
git add .
$timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
git commit -m "Deploy: $timestamp"
git push origin main

Write-Host "✅ Pushed to Git. Now SSH to server and run:" -ForegroundColor Green
Write-Host ""
Write-Host "cd /var/www/smartwatch-showroom" -ForegroundColor Yellow
Write-Host "git pull origin main" -ForegroundColor Yellow
Write-Host "composer install --optimize-autoloader --no-dev" -ForegroundColor Yellow
Write-Host "npm install && npm run build" -ForegroundColor Yellow
Write-Host "php artisan migrate --force" -ForegroundColor Yellow
Write-Host "php artisan optimize" -ForegroundColor Yellow
```

## Important Files to Keep Secure

Never commit these to Git (already in `.gitignore`):
- `.env` - Contains passwords and secrets
- `/vendor` - PHP dependencies (installed via composer)
- `/node_modules` - Node dependencies (installed via npm)
- `/public/storage` - Symbolic link (created via artisan)

## Typical Workflow Example

**Scenario: You add new products locally, want to deploy**

```bash
# Local machine
git add .
git commit -m "Added new smartwatch products"
git push origin main

# Server
ssh user@your-server-ip
cd /var/www/smartwatch-showroom
git pull origin main
php artisan config:cache
php artisan view:cache
```

## Troubleshooting

### Permission Issues
```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### Cache Issues
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Database Not Syncing
- Export local database: `mysqldump -u root your_db > backup.sql`
- Import on server: `mysql -u user -p your_db < backup.sql`

## Web Server Configuration

### Nginx Example (`/etc/nginx/sites-available/smartwatch-showroom`)
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/smartwatch-showroom/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable site:
```bash
sudo ln -s /etc/nginx/sites-available/smartwatch-showroom /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## Summary

**Daily Workflow:**
1. Make changes locally
2. `git add .`
3. `git commit -m "Description"`
4. `git push origin main`
5. SSH to server → `git pull origin main` → `php artisan optimize`

**First Time Only:**
- Set up Git repository
- Configure server
- Clone to server
- Set permissions
