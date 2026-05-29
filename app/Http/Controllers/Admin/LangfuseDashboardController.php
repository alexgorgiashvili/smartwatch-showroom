<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Chatbot\LangfuseDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LangfuseDashboardController extends Controller
{
    public function __construct(
        private readonly LangfuseDashboardService $dashboard
    ) {}

    public function index(Request $request)
    {
        $hours = (int) $request->query('hours', 24);
        $limit = (int) $request->query('limit', 200);
        $snapshot = $this->dashboard->snapshot($hours, $limit);

        /** @var View $view */
        $view = view('admin.langfuse-dashboard.index', [
            'snapshot' => $snapshot,
            'filters' => [
                'hours' => $hours,
                'limit' => $limit,
                'generated_at' => now()->format('Y-m-d H:i:s'),
            ],
            'hourOptions' => [
                6 => 'ბოლო 6 საათი',
                12 => 'ბოლო 12 საათი',
                24 => 'ბოლო 24 საათი',
                48 => 'ბოლო 48 საათი',
                72 => 'ბოლო 72 საათი',
                168 => 'ბოლო 7 დღე',
            ],
            'limitOptions' => [
                50 => '50 observation',
                100 => '100 observation',
                200 => '200 observation',
                300 => '300 observation',
            ],
        ]);

        return $this->renderPjaxView($request, $view);
    }
}
