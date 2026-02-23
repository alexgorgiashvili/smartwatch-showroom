<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Chatbot\ChatbotQualityMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatbotMetricsController extends Controller
{
    public function summary(Request $request, ChatbotQualityMetricsService $metrics): JsonResponse
    {
        $days = (int) $request->query('days', 7);

        return response()->json([
            'success' => true,
            'summary' => $metrics->getRangeSummary($days),
        ]);
    }
}
