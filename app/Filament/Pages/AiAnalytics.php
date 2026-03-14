<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class AiAnalytics extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.pages.ai-analytics';

    protected static ?string $navigationGroup = 'AI Lab';

    protected static ?string $navigationLabel = 'AI Analytics';

    protected static ?int $navigationSort = 1;

    public function getTitle(): string
    {
        return 'AI Traffic Analytics';
    }

    public function getHeading(): string
    {
        return 'AI ტრაფიკის ანალიტიკა';
    }

    /**
     * Get AI traffic statistics
     */
    public function getStats(): array
    {
        $today = now()->startOfDay();
        $weekAgo = now()->subWeek();
        $monthAgo = now()->subMonth();

        return [
            'total_visits' => DB::table('ai_traffic')->count(),
            'today_visits' => DB::table('ai_traffic')->where('created_at', '>=', $today)->count(),
            'week_visits' => DB::table('ai_traffic')->where('created_at', '>=', $weekAgo)->count(),
            'month_visits' => DB::table('ai_traffic')->where('created_at', '>=', $monthAgo)->count(),
        ];
    }

    /**
     * Get visits by AI family
     */
    public function getVisitsByFamily(): array
    {
        return DB::table('ai_traffic')
            ->select('ai_family', DB::raw('count(*) as count'))
            ->groupBy('ai_family')
            ->orderByDesc('count')
            ->get()
            ->pluck('count', 'ai_family')
            ->toArray();
    }

    /**
     * Get top AI bots
     */
    public function getTopBots(): array
    {
        return DB::table('ai_traffic')
            ->select('ai_bot', 'ai_family', DB::raw('count(*) as count'))
            ->groupBy('ai_bot', 'ai_family')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Get top visited paths
     */
    public function getTopPaths(): array
    {
        return DB::table('ai_traffic')
            ->select('path', DB::raw('count(*) as count'))
            ->groupBy('path')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Get recent AI visits
     */
    public function getRecentVisits(): array
    {
        return DB::table('ai_traffic')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->toArray();
    }

    /**
     * Get daily visits chart data
     */
    public function getDailyVisitsChart(): array
    {
        $days = 30;
        $data = DB::table('ai_traffic')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('count(*) as count')
            )
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $labels = [];
        $values = [];

        foreach ($data as $row) {
            $labels[] = $row->date;
            $values[] = $row->count;
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    /**
     * Get API endpoint usage
     */
    public function getApiEndpointUsage(): array
    {
        return DB::table('ai_traffic')
            ->select('path', DB::raw('count(*) as count'))
            ->where('path', 'like', '/api/ai/%')
            ->groupBy('path')
            ->orderByDesc('count')
            ->get()
            ->toArray();
    }
}
