<?php

namespace App\Http\Controllers\Admin;

use App\Events\MessageReceived;
use App\Services\MetaApiService;
use App\Services\OmnichannelService;
use App\Services\WebhookVerificationService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    protected WebhookVerificationService $verificationService;
    protected OmnichannelService $omnichannelService;

    public function __construct(
        WebhookVerificationService $verificationService,
        OmnichannelService $omnichannelService
    ) {
        $this->verificationService = $verificationService;
        $this->omnichannelService = $omnichannelService;
    }

    /**
     * Handle incoming webhook payload from Meta (Facebook/Instagram) or WhatsApp
     *
     * @param Request $request
     * @return Response
     */
    public function handle(Request $request): Response
    {
        // Get platform from middleware
        $platform = $request->attributes->get('webhook_platform', 'facebook');
        $isVerified = $request->attributes->get('webhook_verified', false);

        // Extract event type and entry details
        $object = $request->input('object', 'unknown');
        $entries = $request->input('entry', []);

        // Determine event type from entries if available
        $eventType = $this->extractEventType($entries, $object);

        Log::info('Webhook received', [
            'platform' => $platform,
            'object' => $object,
            'event_type' => $eventType,
            'verified' => $isVerified,
            'entries_count' => count($entries),
        ]);

        // Log the webhook to database
        $webhookLog = $this->verificationService->logWebhook(
            $platform,
            $eventType,
            $request->all(),
            $isVerified
        );

        // Mark as verified if signature was valid
        if ($isVerified) {
            $webhookLog->markAsVerified();
        }

        // Process message events
        if ($eventType === 'message' && $isVerified) {
            $this->processMessage($platform, $request->all());
        }

        // Return 200 OK for all verified webhooks
        // Meta expects a 200 response within 20 seconds
        return response('', Response::HTTP_OK);
    }

    /**
     * Process an incoming message from webhook
     *
     * @param string $platform
     * @param array $payload
     * @return void
     */
    protected function processMessage(string $platform, array $payload): void
    {
        try {
            // Parse the webhook payload based on platform
            $parsedMessage = match ($platform) {
                'whatsapp' => app(WhatsAppService::class)->parseWebhookPayload($payload),
                'facebook', 'instagram' => app(MetaApiService::class)->parseWebhookPayload($payload),
                default => null,
            };

            // Skip if parsing failed
            if (!$parsedMessage) {
                Log::warning('Failed to parse webhook message', ['platform' => $platform]);
                return;
            }

            // Process the message through omnichannel service
            $message = $this->omnichannelService->processWebhookMessage($platform, $parsedMessage);

            // Broadcast event if message was created successfully
            if ($message) {
                $conversation = $message->conversation;
                $customer = $message->customer;

                broadcast(new MessageReceived($message, $conversation, $customer, $platform))->toOthers();

                Log::info('Message received event broadcasted', [
                    'message_id' => $message->id,
                    'conversation_id' => $conversation->id,
                    'customer_id' => $customer->id,
                    'platform' => $platform,
                ]);

                if ($conversation->ai_enabled) {
                    $aiService = app(\App\Services\AiConversationService::class);

                    if ($aiService->shouldAutoReplyToConversation($conversation, $message)) {
                        $success = $aiService->autoReply($conversation);

                        Log::info('Selective AI auto-reply attempt from webhook flow', [
                            'conversation_id' => $conversation->id,
                            'message_id' => $message->id,
                            'success' => $success,
                        ]);
                    } else {
                        Log::info('Selective AI policy skipped auto-reply from webhook flow', [
                            'conversation_id' => $conversation->id,
                            'message_id' => $message->id,
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Error processing webhook message', [
                'platform' => $platform,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Handle Meta webhook verification challenge (GET request)
     *
     * @param Request $request
     * @return Response|string
     */
    public function verify(Request $request)
    {
        // Get verification parameters from Meta
        $hubMode = $request->input('hub.mode');
        $hubChallenge = $request->input('hub.challenge');
        $hubVerifyToken = $request->input('hub.verify_token');

        // Get verify token from config
        $expectedToken = config('services.meta.verify_token');

        Log::info('Webhook verification challenge received', [
            'hub_mode' => $hubMode,
            'token_match' => $hubVerifyToken === $expectedToken,
        ]);

        // Verify the token matches
        if ($hubMode === 'subscribe' && $hubVerifyToken === $expectedToken) {
            // Return the challenge for verification
            return $hubChallenge;
        }

        // Token verification failed
        Log::warning('Webhook verification failed - invalid token');
        return response('Unauthorized', Response::HTTP_FORBIDDEN);
    }

    /**
     * Extract event type from webhook entries
     *
     * @param array $entries
     * @param string $defaultType
     * @return string
     */
    protected function extractEventType(array $entries, string $defaultType = 'unknown'): string
    {
        if (empty($entries)) {
            return $defaultType;
        }

        $entry = $entries[0];

        // Check for messaging events (most common)
        if (isset($entry['messaging'])) {
            $messaging = $entry['messaging'][0] ?? [];
            if (isset($messaging['message'])) {
                return 'message';
            }
            if (isset($messaging['postback'])) {
                return 'postback';
            }
            if (isset($messaging['delivery'])) {
                return 'delivery';
            }
            if (isset($messaging['read'])) {
                return 'read';
            }
            return 'messaging';
        }

        // Check for standby events
        if (isset($entry['standby'])) {
            return 'standby';
        }

        // Check for changes (for Page Updates)
        if (isset($entry['changes'])) {
            $changes = $entry['changes'][0] ?? [];
            return $changes['field'] ?? 'page_change';
        }

        return $defaultType;
    }
}
