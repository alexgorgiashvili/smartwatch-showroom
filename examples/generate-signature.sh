#!/bin/bash

################################################################################
# Webhook Signature Generator
#
# This script helps generate valid webhook signatures for testing.
#
# Usage:
#   ./generate-signature.sh [payload_file] [secret]
#
# Example:
#   ./generate-signature.sh meta-payload.json my-app-secret
#
################################################################################

PAYLOAD_FILE="${1:-meta-payload.json}"
SECRET="${2:-test-secret}"

if [ ! -f "$PAYLOAD_FILE" ]; then
    echo "Error: Payload file '$PAYLOAD_FILE' not found"
    exit 1
fi

echo "Generating webhook signature..."
echo "Payload file: $PAYLOAD_FILE"
echo "Secret: $SECRET"
echo ""

# Read payload
PAYLOAD=$(cat "$PAYLOAD_FILE")

# Generate signature (same format as Meta webhooks)
HASH=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/.*= //')
SIGNATURE="sha256=$HASH"

echo "Generated Signature:"
echo "$SIGNATURE"
echo ""
echo "Full Header:"
echo "X-Hub-Signature-256: $SIGNATURE"
echo ""
echo "Use this header when testing webhooks:"
echo ""
echo "curl -X POST http://localhost:8000/api/webhooks/messages \\"
echo "  -H 'X-Hub-Signature-256: $SIGNATURE' \\"
echo "  -H 'Content-Type: application/json' \\"
echo "  -d @$PAYLOAD_FILE"
