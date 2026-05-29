<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RunChatbotTrainingBatchJob;
use App\Services\Chatbot\ChatbotFlowInspectorService;
use App\Services\Chatbot\ChatbotTrainingRunnerService;
use App\Services\Chatbot\ChatbotTrainingStoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChatbotTrainingController extends Controller
{
    public function __construct(
        private readonly ChatbotTrainingStoreService $store,
        private readonly ChatbotTrainingRunnerService $runner,
        private readonly ChatbotFlowInspectorService $flowInspector
    ) {}

    public function index(Request $request)
    {
        $activeTab = (string) $request->query('tab', 'overview');
        $traceHours = (int) $request->query('trace_hours', 24);
        $traceSearch = (string) $request->query('trace_search', '');
        $traceLimit = (int) $request->query('trace_limit', 50);
        $selectedTraceId = (string) $request->query('trace_id', '');

        $flowSessions = $this->flowInspector->sessions($traceHours, $traceSearch, $traceLimit);
        $traceDetail = $selectedTraceId !== '' ? $this->flowInspector->detail($selectedTraceId, max(24, $traceHours)) : null;

        $view = view('admin.chatbot-training.index', [
            'activeTab' => $activeTab,
            'snapshot' => $this->store->dashboardSnapshot(),
            'generationRequests' => $this->store->listGenerationRequests(),
            'batches' => $this->store->listBatches(),
            'runs' => $this->store->listRuns(),
            'reviews' => $this->store->listReviews(),
            'historySummary' => $this->store->questionHistorySummary(),
            'flowSessions' => $flowSessions['sessions'],
            'flowMeta' => $flowSessions['meta'],
            'traceDetail' => $traceDetail,
            'filters' => [
                'trace_hours' => $traceHours,
                'trace_search' => $traceSearch,
                'trace_limit' => $traceLimit,
                'trace_id' => $selectedTraceId,
            ],
            'hourOptions' => [
                6 => 'ბოლო 6 საათი',
                12 => 'ბოლო 12 საათი',
                24 => 'ბოლო 24 საათი',
                48 => 'ბოლო 48 საათი',
                72 => 'ბოლო 72 საათი',
                168 => 'ბოლო 7 დღე',
            ],
        ]);

        return $this->renderPjaxView($request, $view);
    }

    public function requestGeneration(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'count' => ['required', 'integer', 'min:3', 'max:100'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['string'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $generationRequest = $this->store->createGenerationRequest(
            (string) ($data['name'] ?? ''),
            (int) $data['count'],
            array_values($data['categories'] ?? []),
            (string) ($data['notes'] ?? '')
        );

        return redirect()
            ->route('admin.chatbot-training', ['tab' => 'overview'])
            ->with('status', 'Cascade generation request შეიქმნა: ' . ($generationRequest['id'] ?? 'request'));
    }

    public function importGeneratedBatch(Request $request, string $generationRequest): RedirectResponse
    {
        $data = $request->validate([
            'payload' => ['required', 'string'],
            'batch_name' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $batch = $this->store->importGeneratedBatch(
                $generationRequest,
                (string) $data['payload'],
                (string) ($data['batch_name'] ?? '')
            );
        } catch (\Throwable $exception) {
            return redirect()
                ->route('admin.chatbot-training', ['tab' => 'overview'])
                ->with('warning', $exception->getMessage());
        }

        return redirect()
            ->route('admin.chatbot-training', ['tab' => 'batches'])
            ->with('status', 'Cascade batch დაიმპორტდა: ' . ($batch['name'] ?? $batch['id']));
    }

    public function generateBatch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'count' => ['required', 'integer', 'min:5', 'max:100'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['string'],
        ]);

        $batch = $this->store->createGeneratedBatch(
            (string) ($data['name'] ?? ''),
            (int) $data['count'],
            array_values($data['categories'] ?? [])
        );

        return redirect()
            ->route('admin.chatbot-training', ['tab' => 'batches'])
            ->with('status', 'Training batch შეიქმნა: ' . ($batch['name'] ?? $batch['id']));
    }

    public function runManualFlow(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
            'category' => ['nullable', 'string', 'max:50'],
        ]);

        $result = $this->runner->runSingleQuestion(
            (string) $data['question'],
            (string) ($data['category'] ?? 'manual')
        );

        return redirect()
            ->route('admin.chatbot-training', [
                'tab' => 'flow',
                'trace_id' => $result['trace_id'] ?? '',
                'trace_hours' => 24,
            ])
            ->with('status', 'Manual flow გაეშვა.');
    }

    public function runBatch(string $batch): RedirectResponse
    {
        $batch = $this->store->getBatch($batch);

        if ($batch === null) {
            return redirect()
                ->route('admin.chatbot-training', ['tab' => 'batches'])
                ->with('warning', 'Batch ვერ მოიძებნა.');
        }

        $run = $this->store->createQueuedRun($batch);
        RunChatbotTrainingBatchJob::dispatch((string) ($run['id'] ?? ''), (string) ($batch['id'] ?? ''));

        return redirect()
            ->route('admin.chatbot-training', ['tab' => 'runs'])
            ->with('status', 'Batch დაემატა რიგში: ' . ($run['id'] ?? 'run'));
    }

    public function createReviewRequest(string $run): RedirectResponse
    {
        try {
            $review = $this->store->createReviewRequest($run);
        } catch (\Throwable $exception) {
            return redirect()
                ->route('admin.chatbot-training', ['tab' => 'reviews'])
                ->with('warning', $exception->getMessage());
        }

        return redirect()
            ->route('admin.chatbot-training', ['tab' => 'reviews'])
            ->with('status', 'Review request შეიქმნა: ' . ($review['id'] ?? 'review'));
    }

    public function importReviewAnalysis(Request $request, string $review): RedirectResponse
    {
        $data = $request->validate([
            'payload' => ['required', 'string'],
        ]);

        try {
            $this->store->importReviewAnalysis($review, (string) $data['payload']);
        } catch (\Throwable $exception) {
            return redirect()
                ->route('admin.chatbot-training', ['tab' => 'reviews'])
                ->with('warning', $exception->getMessage());
        }

        return redirect()
            ->route('admin.chatbot-training', ['tab' => 'reviews'])
            ->with('status', 'Cascade review analysis დაიმპორტდა.');
    }

    public function updateReviewDecision(Request $request, string $review): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected,needs_edit'],
        ]);

        try {
            $this->store->updateReviewDecision($review, (string) $data['decision']);
        } catch (\Throwable $exception) {
            return redirect()
                ->route('admin.chatbot-training', ['tab' => 'reviews'])
                ->with('warning', $exception->getMessage());
        }

        return redirect()
            ->route('admin.chatbot-training', ['tab' => 'reviews'])
            ->with('status', 'Review სტატუსი განახლდა.');
    }
}
