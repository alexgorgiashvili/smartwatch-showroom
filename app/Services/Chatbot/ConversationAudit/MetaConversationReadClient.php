<?php

namespace App\Services\Chatbot\ConversationAudit;

use Illuminate\Support\Facades\Http;

/** Meta Graph read client. This class performs GET requests only. */
class MetaConversationReadClient
{
    private int $requests = 0;

    public function __construct(private readonly int $maxRequests = 1200)
    {
    }

    public function fetchMessenger(
        ConversationAuditBuilder $builder,
        string $pageId,
        string $pageToken,
        int $maxConversations = 500,
        int $maxMessagesPerConversation = 200
    ): array {
        if ($pageId === '' || $pageToken === '') {
            return ['status' => 'not_configured', 'conversations' => 0, 'messages_seen' => 0];
        }

        return $this->fetchChannel(
            $builder,
            'facebook',
            'https://graph.facebook.com/v24.0',
            $pageId,
            $pageId,
            $pageToken,
            [],
            $maxConversations,
            $maxMessagesPerConversation
        );
    }

    public function fetchInstagram(
        ConversationAuditBuilder $builder,
        string $pageId,
        string $pageToken,
        string $instagramId,
        string $instagramToken,
        int $maxConversations = 500,
        int $maxMessagesPerConversation = 200
    ): array {
        if ($pageId !== '' && $pageToken !== '') {
            $pageResult = $this->fetchChannel(
                $builder,
                'instagram',
                'https://graph.facebook.com/v24.0',
                $pageId,
                $instagramId,
                $pageToken,
                ['platform' => 'instagram'],
                $maxConversations,
                $maxMessagesPerConversation
            );
            $pageResult['api_path'] = 'page_conversations_platform_instagram';
            if (($pageResult['conversations'] ?? 0) > 0) {
                return $pageResult;
            }
        } else {
            $pageResult = ['status' => 'page_token_or_id_missing', 'conversations' => 0];
        }

        if ($instagramId === '' || $instagramToken === '') {
            return [
                'status' => 'no_confirmed_history',
                'page_probe' => $this->safeProbe($pageResult),
                'instagram_probe' => 'not_configured',
                'conversations' => 0,
                'messages_seen' => 0,
            ];
        }

        $instagramResult = $this->fetchChannel(
            $builder,
            'instagram',
            'https://graph.instagram.com/v24.0',
            $instagramId,
            $instagramId,
            $instagramToken,
            [],
            $maxConversations,
            $maxMessagesPerConversation
        );
        $instagramResult['api_path'] = 'instagram_conversations';
        $instagramResult['page_probe'] = $this->safeProbe($pageResult);

        if (($instagramResult['conversations'] ?? 0) === 0) {
            $instagramResult['status'] = 'no_confirmed_history';
        }

        return $instagramResult;
    }

    public function probeWhatsApp(string $phoneId, string $accessToken): array
    {
        if ($phoneId === '' || $accessToken === '') {
            return [
                'status' => 'not_configured',
                'history' => 'no_general_history_endpoint_in_this_integration',
            ];
        }

        $response = $this->get(
            'https://graph.facebook.com/v24.0/' . rawurlencode($phoneId),
            $accessToken,
            ['fields' => 'id']
        );

        return [
            'status' => $response['ok'] ? 'phone_access_confirmed' : 'phone_access_failed',
            'http_status' => $response['http_status'],
            'meta_error_code' => $response['meta_error_code'],
            'history' => 'no_general_history_endpoint_in_this_integration',
        ];
    }

    public function requestsMade(): int
    {
        return $this->requests;
    }

