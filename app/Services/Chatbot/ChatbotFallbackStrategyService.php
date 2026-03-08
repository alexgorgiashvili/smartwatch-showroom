<?php

namespace App\Services\Chatbot;

use App\Services\Chatbot\ChatbotFallbackResolution;
use Illuminate\Support\Facades\Log;

class ChatbotFallbackStrategyService
{
    private const STATIC_REASON_REPLIES = [
        ChatbotOutcomeReason::CHATBOT_DISABLED => 'ჩატბოტი დროებით გამორთულია. სცადეთ მოგვიანებით.',
        ChatbotOutcomeReason::PROVIDER_UNAVAILABLE => 'ბოდიში, სერვისი დროებით მიუწვდომელია.',
        ChatbotOutcomeReason::PROVIDER_EXCEPTION => 'ბოდიში, დროებით პრობლემა გვაქვს. სცადეთ მოგვიანებით.',
        ChatbotOutcomeReason::EMPTY_MODEL_OUTPUT => 'ბოდიში, პასუხი ვერ მივიღე. სცადეთ კიდევ ერთხელ.',
    ];

    public function __construct(
        private UnifiedAiPolicyService $policy,
        private ResponseValidatorService $responseValidator
    ) {
    }

    public function resolveGuardOutcome(InputGuardResult $guardResult): ChatbotFallbackResolution
    {
        $reply = $guardResult->safeReply() ?: $this->policy->strictGeorgianFallback();

        return $this->resolution(
            $reply,
            ChatbotOutcomeReason::INPUT_GUARD,
            true,
            [],
            $this->policy->passesStrictGeorgianQa($reply)
        );
    }

    public function resolveGreetingOutcome(): ChatbotFallbackResolution
    {
        $reply = $this->policy->websiteGreetingReply();

        return $this->resolution(
            $reply,
            ChatbotOutcomeReason::GREETING_ONLY,
            true,
            [],
            $this->policy->passesStrictGeorgianQa($reply)
        );
    }

    public function resolveIntentOutcome(IntentResult $intentResult, string $reply): ChatbotFallbackResolution
    {
        $reason = match ($intentResult->intent()) {
            'out_of_domain' => ChatbotOutcomeReason::OUT_OF_DOMAIN,
            'clarification_needed' => ChatbotOutcomeReason::CLARIFICATION_NEEDED,
            default => null,
        };

        return $this->resolution(
            $reply,
            $reason,
            true,
            [],
            $this->policy->passesStrictGeorgianQa($reply)
        );
    }

    public function resolveStaticReason(string $reason, ?string $reply = null): ChatbotFallbackResolution
    {
        $resolvedReply = $reply ?? $this->replyForReason($reason);

        return $this->resolution(
            $resolvedReply,
            $reason,
            true,
            [],
            $this->policy->passesStrictGeorgianQa($resolvedReply)
        );
    }

    public function resolveModelOutcome(
        string $modelReply,
        ?string $initialReason,
        array $validationContext,
        ?IntentResult $intentResult,
        callable $regenerate,
        int $conversationId
    ): ChatbotFallbackResolution {
        if ($initialReason !== null) {
            return $this->resolveStaticReason($initialReason);
        }

        if (!$this->policy->passesStrictGeorgianQa($modelReply)) {
            return $this->resolveStaticReason(ChatbotOutcomeReason::STRICT_GEORGIAN);
        }

        $validation = $this->responseValidator->validateAll($modelReply, $validationContext, $intentResult);

        if ($validation->isValid()) {
            return $this->resolution($modelReply, null, true, [], true);
        }

        Log::warning('Chat pipeline validator blocked reply; attempting regeneration', [
            'conversation_id' => $conversationId,
            'violations' => $validation->violations(),
        ]);

        $regenerated = $regenerate($validation->violations());
        $regeneratedReason = $regenerated['reason'] ?? null;

        if ($regeneratedReason === null) {
            $candidateReply = (string) ($regenerated['reply'] ?? '');

            if ($this->policy->passesStrictGeorgianQa($candidateReply)) {
                $candidateValidation = $this->responseValidator->validateAll($candidateReply, $validationContext, $intentResult);

                if ($candidateValidation->isValid()) {
                    return $this->resolution($candidateReply, null, true, [], true, true, true);
                }
            }
        }

        $fallbackReason = $regeneratedReason === ChatbotOutcomeReason::STRICT_GEORGIAN
            ? ChatbotOutcomeReason::STRICT_GEORGIAN
            : ChatbotOutcomeReason::VALIDATOR_RETRY_FAILED;

        $fallback = $this->resolveStaticReason($fallbackReason);

        return $this->resolution(
            $fallback->reply(),
            $fallback->fallbackReason(),
            false,
            $validation->violations(),
            $fallback->georgianPassed(),
            true,
            false
        );
    }

    private function replyForReason(string $reason): string
    {
        return match ($reason) {
            ChatbotOutcomeReason::STRICT_GEORGIAN => $this->policy->strictGeorgianFallback(),
            ChatbotOutcomeReason::VALIDATOR_FAILED,
            ChatbotOutcomeReason::VALIDATOR_RETRY_FAILED => $this->responseValidator->integrityFallback(),
            default => self::STATIC_REASON_REPLIES[$reason] ?? $this->responseValidator->integrityFallback(),
        };
    }

    private function resolution(
        string $reply,
        ?string $fallbackReason,
        bool $validationPassed,
        array $validationViolations,
        bool $georgianPassed,
        bool $regenerationAttempted = false,
        bool $regenerationSucceeded = false
    ): ChatbotFallbackResolution {
        return new ChatbotFallbackResolution(
            $reply,
            $fallbackReason,
            $validationPassed,
            $validationViolations,
            $georgianPassed,
            $regenerationAttempted,
            $regenerationSucceeded
        );
    }
}
