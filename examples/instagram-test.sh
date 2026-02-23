#!/bin/bash

################################################################################
# Omnichannel Webhook Test - Instagram Direct Message
#
# This example tests Instagram Direct Message webhook payload
#
# Instagram DM payloads differ from Facebook Messenger format.
# This shows the expected structure for Instagram DMs.
#
################################################################################

# Webhook URL
WEBHOOK_URL="${WEBHOOK_URL:-http://localhost:8000/api/webhooks/messages}"

# Instagram payload (different from Facebook Messenger)
PAYLOAD='{
  "object": "instagram",
  "entry": [
    {
      "id": "17841407866175038",
      "time": 1645180154,
      "changes": [
        {
          "field": "messages",
          "value": {
            "from": {
              "id": "12345678901234",
              "name": "Andrew Smith"
            },
            "to": [
              {
                "data": [
                  {
                    "id": "987654321"
                  }
                ]
              }
            ],
            "message": "Hi! I saw your post about the new smartwatch. Is it waterproof? How long does the battery last?",
            "timestamp": 1645180154
          }
        }
      ]
    }
  ]
}'

# App Secret for signature
APP_SECRET="your_app_secret"

# Calculate signature the same way Meta does
SIGNATURE="sha256=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$APP_SECRET" | sed 's/.*= //')"

echo "Testing Instagram Direct Message webhook..."
echo "Webhook URL: $WEBHOOK_URL"
echo "Signature: $SIGNATURE"
echo ""

# Send the webhook
curl -X POST "$WEBHOOK_URL" \
  -H "X-Hub-Signature-256: $SIGNATURE" \
  -H "Content-Type: application/json" \
  -d "$PAYLOAD" \
  -v

echo ""
echo "Test complete!"
