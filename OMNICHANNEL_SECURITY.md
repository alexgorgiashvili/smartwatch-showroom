# Omnichannel Admin Inbox - Security Policy

## Overview

This document outlines the security measures implemented in the KidSIM Watch omnichannel admin inbox system to protect customer data, authenticate external webhooks, prevent abuse, and maintain audit trails.

## 1. Webhook Authentication

### Meta (Facebook/Instagram) Webhooks

**Signature Verification:**
- Algorithm: HMAC-SHA256
- Header: `X-Hub-Signature-256`
- Format: `sha256={hex_digest}`
- Implementation: `WebhookVerificationService::verifyMetaSignature()`

**Verification Process:**
1. Extract signature from `X-Hub-Signature-256` header
2. Create HMAC-SHA256 hash of raw request body using app secret
3. Compare using `hash_equals()` to prevent timing attacks
4. Log unverified webhooks to `webhook_logs` table with `verified=false`
5. Return 403 Forbidden if signature invalid

**Timestamp Validation:**
- All webhook payloads checked for age (max 5 minutes old)
- Prevents replay attacks using stale webhook data
- Timestamp extracted from webhook payload or HTTP header

### WhatsApp Cloud API Webhooks

**Authentication:**
- Authentication via `Authorization: Bearer {ACCESS_TOKEN}` header
- Access token verified against `WHATSAPP_API_KEY` config
- Request signature validation via Meta Graph API signature

### Rate Limiting by Sender

- Maximum 100 messages per minute per customer
- Prevents webhook spam and abuse
- Rate limit state stored in Redis (with fallback database)
- Returns `429 Too Many Requests` if exceeded

## 2. Input Validation

### Message Content

- **Required:** Must be present and non-empty
- **Length:** Maximum 5000 characters
- **Format:** Valid UTF-8 text, trimmed
- **Injection Prevention:** Content escaped using Laravel's `e()` helper before display
- **Validation Rule:** `ValidateInboxInput` middleware

### Media URLs

- **Format:** Must be valid HTTP/HTTPS URL
- **Whitelist:** Only URLs from trusted platforms:
  - `graph.facebook.com` (Meta CDN)
  - `media.whatsapp.net` (WhatsApp CDN)
  - AWS/Google CDN for attachments
- **Timeout:** Media downloads must complete within 30 seconds
- **Size Limit:** Maximum 25MB per attachment

### Platform-Specific IDs

**Facebook/Instagram:**
- Sender ID: Numeric PSID or IGID (15-20 digits)
- Conversation ID: Facebook-generated identifier
- Validation: `ValidPlatformId` custom rule

**WhatsApp:**
- Sender ID: E.164 format phone number (+1234567890)
- Country code required
- Validation: `ValidPlatformId` custom rule with phone validation

### Request Parameters

- `conversation_id`: Must exist in database and be accessible to authenticated admin
- `platform`: Must be one of: `facebook`, `instagram`, `whatsapp`
- All parameters validated before database queries (prevents SQL injection)

## 3. Rate Limiting

### Admin Endpoints Rate Limits

| Endpoint | Limit | Window |
|----------|-------|--------|
| Message Send | 30/min | Per admin user |
| AI Suggestions | 10/min | Per admin user |
| Search Inbox | 60/min | Per admin user |
| Archive/Status | 50/min | Per admin user |
| GET Conversations | 200/min | Per admin user |

### Webhook Endpoints Rate Limits

| Platform | Limit | Window |
|----------|-------|--------|
| Facebook Webhooks | 1000/min | Per app |
| Instagram Webhooks | 1000/min | Per app |
| WhatsApp Webhooks | 1000/min | Per app |

### Implementation

- Rate limiting via `throttle` middleware
- Storage: Redis (preferred) with fallback to database
- Responses: Include `X-RateLimit-*` headers for client information
- Exceeded: Returns HTTP 429 with retry-after header

## 4. Access Control

### Authentication

- All admin endpoints require `auth` middleware
- Session-based authentication via Laravel session guard
- Admin-only routes protected via `admin` middleware checking `is_admin` boolean

### Authorization

