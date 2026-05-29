<?php

namespace App\Services\Chatbot;

use App\Services\AiConversationService;
use Illuminate\Support\Facades\Log;

class ChatbotLabService
{
    public function getSessionState($conversationId = null)
    {
        if ($conversationId) {
            $conversation = \App\Models\Conversation::find($conversationId);
            if ($conversation) {
                return [
                    'conversation_id' => $conversationId,
                    'messages_count' => $conversation->messages()->count(),
                ];
            }
        }

        return null;
    }

    public function runManualTest(string $prompt, string $previousPrompts = '', $conversationId = null, bool $continueSession = false)
    {
        try {
            if ($conversationId && $continueSession) {
                $conversation = \App\Models\Conversation::findOrFail($conversationId);
            } else {
                $conversation = \App\Models\Conversation::create([
                    'customer_name' => 'Lab Test',
                    'platform' => 'test',
                    'platform_conversation_id' => 'lab-' . time(),
                    'status' => 'open',
                ]);
            }

            \App\Models\Message::create([
                'conversation_id' => $conversation->id,
                'content' => $prompt,
                'sender_type' => 'customer',
            ]);

            $aiService = app(AiConversationService::class);
            $response = $aiService->generateResponse($conversation);

            if (!$continueSession) {
                $conversation->delete();
            }

            return [
                'success' => true,
                'response' => $response ?? 'No response generated',
                'session' => $continueSession ? ['conversation_id' => $conversation->id] : [],
                'metadata' => [],
            ];
        } catch (\Exception $e) {
            Log::error('ChatbotLabService manual test failed', [
                'error' => $e->getMessage(),
                'prompt' => $prompt,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function runRetriedManualTest(string $prompt, string $previousPrompts, string $retryStrategy, array $retryContext, $conversationId = null, bool $continueSession = false)
    {
        return $this->runManualTest($prompt, $previousPrompts, $conversationId, $continueSession);
    }

    public function resetSession($conversationId = null)
    {
        if ($conversationId) {
            $conversation = \App\Models\Conversation::find($conversationId);
            if ($conversation) {
                $conversation->delete();
            }
        }
    }
}
