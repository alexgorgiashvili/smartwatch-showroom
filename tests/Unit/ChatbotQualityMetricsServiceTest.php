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

        $service->recordWidgetResponseQuality(10, 20, true, true, 'provider_unavailable', true, false, 9000);

        $this->assertSame(1, Cache::get("chatbot_quality:{$date}:widget_response_total"));
        $this->assertSame(1, Cache::get("chatbot_quality:{$date}:widget_response_fallback_total"));
        $this->assertSame(1, Cache::get("chatbot_quality:{$date}:widget_model_non_georgian_total"));
        $this->assertSame(1, Cache::get("chatbot_quality:{$date}:widget_response_slow_total"));
        $this->assertSame(1, Cache::get("chatbot_quality:{$date}:widget_response_regeneration_attempt_total"));
        $this->assertSame(1, Cache::get("chatbot_quality:{$date}:widget_response_fallback_provider_total"));
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

    public function testRecordProviderIncidentTracksChannelAndReasonCounters(): void
    {
        $service = new ChatbotQualityMetricsService();
        $date = now()->toDateString();

        $service->recordProviderIncident(55, 'omnichannel', 'no_suggestions');

        $this->assertSame(1, Cache::get("chatbot_quality:{$date}:omnichannel_provider_incident_total"));
        $this->assertSame(1, Cache::get("chatbot_quality:{$date}:omnichannel_provider_incident_no_suggestions_total"));
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
        Cache::put("chatbot_quality:{$date}:widget_response_slow_total", 4, now()->addMinutes(10));
        Cache::put("chatbot_quality:{$date}:omnichannel_response_slow_total", 2, now()->addMinutes(10));
        Cache::put("chatbot_quality:{$date}:widget_response_regeneration_attempt_total", 3, now()->addMinutes(10));
        Cache::put("chatbot_quality:{$date}:omnichannel_response_regeneration_attempt_total", 1, now()->addMinutes(10));
        Cache::put("chatbot_quality:{$date}:widget_response_regeneration_success_total", 2, now()->addMinutes(10));
        Cache::put("chatbot_quality:{$date}:omnichannel_response_regeneration_success_total", 1, now()->addMinutes(10));
        Cache::put("chatbot_quality:{$date}:widget_response_fallback_provider_total", 2, now()->addMinutes(10));
        Cache::put("chatbot_quality:{$date}:omnichannel_response_fallback_provider_total", 1, now()->addMinutes(10));
        Cache::put("chatbot_quality:{$date}:omnichannel_provider_incident_total", 2, now()->addMinutes(10));
        Cache::put("chatbot_quality:{$date}:widget_response_fallback_validator_total", 1, now()->addMinutes(10));
        Cache::put("chatbot_quality:{$date}:omnichannel_response_fallback_validator_total", 2, now()->addMinutes(10));
        Cache::put("chatbot_quality:{$date}:widget_response_fallback_policy_total", 1, now()->addMinutes(10));
        Cache::put("chatbot_quality:{$date}:omnichannel_response_fallback_policy_total", 0, now()->addMinutes(10));
        Cache::put("chatbot_quality:{$date}:auto_reply_decision_total", 10, now()->addMinutes(10));
        Cache::put("chatbot_quality:{$date}:auto_reply_accepted_total", 7, now()->addMinutes(10));
        Cache::put("chatbot_quality:{$date}:auto_reply_rejected_total", 3, now()->addMinutes(10));

        $summary = $service->getDailySummary();

        $this->assertSame(20, $summary['counts']['response_total']);
        $this->assertSame(5, $summary['counts']['fallback_total']);
        $this->assertSame(2, $summary['counts']['non_georgian_total']);
        $this->assertSame(6, $summary['counts']['slow_response_total']);
        $this->assertSame(4, $summary['counts']['regeneration_attempt_total']);
        $this->assertSame(3, $summary['counts']['regeneration_success_total']);
        $this->assertSame(3, $summary['counts']['provider_fallback_total']);
        $this->assertSame(2, $summary['counts']['provider_incident_total']);
        $this->assertSame(3, $summary['counts']['validator_fallback_total']);
        $this->assertSame(1, $summary['counts']['policy_fallback_total']);
        $this->assertSame(25.0, $summary['rates']['fallback_rate']);
        $this->assertSame(10.0, $summary['rates']['non_georgian_rate']);
        $this->assertSame(30.0, $summary['rates']['slow_response_rate']);
        $this->assertSame(20.0, $summary['rates']['regeneration_attempt_rate']);
        $this->assertSame(75.0, $summary['rates']['regeneration_success_rate']);
        $this->assertSame(15.0, $summary['rates']['provider_fallback_rate']);
        $this->assertSame(9.09, $summary['rates']['provider_incident_rate']);
        $this->assertSame(15.0, $summary['rates']['validator_fallback_rate']);
        $this->assertSame(5.0, $summary['rates']['policy_fallback_rate']);
        $this->assertSame(70.0, $summary['rates']['auto_reply_accept_rate']);
        $this->assertSame(8000, $summary['thresholds']['slow_response_ms']);
    }

    public function testThresholdsCanBeConfigured(): void
    {
        config()->set('chatbot-monitoring.thresholds.slow_response_ms', 6500);
        config()->set('chatbot-monitoring.thresholds.fallback_alert_rate', 12.5);
        config()->set('chatbot-monitoring.thresholds.provider_incident_alert_rate', 3.5);

        $service = new ChatbotQualityMetricsService();
        $thresholds = $service->thresholds();

        $this->assertSame(6500, $thresholds['slow_response_ms']);
        $this->assertSame(12.5, $thresholds['fallback_alert_rate']);
        $this->assertSame(3.5, $thresholds['provider_incident_alert_rate']);
    }
}
