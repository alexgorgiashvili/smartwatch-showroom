<?php

namespace App\Services\Business;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Repositories\ConversationRepository;
use App\Repositories\MessageRepository;
use App\Repositories\CustomerRepository;
use App\Services\Platform\InstagramApiService;
use App\Services\Platform\MessengerApiService;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MessageDispatcher
{
    public function __construct(
        protected MessageRepository $messageRepository,
        protected ConversationRepository $conversationRepository,
        protected CustomerRepository $customerRepository,
        protected ConversationManager $conversationManager
    ) {}

    public function processIncomingMessage(string $platform, array $parsedData): ?Message
    {
        try {
            return DB::transaction(function () use ($platform, $parsedData) {
                Log::info('Processing incoming message', [
                    'platform' => $platform,
                    'sender_id' => $parsedData['sender_id'] ?? 'unknown',
                ]);

                $senderId = $parsedData['sender_id'] ?? null;
                $conversationId = $parsedData['conversation_id'] ?? null;
                $messageText = $parsedData['message_text'] ?? '';
                $attachments = $parsedData['attachments'] ?? [];
                $platformMessageId = $parsedData['platform_message_id'] ?? null;

                if (!$senderId || !$conversationId) {
                    Log::error('Missing required fields', [
                        'sender_id' => $senderId,
                        'conversation_id' => $conversationId,
                    ]);
                    return null;
                }

                if ($platformMessageId) {
                    $existingMessage = $this->messageRepository->findByPlatformMessageId($platformMessageId);

                    if ($existingMessage) {
                        Log::info('Duplicate message skipped', [
                            'platform_message_id' => $platformMessageId,
                        ]);
                        return $existingMessage;
                    }
                }

                $profileData = $this->fetchProfileData($platform, $senderId);
                $resolvedName = trim((string) ($parsedData['name'] ?? ''));
                if ($resolvedName === '') {
                    $resolvedName = trim((string) ($profileData['name'] ?? ''));
                }

                $customer = $this->customerRepository->createOrUpdateCustomer([
                    'platform' => $platform,
                    'platform_id' => $senderId,
                    'name' => $resolvedName !== '' ? $resolvedName : null,
                    'email' => $parsedData['email'] ?? null,
                    'phone' => $parsedData['phone'] ?? ($platform === 'whatsapp' ? $senderId : null),
                    'avatar_url' => $parsedData['avatar_url'] ?? ($profileData['avatar_url'] ?? null),
                ]);

                $conversation = $this->conversationManager->findOrCreateConversation(
                    $customer,
                    $platform,
                    $conversationId
                );

                $attachment = null;
                $attachmentType = null;

                if (!empty($attachments)) {
                    $firstAttachment = $attachments[0];
                    $attachment = $firstAttachment['url'] ?? null;
                    $attachmentType = $firstAttachment['type'] ?? null;
                }

                $message = $this->messageRepository->createMessage([
                    'conversation_id' => $conversation->id,
                    'customer_id' => $customer->id,
                    'sender_type' => 'customer',
                    'sender_id' => $customer->id,
                    'sender_name' => $customer->name,
                    'content' => $messageText,
                    'media_url' => $attachment,
                    'media_type' => $attachmentType,
                    'platform_message_id' => $platformMessageId,
                    'metadata' => [
                        'platform' => $platform,
                        'sender_platform_id' => $senderId,
                        'timestamp' => $parsedData['timestamp'] ?? now()->timestamp,
                    ],
                ]);

                $this->conversationRepository->incrementUnreadCount($conversation->id);

                Log::info('Message processed successfully', [
                    'message_id' => $message->id,
                    'conversation_id' => $conversation->id,
                    'platform' => $platform,
                ]);

                return $message->load(['conversation', 'customer']);
            });
        } catch (\Exception $e) {
            Log::error('Error processing incoming message', [
                'platform' => $platform,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    public function sendOutgoingMessage(
        Conversation $conversation,
        string $content,
        ?string $mediaUrl,
        User $sender,
        string $senderType = 'admin'
    ): ?Message {
        try {
            return DB::transaction(function () use ($conversation, $content, $mediaUrl, $sender, $senderType) {
                $mediaType = null;

                if ($mediaUrl) {
                    $mediaType = $this->detectMediaType($mediaUrl);
                }

                $message = $this->messageRepository->createMessage([
                    'conversation_id' => $conversation->id,
                    'customer_id' => $conversation->customer_id,
                    'sender_type' => $senderType,
                    'sender_id' => $sender->id,
                    'sender_name' => $sender->name,
                    'content' => $content,
                    'media_url' => $mediaUrl,
                    'media_type' => $mediaType,
                    'delivery_status' => 'pending',
                    'metadata' => [
                        'platform' => $conversation->platform,
                        'sent_at' => now()->toIso8601String(),
                    ],
                ]);

                $this->messageRepository->markAsRead($message->id);
                $this->conversationRepository->updateLastMessage($conversation->id, now());

                Log::info('Outgoing message created', [
                    'message_id' => $message->id,
                    'conversation_id' => $conversation->id,
                    'sender_type' => $senderType,
                ]);

                return $message->load(['conversation', 'customer']);
            });
        } catch (\Exception $e) {
            Log::error('Error sending outgoing message', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function broadcastMessage(Message $message): void
    {
        // Broadcasting will be handled by events in Phase 1.5
        // This method is a placeholder for now
    }

    protected function detectMediaType(string $url): string
    {
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return 'image';
        }

        if (in_array($extension, ['mp4', 'avi', 'mov', 'mkv', 'webm'], true)) {
            return 'video';
        }

        if (in_array($extension, ['mp3', 'wav', 'ogg', 'm4a'], true)) {
            return 'audio';
        }

        return 'file';
    }

    protected function fetchProfileData(string $platform, string $senderId): array
    {
        try {
            return match ($platform) {
                'instagram' => app(InstagramApiService::class)->fetchUserProfile($senderId),
                'facebook', 'messenger' => app(MessengerApiService::class)->fetchUserProfile($senderId),
                'whatsapp' => app(WhatsAppService::class)->fetchUserProfile($senderId),
                default => [],
            };
        } catch (\Throwable $e) {
            Log::warning('Profile fetch failed during incoming message processing', [
                'platform' => $platform,
                'sender_id' => $senderId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
