<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Conversation;
use App\Models\Message;
use App\Events\MessageReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FacebookWebhookController extends Controller
{
    /**
     * Verify webhook with Facebook
     */
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $verifyToken = config('services.facebook.webhook_verify_token');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            Log::info('Facebook webhook verified successfully');
            return response($challenge, 200);
        }

        Log::warning('Facebook webhook verification failed', [
            'mode' => $mode,
            'token' => $token,
            'expected' => $verifyToken,
        ]);

        return response('Forbidden', 403);
    }

    /**
     * Handle incoming webhook events from Facebook
     */
    public function webhook(Request $request)
    {
        $data = $request->all();

        Log::info('Facebook webhook received', ['data' => $data]);

        // Verify this is from Facebook
        if ($request->input('object') !== 'page') {
            return response('Not a page event', 404);
        }

        // Process each entry
        foreach ($data['entry'] ?? [] as $entry) {
            foreach ($entry['messaging'] ?? [] as $event) {
                // Determine event type and handle accordingly
                if (isset($event['message'])) {
                    $this->handleMessage($event);
                } elseif (isset($event['delivery'])) {
                    $this->handleDelivery($event);
                } elseif (isset($event['read'])) {
                    $this->handleRead($event);
                } elseif (isset($event['postback'])) {
                    $this->handlePostback($event);
                } else {
                    Log::info('Unknown Facebook event type', ['event' => $event]);
                }
            }
        }

        return response('EVENT_RECEIVED', 200);
    }

    /**
     * Handle incoming message from Facebook Messenger
     */
    private function handleMessage(array $event)
    {
        // Extract sender and message data
        $senderId = $event['sender']['id'] ?? null;
        $recipientId = $event['recipient']['id'] ?? null;
        $message = $event['message'] ?? null;

        if (!$senderId || !$message) {
            Log::info('Skipping event - no message data', ['event' => $event]);
            return;
        }

        // Handle text message
        $messageText = $message['text'] ?? null;

        // Handle attachments
        $attachments = $message['attachments'] ?? [];

        // Skip if no text and no attachments
        if (!$messageText && empty($attachments)) {
            Log::info('Skipping event - no text or attachments', ['event' => $event]);
            return;
        }

        $messageId = $message['mid'] ?? null;
        $timestamp = $event['timestamp'] ?? now()->timestamp;

        Log::info('Processing Facebook message', [
            'sender_id' => $senderId,
            'message' => $messageText,
            'message_id' => $messageId,
            'has_attachments' => !empty($attachments),
        ]);

        try {
            // Get or create customer
            $customer = $this->getOrCreateCustomer($senderId);

            // Get or create conversation
            $conversation = $this->getOrCreateConversation($customer, $senderId);

            // Prepare message content
            $content = $messageText ?? '';

            // If there are attachments, add them to content
            if (!empty($attachments)) {
                $attachmentUrls = [];
                foreach ($attachments as $attachment) {
                    $type = $attachment['type'] ?? 'unknown';
                    $url = $attachment['payload']['url'] ?? null;
                    if ($url) {
                        $attachmentUrls[] = "[$type]: $url";
                    }
                }

                if (!empty($attachmentUrls)) {
                    $content .= ($content ? "\n\n" : '') . "Attachments:\n" . implode("\n", $attachmentUrls);
                }
            }

            // Create message
            $msg = Message::create([
                'conversation_id' => $conversation->id,
                'customer_id' => $customer->id,
                'sender_type' => 'customer',
                'sender_id' => $customer->id,
                'sender_name' => $customer->name,
                'content' => $content,
                'platform_message_id' => $messageId ?? 'fb_' . Str::uuid(),
                'metadata' => [
                    'platform' => 'messenger',
                    'sender_id' => $senderId,
                    'timestamp' => $timestamp,
                    'has_attachments' => !empty($attachments),
                    'attachments' => $attachments,
                ],
            ]);

            // Update conversation
            $conversation->update([
                'last_message_at' => now(),
                'unread_count' => $conversation->unread_count + 1,
            ]);

            // Broadcast event to admin inbox
            event(new MessageReceived(
                $msg,
                $conversation,
                $customer,
                'messenger'
            ));

            Log::info('Facebook message saved successfully', [
                'message_id' => $msg->id,
                'conversation_id' => $conversation->id,
            ]);

            // Check if AI auto-reply is enabled for this conversation
            if ($conversation->ai_enabled) {
                Log::info('AI auto-reply enabled, generating response', [
                    'conversation_id' => $conversation->id
                ]);

                // Use AI service to generate and send response
                $aiService = app(\App\Services\AiConversationService::class);
                if (!$aiService->shouldAutoReplyToConversation($conversation, $msg)) {
                    Log::info('AI selective policy skipped auto-reply', [
                        'conversation_id' => $conversation->id,
                        'message_id' => $msg->id,
                    ]);
                    return;
                }

                $success = $aiService->autoReply($conversation);

                if ($success) {
                    Log::info('AI auto-reply sent successfully', [
                        'conversation_id' => $conversation->id
                    ]);
                } else {
                    Log::warning('AI auto-reply failed', [
                        'conversation_id' => $conversation->id
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error('Error processing Facebook message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Handle message delivery confirmation
     */
    private function handleDelivery(array $event)
    {
        $senderId = $event['sender']['id'] ?? null;
        $delivery = $event['delivery'] ?? null;

        if (!$senderId || !$delivery) {
            return;
        }

        $messageIds = $delivery['mids'] ?? [];

        Log::info('Facebook message delivered', [
            'sender_id' => $senderId,
            'message_ids' => $messageIds,
            'watermark' => $delivery['watermark'] ?? null,
        ]);

        // You could update message status in the database here
        // For now, just logging the delivery
    }

    /**
     * Handle message read receipt
     */
    private function handleRead(array $event)
    {
        $senderId = $event['sender']['id'] ?? null;
        $read = $event['read'] ?? null;

        if (!$senderId || !$read) {
            return;
        }

        Log::info('Facebook message read', [
            'sender_id' => $senderId,
            'watermark' => $read['watermark'] ?? null,
        ]);

        // You could update message status in the database here
        // For now, just logging the read receipt
    }

    /**
     * Handle postback from buttons/quick replies
     */
    private function handlePostback(array $event)
    {
        $senderId = $event['sender']['id'] ?? null;
        $postback = $event['postback'] ?? null;

        if (!$senderId || !$postback) {
            return;
        }

        $payload = $postback['payload'] ?? null;
        $title = $postback['title'] ?? null;

        Log::info('Facebook postback received', [
            'sender_id' => $senderId,
            'payload' => $payload,
            'title' => $title,
        ]);

        // You could handle different postback payloads here
        // For now, just logging the postback
    }

    /**
     * Get or create customer from Facebook sender
     */
    private function getOrCreateCustomer(string $senderId): Customer
    {
        // Try to find existing customer
        $customer = Customer::where('platform_user_ids->messenger', $senderId)->first();

        if ($customer) {
            return $customer;
        }

        // Get user info from Facebook Graph API
        $userInfo = $this->getFacebookUserInfo($senderId);

        return Customer::create([
            'name' => $userInfo['name'] ?? 'Facebook User ' . substr($senderId, 0, 8),
            'platform_user_ids' => ['messenger' => $senderId],
            'avatar_url' => $userInfo['profile_pic'] ?? null,
            'metadata' => [
                'platform' => 'messenger',
                'facebook_user_id' => $senderId,
                'first_interaction' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get or create conversation for Facebook user
     */
    private function getOrCreateConversation(Customer $customer, string $senderId): Conversation
    {
        $conversation = $customer->conversations()
            ->where('platform', 'messenger')
            ->where('status', 'active')
            ->latest('last_message_at')
            ->first();

        if ($conversation) {
            return $conversation;
        }

        return Conversation::create([
            'customer_id' => $customer->id,
            'platform' => 'messenger',
            'platform_conversation_id' => 'messenger_' . $senderId,
            'subject' => 'Facebook Messenger Chat',
            'status' => 'active',
            'unread_count' => 0,
            'last_message_at' => now(),
        ]);
    }

    /**
     * Get user info from Facebook Graph API
     */
    private function getFacebookUserInfo(string $userId): array
    {
        try {
            $token = config('services.facebook.page_access_token');
            $url = "https://graph.facebook.com/v18.0/{$userId}?fields=first_name,last_name,profile_pic&access_token={$token}";

            $response = file_get_contents($url);
            $data = json_decode($response, true);

            return [
                'name' => trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')),
                'profile_pic' => $data['profile_pic'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to get Facebook user info', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
