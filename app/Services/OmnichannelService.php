<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OmnichannelService
{
    /**
     * Platform constants
     */
    const PLATFORM_FACEBOOK = 'facebook';
    const PLATFORM_INSTAGRAM = 'instagram';
    const PLATFORM_WHATSAPP = 'whatsapp';

    protected MetaApiService $metaApiService;
    protected WhatsAppService $whatsAppService;

    public function __construct(MetaApiService $metaApiService, WhatsAppService $whatsAppService)
    {
        $this->metaApiService = $metaApiService;
        $this->whatsAppService = $whatsAppService;
    }

    /**
     * Process a webhook message from any platform
     *
     * Main entry point for message processing
     *
     * @param string $platform Platform name (facebook, instagram, whatsapp)
     * @param array $parsedMessage Parsed message from platform service
     * @return Message|null Created message model or null if processing failed
     */
    public function processWebhookMessage(string $platform, array $parsedMessage): ?Message
    {
        try {
            return DB::transaction(function () use ($platform, $parsedMessage) {
                Log::info('Processing webhook message', [
                    'platform' => $platform,
                    'sender_id' => $parsedMessage['sender_id'] ?? 'unknown',
                    'conversation_id' => $parsedMessage['conversation_id'] ?? 'unknown',
                ]);

                // Validate platform
                if (!$this->isValidPlatform($platform)) {
                    Log::error('Invalid platform', ['platform' => $platform]);
                    return null;
                }

                // Extract message data
                $senderId = $parsedMessage['sender_id'] ?? null;
                $conversationId = $parsedMessage['conversation_id'] ?? null;
                $messageText = $parsedMessage['message_text'] ?? '';
                $attachments = $parsedMessage['attachments'] ?? [];
                $timestamp = $parsedMessage['timestamp'] ?? now()->timestamp;

                if (!$senderId || !$conversationId) {
                    Log::error('Missing required message fields', [
                        'sender_id' => $senderId,
                        'conversation_id' => $conversationId,
                    ]);
                    return null;
                }

                // Find or create customer
                $customer = Customer::findOrCreateByPlatformId($platform, $senderId);

                // Find or create conversation
                $conversation = Conversation::findOrCreateByPlatformId(
                    $customer,
                    $platform,
                    $conversationId
                );

                // Process attachments
                $attachment = null;
                $attachmentType = null;

                if (!empty($attachments)) {
                    $firstAttachment = $attachments[0];
                    $attachment = $firstAttachment['url'] ?? null;
                    $attachmentType = $firstAttachment['type'] ?? null;
                }

                // Create message record
                $message = Message::create([
                    'conversation_id' => $conversation->id,
                    'customer_id' => $customer->id,
                    'sender_type' => 'customer',
                    'sender_id' => $customer->id,
                    'sender_name' => $customer->name,
                    'content' => $messageText,
                    'media_url' => $attachment,
                    'media_type' => $attachmentType,
                    'platform_message_id' => $this->generatePlatformMessageId($platform, $senderId),
                    'metadata' => [
                        'platform' => $platform,
                        'sender_platform_id' => $senderId,
                        'attachments_count' => count($attachments),
                        'timestamp' => $timestamp,
                    ],
                ]);

                // Update conversation
                $conversation->incrementUnreadCount();

                // Log successful processing
                Log::info('Message processed successfully', [
                    'message_id' => $message->id,
                    'customer_id' => $customer->id,
                    'conversation_id' => $conversation->id,
                    'platform' => $platform,
                ]);

                // Eager load relationships for broadcasting efficiency
                $message->load(['conversation', 'customer']);

                return $message;
            });
        } catch (\Exception $e) {
            Log::error('Error processing webhook message', [
                'platform' => $platform,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Send a reply from admin to customer
     *
     * @param int $conversationId
     * @param int $adminUserId
     * @param string $message
     * @param string|null $mediaUrl
     * @return Message|null Created message or null if failed
     */
    public function sendReply(int $conversationId, int $adminUserId, string $message, ?string $mediaUrl = null): ?Message
    {
        try {
            return DB::transaction(function () use ($conversationId, $adminUserId, $message, $mediaUrl) {
                // Get admin user and check permissions
                $admin = User::find($adminUserId);
                if (!$admin || !$admin->is_admin) {
                    Log::warning('Unauthorized reply attempt', [
                        'user_id' => $adminUserId,
                        'is_admin' => $admin?->is_admin ?? false,
                    ]);
                    throw new \Exception('Unauthorized: user is not an admin');
                }

                // Get conversation with customer
                $conversation = Conversation::with('customer')->find($conversationId);
                if (!$conversation) {
                    Log::warning('Conversation not found', ['conversation_id' => $conversationId]);
                    throw new \Exception('Conversation not found');
                }

                $customer = $conversation->customer;

                // Determine media type if media is provided
                $mediaType = null;
                if ($mediaUrl) {
                    $mediaType = $this->detectMediaType($mediaUrl);
                }

                // Create message record
                $replyMessage = Message::create([
                    'conversation_id' => $conversation->id,
                    'customer_id' => $customer->id,
                    'sender_type' => 'admin',
                    'sender_id' => $adminUserId,
                    'sender_name' => $admin->name,
                    'content' => $message,
                    'media_url' => $mediaUrl,
                    'media_type' => $mediaType,
                    'platform_message_id' => null,
                    'metadata' => [
                        'platform' => $conversation->platform,
                        'admin_id' => $adminUserId,
                    ],
                    'read_at' => now(),
                ]);

                // Prepare API call for appropriate service
                $apiPayload = $this->prepareOutgoingMessage(
                    $conversation->platform,
                    $customer,
                    $message,
                    $mediaUrl
                );

                Log::info('Admin reply prepared', [
                    'message_id' => $replyMessage->id,
                    'conversation_id' => $conversation->id,
                    'admin_id' => $adminUserId,
                    'platform' => $conversation->platform,
                    'has_api_payload' => !empty($apiPayload),
                ]);

                return $replyMessage;
            });
        } catch (\Exception $e) {
            Log::error('Error sending reply', [
                'conversation_id' => $conversationId,
                'admin_id' => $adminUserId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get conversation with paginated messages
     *
     * @param int $conversationId
     * @param int $perPage
     * @param int $page
     * @return Conversation|null Conversation with eager-loaded customer and paginated messages
     */
    public function getConversationWithMessages(int $conversationId, int $perPage = 50, int $page = 1): ?Conversation
    {
        try {
            $conversation = Conversation::with('customer')
                ->find($conversationId);

            if (!$conversation) {
                return null;
            }

            // Load paginated messages
            $messages = $conversation->messages()
                ->paginate($perPage, ['*'], 'page', $page);

            // Attach messages to conversation
            $conversation->setRelation('messages', $messages);

            return $conversation;
        } catch (\Exception $e) {
            Log::error('Error fetching conversation with messages', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Prepare outgoing message for API call
     *
     * @param string $platform
     * @param Customer $customer
     * @param string $message
     * @param string|null $mediaUrl
     * @return array API payload
     */
    protected function prepareOutgoingMessage(
        string $platform,
        Customer $customer,
        string $message,
        ?string $mediaUrl = null
    ): array {
        $platformIds = $customer->platform_user_ids ?? [];
        $senderId = $platformIds[$platform] ?? null;

        if (!$senderId) {
            Log::warning('No platform ID for customer', [
                'customer_id' => $customer->id,
                'platform' => $platform,
            ]);
            return [];
        }

        // Determine conversation ID based on platform
        $conversation = $customer->conversations()
            ->where('platform', $platform)
            ->first();

        $conversationId = $conversation->platform_conversation_id ?? '';

        return match ($platform) {
            self::PLATFORM_FACEBOOK, self::PLATFORM_INSTAGRAM => $this->metaApiService->sendMessage(
                $senderId,
                $conversationId,
                $message,
                $mediaUrl
            ),
            self::PLATFORM_WHATSAPP => $this->whatsAppService->sendMessage(
                $senderId,
                $conversationId,
                $message,
                $mediaUrl
            ),
            default => [],
        };
    }

    /**
     * Check if platform is valid
     *
     * @param string $platform
     * @return bool
     */
    protected function isValidPlatform(string $platform): bool
    {
        return in_array($platform, [
            self::PLATFORM_FACEBOOK,
            self::PLATFORM_INSTAGRAM,
            self::PLATFORM_WHATSAPP,
        ]);
    }

    /**
     * Detect media type from URL
     *
     * @param string $url
     * @return string
     */
    protected function detectMediaType(string $url): string
    {
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        // Image extensions
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            return 'image';
        }

        // Video extensions
        if (in_array($extension, ['mp4', 'avi', 'mov', 'mkv', 'webm'])) {
            return 'video';
        }

        // Audio extensions
        if (in_array($extension, ['mp3', 'wav', 'aac', 'ogg', 'm4a'])) {
            return 'audio';
        }

        // Document extensions
        if (in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'])) {
            return 'file';
        }

        return 'file';
    }

    /**
     * Generate platform message ID
     *
     * @param string $platform
     * @param string $senderId
     * @return string
     */
    protected function generatePlatformMessageId(string $platform, string $senderId): string
    {
        return implode('_', [
            $platform,
            $senderId,
            now()->timestamp,
            uniqid(),
        ]);
    }
}
