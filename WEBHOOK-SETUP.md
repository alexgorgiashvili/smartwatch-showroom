# GitHub Webhook Auto-Deploy Setup Guide

This guide will help you set up automatic deployment from GitHub to your production server. After setup, whenever you push changes to GitHub, your server will automatically pull and deploy them within seconds.

## 📋 Prerequisites

Before starting, ensure you have:

- ✅ GitHub repository created: `smartwatch-showroom`
- ✅ Server with SSH access (Ubuntu/Debian Linux)
- ✅ Server has: PHP 8.1+, Composer, Node.js, Nginx, MySQL, Git
- ✅ Domain name or server IP address
- ✅ Your GitHub username

---

## 🚀 Part 1: Push to GitHub (On Your Computer)

You've already initialized Git. Now connect to your GitHub repository:

### Step 1: Add GitHub Remote

Replace `YOUR_USERNAME` with your actual GitHub username:

```powershell
git remote add origin https://github.com/YOUR_USERNAME/smartwatch-showroom.git
git branch -M main
git push -u origin main
```

**Enter your GitHub credentials when prompted.**

### Step 2: Verify on GitHub

Visit `https://github.com/YOUR_USERNAME/smartwatch-showroom` and confirm all files are there.

---

## 🖥️ Part 2: Initial Server Setup

### Step 1: SSH Into Your Server

```bash
ssh your-username@your-server-ip
```

### Step 2: Install Required Software (if not already installed)

```bash
# Update package list
sudo apt update

# Install PHP 8.1 and extensions
sudo apt install -y php8.1 php8.1-fpm php8.1-mysql php8.1-xml php8.1-mbstring \
    php8.1-curl php8.1-zip php8.1-gd php8.1-bcmath php8.1-intl

# Install Nginx
sudo apt install -y nginx

# Install MySQL
sudo apt install -y mysql-server

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js (LTS version)
curl -fsSL https://deb.nodesource.com/setup_lts.x | sudo -E bash -
sudo apt install -y nodejs

# Install Git (usually pre-installed)
sudo apt install -y git
```

### Step 3: Clone Repository

```bash
# Navigate to web directory
cd /var/www

# Clone your repository (replace YOUR_USERNAME)
sudo git clone https://github.com/YOUR_USERNAME/smartwatch-showroom.git

# Set ownership to your user for now
sudo chown -R $USER:$USER smartwatch-showroom

# Enter project directory
cd smartwatch-showroom
```

### Step 4: Configure Environment

```bash
# Copy environment file
cp .env.example .env

# Edit environment file
nano .env
```

**Update these values in `.env`:**

```env
APP_NAME="KidSIM Watch Showroom"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=smartwatch_db
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password
```

Save and exit (Ctrl+X, Y, Enter)

### Step 5: Create Database

```bash
# Login to MySQL
sudo mysql

# Run these SQL commands:
```

```sql
CREATE DATABASE smartwatch_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'smartwatch_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON smartwatch_db.* TO 'smartwatch_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

**Update `.env` with the database credentials you just created.**

### Step 6: Install Dependencies & Deploy

```bash
# Install PHP dependencies
composer install --optimize-autoloader --no-dev

# Install Node dependencies and build assets
npm install
npm run build

# Generate application key
php artisan key:generate

# Run database migrations
php artisan migrate --force

# Seed admin user
php artisan db:seed --class=AdminUserSeeder

# Create storage symlink
php artisan storage:link

# Set proper permissions
sudo chown -R www-data:www-data /var/www/smartwatch-showroom
sudo chmod -R 755 /var/www/smartwatch-showroom
sudo chmod -R 775 /var/www/smartwatch-showroom/storage
sudo chmod -R 775 /var/www/smartwatch-showroom/bootstrap/cache

# Make deploy script executable
chmod +x /var/www/smartwatch-showroom/server-deploy.sh

# Cache configuration for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Step 7: Configure Nginx

```bash
# Create Nginx site configuration
sudo nano /etc/nginx/sites-available/smartwatch-showroom
```

**Paste this configuration (update `server_name`):**

```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
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

Save and exit.

```bash
# Enable the site
sudo ln -s /etc/nginx/sites-available/smartwatch-showroom /etc/nginx/sites-enabled/

# Test configuration
sudo nginx -t

# If test passes, reload Nginx
sudo systemctl reload nginx
```

### Step 8: Test Website

Visit `http://your-server-ip` or `http://your-domain.com` in a browser.

