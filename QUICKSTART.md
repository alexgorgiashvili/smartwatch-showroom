# Quick Start: Push Your Project to Server

## Step 1: Initialize Git (First Time Only)

```powershell
# In your project folder
git init
git add .
git commit -m "Initial commit: KidSIM Watch project"
```

## Step 2: Create GitHub Repository

### Option A: Using GitHub Website
1. Go to [github.com](https://github.com) and login
2. Click "+" → "New repository"
3. Name it: `smartwatch-showroom`
4. **Don't** check "Initialize with README"
5. Click "Create repository"
6. Copy the repository URL (looks like: `https://github.com/username/smartwatch-showroom.git`)

### Then connect your local project:
```powershell
git remote add origin https://github.com/YOUR_USERNAME/smartwatch-showroom.git
git branch -M main
git push -u origin main
```

### Option B: Using GitHub Desktop
1. Download [GitHub Desktop](https://desktop.github.com/)
2. Install and login
3. File → Add Local Repository → Choose your project folder
4. Click "Publish repository"

## Step 3: Deploy to Your Server

### A. Clone to Server (First Time)
```bash
# SSH into your server
ssh user@your-server-ip

# Navigate to web directory
cd /var/www

# Clone your repository
git clone https://github.com/YOUR_USERNAME/smartwatch-showroom.git

# Go to project folder
cd smartwatch-showroom

# Copy environment file
cp .env.example .env

# Edit .env with your database credentials
nano .env
```

### B. Install Dependencies
```bash
# Install PHP packages
composer install --optimize-autoloader --no-dev

# Install Node packages and build
npm install
npm run build

# Generate app key
php artisan key:generate

# Run migrations
php artisan migrate --force

# Create storage link
php artisan storage:link

# Set permissions
sudo chown -R www-data:www-data .
sudo chmod -R 775 storage bootstrap/cache

# Cache for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Step 4: Daily Push/Pull Workflow

### When you make changes on your computer:

#### Option 1: Using PowerShell Script (Easiest)
```powershell
.\deploy.ps1
```
Then SSH to server and pull updates:
```bash
cd /var/www/smartwatch-showroom
./server-deploy.sh
```

#### Option 2: Manual Commands
```powershell
# Save your work
git add .
git commit -m "Your change description"
git push origin main
```

Then on server:
```bash
ssh user@your-server-ip
cd /var/www/smartwatch-showroom
git pull origin main
composer install --optimize-autoloader --no-dev
npm run build
php artisan migrate --force
php artisan optimize
```

## Step 5: Configure Web Server

### For Nginx
```bash
sudo nano /etc/nginx/sites-available/smartwatch-showroom
```

Add:
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

Enable:
```bash
sudo ln -s /etc/nginx/sites-available/smartwatch-showroom /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

## Troubleshooting

### "Git is not recognized"
Install Git: https://git-scm.com/download/win

### Permission denied on server
```bash
sudo chown -R www-data:www-data /var/www/smartwatch-showroom
sudo chmod -R 775 /var/www/smartwatch-showroom/storage
sudo chmod -R 775 /var/www/smartwatch-showroom/bootstrap/cache
```

### Database connection error
Check `.env` file on server has correct database credentials

### 500 error after deployment
```bash
php artisan cache:clear
php artisan config:clear
sudo chmod -R 775 storage bootstrap/cache
```

## Summary

**First time setup:**
1. `git init` → `git commit` → Create GitHub repo → `git push`
2. SSH to server → `git clone` → `composer install` → `npm run build`
3. Configure `.env` and web server

**Every update after:**
1. Local: `git add .` → `git commit` → `git push`
2. Server: `git pull` → `php artisan optimize`

✅ Done!
