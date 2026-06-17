<?php

namespace App\Services\Chatbot;

use App\Models\Conversation;
use App\Models\Customer;
use App\Services\Chatbot\Agents\SupervisorAgent;

class ChatPipelineService
{
    public function process(
        string $safeIncomingMessage,
        Conversation $conversation,
        Customer $customer,
        ?string $traceId,
        BifurcatedMemoryService $memory,
        IntentAnalyzerService $intentAnalyzer,
        SupervisorAgent $supervisor,
        UnifiedAiPolicyService $policy,
        ChatbotFallbackStrategyService $fallbackStrategy
    ): PipelineResult {
        $trace = array_filter([
            'trace_id' => $traceId,
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
        ], fn ($value) => $value !== null);

        if ($policy->isGreetingOnly($safeIncomingMessage)) {
            $greetingReply = $fallbackStrategy->resolveGreetingOutcome()->reply();

            return new PipelineResult(
                $greetingReply,
                $conversation->id,
                '',
                IntentResult::fallback($safeIncomingMessage),
                ['products' => []],
                true,
                null,
                true,
                [],
                true,
                0,
                ChatbotOutcomeReason::GREETING_ONLY,
                false,
                false
            );
        }

        $sessionContext = $memory->getSessionContext($conversation->id);
        $history = $sessionContext['recent'] ?? [];
        $preferences = $memory->getUserPreferences($customer->id);

        $intentResult = $intentAnalyzer->analyze(
            $safeIncomingMessage,
            $history,
            $preferences,
            $trace
        );

        $memory->appendMessage($conversation->id, 'user', $safeIncomingMessage);

        $supervisorResult = $supervisor->orchestrate(
            $safeIncomingMessage,
            $conversation->id,
            $customer->id,
            $intentResult,
            $preferences,
            $trace
        );

        $extractedPreferences = is_array($supervisorResult['extracted_preferences'] ?? null)
            ? $supervisorResult['extracted_preferences']
            : [];

        if ($extractedPreferences !== []) {
            $memory->updateUserPreferences($customer->id, $extractedPreferences);
        }

        $validationPassed = (bool) ($supervisorResult['validation_passed'] ?? false);
        if ($supervisorResult['success'] ?? false) {
            $memory->appendMessage($conversation->id, 'assistant', (string) ($supervisorResult['response'] ?? ''));
        }

        $agentResponse = (string) ($supervisorResult['response'] ?? '');
        $agentReason = $supervisorResult['reason'] ?? null;

        if ($agentReason === null && !$validationPassed) {
            $agentReason = ChatbotOutcomeReason::VALIDATOR_RETRY_FAILED;
            $agentResponse = $fallbackStrategy->resolveStaticReason($agentReason)->reply();
        }

        if ($agentResponse === '' && $agentReason !== null) {
            $agentResponse = $fallbackStrategy->resolveStaticReason((string) $agentReason)->reply();
        }

        $georgianPassed = $agentResponse === '' || $policy->passesStrictGeorgianQa($agentResponse);
        if (!$georgianPassed) {
            $agentResponse = $policy->strictGeorgianFallback();
            $agentReason = ChatbotOutcomeReason::STRICT_GEORGIAN;
        }

        return new PipelineResult(
            $agentResponse,
            $conversation->id,
            '',
            $intentResult,
            $supervisorResult['validation_context'] ?? ['products' => []],
            true,
            null,
            (bool) ($supervisorResult['validation_passed'] ?? false),
            $supervisorResult['violations'] ?? [],
            $georgianPassed,
            0,
            $agentReason,
            (bool) (($supervisorResult['reflection_attempts'] ?? 0) > 0),
            (bool) ($supervisorResult['success'] ?? false)
        );
    }
}
