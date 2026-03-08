<?php

namespace App\Services\Chatbot;

final class ChatbotOutcomeReason
{
    public const INPUT_GUARD = 'input_guard';
    public const GREETING_ONLY = 'greeting_only';
    public const OUT_OF_DOMAIN = 'out_of_domain';
    public const CLARIFICATION_NEEDED = 'clarification_needed';
    public const CHATBOT_DISABLED = 'chatbot_disabled';
    public const PROVIDER_UNAVAILABLE = 'provider_unavailable';
    public const PROVIDER_EXCEPTION = 'provider_exception';
    public const EMPTY_MODEL_OUTPUT = 'empty_model_output';
    public const RUNTIME_EXCEPTION = 'runtime_exception';
    public const GENERIC_REPEATED = 'generic_repeated';
    public const STRICT_GEORGIAN = 'strict_georgian';
    public const VALIDATOR_FAILED = 'validator_failed';
    public const VALIDATOR_RETRY_FAILED = 'validator_retry_failed';

    /**
     * @return list<string>
     */
    public static function fallbackReasons(): array
    {
        return [
            self::INPUT_GUARD,
            self::OUT_OF_DOMAIN,
            self::CLARIFICATION_NEEDED,
            self::CHATBOT_DISABLED,
            self::PROVIDER_UNAVAILABLE,
            self::PROVIDER_EXCEPTION,
            self::EMPTY_MODEL_OUTPUT,
            self::RUNTIME_EXCEPTION,
            self::GENERIC_REPEATED,
            self::STRICT_GEORGIAN,
            self::VALIDATOR_FAILED,
            self::VALIDATOR_RETRY_FAILED,
        ];
    }

    public static function isFallbackReason(?string $reason): bool
    {
        return is_string($reason) && in_array($reason, self::fallbackReasons(), true);
    }
}