**You should see the smartwatch showroom homepage!**

Test admin login: `http://your-domain.com/admin/login`
- Email: `admin@kidsimwatch.ge`
- Password: `password123`

---

## 🔗 Part 3: Setup GitHub Webhook (Auto-Deploy Magic!)

### Step 1: Generate Webhook Secret

On your **server**, generate a secure secret token:

```bash
openssl rand -hex 32
```

**Copy the output** (e.g., `a1b2c3d4e5f6...`) - you'll need it in the next steps.

### Step 2: Configure Webhook Script

Edit the webhook file on the server:

```bash
nano /var/www/smartwatch-showroom/public/deploy-webhook.php
```

Find this line:

```php
define('WEBHOOK_SECRET', 'YOUR_SECRET_TOKEN_HERE');
```

Replace `YOUR_SECRET_TOKEN_HERE` with the secret you generated. Example:

```php
define('WEBHOOK_SECRET', 'a1b2c3d4e5f6789...');
```

Save and exit (Ctrl+X, Y, Enter).

### Step 3: Add Webhook to GitHub

1. Go to your GitHub repository: `https://github.com/YOUR_USERNAME/smartwatch-showroom`
2. Click **Settings** → **Webhooks** → **Add webhook**
3. Fill in:

   | Field | Value |
   |-------|-------|
   | **Payload URL** | `http://your-domain.com/deploy-webhook.php` |
   | **Content type** | `application/json` |
   | **Secret** | (paste the secret you generated) |
   | **Which events?** | Select "Just the push event" |
   | **Active** | ✅ Checked |

4. Click **Add webhook**

### Step 4: Test the Webhook

#### Method 1: Make a Test Change

On your local computer:

```powershell
# Make a small change (e.g., edit README.md)
echo "" >> README.md

# Push to GitHub
git add .
git commit -m "Test webhook deployment"
git push origin main
```

#### Method 2: Use GitHub's Test Button

1. In GitHub → Settings → Webhooks → Click your webhook
2. Scroll down to "Recent Deliveries"
3. Click "Redeliver" on a recent delivery

### Step 5: Verify Deployment

On your **server**, check the deployment log:

```bash
tail -f /var/www/smartwatch-showroom/storage/logs/deploy.log
```

You should see:
```
[2026-02-18 12:34:56] [INFO] ========================================
[2026-02-18 12:34:56] [INFO] Webhook received from 140.82.115.xxx
[2026-02-18 12:34:57] [SUCCESS] ✓ Deployment completed successfully
```

**Check GitHub webhook status:**
- Go to your webhook settings
- Scroll to "Recent Deliveries"
- You should see a green ✓ checkmark

---

## 🎯 Part 4: Daily Workflow

### Your New Automated Workflow

**On your local computer:**

#### Option 1: Use Deploy Script (Recommended)

```powershell
.\deploy.ps1
```

The script will:
- ✅ Check you're on main branch
- ✅ Add all changes
- ✅ Commit with timestamp
- ✅ Push to GitHub
- ✅ **Server automatically deploys!** (no SSH needed)

#### Option 2: Manual Git Commands

```powershell
git add .
git commit -m "Your change description"
git push origin main
```

**Server automatically deploys within 5-10 seconds!**

### What Happens Automatically

When you push to GitHub `main` branch:

1. ✅ GitHub sends webhook notification to your server
2. ✅ Server verifies the secret (security)
3. ✅ Server runs `git pull origin main`
4. ✅ Server runs `composer install`
5. ✅ Server runs `npm run build`
6. ✅ Server runs `php artisan migrate --force`
7. ✅ Server clears and rebuilds caches
8. ✅ Server reloads PHP-FPM
9. ✅ **Your changes are LIVE!**

---

## 🔍 Monitoring & Troubleshooting

### Check Deployment Logs

```bash
# On server - watch live deployments
tail -f /var/www/smartwatch-showroom/storage/logs/deploy.log

# View last 50 lines
tail -n 50 /var/www/smartwatch-showroom/storage/logs/deploy.log

# Search for errors
grep ERROR /var/www/smartwatch-showroom/storage/logs/deploy.log
```

### Common Issues

#### 1. Webhook Shows Red X on GitHub

**Check webhook response:**
- GitHub → Settings → Webhooks → Click webhook → Recent Deliveries
- Click the failed delivery to see error message

