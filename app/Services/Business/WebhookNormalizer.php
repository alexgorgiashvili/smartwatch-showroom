<?php

namespace App\Services\Business;

use Illuminate\Support\Facades\Log;

class WebhookNormalizer
{
    /**
     * Normalize webhook payload from any platform to a standard format
     *
     * @param string $platform Platform name (instagram, facebook, whatsapp)
     * @param array $rawPayload Raw webhook payload
     * @return array|null Normalized data or null if not a message event
     */
    public function normalize(string $platform, array $rawPayload): ?array
    {
        return match ($platform) {
            'instagram' => $this->normalizeInstagram($rawPayload),
            'facebook', 'messenger' => $this->normalizeFacebook($rawPayload),
            'whatsapp' => $this->normalizeWhatsApp($rawPayload),
            default => null,
        };
    }

    /**
     * Normalize Instagram Direct message webhook
     */
    protected function normalizeInstagram(array $payload): ?array
    {
        $entry = $payload['entry'][0] ?? null;
        if (!$entry) {
            return null;
        }

        // Instagram uses 'messaging' directly in entry, not 'changes'
        $messaging = $entry['messaging'][0] ?? null;
        if (!$messaging || !isset($messaging['message'])) {
            return null;
        }

        $message = $messaging['message'];
        $senderId = $messaging['sender']['id'] ?? null;
        $recipientId = $messaging['recipient']['id'] ?? null;
        $entryAccountId = (string) ($entry['id'] ?? '');

        if (!$senderId || !$recipientId) {
            return null;
        }

        if (isset($message['is_echo']) && $message['is_echo']) {
            return null;
        }

        if ($entryAccountId !== '' && (string) $senderId === $entryAccountId) {
            return null;
        }

        // Prefix conversation IDs to avoid collisions with linked Meta accounts.
        $conversationId = 'ig_' . $senderId;

        return [
            'sender_id' => $senderId,
            'conversation_id' => $conversationId,
            'message_text' => $message['text'] ?? '',
            'attachments' => $this->extractAttachments($message['attachments'] ?? []),
            'timestamp' => $messaging['timestamp'] ?? now()->timestamp,
            'platform_message_id' => $message['mid'] ?? null,
        ];
    }

    /**
     * Normalize Facebook Messenger webhook
     */
    protected function normalizeFacebook(array $payload): ?array
    {
        $entry = $payload['entry'][0] ?? null;
        if (!$entry) {
            return null;
        }

        $messaging = $entry['messaging'][0] ?? null;
        if (!$messaging || !isset($messaging['message'])) {
            return null;
        }

        $message = $messaging['message'];

        if (isset($message['is_echo']) && $message['is_echo']) {
            return null;
        }

        $senderId = $messaging['sender']['id'] ?? null;
        $recipientId = $messaging['recipient']['id'] ?? null;

        if (!$senderId || !$recipientId) {
            return null;
        }

        // Prefix conversation IDs to avoid collisions with linked Meta accounts.
        $conversationId = 'fb_' . $senderId;

        return [
            'sender_id' => $senderId,
            'conversation_id' => $conversationId,
            'message_text' => $message['text'] ?? '',
            'attachments' => $this->extractAttachments($message['attachments'] ?? []),
            'timestamp' => $messaging['timestamp'] ?? now()->timestamp,
            'platform_message_id' => $message['mid'] ?? null,
        ];
    }

    /**
     * Normalize WhatsApp Cloud API webhook
     */
    protected function normalizeWhatsApp(array $payload): ?array
    {
        $entry = $payload['entry'][0] ?? null;
        if (!$entry) {
            return null;
        }

        $changes = $entry['changes'][0] ?? null;
        if (!$changes) {
            return null;
        }

        $value = $changes['value'] ?? [];
        $field = $changes['field'] ?? null;

        if ($field !== 'messages') {
            return null;
        }

        if (isset($value['statuses']) && !isset($value['messages'])) {
            return null;
        }

        $messages = $value['messages'] ?? [];
        if (empty($messages)) {
            return null;
        }

        $message = $messages[0];
        $messageType = $message['type'] ?? 'text';
        $senderId = $message['from'] ?? null;
        $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;

        if (!$senderId || !$phoneNumberId) {
            return null;
        }

        $conversationId = $phoneNumberId;
        $messageData = $this->extractWhatsAppContent($message, $messageType);

        if ($messageData === null) {
            return null;
        }

        return [
            'sender_id' => $senderId,
            'conversation_id' => $conversationId,
            'message_text' => $messageData['text'],
            'attachments' => $messageData['attachments'],
            'timestamp' => (int) ($message['timestamp'] ?? now()->timestamp),
            'platform_message_id' => $message['id'] ?? null,
            'phone' => $senderId,
        ];
    }

    /**
     * Extract attachments from Meta platforms (Instagram/Facebook)
     */
    protected function extractAttachments(array $attachments): array
    {
        $result = [];

        foreach ($attachments as $attachment) {
            $type = $attachment['type'] ?? 'file';
            $payload = $attachment['payload'] ?? [];

            $result[] = [
                'type' => $type,
                'url' => $payload['url'] ?? null,
            ];
        }

        return $result;
    }

    /**
     * Extract message content from WhatsApp based on type
     */
    protected function extractWhatsAppContent(array $message, string $type): ?array
    {
        $text = '';
        $attachments = [];

        switch ($type) {
            case 'text':
                $text = $message['text']['body'] ?? '';
                break;

            case 'image':
                $imageData = $message['image'] ?? [];
                $attachments[] = [
                    'type' => 'image',
                    'url' => $imageData['link'] ?? null,
                ];
                $text = $imageData['caption'] ?? '';
                break;

            case 'video':
                $videoData = $message['video'] ?? [];
                $attachments[] = [
                    'type' => 'video',
                    'url' => $videoData['link'] ?? null,
                ];
                $text = $videoData['caption'] ?? '';
                break;

            case 'audio':
                $audioData = $message['audio'] ?? [];
                $attachments[] = [
                    'type' => 'audio',
                    'url' => $audioData['link'] ?? null,
                ];
                break;

            case 'document':
                $documentData = $message['document'] ?? [];
                $attachments[] = [
                    'type' => 'file',
                    'url' => $documentData['link'] ?? null,
                ];
                $text = $documentData['caption'] ?? '';
                break;

            case 'location':
                $location = $message['location'] ?? [];
                $text = sprintf(
                    'Location: %.4f, %.4f',
                    $location['latitude'] ?? 0,
                    $location['longitude'] ?? 0
                );
                break;

            case 'contacts':
                $contacts = $message['contacts'] ?? [];
                $text = 'Contact: ' . ($contacts[0]['profile']['name'] ?? 'Unknown');
                break;

            default:
                Log::warning('Unknown WhatsApp message type', ['type' => $type]);
                return null;
        }

        return [
            'text' => $text,
            'attachments' => $attachments,
        ];
    }
}
