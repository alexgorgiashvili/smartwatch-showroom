#!/bin/bash

################################################################################
# Omnichannel Inbox - Webhook Testing Script
#
# This script tests webhook functionality for Meta (Facebook/Instagram)
# and WhatsApp Cloud API integrations.
#
# Usage:
#   ./webhook-test.sh [option]
#
# Options:
#   meta             - Test Meta webhook
#   whatsapp         - Test WhatsApp webhook
#   meta-verify      - Test Meta verification challenge
#   load             - Load test with multiple requests
#   all              - Run all tests
#
################################################################################

# Configuration
WEBHOOK_URL="${WEBHOOK_URL:-http://localhost:8000/api/webhooks/messages}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[✓]${NC} $1"
}

print_error() {
    echo -e "${RED}[✗]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[!]${NC} $1"
}

# Display usage
usage() {
    echo "Usage: $0 [option]"
    echo ""
    echo "Options:"
    echo "  meta              Test Meta webhook processing"
    echo "  whatsapp          Test WhatsApp webhook processing"
    echo "  meta-verify       Test Meta verification challenge"
    echo "  load              Load test (10 requests)"
    echo "  all               Run all tests"
    echo "  help              Show this help message"
    echo ""
    echo "Environment Variables:"
    echo "  WEBHOOK_URL       Webhook endpoint (default: http://localhost:8000/api/webhooks/messages)"
    echo ""
}

# Test Meta webhook
test_meta_webhook() {
    print_status "Testing Meta webhook..."

    # Load Meta payload
    if [ ! -f "$SCRIPT_DIR/meta-payload.json" ]; then
        print_error "meta-payload.json not found in $SCRIPT_DIR"
        return 1
    fi

    PAYLOAD=$(cat "$SCRIPT_DIR/meta-payload.json")
    APP_SECRET="${APP_SECRET:-test-secret}"

    # Calculate signature
    SIGNATURE="sha256=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$APP_SECRET" -binary | xxd -p -r | base64 -w0 | sed 's/=$//')"

    # Send webhook
    print_status "Sending payload to $WEBHOOK_URL"
    print_status "Signature Header: $SIGNATURE"

    RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$WEBHOOK_URL" \
        -H "X-Hub-Signature-256: $SIGNATURE" \
        -H "Content-Type: application/json" \
        -d "$PAYLOAD")

    HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
    BODY=$(echo "$RESPONSE" | head -n-1)

    print_status "Response Code: $HTTP_CODE"

    if [ "$HTTP_CODE" = "200" ]; then
        print_success "Meta webhook test passed!"
        return 0
    else
        print_error "Meta webhook test failed with code $HTTP_CODE"
        echo "Response: $BODY"
        return 1
    fi
}

# Test WhatsApp webhook
test_whatsapp_webhook() {
    print_status "Testing WhatsApp webhook..."

    # Load WhatsApp payload
    if [ ! -f "$SCRIPT_DIR/whatsapp-payload.json" ]; then
        print_error "whatsapp-payload.json not found in $SCRIPT_DIR"
        return 1
    fi

    PAYLOAD=$(cat "$SCRIPT_DIR/whatsapp-payload.json")
    API_KEY="${WHATSAPP_API_KEY:-test-key}"

    # Send webhook with authorization
    print_status "Sending payload to $WEBHOOK_URL"

    RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$WEBHOOK_URL" \
        -H "Authorization: Bearer $API_KEY" \
        -H "Content-Type: application/json" \
        -d "$PAYLOAD")

    HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
    BODY=$(echo "$RESPONSE" | head -n-1)

    print_status "Response Code: $HTTP_CODE"

    if [ "$HTTP_CODE" = "200" ]; then
        print_success "WhatsApp webhook test passed!"
        return 0
    else
        print_error "WhatsApp webhook test failed with code $HTTP_CODE"
        echo "Response: $BODY"
        return 1
    fi
}