    private function fetchChannel(
        ConversationAuditBuilder $builder,
        string $channel,
        string $baseUrl,
        string $conversationOwnerId,
        string $senderOwnerId,
        string $token,
        array $extraParams,
        int $maxConversations,
        int $maxMessagesPerConversation
    ): array {
        $coverage = [
            'status' => 'complete',
            'conversations' => 0,
            'messages_seen' => 0,
            'conversations_truncated' => 0,
            'messages_without_text' => 0,
            'has_more_conversations' => false,
            'http_status' => null,
            'meta_error_code' => null,
        ];
        $after = null;

        while ($coverage['conversations'] < $maxConversations) {
            $params = array_merge(['fields' => 'id,updated_time', 'limit' => 100], $extraParams);
            if ($after !== null) {
                $params['after'] = $after;
            }
            $response = $this->get(
                $baseUrl . '/' . rawurlencode($conversationOwnerId) . '/conversations',
                $token,
                $params
            );
            if (!$response['ok']) {
                $coverage['status'] = 'api_error';
                $coverage['http_status'] = $response['http_status'];
                $coverage['meta_error_code'] = $response['meta_error_code'];
                return $coverage;
            }

            $body = $response['body'];
            $rows = is_array($body['data'] ?? null) ? $body['data'] : [];
            foreach ($rows as $conversation) {
                if ($coverage['conversations'] >= $maxConversations) {
                    $coverage['has_more_conversations'] = true;
                    $coverage['status'] = 'conversation_limit';
                    break;
                }
                $conversationId = (string) ($conversation['id'] ?? '');
                if ($conversationId === '') {
                    continue;
                }
                $coverage['conversations']++;
                $this->fetchMessages(
                    $builder,
                    $coverage,
                    $channel,
                    $baseUrl,
                    $conversationId,
                    $senderOwnerId,
                    $token,
                    $maxMessagesPerConversation
                );
                if ($coverage['status'] === 'api_error' || $coverage['status'] === 'request_limit') {
                    return $coverage;
                }
            }

            if ($coverage['status'] === 'conversation_limit') {
                break;
            }
            $next = $body['paging']['cursors']['after'] ?? null;
            $hasNext = isset($body['paging']['next']) && is_string($next) && $next !== '';
            $coverage['has_more_conversations'] = $hasNext;
            if (!$hasNext) {
                break;
            }
            $after = $next;
        }

        if ($coverage['has_more_conversations'] && $coverage['status'] === 'complete') {
            $coverage['status'] = 'conversation_limit';
        } elseif ($coverage['conversations'] === 0 && $coverage['status'] === 'complete') {
            $coverage['status'] = 'empty_unverified';
        }

        return $coverage;
    }

    private function fetchMessages(
        ConversationAuditBuilder $builder,
        array &$coverage,
        string $channel,
        string $baseUrl,
        string $conversationId,
        string $senderOwnerId,
        string $token,
        int $maxMessages
    ): void {
        $after = null;
        $count = 0;
        do {
            $params = [
                'fields' => 'id,message,from,to,created_time',
                'limit' => min(100, $maxMessages - $count),
            ];
            if ($after !== null) {
                $params['after'] = $after;
            }
            $response = $this->get(
                $baseUrl . '/' . rawurlencode($conversationId) . '/messages',
                $token,
                $params
            );
            if (!$response['ok']) {
                $coverage['status'] = $response['http_status'] === 0 ? 'request_limit' : 'api_error';
                $coverage['http_status'] = $response['http_status'];
                $coverage['meta_error_code'] = $response['meta_error_code'];
                return;
            }
            $body = $response['body'];
            foreach ((array) ($body['data'] ?? []) as $message) {
                $fromId = (string) ($message['from']['id'] ?? '');
                $role = $fromId === '' ? 'system' : ($fromId === $senderOwnerId ? 'page' : 'customer');
                $content = (string) ($message['message'] ?? '');
                if (trim($content) === '') {
                    $coverage['messages_without_text']++;
                }
                $builder->ingest(
                    $channel,
                    $role,
                    $content,
                    (string) ($message['id'] ?? ''),
                    (string) ($message['created_time'] ?? ''),
                    'meta_graph'
                );
                $coverage['messages_seen']++;
                $count++;
            }
            $next = $body['paging']['cursors']['after'] ?? null;
            $hasNext = isset($body['paging']['next']) && is_string($next) && $next !== '';
            if ($hasNext && $count >= $maxMessages) {
                $coverage['conversations_truncated']++;
                break;
            }
            $after = $hasNext ? $next : null;
        } while ($after !== null && $count < $maxMessages);
    }

    /** @return array{ok:bool,http_status:int,meta_error_code:int|string|null,body:array} */
    private function get(string $url, string $token, array $params): array
    {
        if ($this->requests >= $this->maxRequests) {
            return ['ok' => false, 'http_status' => 0, 'meta_error_code' => 'request_limit', 'body' => []];
        }

        $last = ['ok' => false, 'http_status' => 0, 'meta_error_code' => 'connection_error', 'body' => []];
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->requests++;
            try {
                $response = Http::withToken($token)->acceptJson()->timeout(20)->get($url, $params);
                $body = $response->json();
                $body = is_array($body) ? $body : [];
                $last = [
                    'ok' => $response->successful(),
                    'http_status' => $response->status(),
                    'meta_error_code' => $body['error']['code'] ?? null,
                    'body' => $response->successful() ? $body : [],
                ];
                if ($last['ok'] || !in_array($response->status(), [429, 500, 502, 503, 504], true)) {
                    return $last;
                }
            } catch (\Throwable) {
                // Never include URLs, tokens, response bodies, or messages in logs.
            }
            if ($this->requests >= $this->maxRequests) {
                break;
            }
            usleep(400000 * ($attempt + 1));
        }

        return $last;
    }

    private function safeProbe(array $coverage): array
    {
        return [
            'status' => $coverage['status'] ?? 'unknown',
            'http_status' => $coverage['http_status'] ?? null,
            'meta_error_code' => $coverage['meta_error_code'] ?? null,
            'conversations' => $coverage['conversations'] ?? 0,
        ];
    }
}