**Common causes:**
- Server is down/unreachable
- Wrong webhook URL
- Secret mismatch

**Fix:**
```bash
# Test webhook URL manually
curl http://your-domain.com/deploy-webhook.php
```

#### 2. Webhook Works But Deployment Fails

**Check deploy script execution:**

```bash
# Make sure script is executable
chmod +x /var/www/smartwatch-showroom/server-deploy.sh

# Test script manually
cd /var/www/smartwatch-showroom
./server-deploy.sh
```

#### 3. Permission Errors

```bash
# Fix ownership
sudo chown -R www-data:www-data /var/www/smartwatch-showroom

# Fix permissions
sudo chmod -R 755 /var/www/smartwatch-showroom
sudo chmod -R 775 /var/www/smartwatch-showroom/storage
sudo chmod -R 775 /var/www/smartwatch-showroom/bootstrap/cache
```

#### 4. Git Pull Fails (Merge Conflicts)

**Never edit files directly on the server!** Always edit locally and push.

If you must fix:
```bash
cd /var/www/smartwatch-showroom
git reset --hard origin/main
```

#### 5. Database Migration Errors

```bash
# On server
cd /var/www/smartwatch-showroom
php artisan migrate:status
php artisan migrate --force
```

### Disable Auto-Deploy Temporarily

Edit webhook script on server:

```bash
nano /var/www/smartwatch-showroom/public/deploy-webhook.php
```

Change:
```php
define('DEPLOYMENT_ENABLED', false);
```

### View Laravel Application Logs

```bash
tail -f /var/www/smartwatch-showroom/storage/logs/laravel.log
```

---

## 🔒 Security Best Practices

### 1. Use Strong Webhook Secret

Always use a cryptographically secure random secret:

```bash
openssl rand -hex 32
```

### 2. Enable HTTPS (Recommended)

Install Let's Encrypt SSL certificate:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com -d www.your-domain.com
```

After SSL is installed, update webhook URL in GitHub:
- Change `http://` to `https://`

### 3. Restrict Webhook Access (Optional)

Edit webhook script to only accept GitHub IPs:

```php
// In deploy-webhook.php, add after CONFIGURATION section:
$githubIps = [
    '192.30.252.0/22',
    '185.199.108.0/22',
    '140.82.112.0/20',
    '143.55.64.0/20',
];
// Add IP verification logic
```

### 4. Never Commit Sensitive Data

Keep these out of Git (already in `.gitignore`):
- `.env` file
- `vendor/` directory
- `node_modules/` directory
- Database backups
- Private keys

---

## 📊 Monitoring Deployment Success

### Check Last Deployment Time

```bash
# On server
git log -1 --format="%ai - %s"
```

### View Recent Commits

```bash
git log --oneline -10
```

### Test Application After Deploy

Quick health check script:

```bash
# On server
cd /var/www/smartwatch-showroom

# Check Git status
echo "=== Git Status ==="
git status

# Check Laravel status
echo -e "\n=== Laravel Status ==="
php artisan --version
php artisan config:show app.env

# Check database connection
echo -e "\n=== Database ==="
php artisan migrate:status

# Check permissions
echo -e "\n=== Permissions ==="
ls -la storage/logs/
```

---

## ✅ Success Checklist

After completing this guide, verify:

- [ ] Website loads at `http://your-domain.com`
- [ ] Admin panel works at `http://your-domain.com/admin/login`
- [ ] GitHub webhook shows green ✓ in Recent Deliveries
- [ ] Test push triggers automatic deployment
- [ ] Deployment logs show successful deploys
- [ ] Changes appear on website within 10 seconds of push
- [ ] SSL certificate installed (optional but recommended)

---

## 🆘 Getting Help

If you encounter issues:

1. **Check deployment logs** (see Monitoring section above)
2. **Check GitHub webhook deliveries** for error messages
3. **Test webhook URL** manually with curl
4. **Verify server permissions** and ownership
5. **Check Laravel logs** for application errors

---

## 🎉 You're Done!

Your automated deployment pipeline is now active!

**Workflow Summary:**
1. Edit code on your computer
2. Run `.\deploy.ps1` (or `git push`)
3. ☕ Grab coffee while server deploys automatically
4. ✅ Visit your website - changes are live!

No more SSH, no more manual commands - just push and deploy! 🚀
