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

        $directFallbackIntent = $this->directFallbackIntentForMessage($safeIncomingMessage);
        if ($directFallbackIntent instanceof IntentResult) {
            $memory->appendMessage($conversation->id, 'user', $safeIncomingMessage);
            $validationContext = $this->directFallbackValidationContext();

            $directReply = $fallbackStrategy->resolveProviderFailureOutcome(
                $directFallbackIntent,
                $validationContext,
                [],
                []
            )->reply();

            $memory->appendMessage($conversation->id, 'assistant', $directReply);

            return new PipelineResult(
                $directReply,
                $conversation->id,
                '',
                $directFallbackIntent,
                $validationContext,
                true,
                null,
                true,
                [],
                true,
                0,
                null,
                false,
                true
            );
        }

        $sessionContext = $memory->getSessionContext($conversation->id);
        $history = $sessionContext['recent'] ?? [];
        $preferences = $memory->getUserPreferences($customer->id);
        $scopedPreferences = $memory->scopePreferencesForMessage($preferences, $safeIncomingMessage);

        $intentResult = $intentAnalyzer->analyze(
            $safeIncomingMessage,
            $history,
            $scopedPreferences,
            $trace
        );

        $memory->appendMessage($conversation->id, 'user', $safeIncomingMessage);

        $supervisorResult = $supervisor->orchestrate(
            $safeIncomingMessage,
            $conversation->id,
            $customer->id,
            $intentResult,
            $scopedPreferences,
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

        $localePassed = $agentResponse === '' || (
            app()->getLocale() === 'en'
                ? $policy->passesLocaleQa($agentResponse, 'en')
                : $policy->passesStrictGeorgianQa($agentResponse)
        );
        if (!$localePassed) {
            $agentResponse = $policy->localeFallback();
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
            $localePassed,
            0,
            $agentReason,
            (bool) (($supervisorResult['reflection_attempts'] ?? 0) > 0),
            (bool) ($supervisorResult['success'] ?? false)
        );
    }

    private function directFallbackIntentForMessage(string $message): ?IntentResult
    {
        $normalized = mb_strtolower(trim($message));

        if ($normalized === '') {
            return null;
        }

        if ($this->containsAny($normalized, ['საკონტაქტო', 'კონტაქტ', 'whatsapp', 'messenger', 'ვაცაპ', 'მესენჯერ'])) {
            return IntentResult::fromArray([
                'standalone_query' => $message,
                'intent' => 'general',
                'entities' => [],
                'needs_product_data' => false,
                'search_keywords' => ['contact', 'whatsapp', 'messenger'],
                'is_out_of_domain' => false,
                'confidence' => 1.0,
            ], 0);
        }

        if ($this->containsAny($normalized, ['რა მოდელები გაქვთ', 'რა საათები გაქვთ', 'რომელი მოდელები გაქვთ'])) {
            return IntentResult::fromArray([
                'standalone_query' => $message,
                'intent' => 'recommendation',
                'entities' => [],
                'needs_product_data' => true,
                'search_keywords' => ['მოდელები', 'catalog'],
                'is_out_of_domain' => false,
                'confidence' => 1.0,
            ], 0);
        }

        if ($this->containsAny($normalized, ['რა ფასები გაქვთ', 'ფასები გაქვთ', 'ფასები მაჩვენე'])) {
            return IntentResult::fromArray([
                'standalone_query' => $message,
                'intent' => 'price_query',
                'entities' => [],
                'needs_product_data' => true,
                'search_keywords' => ['ფასი', 'catalog'],
                'is_out_of_domain' => false,
                'confidence' => 1.0,
            ], 0);
        }

        return null;
    }

    /**
     * @param array<int, string> $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (trim($needle) !== '' && str_contains($haystack, mb_strtolower(trim($needle)))) {
                return true;
            }
        }

        return false;
    }

    private function directFallbackValidationContext(): array
    {
        $contactSettings = \App\Models\ContactSetting::allKeyed();
        $allowedUrls = array_values(array_unique(array_filter([
            rtrim(route('home'), '/'),
            rtrim(route('products.index'), '/'),
            rtrim(route('contact'), '/'),
            !empty($contactSettings['whatsapp_url']) ? rtrim((string) $contactSettings['whatsapp_url'], '/') : null,
            !empty($contactSettings['messenger_url']) ? rtrim((string) $contactSettings['messenger_url'], '/') : null,
        ])));

        return [
            'products' => [],
            'allowed_urls' => $allowedUrls,
        ];
    }
}