**Admin Inbox Access:**
- Only authenticated users with `is_admin = true` can access inbox
- Verified in `AdminMiddleware` or route middleware

**Conversation Access:**
- Admins can access all conversations (no per-conversation access control yet)
- Future: Implement team-based access control

**Message Ownership:**
- Admins can only delete their own messages
- Delete allowed only within 1 hour of creation (prevent data destruction)
- Enforcement in `MessageController::delete()`

### Private Broadcasting Channel

- Broadcast channel: `private-inbox`
- Requires authentication and `is_admin = true`
- Implementation: `routes/channels.php`

## 5. Data Encryption

### At Rest

**Message Content:**
- Currently stored plain-text in database
- Future: Implement encryption using Laravel's encryption APIs
- Option: Encrypt sensitive messages flagged with `encrypted` flag

**Customer Data:**
- Phone numbers, emails stored plain-text for platform integration
- Consider: Hashing/masking for PII compliance

### In Transit

**HTTPS/TLS:**
- All connections require HTTPS in production
- Configured via `.env` and `FORCE_HTTPS` setting
- Strict-Transport-Security header enforces HTTPS

**WebSocket Security (Reverb/Pusher):**
- All WebSocket connections use WSS (WebSocket Secure)
- TLS v1.2 minimum
- Authentication via broadcast token

## 6. Audit Logging

### Audit Trail

All admin actions logged to `admin_audit_logs` table via `AuditTrait`:

- **Fields Logged:**
  - `admin_user_id`: Who performed the action
  - `action`: inbox.message.send, inbox.status.update, etc.
  - `conversation_id`: Which conversation affected
  - `customer_id`: Which customer affected
  - `ip_address`: IP address of request
  - `user_agent`: Browser/app information
  - `parameters`: Input parameters (sanitized, no secrets)
  - `response_code`: HTTP status returned
  - `created_at`: When action occurred

### Webhook Logging

All webhook payloads logged to `webhook_logs` table:

- **Fields:**
  - `platform`: facebook, instagram, whatsapp
  - `event_type`: message, delivery, read, etc.
  - `payload`: Full JSON payload (for debugging)
  - `verified`: Whether signature verification passed
  - `processed`: Whether action was taken
  - `error`: Any error messages if processing failed

### Retention Policy

- Admin audit logs: Keep for 90 days
- Webhook logs: Keep for 30 days (use `php artisan audit:cleanup`)
- Message content: Keep indefinitely (customer records)

## 7. Security Headers

### HTTP Response Headers

```
X-Content-Type-Options: nosniff
  ↳ Prevents MIME sniffing attacks

X-Frame-Options: DENY
  ↳ Prevents clickjacking (disallow framing in iframes)

Content-Security-Policy: 
  default-src 'self';
  script-src 'self' cdn.jsdelivr.net;
  style-src 'self' 'unsafe-inline' cdn.jsdelivr.net;
  img-src 'self' data: https:;
  ↳ Prevents XSS and injection attacks

Strict-Transport-Security: max-age=31536000; includeSubDomains
  ↳ Forces HTTPS for 1 year

X-XSS-Protection: 1; mode=block
  ↳ Browser XSS protection (legacy)

Referrer-Policy: strict-origin-when-cross-origin
  ↳ Limits referrer information in requests
```

Implementation: `SecurityHeaders` middleware in `app/Http/Middleware/SecurityHeaders.php`

## 8. Configuration & Secrets Management

### Required Environment Variables

```env
# Meta (Facebook/Instagram)  
META_APP_ID=
META_APP_SECRET=
META_PAGE_ACCESS_TOKEN=
META_WEBHOOK_VERIFY_TOKEN=

# WhatsApp
WHATSAPP_BUSINESS_ACCOUNT_ID=
WHATSAPP_API_KEY=
WHATSAPP_PHONE_NUMBER_ID=

# Real-time Broadcasting
BROADCAST_DRIVER=reverb  # or pusher
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=

# AI Integration
OPENAI_API_KEY=
OPENAI_ORG_ID=
PINECONE_API_KEY=
PINECONE_INDEX=

# Security
WEBHOOK_SIGNATURE_TIMEOUT=300  # seconds
WEBHOOK_RATE_LIMIT=1000  # per minute
MAX_MESSAGE_LENGTH=5000  # characters
AI_SUGGESTION_RATE_LIMIT=10  # per minute
MESSAGE_SEND_RATE_LIMIT=30  # per minute
LOG_WEBHOOK_PAYLOADS=false  # never log full payloads in production
```

