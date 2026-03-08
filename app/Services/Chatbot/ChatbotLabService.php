<?php

namespace App\Services\Chatbot;

use App\Models\Conversation;
use App\Models\Customer;
use Illuminate\Support\Str;

class ChatbotLabService
{
    public function __construct(
        private ChatPipelineService $chatPipeline,
        private UnifiedAiPolicyService $policy,
        private ConversationMemoryService $memoryService
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function runManualTest(
        string $prompt,
        string $previousPrompts = '',
        ?int $conversationId = null,
        bool $persistentSession = false
    ): array
    {
        $conversation = $this->resolveLabConversation($conversationId);
        $normalizedPrompt = $this->policy->normalizeIncomingMessage($prompt);
        $contextPrompts = $this->parsePreviousPrompts($previousPrompts);
        $existingTranscript = $this->buildTranscriptFromHistory($this->memoryService->getContext($conversation->id)['history'] ?? []);
        $bootstrapTranscript = [];

        foreach ($contextPrompts as $contextPrompt) {
            $contextResult = $this->chatPipeline->process($contextPrompt, $conversation->id);

            $bootstrapTranscript[] = [
                'prompt' => $contextPrompt,
                'response' => $contextResult->response(),
            ];
        }

        $result = $this->chatPipeline->process($prompt, $conversation->id);
        $pipeline = $result->toArray();
        $sessionState = $this->buildSessionState($conversation);

        return [
            'prompt' => $prompt,
            'normalized_prompt' => $normalizedPrompt !== '' ? $normalizedPrompt : $prompt,
            'previous_prompts' => $contextPrompts,
            'transcript' => array_merge($existingTranscript, $bootstrapTranscript),
            'response' => (string) ($pipeline['response'] ?? ''),
            'debug' => $this->buildDebugSummary($pipeline),
            'raw_pipeline' => $pipeline,
            'session' => array_merge($sessionState ?? [], [
                'persistent' => $persistentSession,
            ]),
        ];
    }

    /**
     * @param array<string, mixed> $retryContext
     * @return array<string, mixed>
     */
    public function runRetriedManualTest(
        string $prompt,
        string $previousPrompts = '',
        string $strategy = 'same',
        array $retryContext = [],
        ?int $conversationId = null,
        bool $persistentSession = false
    ): array {
        $normalizedStrategy = $strategy === 'constrained' ? 'constrained' : 'same';
        $constraintHints = $this->buildRetryConstraintHints($retryContext);
        $effectivePrompt = $this->buildRetryPrompt($prompt, $normalizedStrategy, $constraintHints);
        $result = $this->runManualTest($effectivePrompt, $previousPrompts, $conversationId, $persistentSession);

        $result['retry'] = [
            'strategy' => $normalizedStrategy,
            'strategy_label' => $normalizedStrategy === 'constrained' ? 'Retry with constraints' : 'Retry same prompt',
            'source_prompt' => $prompt,
            'effective_prompt' => $effectivePrompt,
            'constraint_hints' => $constraintHints,
        ];

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSessionState(?int $conversationId): ?array
    {
        if (!$conversationId) {
            return null;
        }

        $conversation = Conversation::query()->find($conversationId);
        if (!$conversation) {
            return null;
        }

        return $this->buildSessionState($conversation);
    }

    public function resetSession(?int $conversationId): void
    {
        if (!$conversationId) {
            return;
        }

        $conversation = Conversation::query()->find($conversationId);
        if ($conversation) {
            $conversation->close();
        }

        $this->memoryService->clearContext($conversationId);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDebugSummary(array $pipeline): array
    {
        $intent = $pipeline['intent_result'] ?? null;
        $validationContext = is_array($pipeline['validation_context'] ?? null) ? $pipeline['validation_context'] : [];
        $products = is_array($validationContext['products'] ?? null) ? $validationContext['products'] : [];
        $response = (string) ($pipeline['response'] ?? '');
        $validationViolations = is_array($pipeline['validation_violations'] ?? null) ? $pipeline['validation_violations'] : [];

        $intentType = null;
        $intentConfidence = null;
        $intentFallback = null;
        $standaloneQuery = null;

        if ($intent instanceof IntentResult) {
            $intentType = $intent->intent();
            $intentConfidence = $intent->confidence();
            $intentFallback = $intent->isFallback();
            $standaloneQuery = $intent->standaloneQuery();
        } elseif (is_array($intent)) {
            $intentType = $intent['intent'] ?? null;
            $intentConfidence = is_numeric($intent['confidence'] ?? null) ? (float) $intent['confidence'] : null;
            $intentFallback = array_key_exists('is_fallback', $intent) ? (bool) $intent['is_fallback'] : null;
            $standaloneQuery = $intent['standalone_query'] ?? null;
        }

        $fallbackReason = $this->detectFallbackReason($pipeline, $response);

        return [
            'intent' => $intentType,
            'intent_confidence' => $intentConfidence,
            'intent_fallback' => $intentFallback,
            'standalone_query' => $standaloneQuery,
            'validation_passed' => (bool) ($pipeline['validation_passed'] ?? false),
            'validation_violations' => $validationViolations,
            'validation_issue_labels' => $this->summarizeValidationViolations($validationViolations),
            'guard_allowed' => (bool) ($pipeline['guard_allowed'] ?? false),
            'guard_reason' => $pipeline['guard_reason'] ?? null,
            'georgian_passed' => (bool) ($pipeline['georgian_passed'] ?? false),
            'response_time_ms' => (int) ($pipeline['response_time_ms'] ?? 0),
            'products_found' => count($products),
            'products' => $products,
            'regeneration_attempted' => (bool) ($pipeline['regeneration_attempted'] ?? false),
            'regeneration_succeeded' => (bool) ($pipeline['regeneration_succeeded'] ?? false),
            'fallback_reason' => $fallbackReason,
            'fallback_label' => $this->fallbackReasonLabel($fallbackReason),
        ] + $this->buildActionableSignal([
            'intent' => $intentType,
            'intent_confidence' => $intentConfidence,
            'validation_passed' => (bool) ($pipeline['validation_passed'] ?? false),
            'validation_violations' => $validationViolations,
            'guard_allowed' => (bool) ($pipeline['guard_allowed'] ?? false),
            'guard_reason' => $pipeline['guard_reason'] ?? null,
            'georgian_passed' => (bool) ($pipeline['georgian_passed'] ?? false),
            'products_found' => count($products),
            'regeneration_attempted' => (bool) ($pipeline['regeneration_attempted'] ?? false),
            'regeneration_succeeded' => (bool) ($pipeline['regeneration_succeeded'] ?? false),
            'fallback_reason' => $fallbackReason,
        ]);
    }

    /**
     * @return list<string>
     */
    private function parsePreviousPrompts(string $previousPrompts): array
    {
        return collect(preg_split('/\r\n|\r|\n/u', $previousPrompts) ?: [])
            ->map(fn ($line) => trim((string) $line))
            ->filter(fn (string $line): bool => $line !== '')
            ->values()
            ->all();
    }

    private function createLabConversation(): Conversation
    {
        $customer = Customer::create([
            'name' => 'Chatbot Lab Operator',
            'platform_user_ids' => ['chatbot_lab' => 'lab_' . Str::uuid()],
            'metadata' => ['source' => 'chatbot_lab'],
        ]);

        return Conversation::create([
            'customer_id' => $customer->id,
            'platform' => 'home',
            'platform_conversation_id' => 'chatbot_lab_' . Str::uuid(),
            'subject' => 'Chatbot Lab Manual Test',
            'status' => 'active',
            'unread_count' => 0,
            'last_message_at' => now(),
        ]);
    }

    private function resolveLabConversation(?int $conversationId): Conversation
    {
        if ($conversationId) {
            $existingConversation = Conversation::query()->find($conversationId);
            if ($existingConversation) {
                return $existingConversation;
            }
        }

        return $this->createLabConversation();
    }

    /**
     * @param mixed $history
     * @return list<array{prompt:string,response:string}>
     */
    private function buildTranscriptFromHistory(mixed $history): array
    {
        if (!is_array($history) || $history === []) {
            return [];
        }

        $transcript = [];
        $pendingPrompt = null;

        foreach ($history as $entry) {
            $role = is_array($entry) ? ($entry['role'] ?? null) : null;
            $content = is_array($entry) ? trim((string) ($entry['content'] ?? '')) : '';

            if ($content === '' || !is_string($role)) {
                continue;
            }

            if ($role === 'user') {
                $pendingPrompt = $content;
                continue;
            }

            if ($role === 'assistant' && $pendingPrompt !== null) {
                $transcript[] = [
                    'prompt' => $pendingPrompt,
                    'response' => $content,
                ];
                $pendingPrompt = null;
            }
        }

        return $transcript;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildSessionState(Conversation $conversation): ?array
    {
        $context = $this->memoryService->getContext($conversation->id);
        $transcript = $this->buildTranscriptFromHistory($context['history'] ?? []);

        return [
            'conversation_id' => $conversation->id,
            'status' => $conversation->status,
            'turn_count' => count($transcript),
            'last_active' => $context['last_active'] ?? optional($conversation->updated_at)?->toIso8601String(),
            'transcript' => $transcript,
        ];
    }

    private function detectFallbackReason(array $pipeline, string $response): ?string
    {
        $explicitReason = $pipeline['fallback_reason'] ?? null;
        if (is_string($explicitReason) && trim($explicitReason) !== '') {
            return trim($explicitReason);
        }

        if (!(bool) ($pipeline['guard_allowed'] ?? true)) {
            return 'input_guard';
        }

        if (!(bool) ($pipeline['georgian_passed'] ?? true)) {
            return 'strict_georgian';
        }

        $violations = is_array($pipeline['validation_violations'] ?? null) ? $pipeline['validation_violations'] : [];
        if ($violations !== []) {
            return 'validator:' . implode(',', collect($violations)
                ->map(fn (array $violation): string => (string) ($violation['type'] ?? 'unknown'))
                ->unique()
                ->values()
                ->all());
        }

        return match ($response) {
            'ჩატბოტი დროებით გამორთულია. სცადეთ მოგვიანებით.' => 'chatbot_disabled',
            'ბოდიში, სერვისი დროებით მიუწვდომელია.' => 'provider_unavailable',
            'ბოდიში, დროებით პრობლემა გვაქვს. სცადეთ მოგვიანებით.' => 'provider_exception',
            'ბოდიში, პასუხი ვერ მივიღე. სცადეთ კიდევ ერთხელ.' => 'empty_model_output',
            default => null,
        };
    }

    private function fallbackReasonLabel(?string $reason): ?string
    {
        if ($reason === null || trim($reason) === '') {
            return null;
        }

        return match ($reason) {
            ChatbotOutcomeReason::INPUT_GUARD => 'Input guard block',
            ChatbotOutcomeReason::GREETING_ONLY => 'Greeting-only fast path',
            ChatbotOutcomeReason::OUT_OF_DOMAIN => 'Out-of-domain clarification',
            ChatbotOutcomeReason::CLARIFICATION_NEEDED => 'Clarification requested',
            ChatbotOutcomeReason::CHATBOT_DISABLED => 'Chatbot disabled fallback',
            ChatbotOutcomeReason::PROVIDER_UNAVAILABLE => 'Provider unavailable',
            ChatbotOutcomeReason::PROVIDER_EXCEPTION => 'Provider exception',
            ChatbotOutcomeReason::EMPTY_MODEL_OUTPUT => 'Empty model output',
            ChatbotOutcomeReason::GENERIC_REPEATED => 'Generic or repeated fallback',
            ChatbotOutcomeReason::STRICT_GEORGIAN => 'Strict Georgian fallback',
            ChatbotOutcomeReason::VALIDATOR_FAILED => 'Validator blocked reply',
            ChatbotOutcomeReason::VALIDATOR_RETRY_FAILED => 'Validator retry failed',
            default => str_starts_with($reason, 'validator:')
                ? 'Validator mismatch detected'
                : ucwords(str_replace('_', ' ', str_replace(':', ' ', $reason))),
        };
    }

    /**
     * @param array<int, array<string, mixed>> $violations
     * @return list<string>
     */
    private function summarizeValidationViolations(array $violations): array
    {
        return collect($violations)
            ->map(function (array $violation): string {
                $type = (string) ($violation['type'] ?? 'unknown');

                return match ($type) {
                    'price_mismatch' => 'Price in the reply does not match grounded catalog pricing.',
                    'stock_claim_mismatch' => (($violation['claim'] ?? null) === 'out_of_stock')
                        ? 'The reply claims out-of-stock status that the catalog does not support.'
                        : 'The reply claims in-stock availability that the catalog does not support.',
                    'unknown_url_host' => 'The reply includes a URL outside the allowed site or domain list.',
                    'unknown_url_path' => 'The reply includes a URL path that is not present in the grounded context.',
                    default => 'Validation issue: ' . str_replace('_', ' ', $type),
                };
            })
            ->filter(fn (string $line): bool => trim($line) !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $debug
     * @return array{signal_group:string,signal_label:string,signal_severity:string,recommended_action:string}
     */
    private function buildActionableSignal(array $debug): array
    {
        $fallbackReason = (string) ($debug['fallback_reason'] ?? '');
        $violations = is_array($debug['validation_violations'] ?? null) ? $debug['validation_violations'] : [];
        $intentConfidence = is_numeric($debug['intent_confidence'] ?? null) ? (float) $debug['intent_confidence'] : null;

        if (!(bool) ($debug['guard_allowed'] ?? true)) {
            return [
                'signal_group' => 'policy',
                'signal_label' => 'Input guard block',
                'signal_severity' => 'high',
                'recommended_action' => 'Review the prompt for blocked content or adjust guard rules only if the request should be allowed.',
            ];
        }

        if ($fallbackReason === ChatbotOutcomeReason::PROVIDER_UNAVAILABLE || $fallbackReason === ChatbotOutcomeReason::PROVIDER_EXCEPTION || $fallbackReason === ChatbotOutcomeReason::EMPTY_MODEL_OUTPUT || $fallbackReason === ChatbotOutcomeReason::CHATBOT_DISABLED) {
            return [
                'signal_group' => 'provider',
                'signal_label' => 'Generation provider issue',
                'signal_severity' => 'high',
                'recommended_action' => 'Check model credentials, provider health, and recent upstream failures before tuning prompts.',
            ];
        }

        if ($fallbackReason === ChatbotOutcomeReason::STRICT_GEORGIAN) {
            return [
                'signal_group' => 'policy',
                'signal_label' => 'Strict Georgian fallback',
                'signal_severity' => 'medium',
                'recommended_action' => 'Inspect the generated answer and tighten Georgian prompt constraints or examples.',
            ];
        }

        if ($violations !== [] || $fallbackReason === ChatbotOutcomeReason::VALIDATOR_FAILED || $fallbackReason === ChatbotOutcomeReason::VALIDATOR_RETRY_FAILED) {
            return [
                'signal_group' => 'validation',
                'signal_label' => 'Validator blocked reply',
                'signal_severity' => 'high',
                'recommended_action' => 'Inspect the violation list, then adjust grounding, regeneration guidance, or validator thresholds.',
            ];
        }

        if (($debug['regeneration_attempted'] ?? false) && ($debug['regeneration_succeeded'] ?? false)) {
            return [
                'signal_group' => 'validation',
                'signal_label' => 'Recovered after regeneration',
                'signal_severity' => 'low',
                'recommended_action' => 'Review the original violation pattern and reduce how often regeneration is needed.',
            ];
        }

        if (($debug['intent'] ?? null) === 'clarification_needed' || ($intentConfidence !== null && $intentConfidence < 0.55)) {
            return [
                'signal_group' => 'intent',
                'signal_label' => 'Intent uncertainty',
                'signal_severity' => 'medium',
                'recommended_action' => 'Add a clearer disambiguation path or training examples for this request pattern.',
            ];
        }

        if ((int) ($debug['products_found'] ?? 0) === 0 && (($debug['intent'] ?? null) === 'recommendation' || ($debug['intent'] ?? null) === 'price_query')) {
            return [
                'signal_group' => 'search',
                'signal_label' => 'No product grounding',
                'signal_severity' => 'medium',
                'recommended_action' => 'Check search recall, synonyms, and product metadata for this query.',
            ];
        }

        return [
            'signal_group' => 'healthy',
            'signal_label' => 'No major issue detected',
            'signal_severity' => 'low',
            'recommended_action' => 'If the answer still feels weak, inspect prompt wording or add a targeted training case.',
        ];
    }

    /**
     * @param array<string, mixed> $retryContext
     * @return list<string>
     */
    private function buildRetryConstraintHints(array $retryContext): array
    {
        $hints = ['უპასუხე მხოლოდ ქართულად და არ დაამატო დაუდასტურებელი ფაქტები.'];

        $expectedSummary = trim((string) ($retryContext['expected_summary'] ?? ''));
        if ($expectedSummary !== '') {
            $hints[] = 'გაითვალისწინე მოსალოდნელი მიმართულება: ' . $expectedSummary;
        }

        $intent = trim((string) ($retryContext['intent'] ?? ''));
        if ($intent !== '') {
            $hints[] = 'პასუხი აშკარად უნდა შეესაბამებოდეს intent-ს: ' . $intent . '.';
        }

        if (($retryContext['keyword_match'] ?? true) === false) {
            $hints[] = 'პასუხში აუცილებლად ასახე მოსალოდნელი საკვანძო ნიშნები და არ გასცდე კითხვის ფარგლებს.';
        }

        if (($retryContext['intent_match'] ?? true) === false) {
            $hints[] = 'არ აურიო კითხვის ტიპი; ზუსტად იმ ტიპის პასუხი დააბრუნე, რასაც კითხვა მოითხოვს.';
        }

        if (($retryContext['entity_match'] ?? true) === false) {
            $entities = is_array($retryContext['entities'] ?? null) ? $retryContext['entities'] : [];
            $entitySummary = collect([
                isset($entities['brand']) ? 'brand=' . $entities['brand'] : null,
                isset($entities['model']) ? 'model=' . $entities['model'] : null,
                isset($entities['product_slug_hint']) ? 'slug=' . $entities['product_slug_hint'] : null,
                isset($entities['category']) ? 'category=' . $entities['category'] : null,
            ])->filter()->implode(', ');

            $hints[] = $entitySummary !== ''
                ? 'გაითვალისწინე entity მინიშნებები: ' . $entitySummary . '.'
                : 'გაითვალისწინე კითხვაში ნაგულისხმევი ბრენდი, მოდელი ან კატეგორია.';
        }

        if (($retryContext['price_match'] ?? null) === false) {
            $hints[] = 'თუ ფასს ახსენებ, დააფუძნე იგი მხოლოდ რეალურ, კონტექსტში არსებულ ფასზე.';
        }

        if (($retryContext['stock_match'] ?? null) === false) {
            $hints[] = 'მარაგის შესახებ უპასუხე მხოლოდ მაშინ, თუ კონტექსტი ამას ადასტურებს.';
        }

        if (($retryContext['georgian_passed'] ?? true) === false) {
            $hints[] = 'გაასწორე ენა და ფორმულირება, რომ პასუხი იყოს ბუნებრივი და გამართული ქართულად.';
        }

        $fallbackReason = trim((string) ($retryContext['fallback_reason'] ?? ''));
        if ($fallbackReason !== '') {
            $hints[] = 'თავიდან აიცილე წინა fallback-ის მიზეზი: ' . $fallbackReason . '.';
        }

        $violations = is_array($retryContext['validation_violations'] ?? null) ? $retryContext['validation_violations'] : [];
        foreach ($violations as $violation) {
            $type = trim((string) ($violation['type'] ?? ''));
            $message = trim((string) ($violation['message'] ?? ''));
            if ($type !== '' || $message !== '') {
                $hints[] = 'შეასწორე validator issue' . ($type !== '' ? ' [' . $type . ']' : '') . ($message !== '' ? ': ' . $message : '.');
            }
        }

        $judgeNotes = trim((string) ($retryContext['llm_notes'] ?? ''));
        if ($judgeNotes !== '') {
            $hints[] = 'გაითვალისწინე წინა შეფასების შენიშვნა: ' . $judgeNotes;
        }

        $recommendedAction = trim((string) ($retryContext['recommended_action'] ?? ''));
        if ($recommendedAction !== '') {
            $hints[] = 'ოპერატორის შემდეგი ნაბიჯის რეკომენდაცია: ' . $recommendedAction;
        }

        return array_values(array_unique(array_filter($hints, fn (string $hint): bool => trim($hint) !== '')));
    }

    /**
     * @param list<string> $constraintHints
     */
    private function buildRetryPrompt(string $prompt, string $strategy, array $constraintHints): string
    {
        if ($strategy !== 'constrained') {
            return $prompt;
        }

        $lines = [trim($prompt), '', 'Retry guidance:'];

        foreach ($constraintHints as $hint) {
            $lines[] = '- ' . $hint;
        }

        return implode("\n", $lines);
    }
}
