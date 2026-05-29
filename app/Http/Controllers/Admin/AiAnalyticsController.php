<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AiAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $today = now()->startOfDay();
        $weekAgo = now()->subWeek();
        $monthAgo = now()->subMonth();

        $stats = [
            'total_visits' => DB::table('ai_traffic')->count(),
            'today_visits' => DB::table('ai_traffic')->where('created_at', '>=', $today)->count(),
            'week_visits' => DB::table('ai_traffic')->where('created_at', '>=', $weekAgo)->count(),
            'month_visits' => DB::table('ai_traffic')->where('created_at', '>=', $monthAgo)->count(),
        ];

        $visitsByFamily = DB::table('ai_traffic')
            ->select('ai_family', DB::raw('count(*) as count'))
            ->groupBy('ai_family')
            ->orderByDesc('count')
            ->get()
            ->pluck('count', 'ai_family')
            ->toArray();

        $topBots = DB::table('ai_traffic')
            ->select('ai_bot', 'ai_family', DB::raw('count(*) as count'))
            ->groupBy('ai_bot', 'ai_family')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->toArray();

        $topPaths = DB::table('ai_traffic')
            ->select('path', DB::raw('count(*) as count'))
            ->groupBy('path')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->toArray();

        $recentVisits = DB::table('ai_traffic')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->toArray();

        $view = view('admin.ai-analytics.index', [
            'stats' => $stats,
            'visitsByFamily' => $visitsByFamily,
            'topBots' => $topBots,
            'topPaths' => $topPaths,
            'recentVisits' => $recentVisits,
        ]);

        return $this->renderPjaxView($request, $view);
    }
}
