#!/bin/bash

################################################################################
# Comprehensive Webhook Testing Guide
#
# This document explains how to properly test webhooks for the 
# Omnichannel Inbox System.
#
# Prerequisites:
# - Running Laravel application (php artisan serve)
# - curl command-line tool
# - bash shell
#
################################################################################

# Make scripts executable
chmod +x webhook-test.sh
chmod +x generate-signature.sh
chmod +x instagram-test.sh

echo "Omnichannel Webhook Testing Guide"
echo "=================================="
echo ""

echo "1. Start your application:"
echo "   php artisan serve"
echo ""

echo "2. Test Meta webhook verification (no signature required):"
echo "   ./webhook-test.sh meta-verify"
echo ""

echo "3. Test Meta webhook message:"
echo "   ./webhook-test.sh meta"
echo ""

echo "4. Test WhatsApp webhook message:"
echo "   ./webhook-test.sh whatsapp"
echo ""

echo "5. Run all tests:"
echo "   ./webhook-test.sh all"
echo ""

echo "6. Run load test:"
echo "   ./webhook-test.sh load"
echo ""

echo "7. Generate custom signature:"
echo "   ./generate-signature.sh meta-payload.json your-app-secret"
echo ""

echo "8. Test with custom webhook URL:"
echo "   WEBHOOK_URL=https://yourdomain.com/api/webhooks/messages ./webhook-test.sh all"
echo ""

echo "Advanced Testing:"
echo "================"
echo ""

echo "Test with invalid signature (should return 403):"
echo "  curl -X POST http://localhost:8000/api/webhooks/messages \\"
echo "    -H 'X-Hub-Signature-256: sha256=invalidsignature' \\"
echo "    -H 'Content-Type: application/json' \\"
echo "    -d @meta-payload.json"
echo ""

echo "Check webhook logs:"
echo "  php artisan tinker"
echo "  >>> App\Models\WebhookLog::latest()->take(10)->get()"
echo ""

echo "View real-time logs:"
echo "  tail -f storage/logs/laravel.log | grep -i webhook"
echo ""
