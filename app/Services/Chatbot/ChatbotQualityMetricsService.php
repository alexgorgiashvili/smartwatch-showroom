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
        bool $nonGeorgianModelOutput
    ): void {
        $this->incrementDailyCounter('widget_response_total');

        if ($fallbackUsed) {
            $this->incrementDailyCounter('widget_response_fallback_total');
        }

        if ($nonGeorgianModelOutput) {
            $this->incrementDailyCounter('widget_model_non_georgian_total');
        }

        Log::info('chatbot.quality.widget_response', [
            'conversation_id' => $conversationId,
            'customer_id' => $customerId,
            'fallback_used' => $fallbackUsed,
            'non_georgian_model_output' => $nonGeorgianModelOutput,
        ]);
    }

    public function recordOmnichannelResponseQuality(
        int $conversationId,
        bool $fallbackUsed,
        bool $strictQaPassed
    ): void {
        $this->incrementDailyCounter('omnichannel_response_total');

        if ($fallbackUsed) {
            $this->incrementDailyCounter('omnichannel_response_fallback_total');
        }

        if (!$strictQaPassed) {
            $this->incrementDailyCounter('omnichannel_response_non_strict_total');
        }

        Log::info('chatbot.quality.omnichannel_response', [
            'conversation_id' => $conversationId,
            'fallback_used' => $fallbackUsed,
            'strict_qa_passed' => $strictQaPassed,
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

        $widgetResponseTotal = $this->counter($targetDate, 'widget_response_total');
        $widgetFallbackTotal = $this->counter($targetDate, 'widget_response_fallback_total');
        $widgetNonGeorgianTotal = $this->counter($targetDate, 'widget_model_non_georgian_total');

        $omniResponseTotal = $this->counter($targetDate, 'omnichannel_response_total');
        $omniFallbackTotal = $this->counter($targetDate, 'omnichannel_response_fallback_total');
        $omniNonStrictTotal = $this->counter($targetDate, 'omnichannel_response_non_strict_total');

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
                'auto_reply_decision_total' => $autoDecisionTotal,
                'auto_reply_accepted_total' => $autoAcceptedTotal,
                'auto_reply_rejected_total' => $autoRejectedTotal,
            ],
            'rates' => [
                'fallback_rate' => $this->rate($fallbackTotal, $responseTotal),
                'non_georgian_rate' => $this->rate($nonGeorgianTotal, $responseTotal),
                'auto_reply_accept_rate' => $this->rate($autoAcceptedTotal, $autoDecisionTotal),
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

    private function rate(int $numerator, int $denominator): float
    {
        if ($denominator <= 0) {
            return 0.0;
        }

        return round(($numerator / $denominator) * 100, 2);
    }
}
