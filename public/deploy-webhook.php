<?php
/**
 * GitHub Webhook Auto-Deploy Handler
 *
 * This script receives GitHub webhook notifications and automatically
 * deploys the latest changes to the production server.
 *
 * Setup Instructions:
 * 1. Generate a secure secret token (e.g., using: openssl rand -hex 32)
 * 2. Add the secret to GitHub repo: Settings → Webhooks → Add webhook
 *    - Payload URL: http://your-domain.com/deploy-webhook.php
 *    - Content type: application/json
 *    - Secret: [your generated secret]
 *    - Events: Just the push event
 * 3. Set the same secret in the WEBHOOK_SECRET constant below
 * 4. Ensure this file and server-deploy.sh have proper permissions:
 *    chmod 644 deploy-webhook.php
 *    chmod +x ../server-deploy.sh
 */

// ============================================================================
// CONFIGURATION - CHANGE THESE VALUES
// ============================================================================

// Secret token for webhook verification (MUST match GitHub webhook secret)
// Generate with: openssl rand -hex 32
define('WEBHOOK_SECRET', 'YOUR_SECRET_TOKEN_HERE');

// Path to the deployment script
define('DEPLOY_SCRIPT', __DIR__ . '/../server-deploy.sh');

// Path to the log file
define('LOG_FILE', __DIR__ . '/../storage/logs/deploy.log');

// Branch to deploy (only deploy when this branch is pushed)
define('DEPLOY_BRANCH', 'refs/heads/main');

// Enable/disable deployment (set to false to temporarily disable auto-deploy)
define('DEPLOYMENT_ENABLED', true);

// ============================================================================
// DO NOT EDIT BELOW THIS LINE
// ============================================================================

/**
 * Log a message with timestamp
 */
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;

    // Ensure log directory exists
    $logDir = dirname(LOG_FILE);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);

    // Also output for webhook response
    echo $logEntry;
}

/**
 * Verify GitHub webhook signature
 */
function verifyGitHubSignature($payload, $signature) {
    if (empty($signature)) {
        return false;
    }

    list($algo, $hash) = explode('=', $signature, 2) + ['', ''];

    if (empty($hash)) {
        return false;
    }

    $expectedHash = hash_hmac($algo, $payload, WEBHOOK_SECRET);

    return hash_equals($expectedHash, $hash);
}

/**
 * Execute deployment script
 */
function executeDeploy() {
    logMessage('Starting deployment process...');

    if (!file_exists(DEPLOY_SCRIPT)) {
        logMessage('ERROR: Deploy script not found at ' . DEPLOY_SCRIPT, 'ERROR');
        http_response_code(500);
        die('Deploy script not found');
    }

    if (!is_executable(DEPLOY_SCRIPT)) {
        logMessage('ERROR: Deploy script is not executable. Run: chmod +x ' . DEPLOY_SCRIPT, 'ERROR');
        http_response_code(500);
        die('Deploy script is not executable');
    }

    // Change to project root directory
    $projectRoot = dirname(__DIR__);
    chdir($projectRoot);

    // Execute deployment script
    $output = [];
    $returnCode = 0;
    exec(DEPLOY_SCRIPT . ' 2>&1', $output, $returnCode);

    // Log output
    foreach ($output as $line) {
        logMessage($line, 'DEPLOY');
    }

    if ($returnCode === 0) {
        logMessage('✓ Deployment completed successfully', 'SUCCESS');
        return true;
    } else {
        logMessage('✗ Deployment failed with exit code: ' . $returnCode, 'ERROR');
        return false;
    }
}

// ============================================================================
// MAIN EXECUTION
// ============================================================================

// Set content type
header('Content-Type: text/plain');

logMessage('========================================');
logMessage('Webhook received from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

// Check if deployment is enabled
if (!DEPLOYMENT_ENABLED) {
    logMessage('Deployment is currently disabled', 'WARNING');
    http_response_code(200);
    die('Deployment disabled');
}

// Verify webhook secret is configured
if (WEBHOOK_SECRET === 'YOUR_SECRET_TOKEN_HERE') {
    logMessage('ERROR: Webhook secret not configured! Please set WEBHOOK_SECRET constant.', 'ERROR');
    http_response_code(500);
    die('Webhook not configured');
}

// Get the request payload
$payload = file_get_contents('php://input');

if (empty($payload)) {
    logMessage('ERROR: Empty payload received', 'ERROR');
    http_response_code(400);
    die('Empty payload');
}

// Get the signature header
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '';

// Verify the signature
if (!verifyGitHubSignature($payload, $signature)) {
    logMessage('ERROR: Invalid webhook signature - authentication failed', 'ERROR');
    http_response_code(403);
    die('Invalid signature');
}

logMessage('✓ Webhook signature verified');

// Parse the payload
$data = json_decode($payload, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    logMessage('ERROR: Invalid JSON payload - ' . json_last_error_msg(), 'ERROR');
    http_response_code(400);
    die('Invalid JSON');
}

// Check if this is a push event
$event = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';

if ($event !== 'push') {
    logMessage('Ignoring non-push event: ' . $event, 'INFO');
    http_response_code(200);
    die('Not a push event');
}

// Check if pushed to correct branch
$ref = $data['ref'] ?? '';

if ($ref !== DEPLOY_BRANCH) {
    logMessage('Ignoring push to branch: ' . $ref . ' (only deploying ' . DEPLOY_BRANCH . ')', 'INFO');
    http_response_code(200);
    die('Not the deployment branch');
}

// Log commit information
$pusher = $data['pusher']['name'] ?? 'unknown';
$commits = count($data['commits'] ?? []);
$repoName = $data['repository']['full_name'] ?? 'unknown';

logMessage("Push to {$repoName} by {$pusher} - {$commits} commit(s)");

// List commits
if (!empty($data['commits'])) {
    foreach ($data['commits'] as $commit) {
        $message = $commit['message'] ?? 'No message';
        $author = $commit['author']['name'] ?? 'unknown';
        $shortId = substr($commit['id'] ?? '', 0, 7);
        logMessage("  - [{$shortId}] {$message} (by {$author})");
    }
}

// Execute deployment
$success = executeDeploy();

// Return appropriate response
if ($success) {
    http_response_code(200);
    logMessage('========================================');
    echo "\nDeployment successful!";
} else {
    http_response_code(500);
    logMessage('========================================');
    echo "\nDeployment failed! Check logs for details.";
}
