<?php

namespace App\Filament\Widgets;

use App\Services\Chatbot\ChatbotQualityMetricsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ChatbotQualityWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 5;

    protected function getStats(): array
    {
        $summary = app(ChatbotQualityMetricsService::class)->getDailySummary();
        $counts = $summary['counts'] ?? [];
        $rates = $summary['rates'] ?? [];

        return [
            Stat::make('Responses Today', number_format((int) ($counts['response_total'] ?? 0)))
                ->description('Widget + omnichannel responses')
                ->descriptionIcon('heroicon-m-chat-bubble-left-ellipsis')
                ->color('primary'),
            Stat::make('Fallback Rate', $this->formatPercent((float) ($rates['fallback_rate'] ?? 0)))
                ->description(number_format((int) ($counts['fallback_total'] ?? 0)) . ' fallback response(s)')
                ->descriptionIcon('heroicon-m-arrow-uturn-left')
                ->color($this->colorForRate((float) ($rates['fallback_rate'] ?? 0))),
            Stat::make('Non-Georgian Rate', $this->formatPercent((float) ($rates['non_georgian_rate'] ?? 0)))
                ->description(number_format((int) ($counts['non_georgian_total'] ?? 0)) . ' flagged response(s)')
                ->descriptionIcon('heroicon-m-language')
                ->color($this->colorForRate((float) ($rates['non_georgian_rate'] ?? 0))),
            Stat::make('Auto-Reply Accept Rate', $this->formatPercent((float) ($rates['auto_reply_accept_rate'] ?? 0)))
                ->description(number_format((int) ($counts['auto_reply_accepted_total'] ?? 0)) . ' accepted / ' . number_format((int) ($counts['auto_reply_decision_total'] ?? 0)) . ' evaluated')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color($this->positiveColorForRate((float) ($rates['auto_reply_accept_rate'] ?? 0))),
            Stat::make('Provider Incidents', number_format((int) ($counts['provider_incident_total'] ?? 0)))
                ->description('Channel/provider failures today')
                ->descriptionIcon('heroicon-m-signal-slash')
                ->color((int) ($counts['provider_incident_total'] ?? 0) > 0 ? 'danger' : 'success'),
        ];
    }

    private function formatPercent(float $rate): string
    {
        return number_format($rate * 100, 1) . '%';
    }

    private function colorForRate(float $rate): string
    {
        if ($rate >= 0.2) {
            return 'danger';
        }

        if ($rate >= 0.1) {
            return 'warning';
        }

        return 'success';
    }

    private function positiveColorForRate(float $rate): string
    {
        if ($rate >= 0.8) {
            return 'success';
        }

        if ($rate >= 0.6) {
            return 'warning';
        }

        return 'danger';
    }
}
