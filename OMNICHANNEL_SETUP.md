# Omnichannel Inbox System - Setup & Installation Guide

## Table of Contents
1. [Prerequisites](#prerequisites)
2. [Installation Steps](#installation-steps)
3. [Configuration](#configuration)
4. [Testing Installation](#testing-installation)
5. [Deployment](#deployment)
6. [Troubleshooting](#troubleshooting)

---

## Prerequisites

### System Requirements
- **PHP**: 8.1 or higher
- **Laravel**: 11.x
- **MySQL**: 8.0 or higher
- **Redis**: 6.0+ (for caching and real-time features)
- **Composer**: Latest version
- **Node.js**: 18.x or higher
- **npm**: 9.x or higher

### Required Services (for full omnichannel support)
- **Meta API** (Facebook/Instagram)
  - App ID and App Secret from Meta Developers
  - Page Access Token for each Facebook Page
  
- **WhatsApp Cloud API**
  - Business Account ID
  - Phone Number ID
  - Permanent Access Token
  
- **OpenAI API**
  - Valid API key for AI suggestions
  
- **Pinecone**
  - API key and environment
  - Index for vector embeddings

- **Reverb or Pusher**
  - Broadcasting credentials for real-time features

---

## Installation Steps

### 1. Clone Repository

```bash
# HTTPS
git clone https://github.com/your-org/smartwatch-showroom.git

# SSH
git clone git@github.com:your-org/smartwatch-showroom.git

cd smartwatch-showroom
```

### 2. Install PHP Dependencies

```bash
# Install composer dependencies
composer install

# Update dependencies
composer update
```

### 3. Install Node Dependencies

```bash
# Install npm packages
npm install

# Update npm packages
npm update
```

### 4. Environment Configuration

```bash
# Copy example environment file
cp .env.example .env

# Generate application key (creates APP_KEY)
php artisan key:generate

# Verify key was generated
grep APP_KEY .env
```

### 5. Database Setup

```bash
# Create database (do this in MySQL)
mysql -u root -p
> CREATE DATABASE smartwatch_showroom;
> exit;

# Update .env with database credentials
# DB_DATABASE=smartwatch_showroom
# DB_USERNAME=root
# DB_PASSWORD=your_password

# Run migrations
php artisan migrate

# Seed initial data (optional)
php artisan db:seed

# Verify tables were created
php artisan tinker
>>> DB::table('migrations')->get()
```

### 6. Build Frontend Assets

```bash
# Development mode
npm run dev

# Production build
npm run build

# Watch for changes during development
npm run watch
```

### 7. Storage & Cache Configuration

```bash
# Create storage symlink (if using file uploads)
php artisan storage:link

# Cache configuration
php artisan config:cache

# Route caching (production)
php artisan route:cache

# View caching (optional)
php artisan view:cache
```

### 8. Create First Admin User

```bash
php artisan tinker

# Create admin user
$user = new App\Models\User();
$user->name = "Admin User";
$user->email = "admin@example.com";
$user->password = Hash::make("SecurePassword123!");
$user->is_admin = true;
$user->save();

exit
```

---

## Configuration

### Meta (Facebook/Instagram) Setup

See [OMNICHANNEL_CONFIG.md](./OMNICHANNEL_CONFIG.md) for detailed Meta setup instructions.

Update `.env`:
```bash
FACEBOOK_APP_ID=your_app_id
FACEBOOK_APP_SECRET=your_app_secret
FACEBOOK_VERIFY_TOKEN=your_verify_token
FACEBOOK_PAGE_ACCESS_TOKEN=your_page_token
```

### WhatsApp Setup

See [OMNICHANNEL_CONFIG.md](./OMNICHANNEL_CONFIG.md) for detailed WhatsApp setup.

Update `.env`:
```bash
WHATSAPP_BUSINESS_ACCOUNT_ID=your_account_id
WHATSAPP_PHONE_NUMBER_ID=your_phone_id
WHATSAPP_API_KEY=your_api_key
```

### OpenAI Configuration

Update `.env`:
```bash
OPENAI_API_KEY=sk-your-key
OPENAI_MODEL=gpt-3.5-turbo
OPENAI_MAX_TOKENS=500
```

### Pinecone Vector Search

Update `.env`:
```bash
PINECONE_API_KEY=your_api_key
PINECONE_INDEX_NAME=smartwatch-showroom
PINECONE_ENVIRONMENT=production
```

### Broadcasting Setup

**For Reverb (recommended):**
```bash
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
BROADCAST_DRIVER=reverb
```

**For Pusher:**
```bash
PUSHER_APP_ID=your-app-id
PUSHER_APP_KEY=your-app-key
PUSHER_APP_SECRET=your-app-secret
PUSHER_HOST=api-your-region.pusher.com
BROADCAST_DRIVER=pusher
```

### Redis Setup

Update `.env`:
```bash
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
CACHE_DRIVER=redis
SESSION_DRIVER=cookie
QUEUE_CONNECTION=redis
```

### Email Configuration

Update `.env`:
```bash
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=465
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@example.com
```

---

## Testing Installation

### Run Test Suite

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/OmnichannelWebhookTest.php

# Run with coverage
php artisan test --coverage

# Run only unit tests
php artisan test tests/Unit

# Run only feature tests
php artisan test tests/Feature
```

### Verify Webhook Endpoints

```bash
# Start PHP server
php artisan serve

# In another terminal, test webhook
curl -X POST http://localhost:8000/api/webhooks/messages \
  -H "X-Hub-Signature-256: sha256=invalid" \
  -H "Content-Type: application/json" \
  -d '{}' 
# Should return 403 Forbidden
```

### Check Application Health

```bash
php artisan tinker

# Verify database connection
>>> DB::select('SELECT 1')

# Check migrations status
>>> Artisan::call('migrate:status')

# Verify models work
>>> App\Models\User::count()

# Check config loaded
>>> config('services.meta.app_id')
```

### Test Admin Panel

1. Start application: `php artisan serve`
2. Navigate to: `http://localhost:8000/admin/login`
3. Login with admin credentials created earlier
4. Access Inbox: `http://localhost:8000/admin/inbox`
5. Verify conversations load without errors

---

## Deployment

### Pre-Deployment Checklist

```bash
# Verify tests pass
php artisan test

# Check code standards (if using Laravel Pint)
./vendor/bin/pint --test

# Verify no uncommitted changes
git status

# Create database backup
mysqldump -u root -p smartwatch_showroom > backup.sql

# Check Laravel version compatibility
php artisan tinker
>>> Laravel\Sanctum\Sanctum::actingAs(...)
```

### Production Environment Setup

1. **Create Production Database**
   ```bash
   # On production server
   mysql -u admin -p
   > CREATE DATABASE smartwatch_showroom_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   > exit;
   ```

2. **Update Production .env**
   ```bash
   APP_ENV=production
   APP_DEBUG=false
   APP_KEY=base64:YOUR_KEY_FROM_php_artisan_key:generate
   
   DB_DATABASE=smartwatch_showroom_prod
   DB_USERNAME=prod_user
   DB_PASSWORD=strong_password
   
   CACHE_DRIVER=redis
   SESSION_DRIVER=cookie
   QUEUE_CONNECTION=redis
   
   MAIL_MAILER=smtp
   # ... email config for production
   ```

3. **Run Final Migrations**
   ```bash
   php artisan migrate --force
   ```

4. **Cache Configuration (Production)**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

5. **Build Assets**
   ```bash
   npm run build
   ```

6. **Set Permissions**
   ```bash
   chmod -R 755 storage bootstrap/cache
   chmod -R 777 storage/logs
   chmod -R 777 storage/app
   ```

### HTTPS Configuration

```nginx
# Update webhook URL in Meta/WhatsApp to use HTTPS
# Example: https://yourdomain.com/api/webhooks/messages

# Update .env
APP_URL=https://yourdomain.com
TRUSTED_PROXIES=*
```

### SSL Certificate Setup

```bash
# Using Let's Encrypt with nginx
sudo apt-get install certbot python3-certbot-nginx
sudo certbot certify -d yourdomain.com
sudo certbot renew --dry-run
```

### Database Backup Strategy

```bash
# Daily backup script (cron)
#!/bin/bash
BACKUP_DIR="/backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
mysqldump -u root -p${DB_PASSWORD} smartwatch_showroom_prod | gzip > $BACKUP_DIR/db_$TIMESTAMP.sql.gz

# Keep only last 7 days
find $BACKUP_DIR -name "db_*.sql.gz" -mtime +7 -delete
```

Add to crontab:
```bash
# Run daily at 2 AM
0 2 * * * /home/appuser/backup-db.sh
```

### Monitoring Setup

```bash
# Enable Laravel logging
# In .env: LOG_CHANNEL=stack

# Monitor error logs
tail -f storage/logs/laravel.log

# Monitor webhook logs
php artisan tinker
>>> App\Models\WebhookLog::latest()->take(10)->get()
```

### Queue Worker Setup

```bash
# Start queue worker
php artisan queue:work redis

# With timeout and sleep
php artisan queue:work redis --timeout=90 --sleep=3

# Using supervisor for persistent worker
# See: https://laravel.com/docs/11.x/queues#supervisor-configuration
```

---

## Troubleshooting

### Common Database Issues

**Error: "Access denied for user 'root'@'localhost'"**
```bash
# Verify database credentials in .env
# Reset MySQL password if needed
sudo mysql -u root
> ALTER USER 'root'@'localhost' IDENTIFIED BY 'newpassword';
> FLUSH PRIVILEGES;
```

**Error: "SQLSTATE[HY000]: General error: 1030 Got error 28"**
- Disk space issue: `df -h`
- Clean up: `php artisan clean-up-logs`

**Migration Fails with "Unknown collation"**
```bash
# Check MySQL version
mysql --version

# Ensure utf8mb4 support
mysql> SHOW COLLATION LIKE 'utf8mb4%';
```

### Application Issues

**"The Application Could Not Be Found" Error**
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
```

**Webhook Not Receiving Events**
1. Verify webhook URL is correctly set in Meta/WhatsApp dashboards
2. Check webhook logs: `WebhookLog::latest()->take(5)->get()`
3. Verify signature verification: `X-Hub-Signature-256` header

**Real-time Messages Not Broadcasting**
```bash
# Check Redis connection
php artisan tinker
>>> Redis::ping()

# Verify Reverb/Pusher credentials in .env
# Restart queue worker: php artisan queue:work
```

**Admin Panel Not Loading**
```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# Check authentication
php artisan tinker
>>> App\Models\User::where('is_admin', true)->first()
```

### Performance Issues

**Slow Query Performance**
```bash
# Enable query logging
\DB::enableQueryLog()

# Check slow query logs
mysqli> SET GLOBAL slow_query_log = 'ON';
mysqli> SET GLOBAL long_query_time = 2;
```

**Memory Issues**
```bash
# Increase PHP memory limit
# In php.ini: memory_limit = 512M

# Or run commands with limit
php -d memory_limit=1G artisan migrate
```

**Queue Bottleneck**
```bash
# Monitor queue
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Use multiple workers
php artisan queue:work --workers=4
```

### Logging & Debugging

**Enable Debug Mode (DEVELOPMENT ONLY)**
```bash
# .env
APP_DEBUG=true
```

**View Application Logs**
```bash
# Real-time logs
tail -f storage/logs/laravel.log

# Last 100 lines
tail -100 storage/logs/laravel.log

# Search logs
grep "error" storage/logs/laravel.log
```

**Webhook Debugging**
```php
// In WebhookController, add temporary logging
Log::info('Webhook payload received', [
    'platform' => $platform,
    'body' => file_get_contents('php://input'),
]);
```

---

## Next Steps

After successful installation:

1. **Configure Platforms**: Follow [OMNICHANNEL_CONFIG.md](./OMNICHANNEL_CONFIG.md)
2. **Review API**: Read [OMNICHANNEL_API.md](./OMNICHANNEL_API.md)
3. **Admin Training**: See [ADMIN_GUIDE.md](./ADMIN_GUIDE.md)
4. **Test Webhooks**: Use [examples/webhook-test.sh](./examples/webhook-test.sh)

---

## Support & Resources

- **Laravel Documentation**: https://laravel.com/docs/11.x
- **Meta Developer Docs**: https://developers.facebook.com/docs
- **WhatsApp Cloud API**: https://developers.facebook.com/docs/whatsapp/cloud-api
- **OpenAI API**: https://platform.openai.com/docs