# Test Meta verification challenge
test_meta_verify() {
    print_status "Testing Meta verification challenge..."

    VERIFY_TOKEN="${VERIFY_TOKEN:-test-token}"
    CHALLENGE="test_challenge_string_12345"

    # Build query string
    VERIFY_URL="${WEBHOOK_URL}?hub.mode=subscribe&hub.challenge=$CHALLENGE&hub.verify_token=$VERIFY_TOKEN"

    print_status "Sending GET request to verification URL"

    RESPONSE=$(curl -s -w "\n%{http_code}" -X GET "$VERIFY_URL")

    HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
    BODY=$(echo "$RESPONSE" | head -n-1)

    print_status "Response Code: $HTTP_CODE"

    if [ "$HTTP_CODE" = "200" ] && [ "$BODY" = "$CHALLENGE" ]; then
        print_success "Meta verification test passed!"
        print_status "Challenge returned correctly: $BODY"
        return 0
    else
        print_error "Meta verification test failed"
        echo "Expected: $CHALLENGE"
        echo "Got: $BODY"
        return 1
    fi
}

# Load test
test_load() {
    print_status "Running load test (10 requests)..."

    if [ ! -f "$SCRIPT_DIR/meta-payload.json" ]; then
        print_error "meta-payload.json not found"
        return 1
    fi

    PAYLOAD=$(cat "$SCRIPT_DIR/meta-payload.json")
    APP_SECRET="${APP_SECRET:-test-secret}"

    SIGNATURE="sha256=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$APP_SECRET" -binary | xxd -p -r | base64 -w0 | sed 's/=$//')"

    SUCCESS=0
    FAILED=0

    print_status "Sending 10 concurrent requests..."

    for i in {1..10}; do
        RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$WEBHOOK_URL" \
            -H "X-Hub-Signature-256: $SIGNATURE" \
            -H "Content-Type: application/json" \
            -d "$PAYLOAD" &)

        HTTP_CODE=$(echo "$RESPONSE" | tail -n1)

        if [ "$HTTP_CODE" = "200" ]; then
            ((SUCCESS++))
            print_status "Request $i: 200 OK"
        else
            ((FAILED++))
            print_error "Request $i: Failed ($HTTP_CODE)"
        fi
    done

    # Wait for all background jobs
    wait

    print_status ""
    print_status "Load test results:"
    print_success "Success: $SUCCESS/10"

    if [ $FAILED -gt 0 ]; then
        print_error "Failed: $FAILED/10"
        return 1
    else
        return 0
    fi
}

# Verify webhook URL is accessible
verify_connectivity() {
    print_status "Verifying webhook endpoint accessibility..."

    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X GET "$WEBHOOK_URL/health" 2>&1)

    if [ "$HTTP_CODE" = "000" ] || [ "$HTTP_CODE" = "404" ]; then
        print_warning "Endpoint may not be accessible. This might be expected."
        print_status "Continuing anyway... you may see connection errors below."
    else
        print_success "Endpoint is accessible (HTTP $HTTP_CODE)"
    fi
}

# Run all tests
run_all_tests() {
    print_status "Running all webhook tests..."
    echo ""

    verify_connectivity
    echo ""

    ALL_PASS=true

    print_status "Test 1: Meta Verification Challenge"
    if test_meta_verify; then
        print_success "Test 1 passed"
    else
        print_error "Test 1 failed"
        ALL_PASS=false
    fi
    echo ""

    print_status "Test 2: Meta Webhook"
    if test_meta_webhook; then
        print_success "Test 2 passed"
    else
        print_error "Test 2 failed"
        ALL_PASS=false
    fi
    echo ""

    print_status "Test 3: WhatsApp Webhook"
    if test_whatsapp_webhook; then
        print_success "Test 3 passed"
    else
        print_error "Test 3 failed"
        ALL_PASS=false
    fi
    echo ""

    print_status "Test 4: Load Test"
    if test_load; then
        print_success "Test 4 passed"
    else
        print_error "Test 4 failed"
        ALL_PASS=false
    fi
    echo ""

    if [ "$ALL_PASS" = true ]; then
        print_success "All tests passed! ✓"
        return 0
    else
        print_error "Some tests failed. Check output above."
        return 1
    fi
}

# Main
main() {
    OPTION="${1:-help}"

    print_status "Omnichannel Webhook Testing"
    print_status "Webhook URL: $WEBHOOK_URL"
    echo ""

    case "$OPTION" in
        meta)
            test_meta_webhook
            ;;
        whatsapp)
            test_whatsapp_webhook
            ;;
        meta-verify)
            test_meta_verify
            ;;
        load)
            test_load
            ;;
        all)
            run_all_tests
            ;;
        help)
            usage
            ;;
        *)
            echo "Unknown option: $OPTION"
            usage
            exit 1
            ;;
    esac
}

# Run main function
main "$@"
