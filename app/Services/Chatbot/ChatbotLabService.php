<?php

namespace App\Services\Chatbot;

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
                $customer = \App\Models\Customer::firstOrCreate(
                    ['email' => 'lab-test@mytechnic.local'],
                    [
                        'name' => 'Lab Test',
                        'platform_user_ids' => ['whatsapp' => 'lab-test'],
                    ]
                );

                $conversation = \App\Models\Conversation::create([
                    'customer_id' => $customer->id,
                    'platform' => 'whatsapp',
                    'platform_conversation_id' => 'lab-' . uniqid(),
                    'status' => 'active',
                ]);
            }

            \App\Models\Message::create([
                'conversation_id' => $conversation->id,
                'customer_id' => $conversation->customer_id,
                'content' => $prompt,
                'sender_type' => 'customer',
                'sender_id' => $conversation->customer_id,
                'sender_name' => (string) ($conversation->customer->name ?? 'Lab Test'),
            ]);

            $pipeline = app(ChatPipelineService::class);
            $pipelineResult = $pipeline->process(
                $prompt,
                $conversation,
                $conversation->customer,
                'lab_' . uniqid(),
                app(BifurcatedMemoryService::class),
                app(IntentAnalyzerService::class),
                app(\App\Services\Chatbot\Agents\SupervisorAgent::class),
                app(UnifiedAiPolicyService::class),
                app(ChatbotFallbackStrategyService::class)
            );
            $response = $pipelineResult->response();

            \App\Models\Message::create([
                'conversation_id' => $conversation->id,
                'customer_id' => $conversation->customer_id,
                'content' => (string) $response,
                'sender_type' => 'admin',
                'sender_id' => 0,
                'sender_name' => 'AI Assistant',
            ]);

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