### Secrets Security Best Practices

1. **Never commit secrets to repository**
   - Use `.env` file (local, never committed)
   - Use `.env.example` as template

2. **Rotate credentials regularly**
   - Facebook/Instagram tokens: Quarterly
   - WhatsApp tokens: Quarterly
   - API keys: As per provider recommendations

3. **Use environment variable encryption**
   - Option 1: Use Laravel Vault (nova feature)
   - Option 2: Use HashiCorp Vault integration
   - Option 3: Use AWS Secrets Manager

4. **Access logging**
   - Log who accessed what secrets (audit trail)
   - Alert on unauthorized secret access

## 9. Data Privacy & Compliance

### GDPR Compliance

**Data Collection:**
- Customer phone numbers: Required for WhatsApp
- Customer emails: Optional, from inquiry forms
- Customer names: Provided in messages or inquiries
- Message content: Retained for conversation history

**Data Retention:**
- Messages: Keep for customer service history (90+ days minimum)
- Customer data: Keep as long as customer has interactions
- Right to deletion: Implement soft-delete for GDPR right-to-be-forgotten

**Data Subject Rights:**
- Implement data export feature (export all customer messages)
- Implement account deletion (cascade delete conversations/messages)

### Payment Card Industry (PCI) Compliance

- **Never store credit card data in messages**
- Messages referencing orders should not include payment info
- Use masking for order numbers in logs

### Customer Data Protection

- Never share customer data between conversations
- Use scoped queries to prevent data leakage
- Implement field-level access control if needed

## 10. Incident Response

### Security Incident Reporting

**Types of Incidents:**
- Unauthorized webhook signature - Log severity HIGH
- Multiple failed rate limit - Log severity MEDIUM
- SQL injection attempt - Log severity CRITICAL
- XSS attempt - Log severity CRITICAL

**Response Procedure:**

```
1. Detect: Logging/monitoring alerts on suspicious activity
2. Log: Record incident in admin_audit_logs with severity
3. Investigate: Review logs, identify impact
4. Contain: Block attacker IP if applicable
5. Remediate: Fix vulnerability or update configuration  
6. Notify: Alert admins via email/dashboard
7. Document: Create incident report for learning
```

### Monitoring & Alerting

- Monitor webhook failure rates (>5% = alert)
- Monitor rate limit violations (threshold = alert)
- Monitor authentication failures (>10/hour = alert)
- Monitor DB query performance (slow queries = log)

## 11. Deployment Security Checklist

Before deploying to production:

- [ ] All API keys/secrets in `.env` (never hardcoded)
- [ ] Database backups configured and tested
- [ ] HTTPS/TLS enabled and enforced
- [ ] Rate limiting enabled on all public endpoints
- [ ] Audit logging enabled
- [ ] Security headers configured
- [ ] CORS whitelist configured
- [ ] Admin middleware protecting admin routes
- [ ] Database encryption at rest (AWS RDS encryption, etc.)
- [ ] Firewall rules restricting database access
- [ ] Regular security updates scheduled
- [ ] Penetration testing completed (optional for high-value targets)

## 12. Security Tools & Monitoring

### Recommended Tools

- **Snyk**: Dependency vulnerability scanning
- **OWASP ZAP**: Web application security testing
- **GitHub Security**: Automated dependency alerts
- **Sentry**: Error tracking and security monitoring
- **DataDog**: Infrastructure monitoring and logging

### Laravel Security Commands

```bash
# Check for known vulnerabilities
php artisan security:check

# Audit logs cleanup
php artisan audit:cleanup

# Encrypt environment variables
php artisan env:encrypt

# Generate CSRF token validation
php artisan csrf:refresh
```

## Contact & Support

For security issues or vulnerabilities, contact the development team directly (never disclose publicly until patched).

Last Updated: 2026-02-19
