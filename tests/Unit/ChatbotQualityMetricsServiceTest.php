<?php

namespace Tests\Unit;

use App\Services\Chatbot\ChatbotQualityMetricsService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ChatbotQualityMetricsServiceTest extends TestCase
{
    public function testRecordWidgetResponseQualityIncrementsDailyCounters(): void
    {
        $service = new ChatbotQualityMetricsService();
        $date = now()->toDateString();

        $service->recordWidgetResponseQuality(10, 20, true, true);

        $this->assertSame(1, Cache::get("chatbot_quality:{$date}:widget_response_total"));
        $this->assertSame(1, Cache::get("chatbot_quality:{$date}:widget_response_fallback_total"));
        $this->assertSame(1, Cache::get("chatbot_quality:{$date}:widget_model_non_georgian_total"));
    }

    public function testRecordAutoReplyDecisionTracksAcceptedAndRejected(): void
    {
        $service = new ChatbotQualityMetricsService();
        $date = now()->toDateString();

        $service->recordAutoReplyDecision(11, 100, true, 'intent_match');
        $service->recordAutoReplyDecision(11, 101, false, 'below_threshold');

        $this->assertSame(2, Cache::get("chatbot_quality:{$date}:auto_reply_decision_total"));
        $this->assertSame(1, Cache::get("chatbot_quality:{$date}:auto_reply_accepted_total"));
        $this->assertSame(1, Cache::get("chatbot_quality:{$date}:auto_reply_rejected_total"));
    }

    public function testGetDailySummaryCalculatesRates(): void
    {
        $service = new ChatbotQualityMetricsService();
        $date = now()->toDateString();

        Cache::put("chatbot_quality:{$date}:widget_response_total", 10, now()->addMinutes(10));
        Cache::put("chatbot_quality:{$date}:widget_response_fallback_total", 2, now()->addMinutes(10));
        Cache::put("chatbot_quality:{$date}:widget_model_non_georgian_total", 1, now()->addMinutes(10));
        Cache::put("chatbot_quality:{$date}:omnichannel_response_total", 10, now()->addMinutes(10));
        Cache::put("chatbot_quality:{$date}:omnichannel_response_fallback_total", 3, now()->addMinutes(10));
        Cache::put("chatbot_quality:{$date}:omnichannel_response_non_strict_total", 1, now()->addMinutes(10));
        Cache::put("chatbot_quality:{$date}:auto_reply_decision_total", 10, now()->addMinutes(10));
        Cache::put("chatbot_quality:{$date}:auto_reply_accepted_total", 7, now()->addMinutes(10));
        Cache::put("chatbot_quality:{$date}:auto_reply_rejected_total", 3, now()->addMinutes(10));

        $summary = $service->getDailySummary();

        $this->assertSame(20, $summary['counts']['response_total']);
        $this->assertSame(5, $summary['counts']['fallback_total']);
        $this->assertSame(2, $summary['counts']['non_georgian_total']);
        $this->assertSame(25.0, $summary['rates']['fallback_rate']);
        $this->assertSame(10.0, $summary['rates']['non_georgian_rate']);
        $this->assertSame(70.0, $summary['rates']['auto_reply_accept_rate']);
    }
}
