# Omnichannel Inbox System - Configuration Guide

## Table of Contents

1. [Meta (Facebook/Instagram)](#meta-facebookinstagram)
2. [WhatsApp Business](#whatsapp-business)
3. [Broadcasting (Reverb/Pusher)](#broadcasting-reverpusher)
4. [AI Services (OpenAI + Pinecone)](#ai-services-openai--pinecone)
5. [Database Configuration](#database-configuration)
6. [Email Configuration](#email-configuration)
7. [Security Configuration](#security-configuration)

---

## Meta (Facebook/Instagram)

### Step 1: Create Meta App

1. Navigate to [Meta Developers Dashboard](https://developers.facebook.com/)
2. Click **Create App**
3. Choose app type: **Business**
4. Fill in app name and contact information
5. For app use case, select:
   - **Business Messaging** (for Messenger)
   - **Commerce** (for Instagram Shopping)
6. Click **Create App**

### Step 2: Add Messenger Product

1. In your app dashboard, click **+ Add Product**
2. Find **Messenger** and click **Set Up**
3. Select which platforms to support:
   - ✅ Messenger (for Facebook Pages)
   - ✅ Instagram (for Direct Messages)
4. Click **Continue**

### Step 3: Get App Credentials

**Finding Your App ID and Secret:**
1. Go to **Settings** → **Basic**
2. Copy your **App ID**
3. Copy your **App Secret** (don't share this!)
4. Save both to your `.env` file:

```bash
FACEBOOK_APP_ID=your_app_id_here
FACEBOOK_APP_SECRET=your_app_secret_here
```

### Step 4: Create Webhook

**Configure Webhook in Meta Dashboard:**

1. Go to **Messenger** → **Settings**
2. Under **Webhooks**, click **Add Callback URL**
3. Enter these values:
   - **Callback URL**: `https://yourdomain.com/api/webhooks/messages`
   - **Verify Token**: Generate a secure random string (save to `.env`):

```bash
FACEBOOK_VERIFY_TOKEN=your_random_verify_token_here_12345
```

4. Click **Verify and Save**
5. Meta will automatically verify your endpoint

**Subscribe to Events:**

After webhook is verified, under **Webhook Fields**:
- ✅ messages
- ✅ messaging_postbacks
- ✅ messaging_optins
- ✅ message_deliveries
- ✅ message_reads

### Step 5: Get Page Access Token

**For Each Facebook Page You Want to Connect:**

1. Go to **Messenger** → **Settings**
2. Under **Access Tokens**, click **Add Page**
3. Select your Facebook Page from the dropdown
4. Select **Edit Page** permission requested
5. Click **Generate Token**
6. A token will appear - copy it
7. Save to `.env`:

```bash
FACEBOOK_PAGE_ACCESS_TOKEN=eABzXXXXXXXXXXXXXXXXXXX
```

**Store Multiple Tokens (if multiple pages):**

In `config/services.php`:
```php
'meta' => [
    'app_id' => env('FACEBOOK_APP_ID'),
    'app_secret' => env('FACEBOOK_APP_SECRET'),
    'verify_token' => env('FACEBOOK_VERIFY_TOKEN'),
    'pages' => [
        'page_123' => [
            'access_token' => 'token_1',
            'name' => 'Page Name',
        ],
        'page_456' => [
            'access_token' => 'token_2',
            'name' => 'Another Page',
        ],
    ],
],
```

### Step 6: Test Meta Webhook

```bash
# Trigger a test message
# 1. Go to your Facebook Page
# 2. Send a message to your own page (as a customer)
# 3. Check webhook logs in your app

php artisan tinker
>>> App\Models\WebhookLog::latest()->take(5)->get()

# Should see events with:
# - platform: "facebook"
# - event_type: "message"
# - verified: true
```

### Troubleshooting Meta Setup

**Issue: "Invalid webhook signature"**
- Verify you're using the correct `FACEBOOK_APP_SECRET`
- Ensure callback URL is exactly `https://yourdomain.com/api/webhooks/messages`
- Check that HTTPS is enabled on your domain

**Issue: "Webhook not receiving events"**
1. Verify webhook status in Meta Dashboard (should show green checkmark)
2. Check that you selected the correct events to subscribe to
3. Verify your page has permission to use Messenger
4. Send a test message from a different account

**Issue: "Facebook App Restricted"**
- Apps start in **Development** mode
- To receive real customer messages, must request **App Review**
- Go to **App Roles** → **Request Advanced Access**
- Complete Facebook's review process

---

## WhatsApp Business

### Step 1: Create Meta Business Account

1. Go to [WhatsApp Business API Setup](https://developers.facebook.com/docs/whatsapp)
2. Click **Get Started**
3. Create/select your Meta Business Account
4. Agree to terms and conditions

### Step 2: Create WhatsApp Business Account

1. In Meta Business Suite, go to **WhatsApp** → **Getting Started**
2. Click **Create Account**
3. Fill in business information:
   - Business name
   - Business category
   - Website (optional)
   - Business phone (required)

### Step 3: Verify Phone Number

1. Choose verification method:
   - **SMS** - Receive code via text
   - **Call** - Receive code via phone call
2. Enter the verification code
3. Save verified phone number

### Step 4: Get Required Credentials

**Find Business Account ID:**
1. Go to **Settings** → **Business Settings**
2. Select your business
3. Go to **Account** → **Business Information**
4. Copy **Business Account ID**

```bash
WHATSAPP_BUSINESS_ACCOUNT_ID=your_account_id
```

**Get Phone Number ID:**
1. Go to **WhatsApp** → **Getting Started**
2. Find your phone number
3. Click to expand and see **Phone Number ID**

```bash
WHATSAPP_PHONE_NUMBER_ID=your_phone_number_id
```

**Generate Permanent Access Token:**
1. Go to **Settings** → **System Users**
2. Create System User (if needed)
3. Click **Generate New Token**
4. Select permissions: `whatsapp_business_messaging`
5. Set expiration to **Never**
6. Copy token and save:

```bash
WHATSAPP_API_KEY=EAABzzzzzzzzzzzzzzz
```

### Step 5: Configure Webhook

**In WhatsApp Business API Settings:**

1. Go to **Configuration** → **Webhooks**
2. Set Callback URL:
   ```
   https://yourdomain.com/api/webhooks/messages
   ```

3. Set Verify Token (same as Facebook):
   ```bash
   FACEBOOK_VERIFY_TOKEN=your_random_token
   ```

4. Subscribe to webhook fields:
   - ✅ messages
   - ✅ message_status
   - ✅ message_template_status_update

### Step 6: Update Environment

Complete `.env` configuration:
```bash
# WhatsApp Configuration
WHATSAPP_BUSINESS_ACCOUNT_ID=your_account_id
WHATSAPP_PHONE_NUMBER_ID=your_phone_number_id
WHATSAPP_API_KEY=EAABzzzzzzzzzzzzzzz

# Meta verification (shared with Facebook)
FACEBOOK_VERIFY_TOKEN=your_random_verify_token
```

### Step 7: Test WhatsApp Webhook

```bash
# Send test message from verified phone number
# Go to your WhatsApp account settings and find "Test Message"
# Send yourself a test message
# Check logs:

php artisan tinker
>>> App\Models\WebhookLog::where('platform', 'whatsapp')->latest()->take(5)->get()
```

### WhatsApp Message Templates

To send structured WhatsApp messages, create templates:

1. Go to **Message Templates** in WhatsApp Business Settings
2. Create new template with:
   - Name: `hello_world`
   - Category: `TRANSACTIONAL`
   - Content:
     ```
     Hello {{1}}, this is a template message.
     ```
3. Wait for approval (~24 hours)
4. Use template in code:

```php
$whatsappService->sendTemplateMessage(
    phoneNumber: '+5521999999999',
    templateName: 'hello_world',
    parameters: ['John']
);
```

### Troubleshooting WhatsApp Setup

**Issue: "Webhook verification failed"**
- Ensure callback URL is HTTPS
- Verify token matches exactly
- Check that URL doesn't require authentication

**Issue: "Cannot send messages"**
1. Verify your business is **not in sandbox mode**
2. Check that recipient phone number is **verified**
3. Ensure phone number has correct format: `country_code + number`
4. Verify your **Permanent Access Token** has not expired

**Issue: "Template approval pending"**
- WhatsApp reviews templates for compliance
- Avoid marketing/promotional language
- Keep templates simple and clear
- Can retry if rejected

---

## Broadcasting (Reverb/Pusher)

### Option A: Using Reverb (Recommended - Local Development)

**Install Reverb:**
```bash
php artisan install:broadcasting
# When prompted, choose "Reverb"
npm install
```

**Update `.env`:**
```bash
BROADCAST_DRIVER=reverb
REVERB_APP_ID=12345
REVERB_APP_KEY=abcd1234
REVERB_APP_SECRET=secret1234
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

**Start Reverb Server:**
```bash
# In separate terminal
php artisan reverb:start
# Should see: Server started on port 8080
```

**In Browser JavaScript:**
```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    wsHost: import.meta.env.VITE_PUSHER_HOST,
    wsPort: import.meta.env.VITE_PUSHER_PORT,
    forceTLS: import.meta.env.VITE_PUSHER_SCHEME === 'https',
    encrypted: true,
    disableStats: true,
});
```

### Option B: Using Pusher (Production)

**Create Pusher Account:**
1. Go to [pusher.com](https://pusher.com)
2. Click **Sign Up**
3. Create free account (has limits, upgrade for production)
4. Create new app

**Get Credentials:**
1. In Pusher Dashboard, click your app
2. Copy credentials:
   - **App ID**: `xxxxx`
   - **Key**: `xxxxxxxxxxxxxxxx`
   - **Secret**: `xxxxxxxxxxxxxxxx`
   - **Cluster**: `us2`, `eu`, etc.

**Update `.env`:**
```bash
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_HOST=api-your_cluster.pusher.com
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=your_cluster

# Frontend config
VITE_PUSHER_APP_KEY=your_app_key
VITE_PUSHER_APP_CLUSTER=your_cluster
VITE_PUSHER_HOST=api-your_cluster.pusher.com
VITE_PUSHER_PORT=443
VITE_PUSHER_SCHEME=https
```

**Test Broadcasting:**
```php
// In your controller or artisan command
use App\Events\MessageReceived;

broadcast(new MessageReceived($message, $conversation, $customer, 'whatsapp'))->toOthers();
```

### Broadcasting Channels

**Omnichannel uses these channels:**

1. **Private Channel** - Admin Inbox Updates
   ```php
   // Channel: private-inbox:{admin_user_id}
   // Receives: new messages, status updates
   Route::Broadcast::channel('inbox.{admins}', function ($user, $admins) {
       return (int) $user->id === (int) $admins && $user->is_admin;
   });
   ```

2. **Public Channel** - Conversation Updates
   ```php
   // Channel: public-conversation:{conversation_id}
   // Anyone viewing conversation can see updates in real-time
   ```

---

## AI Services (OpenAI + Pinecone)

### OpenAI Configuration

**Get API Key:**
1. Go to [platform.openai.com](https://platform.openai.com)
2. Sign up or login
3. Go to **API Keys** section
4. Click **Create new secret key**
5. Copy the key (you'll only see it once!)

```bash
OPENAI_API_KEY=sk-xxxxxxxxxxxxxxxxxxxxxxxx
```

**Choose Model:**
```bash
# Update .env - available models:
OPENAI_MODEL=gpt-3.5-turbo      # Fast, cheap
OPENAI_MODEL=gpt-4              # Powerful, expensive
OPENAI_MODEL=gpt-4-turbo        # Fast GPT-4

# Set token limits
OPENAI_MAX_TOKENS=500
```

**Cost Estimation:**
- `gpt-3.5-turbo`: $0.0005 per 1K input tokens
- `gpt-4`: $0.03 per 1K input tokens
- AI suggestions for 100 conversations/day: ~$1-5/month (3.5-turbo)

### Pinecone Vector Search Configuration

**Create Pinecone Account:**
1. Go to [pinecone.io](https://pinecone.io)
2. Sign up free account
3. Create **Index**:
   - Name: `smartwatch-showroom`
   - Dimensions: `1536` (OpenAI embeddings)
   - Metric: `cosine`
   - Pod Type: `p1` (starter free tier)

**Get Credentials:**
1. Go to **API Keys** section
2. Copy **API Key**
3. Copy **Environment** (e.g., `us-west1-gcp`)

```bash
PINECONE_API_KEY=xxxxxxxxxxxxxxxxxxxxx
PINECONE_INDEX_NAME=smartwatch-showroom
PINECONE_ENVIRONMENT=us-west1-gcp
```

**How It Works:**
1. When customer messages arrive, they're converted to embeddings
2. Embeddings are stored in Pinecone vector database
3. When generating suggestions, system searches similar contexts
4. Uses similarity search to find relevant past conversations
5. Feeds context to OpenAI for better responses

### Testing AI Features

```bash
php artisan tinker

# Test OpenAI connection
>>> $openai = new \OpenAI\Client(env('OPENAI_API_KEY'));
>>> $response = $openai->chat()->create([
...   'model' => 'gpt-3.5-turbo',
...   'messages' => [['role' => 'user', 'content' => 'Hello!']],
... ]);
>>> dd($response);

# Test Pinecone connection
>>> $pinecone = new \Pinecone\Client(
...   apiKey: env('PINECONE_API_KEY'),
...   environment: env('PINECONE_ENVIRONMENT')
... );
>>> $index = $pinecone->index(env('PINECONE_INDEX_NAME'));
>>> dd($index->describeIndexStats());
```

---

## Database Configuration

### MySQL Setup

**Create Database and User:**
```bash
mysql -u root -p
```

```sql
CREATE DATABASE smartwatch_showroom CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'showroom_user'@'localhost' IDENTIFIED BY 'SecurePassword123!';

GRANT ALL PRIVILEGES ON smartwatch_showroom.* TO 'showroom_user'@'localhost';

FLUSH PRIVILEGES;

exit;
```

**Update `.env`:**
```bash
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=smartwatch_showroom
DB_USERNAME=showroom_user
DB_PASSWORD=SecurePassword123!
```

### Redis Setup (for Caching/Sessions)

**Install Redis:**
```bash
# macOS
brew install redis

# Ubuntu/Debian
sudo apt-get install redis-server

# Windows (via WSL or standalone)
```

**Start Redis:**
```bash
# macOS/Linux
redis-server

# Or as service
redis-cli
> PING
# Should return: PONG
```

**Update `.env`:**
```bash
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

CACHE_DRIVER=redis
SESSION_DRIVER=cookie
QUEUE_CONNECTION=redis
```

### Database Backup

**Automated Daily Backups:**
```bash
#!/bin/bash
# backup-db.sh

BACKUP_DIR="/var/backups/mysql"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
DB_NAME="smartwatch_showroom"
DB_USER="showroom_user"
DB_PASS="SecurePassword123!"

mkdir -p $BACKUP_DIR

mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/backup_$TIMESTAMP.sql.gz

# Keep only last 30 days
find $BACKUP_DIR -name "backup_*.sql.gz" -mtime +30 -delete

echo "Backup completed: backup_$TIMESTAMP.sql.gz"
```

**Add to crontab:**
```bash
crontab -e
# Add line: 0 2 * * * /home/appuser/backup-db.sh
# Runs daily at 2 AM
```

---

## Email Configuration

### Using Gmail

1. Enable 2-Factor Authentication on Gmail
2. Generate [App Password](https://myaccount.google.com/apppasswords)
3. Update `.env`:

```bash
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="Showroom Support"
```

### Using Mailtrap (Development)

1. Go to [mailtrap.io](https://mailtrap.io)
2. Create free account
3. Get SMTP credentials
4. Update `.env`:

```bash
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=465
MAIL_USERNAME=xxxxx
MAIL_PASSWORD=xxxxx
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=test@example.com
```

### Testing Email

```bash
php artisan tinker

>>> Mail::to('test@example.com')->send(
...   new \App\Mail\TestMail('Hello!')
... );
>>> echo "Check Mailtrap inbox";
```

---

## Security Configuration

### CORS Setup

Update `config/cors.php`:
```php
'allowed_origins' => [
    'https://yourdomain.com',
    'https://app.yourdomain.com',
],

'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

'allowed_headers' => ['*'],

'exposed_headers' => [''],

'max_age' => 0,

'supports_credentials' => true,
```

### HTTPS/SSL Configuration

```nginx
# Redirect HTTP to HTTPS
server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$server_name$request_uri;
}

# HTTPS server
server {
    listen 443 ssl http2;
    server_name yourdomain.com;

    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    
    # Strong SSL configuration
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
}
```

### Rate Limiting

Configure in `app/Http/Middleware/ThrottleRequests.php`:
```php
'api' => 'Api\\.*',
'webhook' => 'unlimited',
'admin' => 'auth:sanctum|admin',
```

### Two-Factor Authentication (Optional)

For additional security, add 2FA to admin accounts:
```bash
composer require google2fa-laravel/google2fa-laravel
php artisan publish:config google2fa
```

---

## Verification Checklist

- [ ] Meta App ID and Secret configured
- [ ] Facebook webhook URL verified
- [ ] WhatsApp Business Account created
- [ ] WhatsApp webhook configured
- [ ] OpenAI API key added
- [ ] Pinecone index created
- [ ] Broadcasting service configured (Reverb or Pusher)
- [ ] Redis server running
- [ ] Database created and migrations run
- [ ] Email service configured
- [ ] HTTPS/SSL certificates installed
- [ ] Backups automated and tested
- [ ] All environment variables loaded: `php artisan config:clear && php artisan config:cache`

Run full test to verify:
```bash
php artisan test
```

All tests should pass ✅
