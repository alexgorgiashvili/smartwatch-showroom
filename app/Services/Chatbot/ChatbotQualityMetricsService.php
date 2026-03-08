<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ChatbotQualityMetricsService
{
    private const RETENTION_DAYS = 14;

    public function recordWidgetResponseQuality(
        int $conversationId,
        int $customerId,
        bool $fallbackUsed,
        bool $nonGeorgianModelOutput,
        ?string $fallbackReason = null,
        bool $regenerationAttempted = false,
        bool $regenerationSucceeded = false,
        ?int $responseTimeMs = null
    ): void {
        $this->incrementDailyCounter('widget_response_total');

        if ($fallbackUsed) {
            $this->incrementDailyCounter('widget_response_fallback_total');
        }

        if ($nonGeorgianModelOutput) {
            $this->incrementDailyCounter('widget_model_non_georgian_total');
        }

        $this->recordResponseDiagnostics(
            'widget',
            $fallbackReason,
            $regenerationAttempted,
            $regenerationSucceeded,
            $responseTimeMs
        );

        Log::info('chatbot.quality.widget_response', [
            'conversation_id' => $conversationId,
            'customer_id' => $customerId,
            'fallback_used' => $fallbackUsed,
            'non_georgian_model_output' => $nonGeorgianModelOutput,
            'fallback_reason' => $fallbackReason,
            'regeneration_attempted' => $regenerationAttempted,
            'regeneration_succeeded' => $regenerationSucceeded,
            'response_time_ms' => $responseTimeMs,
        ]);
    }

    public function recordOmnichannelResponseQuality(
        int $conversationId,
        bool $fallbackUsed,
        bool $strictQaPassed,
        ?string $fallbackReason = null,
        bool $regenerationAttempted = false,
        bool $regenerationSucceeded = false,
        ?int $responseTimeMs = null
    ): void {
        $this->incrementDailyCounter('omnichannel_response_total');

        if ($fallbackUsed) {
            $this->incrementDailyCounter('omnichannel_response_fallback_total');
        }

        if (!$strictQaPassed) {
            $this->incrementDailyCounter('omnichannel_response_non_strict_total');
        }

        $this->recordResponseDiagnostics(
            'omnichannel',
            $fallbackReason,
            $regenerationAttempted,
            $regenerationSucceeded,
            $responseTimeMs
        );

        Log::info('chatbot.quality.omnichannel_response', [
            'conversation_id' => $conversationId,
            'fallback_used' => $fallbackUsed,
            'strict_qa_passed' => $strictQaPassed,
            'fallback_reason' => $fallbackReason,
            'regeneration_attempted' => $regenerationAttempted,
            'regeneration_succeeded' => $regenerationSucceeded,
            'response_time_ms' => $responseTimeMs,
        ]);
    }

    public function recordAutoReplyDecision(
        int $conversationId,
        int $messageId,
        bool $accepted,
        string $reason
    ): void {
        $this->incrementDailyCounter('auto_reply_decision_total');

        if ($accepted) {
            $this->incrementDailyCounter('auto_reply_accepted_total');
        } else {
            $this->incrementDailyCounter('auto_reply_rejected_total');
        }

        Log::info('chatbot.quality.auto_reply_decision', [
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'accepted' => $accepted,
            'reason' => $reason,
        ]);
    }

    public function recordProviderIncident(int $conversationId, string $channel, string $reason): void
    {
        $normalizedChannel = in_array($channel, ['widget', 'omnichannel'], true) ? $channel : 'omnichannel';
        $normalizedReason = trim($reason) !== '' ? trim($reason) : 'unknown';

        $this->incrementDailyCounter($normalizedChannel . '_provider_incident_total');
        $this->incrementDailyCounter($normalizedChannel . '_provider_incident_' . $normalizedReason . '_total');

        Log::warning('chatbot.quality.provider_incident', [
            'conversation_id' => $conversationId,
            'channel' => $normalizedChannel,
            'reason' => $normalizedReason,
        ]);
    }

    private function incrementDailyCounter(string $metric): void
    {
        $date = now()->toDateString();
        $key = "chatbot_quality:{$date}:{$metric}";
        $ttl = now()->addDays(self::RETENTION_DAYS);

        Cache::add($key, 0, $ttl);
        Cache::increment($key);
    }

    public function getDailySummary(?string $date = null): array
    {
        $targetDate = $date ?: now()->toDateString();
        $thresholds = $this->thresholds();

        $widgetResponseTotal = $this->counter($targetDate, 'widget_response_total');
        $widgetFallbackTotal = $this->counter($targetDate, 'widget_response_fallback_total');
        $widgetNonGeorgianTotal = $this->counter($targetDate, 'widget_model_non_georgian_total');

        $omniResponseTotal = $this->counter($targetDate, 'omnichannel_response_total');
        $omniFallbackTotal = $this->counter($targetDate, 'omnichannel_response_fallback_total');
        $omniNonStrictTotal = $this->counter($targetDate, 'omnichannel_response_non_strict_total');

        $slowResponseTotal = $this->counter($targetDate, 'widget_response_slow_total')
            + $this->counter($targetDate, 'omnichannel_response_slow_total');
        $regenerationAttemptTotal = $this->counter($targetDate, 'widget_response_regeneration_attempt_total')
            + $this->counter($targetDate, 'omnichannel_response_regeneration_attempt_total');
        $regenerationSuccessTotal = $this->counter($targetDate, 'widget_response_regeneration_success_total')
            + $this->counter($targetDate, 'omnichannel_response_regeneration_success_total');
        $providerFallbackTotal = $this->counter($targetDate, 'widget_response_fallback_provider_total')
            + $this->counter($targetDate, 'omnichannel_response_fallback_provider_total');
        $providerIncidentTotal = $this->counter($targetDate, 'widget_provider_incident_total')
            + $this->counter($targetDate, 'omnichannel_provider_incident_total');
        $validatorFallbackTotal = $this->counter($targetDate, 'widget_response_fallback_validator_total')
            + $this->counter($targetDate, 'omnichannel_response_fallback_validator_total');
        $policyFallbackTotal = $this->counter($targetDate, 'widget_response_fallback_policy_total')
            + $this->counter($targetDate, 'omnichannel_response_fallback_policy_total');
        $otherFallbackTotal = $this->counter($targetDate, 'widget_response_fallback_other_total')
            + $this->counter($targetDate, 'omnichannel_response_fallback_other_total');

        $autoDecisionTotal = $this->counter($targetDate, 'auto_reply_decision_total');
        $autoAcceptedTotal = $this->counter($targetDate, 'auto_reply_accepted_total');
        $autoRejectedTotal = $this->counter($targetDate, 'auto_reply_rejected_total');

        $responseTotal = $widgetResponseTotal + $omniResponseTotal;
        $fallbackTotal = $widgetFallbackTotal + $omniFallbackTotal;
        $nonGeorgianTotal = $widgetNonGeorgianTotal + $omniNonStrictTotal;

        return [
            'date' => $targetDate,
            'counts' => [
                'response_total' => $responseTotal,
                'fallback_total' => $fallbackTotal,
                'non_georgian_total' => $nonGeorgianTotal,
                'slow_response_total' => $slowResponseTotal,
                'regeneration_attempt_total' => $regenerationAttemptTotal,
                'regeneration_success_total' => $regenerationSuccessTotal,
                'provider_fallback_total' => $providerFallbackTotal,
                'provider_incident_total' => $providerIncidentTotal,
                'validator_fallback_total' => $validatorFallbackTotal,
                'policy_fallback_total' => $policyFallbackTotal,
                'other_fallback_total' => $otherFallbackTotal,
                'auto_reply_decision_total' => $autoDecisionTotal,
                'auto_reply_accepted_total' => $autoAcceptedTotal,
                'auto_reply_rejected_total' => $autoRejectedTotal,
            ],
            'rates' => [
                'fallback_rate' => $this->rate($fallbackTotal, $responseTotal),
                'non_georgian_rate' => $this->rate($nonGeorgianTotal, $responseTotal),
                'slow_response_rate' => $this->rate($slowResponseTotal, $responseTotal),
                'regeneration_attempt_rate' => $this->rate($regenerationAttemptTotal, $responseTotal),
                'regeneration_success_rate' => $this->rate($regenerationSuccessTotal, $regenerationAttemptTotal),
                'provider_fallback_rate' => $this->rate($providerFallbackTotal, $responseTotal),
                'provider_incident_rate' => $this->rate($providerIncidentTotal, $responseTotal + $providerIncidentTotal),
                'validator_fallback_rate' => $this->rate($validatorFallbackTotal, $responseTotal),
                'policy_fallback_rate' => $this->rate($policyFallbackTotal, $responseTotal),
                'other_fallback_rate' => $this->rate($otherFallbackTotal, $responseTotal),
                'auto_reply_accept_rate' => $this->rate($autoAcceptedTotal, $autoDecisionTotal),
            ],
            'thresholds' => [
                'slow_response_ms' => $thresholds['slow_response_ms'],
            ],
        ];
    }

    public function getRangeSummary(int $days = 7): array
    {
        $safeDays = max(1, min($days, self::RETENTION_DAYS));
        $daily = [];

        for ($i = $safeDays - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $daily[] = $this->getDailySummary($date);
        }

        return [
            'days' => $safeDays,
            'daily' => $daily,
        ];
    }

    private function counter(string $date, string $metric): int
    {
        return (int) Cache::get("chatbot_quality:{$date}:{$metric}", 0);
    }

    private function recordResponseDiagnostics(
        string $channel,
        ?string $fallbackReason,
        bool $regenerationAttempted,
        bool $regenerationSucceeded,
        ?int $responseTimeMs
    ): void {
        if ($responseTimeMs !== null && $responseTimeMs >= $this->thresholds()['slow_response_ms']) {
            $this->incrementDailyCounter($channel . '_response_slow_total');
        }

        if ($regenerationAttempted) {
            $this->incrementDailyCounter($channel . '_response_regeneration_attempt_total');
        }

        if ($regenerationSucceeded) {
            $this->incrementDailyCounter($channel . '_response_regeneration_success_total');
        }

        $fallbackCategory = $this->fallbackCategory($fallbackReason);
        if ($fallbackCategory !== null) {
            $this->incrementDailyCounter($channel . '_response_fallback_' . $fallbackCategory . '_total');
        }
    }

    private function fallbackCategory(?string $fallbackReason): ?string
    {
        $reason = trim((string) $fallbackReason);
        if ($reason === '') {
            return null;
        }

        return match ($reason) {
            ChatbotOutcomeReason::PROVIDER_UNAVAILABLE,
            ChatbotOutcomeReason::PROVIDER_EXCEPTION,
            ChatbotOutcomeReason::EMPTY_MODEL_OUTPUT,
            ChatbotOutcomeReason::CHATBOT_DISABLED,
            ChatbotOutcomeReason::RUNTIME_EXCEPTION => 'provider',
            ChatbotOutcomeReason::VALIDATOR_FAILED,
            ChatbotOutcomeReason::VALIDATOR_RETRY_FAILED => 'validator',
            ChatbotOutcomeReason::INPUT_GUARD,
            ChatbotOutcomeReason::STRICT_GEORGIAN,
            ChatbotOutcomeReason::OUT_OF_DOMAIN,
            ChatbotOutcomeReason::CLARIFICATION_NEEDED,
            ChatbotOutcomeReason::GREETING_ONLY => 'policy',
            ChatbotOutcomeReason::GENERIC_REPEATED => 'other',
            default => 'other',
        };
    }

    /**
     * @return array{slow_response_ms:int,fallback_alert_rate:float,slow_response_alert_rate:float,provider_fallback_alert_rate:float,provider_incident_alert_rate:float,validator_fallback_alert_rate:float,regeneration_attempt_alert_rate:float,regeneration_success_min_rate:float}
     */
    public function thresholds(): array
    {
        return [
            'slow_response_ms' => (int) config('chatbot-monitoring.thresholds.slow_response_ms', 8000),
            'fallback_alert_rate' => (float) config('chatbot-monitoring.thresholds.fallback_alert_rate', 20.0),
            'slow_response_alert_rate' => (float) config('chatbot-monitoring.thresholds.slow_response_alert_rate', 15.0),
            'provider_fallback_alert_rate' => (float) config('chatbot-monitoring.thresholds.provider_fallback_alert_rate', 10.0),
            'provider_incident_alert_rate' => (float) config('chatbot-monitoring.thresholds.provider_incident_alert_rate', 5.0),
            'validator_fallback_alert_rate' => (float) config('chatbot-monitoring.thresholds.validator_fallback_alert_rate', 10.0),
            'regeneration_attempt_alert_rate' => (float) config('chatbot-monitoring.thresholds.regeneration_attempt_alert_rate', 20.0),
            'regeneration_success_min_rate' => (float) config('chatbot-monitoring.thresholds.regeneration_success_min_rate', 50.0),
        ];
    }

    private function rate(int $numerator, int $denominator): float
    {
        if ($denominator <= 0) {
            return 0.0;
        }

        return round(($numerator / $denominator) * 100, 2);
    }
}
