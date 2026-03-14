<?php

namespace App\Http\Controllers\Admin;

use App\Events\MessageReceived;
use App\Services\Business\MessageDispatcher;
use App\Services\Business\WebhookNormalizer;
use App\Services\PushNotificationService;
use App\Services\WebhookVerificationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    protected WebhookVerificationService $verificationService;
    protected WebhookNormalizer $webhookNormalizer;
    protected MessageDispatcher $messageDispatcher;
    protected PushNotificationService $pushNotificationService;

    public function __construct(
        WebhookVerificationService $verificationService,
        WebhookNormalizer $webhookNormalizer,
        MessageDispatcher $messageDispatcher,
        PushNotificationService $pushNotificationService
    ) {
        $this->verificationService = $verificationService;
        $this->webhookNormalizer = $webhookNormalizer;
        $this->messageDispatcher = $messageDispatcher;
        $this->pushNotificationService = $pushNotificationService;
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

        // Detect Instagram webhooks by object type
        if ($object === 'instagram') {
            $platform = 'instagram';
        }

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
        if (in_array($eventType, ['message', 'messages'], true) && $isVerified) {
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
            Log::info('Processing webhook message', [
                'platform' => $platform,
                'payload_keys' => array_keys($payload),
            ]);

            // Normalize the webhook payload to standard format
            $normalized = $this->webhookNormalizer->normalize($platform, $payload);

            // Skip if normalization failed
            if (!$normalized) {
                Log::warning('Failed to normalize webhook message', [
                    'platform' => $platform,
                    'payload' => $payload,
                ]);
                return;
            }

            Log::info('Webhook message normalized successfully', [
                'platform' => $platform,
                'normalized_keys' => array_keys($normalized),
            ]);

            // Process the message through message dispatcher
            $message = $this->messageDispatcher->processIncomingMessage($platform, $normalized);

            Log::info('Message dispatcher result', [
                'platform' => $platform,
                'message_created' => $message !== null,
                'message_id' => $message?->id,
            ]);

            // Broadcast event if message was created successfully
            if ($message) {
                $conversation = $message->conversation;
                $customer = $message->customer;

                broadcast(new MessageReceived($message, $conversation, $customer, $platform))->toOthers();

                if ($message->sender_type !== 'admin') {
                    $this->pushNotificationService->sendToAdmins(
                        'New message from ' . ($customer->name ?: 'Customer'),
                        mb_substr((string) $message->content, 0, 120),
                        url('/admin/inbox?conversation=' . $conversation->id),
                        [
                            'conversation_id' => $conversation->id,
                            'message_id' => $message->id,
                            'platform' => $platform,
                        ]
                    );
                }

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
        $hubMode = $request->query('hub_mode')
            ?? $request->query('hub.mode')
            ?? $request->header('hub_mode')
            ?? $request->header('hub.mode');

        $hubChallenge = $request->query('hub_challenge')
            ?? $request->query('hub.challenge')
            ?? $request->header('hub_challenge')
            ?? $request->header('hub.challenge');

        $hubVerifyToken = $request->query('hub_verify_token')
            ?? $request->query('hub.verify_token')
            ?? $request->header('hub_verify_token')
            ?? $request->header('hub.verify_token');

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
