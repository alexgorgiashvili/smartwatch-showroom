# Omnichannel Inbox System - API Documentation

## Overview

The Omnichannel Inbox System provides comprehensive REST API endpoints for:
- Receiving messages from multiple platforms (Meta, WhatsApp)
- Managing conversations and customer interactions
- Sending replies through the admin panel
- Getting AI-powered message suggestions
- Managing message status and metadata

**Base URL**: `https://yourdomain.com`
**API Version**: v1
**Authentication**: Laravel Sanctum tokens + Webhook signature verification

---

## Table of Contents

1. [Webhook Endpoints](#webhook-endpoints)
2. [Admin Inbox Endpoints](#admin-inbox-endpoints)
3. [Message Endpoints](#message-endpoints)
4. [Error Handling](#error-handling)
5. [Rate Limiting](#rate-limiting)
6. [Examples](#examples)

---

## Webhook Endpoints

### Receive Messages from Platforms
**Endpoint**: `POST /api/webhooks/messages`

Receiving endpoint for webhook events from Meta (Facebook/Instagram) and WhatsApp Cloud API.

**Authentication**: Signature verification via `X-Hub-Signature-256` header (Meta) or `Authorization` bearer token (WhatsApp)

**Headers Required**:
```
X-Hub-Signature-256: sha256=<hmac_signature>
Content-Type: application/json
```

**Request Body** (Meta Example):
```json
{
  "object": "page",
  "entry": [
    {
      "id": "page_id",
      "time": 1234567890,
      "messaging": [
        {
          "sender": {
            "id": "user_id"
          },
          "recipient": {
            "id": "page_id"
          },
          "timestamp": 1234567890000,
          "message": {
            "mid": "message_id",
            "text": "Hello, how can I help?",
            "attachments": [
              {
                "type": "image",
                "payload": {
                  "url": "https://example.com/image.jpg"
                }
              }
            ]
          }
        }
      ]
    }
  ]
}
```

**Request Body** (WhatsApp Example):
```json
{
  "object": "whatsapp_business_account",
  "entry": [
    {
      "id": "account_id",
      "changes": [
        {
          "field": "messages",
          "value": {
            "messaging_product": "whatsapp",
            "metadata": {
              "display_phone_number": "1234567890",
              "phone_number_id": "1234567890"
            },
            "messages": [
              {
                "from": "1234567890",
                "id": "wamid.123456",
                "timestamp": "1234567890",
                "type": "text",
                "text": {
                  "body": "Hello from WhatsApp"
                }
              }
            ]
          }
        }
      ]
    }
  ]
}
```

**Response** (200 OK):
```json
""
```
*Note: Meta and WhatsApp expect an empty 200 response within 20 seconds*

**Response** (403 Forbidden - Invalid Signature):
```json
{
  "error": "Invalid webhook signature"
}
```

**Status Codes**:
- `200 OK` - Webhook received and processed successfully
- `403 Forbidden` - Invalid signature verification
- `422 Unprocessable Entity` - Invalid webhook format
- `500 Internal Server Error` - Processing error

**Rate Limits**: None (webhook endpoint, rate limited by platform)

**Processing Details**:
- Signature verified using HMAC-SHA256 (Meta) or bearer token (WhatsApp)
- Webhook logged to `webhook_logs` table
- Message parsed and processed asynchronously
- `MessageReceived` event broadcasted via Reverb/Pusher
- Customer automatically created if new
- Conversation automatically created/matched

---

### Meta Webhook Verification
**Endpoint**: `GET /api/webhooks/messages`

Handle Meta's initial webhook verification challenge. Called once when setting up webhook in Meta app dashboard.

**Query Parameters**:
```
hub.mode=subscribe
hub.challenge=<challenge_string>
hub.verify_token=<your_verify_token>
```

**Example Request**:
```bash
GET /api/webhooks/messages?hub.mode=subscribe&hub.challenge=abc123&hub.verify_token=my_token
```

**Response** (200 OK):
```
abc123
```
*Returns the challenge string as plain text*

**Response** (403 Forbidden):
```
{"error": "Invalid verification token"}
```

**Status Codes**:
- `200 OK` - Verification successful
- `403 Forbidden` - Invalid verification token

**Usage**:
1. Go to Meta App Dashboard → Settings → Messenger Platform
2. Select your app and page
3. Under "Webhooks", click "Set Up Webhooks"
4. Enter callback URL: `https://yourdomain.com/api/webhooks/messages`
5. Enter verify token (from your `.env` file)
6. Meta will automatically call this endpoint to verify

---

## Admin Inbox Endpoints

All inbox endpoints require authentication and admin privileges.

### List All Conversations
**Endpoint**: `GET /admin/inbox`

Retrieve paginated list of all conversations with latest message preview.

**Authentication**: Required (Laravel Sanctum)

**Query Parameters**:
| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `status` | string | Filter by status: `active`, `archived`, `closed` | `?status=active` |
| `platform` | string | Filter by platform: `facebook`, `instagram`, `whatsapp` | `?platform=whatsapp` |
| `unread` | boolean | Show only unread conversations | `?unread=true` |
| `q` | string | Search customer name or message content | `?q=John` |
| `page` | integer | Page number (default: 1) | `?page=2` |
| `per_page` | integer | Items per page (default: 20) | `?per_page=50` |

**Example Request**:
```bash
curl -X GET "https://yourdomain.com/admin/inbox?status=active&platform=whatsapp&page=1" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

**Response** (200 OK):
```json
{
  "data": [
    {
      "id": 1,
      "customer": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "phone": "+1234567890"
      },
      "platform": "whatsapp",
      "subject": null,
      "status": "active",
      "unread_count": 3,
      "last_message_at": "2024-02-19T10:30:00Z",
      "created_at": "2024-02-10T08:00:00Z",
      "updated_at": "2024-02-19T10:30:00Z",
      "last_message": {
        "id": 45,
        "content": "Thanks for your help!",
        "sender_type": "customer",
        "created_at": "2024-02-19T10:30:00Z"
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "per_page": 20,
    "to": 20,
    "total": 100
  },
  "links": {
    "first": "https://yourdomain.com/admin/inbox?page=1",
    "last": "https://yourdomain.com/admin/inbox?page=5",
    "next": "https://yourdomain.com/admin/inbox?page=2"
  }
}
```

**Status Codes**:
- `200 OK` - Conversations retrieved
- `401 Unauthorized` - No valid authentication token
- `403 Forbidden` - User not an admin

---

### Get Conversation Detail
**Endpoint**: `GET /admin/inbox/{id}`

Retrieve single conversation with all messages in chronological order.

**Authentication**: Required (Laravel Sanctum)

**Path Parameters**:
| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | Conversation ID |

**Query Parameters**:
| Parameter | Type | Description |
|-----------|------|-------------|
| `page` | integer | Message page (default: 1) |
| `per_page` | integer | Messages per page (default: 50) |

**Example Request**:
```bash
curl -X GET "https://yourdomain.com/admin/inbox/1" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

**Response** (200 OK):
```json
{
  "conversation": {
    "id": 1,
    "customer_id": 1,
    "customer": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "phone": "+1234567890",
      "avatar_url": "https://example.com/avatar.jpg"
    },
    "platform": "whatsapp",
    "platform_conversation_id": "1234567890",
    "subject": null,
    "status": "active",
    "unread_count": 0,
    "last_message_at": "2024-02-19T10:30:00Z",
    "created_at": "2024-02-10T08:00:00Z",
    "updated_at": "2024-02-19T10:30:00Z"
  },
  "messages": {
    "data": [
      {
        "id": 40,
        "conversation_id": 1,
        "sender_type": "customer",
        "sender_name": "John Doe",
        "content": "Hello, I have a question about your product",
        "media_url": null,
        "media_type": null,
        "read_at": null,
        "created_at": "2024-02-19T10:15:00Z"
      },
      {
        "id": 41,
        "conversation_id": 1,
        "sender_type": "admin",
        "sender_name": "Support Agent",
        "content": "Hi John! How can I help?",
        "media_url": null,
        "media_type": null,
        "read_at": "2024-02-19T10:16:00Z",
        "created_at": "2024-02-19T10:15:30Z"
      }
    ],
    "meta": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 50,
      "total": 2
    }
  }
}
```

**Side Effects**:
- Conversation automatically marked as read
- `unread_count` set to 0
- Messages automatically marked as read (via event listener)

**Status Codes**:
- `200 OK` - Conversation retrieved
- `401 Unauthorized` - No valid authentication token
- `403 Forbidden` - User not an admin
- `404 Not Found` - Conversation doesn't exist

---

## Message Endpoints

### Send Reply Message
**Endpoint**: `POST /admin/inbox/{conversation_id}/messages`

Send a reply message from admin to customer on the selected platform.

**Authentication**: Required (Laravel Sanctum + Admin)

**Path Parameters**:
| Parameter | Type | Description |
|-----------|------|-------------|
| `conversation_id` | integer | Conversation ID to reply to |

**Request Body**:
```json
{
  "content": "Thank you for reaching out! How can we assist you?"
}
```

**Validation Rules**:
- `content` - Required, string, min: 1, max: 2000 characters
- Whitespace-only content is rejected

**Example Request**:
```bash
curl -X POST "https://yourdomain.com/admin/inbox/1/messages" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "Thank you for reaching out!"
  }'
```

**Response** (200 OK | 201 Created):
```json
{
  "id": 42,
  "conversation_id": 1,
  "customer_id": 1,
  "sender_type": "admin",
  "sender_name": "Support Agent",
  "sender_id": 8,
  "content": "Thank you for reaching out!",
  "media_url": null,
  "media_type": null,
  "platform_message_id": "msg_admin_42",
  "read_at": "2024-02-19T10:17:00Z",
  "created_at": "2024-02-19T10:17:00Z",
  "updated_at": "2024-02-19T10:17:00Z"
}
```

**Response** (422 Unprocessable Entity):
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "content": [
      "The content field is required.",
      "The content field must be at least 1 characters."
    ]
  }
}
```

**Side Effects**:
- Message created in database
- `conversation.last_message_at` updated
- Message sent to customer on their platform (Meta/WhatsApp)
- `MessageReceived` event broadcasted to admin users

**Status Codes**:
- `200 OK` - Message sent successfully
- `201 Created` - Message created
- `400 Bad Request` - Invalid conversation ID
- `401 Unauthorized` - No valid authentication token
- `403 Forbidden` - User not an admin
- `404 Not Found` - Conversation doesn't exist
- `422 Unprocessable Entity` - Validation failed

**Rate Limits**: 30 requests per minute per user

---

### Mark Message as Read
**Endpoint**: `PATCH /admin/inbox/{conversation_id}/messages/{message_id}/read`

Mark individual message as read (updates `read_at` timestamp).

**Authentication**: Required (Laravel Sanctum + Admin)

**Path Parameters**:
| Parameter | Type | Description |
|-----------|------|-------------|
| `conversation_id` | integer | Conversation ID |
| `message_id` | integer | Message ID |

**Request Body**: None (empty)

**Example Request**:
```bash
curl -X PATCH "https://yourdomain.com/admin/inbox/1/messages/40/read" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

**Response** (200 OK):
```json
{
  "id": 40,
  "conversation_id": 1,
  "content": "Hello, I have a question...",
  "read_at": "2024-02-19T10:25:00Z",
  "updated_at": "2024-02-19T10:25:00Z"
}
```

**Status Codes**:
- `200 OK` - Message marked as read
- `404 Not Found` - Message doesn't exist

---

### Delete Message
**Endpoint**: `DELETE /admin/inbox/{conversation_id}/messages/{message_id}`

Delete a message (soft delete, preserves audit trail).

**Authentication**: Required (Laravel Sanctum + Admin)

**Path Parameters**:
| Parameter | Type | Description |
|-----------|------|-------------|
| `conversation_id` | integer | Conversation ID |
| `message_id` | integer | Message ID |

**Example Request**:
```bash
curl -X DELETE "https://yourdomain.com/admin/inbox/1/messages/40" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Response** (204 No Content):
```
[empty body]
```

**Status Codes**:
- `204 No Content` - Message deleted successfully
- `404 Not Found` - Message doesn't exist

---

### Get AI Suggestions
**Endpoint**: `GET /admin/inbox/{conversation_id}/suggest-ai`

Get AI-powered message suggestions based on conversation context.

**Authentication**: Required (Laravel Sanctum + Admin)

**Path Parameters**:
| Parameter | Type | Description |
|-----------|------|-------------|
| `conversation_id` | integer | Conversation ID |

**Query Parameters**:
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `count` | integer | 3 | Number of suggestions (max: 5) |
| `context_messages` | integer | 5 | Number of context messages to consider |

**Example Request**:
```bash
curl -X GET "https://yourdomain.com/admin/inbox/1/suggest-ai?count=3" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

**Response** (200 OK):
```json
{
  "suggestions": [
    "Thank you for contacting us! We're here to help with any questions.",
    "Could you provide more details about what you're looking for?",
    "I'd be happy to assist. What specific product are you interested in?"
  ],
  "tokens_used": 150,
  "created_at": "2024-02-19T10:30:00Z"
}
```

**Response** (402 Payment Required - API Quota Exceeded):
```json
{
  "error": "OpenAI API quota exceeded"
}
```

**Status Codes**:
- `200 OK` - Suggestions generated
- `400 Bad Request` - Invalid parameters
- `401 Unauthorized` - No valid authentication token
- `403 Forbidden` - User not an admin
- `404 Not Found` - Conversation doesn't exist
- `500 Internal Server Error` - AI service unavailable

**Rate Limits**: 10 requests per minute per user

---

### Update Conversation Status
**Endpoint**: `POST /admin/inbox/{conversation_id}/status`

Update conversation status (active, archived, closed).

**Authentication**: Required (Laravel Sanctum + Admin)

**Path Parameters**:
| Parameter | Type | Description |
|-----------|------|-------------|
| `conversation_id` | integer | Conversation ID |

**Request Body**:
```json
{
  "status": "archived"
}
```

**Valid Statuses**:
- `active` - Open conversation, replies possible
- `archived` - Archived but retrievable, replies possible
- `closed` - Closed conversation, no new replies (unless reopened)

**Example Request**:
```bash
curl -X POST "https://yourdomain.com/admin/inbox/1/status" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "closed"
  }'
```

**Response** (200 OK):
```json
{
  "id": 1,
  "status": "closed",
  "updated_at": "2024-02-19T10:32:00Z"
}
```

**Events**: `ConversationStatusChanged` broadcast to all admins

**Status Codes**:
- `200 OK` - Status updated
- `400 Bad Request` - Invalid status value
- `401 Unauthorized` - No valid authentication token
- `403 Forbidden` - User not an admin
- `404 Not Found` - Conversation doesn't exist

---

## Error Handling

### Error Response Format

All errors return JSON with the following structure:

```json
{
  "error": "Error message",
  "code": "ERROR_CODE",
  "details": {}
}
```

### Common Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `INVALID_SIGNATURE` | 403 | Webhook signature verification failed |
| `INVALID_TOKEN` | 401 | Authentication token invalid or expired |
| `UNAUTHORIZED` | 403 | User lacks required permissions |
| `NOT_FOUND` | 404 | Resource does not exist |
| `VALIDATION_ERROR` | 422 | Request validation failed |
| `RATE_LIMITED` | 429 | Too many requests |
| `INTERNAL_ERROR` | 500 | Server error |

### Validation Error Response

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "content": [
      "The content field is required."
    ],
    "status": [
      "The selected status is invalid."
    ]
  }
}
```

---

## Rate Limiting

Rate limits are applied per authenticated user and endpoint:

| Endpoint | Limit | Window |
|----------|-------|--------|
| Send Message | 30 | 1 minute |
| AI Suggestion | 10 | 1 minute |
| List Conversations | 60 | 1 minute |
| Webhook Receive | Unlimited | - |

**Rate Limit Headers**:
```
X-RateLimit-Limit: 30
X-RateLimit-Remaining: 28
X-RateLimit-Reset: 1645264200
```

**Rate Limit Exceeded Response** (429):
```json
{
  "error": "Too Many Requests",
  "retry_after": 45
}
```

---

## Examples

### Complete Webhook Processing Flow

**1. Receive Webhook from WhatsApp**
```bash
curl -X POST "https://yourdomain.com/api/webhooks/messages" \
  -H "Authorization: Bearer YOUR_WHATSAPP_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "object": "whatsapp_business_account",
    "entry": [
      {
        "changes": [
          {
            "field": "messages",
            "value": {
              "messaging_product": "whatsapp",
              "metadata": {
                "phone_number_id": "1234567890"
              },
              "messages": [
                {
                  "from": "5521999999999",
                  "id": "wamid.123",
                  "timestamp": "1645100000",
                  "type": "text",
                  "text": {"body": "Hi, I need help with my order"}
                }
              ]
            }
          }
        ]
      }
    ]
  }'
```

**2. Get Conversation (as Admin)**
```bash
curl -X GET "https://yourdomain.com/admin/inbox/1" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

**3. Send Reply**
```bash
curl -X POST "https://yourdomain.com/admin/inbox/1/messages" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "Hi there! Thank you for contacting us. We'"'"'re looking into your order right now."
  }'
```

**4. Get AI Suggestions**
```bash
curl -X GET "https://yourdomain.com/admin/inbox/1/suggest-ai?count=3" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

**5. Close Conversation**
```bash
curl -X POST "https://yourdomain.com/admin/inbox/1/status" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status": "closed"}'
```

---

## Authentication

### Obtaining Token

Tokens are obtained via Laravel Sanctum. Contact your admin for token generation:

```bash
# Via Tinker (admin only)
php artisan tinker
>>> $user = User::find(1);
>>> $token = $user->createToken('api-token')->plainTextToken;
>>> echo $token;
```

### Using Token

Include token in Authorization header:
```
Authorization: Bearer YOUR_TOKEN_HERE
```

### Token Security

- Keep tokens secure, never commit to version control
- Rotate tokens regularly (at least monthly)
- Revoke tokens when staff leaves
- Use HTTPS for all API requests

---

## Webhook Security

### Signature Verification (Meta)

Meta signs all webhooks with `X-Hub-Signature-256` header:

```
X-Hub-Signature-256: sha256=<hash>
```

To verify:
```php
$signature = $request->header('X-Hub-Signature-256');
$appSecret = config('services.meta.app_secret');
$payload = file_get_contents('php://input');

$expectedHash = hash_hmac('sha256', $payload, $appSecret);
$providedHash = substr($signature, 7); // Remove "sha256=" prefix

$isValid = hash_equals($expectedHash, $providedHash);
```

The system automatically handles this verification.

---

## Support

For API issues or questions:
1. Check logs: `storage/logs/laravel.log`
2. Review webhook logs: `SELECT * FROM webhook_logs ORDER BY created_at DESC;`
3. Contact support with request/response details
