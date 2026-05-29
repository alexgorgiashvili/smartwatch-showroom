<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Chatbot\WidgetTraceReadService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChatbotTracesController extends Controller
{
    public function __construct(
        private readonly WidgetTraceReadService $traceReader
    ) {}

    public function index(Request $request)
    {
        $hours = (int) $request->query('hours', 24);
        $stepSearch = (string) $request->query('step', '');
        $fallbackOnly = (bool) $request->query('fallback', false);
        $multiAgentOnly = (bool) $request->query('multi', false);
        $limit = (int) $request->query('limit', 300);

        $snapshot = $this->traceReader->pipelineSnapshot(
            $hours,
            $stepSearch,
            $fallbackOnly,
            $multiAgentOnly,
            $limit
        );

        $view = view('admin.chatbot-traces.index', [
            'entries' => $snapshot['entries'],
            'summary' => $snapshot['summary'],
            'meta' => $snapshot['meta'],
            'filters' => [
                'hours' => $hours,
                'step_search' => $stepSearch,
                'fallback_only' => $fallbackOnly,
                'multi_agent_only' => $multiAgentOnly,
                'limit' => $limit,
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
                100 => '100 ჩანაწერი',
                300 => '300 ჩანაწერი',
                500 => '500 ჩანაწერი',
                1000 => '1000 ჩანაწერი',
            ],
        ]);

        return $this->renderPjaxView($request, $view);
    }
}
