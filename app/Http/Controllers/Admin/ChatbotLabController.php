<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatbotTestResult;
use App\Models\ChatbotTestRun;
use App\Models\ChatbotTrainingCase;
use App\Services\Chatbot\ChatbotLabService;
use App\Services\Chatbot\ChatbotLabRunService;
use App\Services\Chatbot\ChatbotTrainingCaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class ChatbotLabController extends Controller
{
    private const MANUAL_SESSION_KEY = 'chatbot_lab.active_conversation_id';

    public function index(
        Request $request,
        ChatbotTrainingCaseService $trainingCaseService,
        ChatbotLabService $labService
    ): View
    {
        return view('admin.chatbot-lab.index', $this->manualPageData($trainingCaseService, null, [
            'result' => null,
            'formData' => [
                'prompt' => '',
                'previous_prompts' => '',
                'continue_session' => '',
            ],
        ], $labService->getSessionState((int) $request->session()->get(self::MANUAL_SESSION_KEY))));
    }

    public function runManualTest(
        Request $request,
        ChatbotLabService $labService,
        ChatbotTrainingCaseService $trainingCaseService
    ): View
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
            'previous_prompts' => ['nullable', 'string', 'max:5000'],
            'continue_session' => ['nullable', 'in:1'],
        ]);

        $continueSession = (bool) ($data['continue_session'] ?? false);
        $existingConversationId = (int) $request->session()->get(self::MANUAL_SESSION_KEY);
        $activeConversationId = $continueSession ? $existingConversationId : 0;
        $previousPrompts = $continueSession ? '' : (string) ($data['previous_prompts'] ?? '');
        $result = $labService->runManualTest(
            (string) $data['prompt'],
            $previousPrompts,
            $activeConversationId > 0 ? $activeConversationId : null,
            $continueSession
        );

        $sessionState = $continueSession
            ? $labService->getSessionState((int) ($result['session']['conversation_id'] ?? 0))
            : ($existingConversationId > 0 ? $labService->getSessionState($existingConversationId) : null);

        if ($continueSession && !empty($result['session']['conversation_id'])) {
            $request->session()->put(self::MANUAL_SESSION_KEY, (int) $result['session']['conversation_id']);
        }

        return view('admin.chatbot-lab.index', $this->manualPageData($trainingCaseService, null, [
            'result' => $result,
            'formData' => [
                'prompt' => (string) $data['prompt'],
                'previous_prompts' => $previousPrompts,
                'continue_session' => $continueSession ? '1' : '',
            ],
        ], $sessionState));
    }

    public function retryManualResult(
        Request $request,
        ChatbotLabService $labService,
        ChatbotTrainingCaseService $trainingCaseService
    ): View
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
            'previous_prompts' => ['nullable', 'string', 'max:5000'],
            'continue_session' => ['nullable', 'in:1'],
            'retry_strategy' => ['required', 'in:same,constrained'],
            'retry_context' => ['nullable', 'string', 'max:20000'],
        ]);

        $continueSession = (bool) ($data['continue_session'] ?? false);
        $existingConversationId = (int) $request->session()->get(self::MANUAL_SESSION_KEY);
        $activeConversationId = $continueSession ? $existingConversationId : 0;
        $previousPrompts = $continueSession ? '' : (string) ($data['previous_prompts'] ?? '');
        $retryContext = $this->decodeRetryContext((string) ($data['retry_context'] ?? ''));
        $result = $labService->runRetriedManualTest(
            (string) $data['prompt'],
            $previousPrompts,
            (string) $data['retry_strategy'],
            $retryContext,
            $activeConversationId > 0 ? $activeConversationId : null,
            $continueSession
        );

        $sessionState = $continueSession
            ? $labService->getSessionState((int) ($result['session']['conversation_id'] ?? 0))
            : ($existingConversationId > 0 ? $labService->getSessionState($existingConversationId) : null);

        if ($continueSession && !empty($result['session']['conversation_id'])) {
            $request->session()->put(self::MANUAL_SESSION_KEY, (int) $result['session']['conversation_id']);
        }

        return view('admin.chatbot-lab.index', $this->manualPageData($trainingCaseService, 'ხელახალი გაშვება დასრულდა.', [
            'result' => $result,
            'formData' => [
                'prompt' => (string) $data['prompt'],
                'previous_prompts' => $previousPrompts,
                'continue_session' => $continueSession ? '1' : '',
            ],
        ], $sessionState));
    }

    public function resetManualSession(
        Request $request,
        ChatbotLabService $labService
    ): RedirectResponse
    {
        $conversationId = (int) $request->session()->get(self::MANUAL_SESSION_KEY);

        $labService->resetSession($conversationId > 0 ? $conversationId : null);
        $request->session()->forget(self::MANUAL_SESSION_KEY);

        return redirect()
            ->route('admin.chatbot-lab.index')
            ->with('status', 'ლაბის მიმდინარე სესია გასუფთავდა.');
    }

    public function cases(Request $request, ChatbotTrainingCaseService $trainingCaseService): View
    {
        $filters = [
            'search' => (string) $request->query('search', ''),
            'status' => (string) $request->query('status', 'all'),
            'tag' => (string) $request->query('tag', ''),
        ];

        $cases = $trainingCaseService->listCases($filters);

        return view('admin.chatbot-lab.cases.index', [
            'cases' => $cases,
            'caseDiagnostics' => $trainingCaseService->diagnosticsForCases($cases->items()),
            'filters' => $filters,
            'labStats' => $trainingCaseService->stats(),
            'casesReady' => $trainingCaseService->isReady(),
        ]);
    }

    public function storeCase(Request $request, ChatbotTrainingCaseService $trainingCaseService): RedirectResponse
    {
        if (!$trainingCaseService->isReady()) {
            return redirect()
                ->route('admin.chatbot-lab.cases.index')
                ->with('warning', 'ჯერ გაუშვით მიგრაციები, რომ ქეისების ცხრილი შეიქმნას.');
        }

        $trainingCaseService->createCase($this->validateTrainingCase($request), $request->user()?->id);

        return redirect()
            ->route('admin.chatbot-lab.cases.index', $request->only(['search', 'status', 'tag', 'page']))
            ->with('status', 'სატესტო ქეისი დაემატა.');
    }

    public function updateCase(
        Request $request,
        ChatbotTrainingCase $trainingCase,
        ChatbotTrainingCaseService $trainingCaseService
    ): RedirectResponse
    {
        if (!$trainingCaseService->isReady()) {
            return redirect()
                ->route('admin.chatbot-lab.cases.index')
                ->with('warning', 'ჯერ გაუშვით მიგრაციები, რომ ქეისების ცხრილი შეიქმნას.');
        }

        $trainingCaseService->updateCase($trainingCase, $this->validateTrainingCase($request));

        return redirect()
            ->route('admin.chatbot-lab.cases.index', $request->only(['search', 'status', 'tag', 'page']))
            ->with('status', 'სატესტო ქეისი განახლდა.');
    }

    public function previewCaseDiagnostics(
        Request $request,
        ChatbotTrainingCaseService $trainingCaseService,
        ?ChatbotTrainingCase $trainingCase = null
    ): JsonResponse
    {
        $payload = $this->validateTrainingCase($request, false);

        return response()->json([
            'diagnostics' => $trainingCaseService->previewDiagnostics($payload, $trainingCase?->id),
        ]);
    }

    public function destroyCase(Request $request, ChatbotTrainingCase $trainingCase): RedirectResponse
    {
        $trainingCase->delete();

        return redirect()
            ->route('admin.chatbot-lab.cases.index', $request->only(['search', 'status', 'tag', 'page']))
            ->with('status', 'სატესტო ქეისი წაიშალა.');
    }

    public function runs(ChatbotTrainingCaseService $trainingCaseService, ChatbotLabRunService $runService): View
    {
        $selectableCases = $runService->selectableCases();

        return view('admin.chatbot-lab.runs.index', [
            'labStats' => $trainingCaseService->stats(),
            'casesReady' => $trainingCaseService->isReady(),
            'runStorageReady' => $runService->runsReady(),
            'queueStatus' => $runService->queueStatus(),
            'selectableCases' => $selectableCases,
            'caseDiagnostics' => $trainingCaseService->diagnosticsForCases($selectableCases),
            'selectionPreflight' => $trainingCaseService->preflightSelection($selectableCases->pluck('id')->map(fn ($id): int => (int) $id)->all()),
            'recentRuns' => $runService->recentRuns(),
            'observabilitySummary' => $runService->observabilitySummary(),
        ]);
    }

    public function startRun(
        Request $request,
        ChatbotLabRunService $runService,
        ChatbotTrainingCaseService $trainingCaseService
    ): RedirectResponse
    {
        if (!$runService->casesReady()) {
            return redirect()
                ->route('admin.chatbot-lab.runs.index')
                ->with('warning', 'Run migrations first to create the chatbot training cases table.');
        }

        if (!$runService->runsReady()) {
            return redirect()
                ->route('admin.chatbot-lab.runs.index')
                ->with('warning', 'Legacy run storage tables are missing. Create chatbot test run tables before starting evaluation runs.');
        }

        $queueStatus = $runService->queueStatus();
        if (!$queueStatus['can_dispatch']) {
            return redirect()
                ->route('admin.chatbot-lab.runs.index')
                ->with('warning', $queueStatus['message']);
        }

        $data = $request->validate([
            'case_ids' => ['required', 'array', 'min:1'],
            'case_ids.*' => ['integer'],
            'use_llm_judge' => ['nullable', 'in:1'],
        ]);

        $preflight = $trainingCaseService->preflightSelection(array_map('intval', $data['case_ids'] ?? []));
        if ($preflight['blocking_count'] > 0) {
            return redirect()
                ->route('admin.chatbot-lab.runs.index')
                ->with('warning', 'Selected cases need stronger expectations before execution: ' . implode(' | ', array_slice($preflight['blocking_messages'], 0, 3)));
        }

        try {
            $run = $runService->queueRun(
                array_map('intval', $data['case_ids'] ?? []),
                (bool) ($data['use_llm_judge'] ?? false)
            );
        } catch (\Throwable $exception) {
            return redirect()
                ->route('admin.chatbot-lab.runs.index')
                ->with('warning', 'Run failed: ' . $exception->getMessage());
        }

        return redirect()
            ->route('admin.chatbot-lab.runs.show', $run)
            ->with('status', $queueStatus['background_capable']
                ? 'Evaluation run queued. Refresh the page to monitor progress.'
                : 'Evaluation run started with the sync queue driver. It will execute during the request until a background worker is configured.');
    }

    public function showRun(Request $request, ChatbotTestRun $run, ChatbotLabRunService $runService): View
    {
        $labRun = $runService->labRunDetail($run->id);

        abort_if($labRun === null, Response::HTTP_NOT_FOUND);

        $filters = [
            'status' => (string) $request->query('status', ''),
            'search' => (string) $request->query('search', ''),
        ];

        $results = $runService->filteredResults($labRun, $filters);
        $resultSignals = [];

        foreach ($results->items() as $result) {
            $resultSignals[$result->id] = $runService->summarizeResultSignal($result);
        }

        return view('admin.chatbot-lab.runs.show', [
            'run' => $labRun,
            'runSnapshot' => $runService->statusSnapshot($labRun),
            'runObservability' => $runService->runObservabilitySnapshot($labRun),
            'results' => $results,
            'resultSignals' => $resultSignals,
            'filters' => $filters,
            'queueStatus' => $runService->queueStatus(),
        ]);
    }

    public function runStatus(ChatbotTestRun $run, ChatbotLabRunService $runService): JsonResponse
    {
        $labRun = $runService->labRunDetail($run->id);

        abort_if($labRun === null, Response::HTTP_NOT_FOUND);

        return response()->json([
            'run' => $runService->statusSnapshot($labRun),
        ]);
    }

    public function cancelRunAction(ChatbotTestRun $run, ChatbotLabRunService $runService): RedirectResponse
    {
        $labRun = $runService->labRunDetail($run->id);

        abort_if($labRun === null, Response::HTTP_NOT_FOUND);

        if ($labRun->isTerminal()) {
            return redirect()
                ->route('admin.chatbot-lab.runs.show', $labRun)
                ->with('warning', 'This run is already finished and cannot be cancelled.');
        }

        $runService->cancelRun($labRun);

        return redirect()
            ->route('admin.chatbot-lab.runs.show', $labRun)
            ->with('status', 'Evaluation run cancelled.');
    }

    public function exportRunCsv(ChatbotTestRun $run, ChatbotLabRunService $runService): StreamedResponse
    {
        $labRun = $runService->labRunDetail($run->id);

        abort_if($labRun === null, Response::HTTP_NOT_FOUND);

        $filename = 'chatbot-lab-run-' . $labRun->id . '.csv';

        return response()->streamDownload(function () use ($labRun): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'case_id',
                'category',
                'question',
                'status',
                'keyword_match',
                'price_match',
                'stock_match',
                'guardrail_passed',
                'georgian_qa_passed',
                'llm_overall',
                'response_time_ms',
                'created_at',
            ]);

            $labRun->results()->orderBy('id')->chunk(200, function ($rows) use ($handle): void {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->case_id,
                        $row->category,
                        $row->question,
                        $row->status,
                        $row->keyword_match ? '1' : '0',
                        $row->price_match === null ? '' : ($row->price_match ? '1' : '0'),
                        $row->stock_match === null ? '' : ($row->stock_match ? '1' : '0'),
                        $row->guardrail_passed === null ? '' : ($row->guardrail_passed ? '1' : '0'),
                        $row->georgian_qa_passed === null ? '' : ($row->georgian_qa_passed ? '1' : '0'),
                        $row->llm_overall,
                        $row->response_time_ms,
                        optional($row->created_at)->toDateTimeString(),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function rerunResult(
        Request $request,
        ChatbotTestResult $result,
        ChatbotLabService $labService,
        ChatbotTrainingCaseService $trainingCaseService
    ): View
    {
        $labResult = $this->labResultOrFail($result);
        $data = $request->validate([
            'retry_strategy' => ['required', 'in:same,constrained'],
        ]);

        $rerunResult = $labService->runRetriedManualTest(
            (string) $labResult->question,
            '',
            (string) $data['retry_strategy'],
            $this->retryContextFromLabResult($labResult),
            null,
            false
        );

        return view('admin.chatbot-lab.index', $this->manualPageData($trainingCaseService, 'შედეგი გადაიტანეს ხელახალი ტესტისთვის: #' . $labResult->id . '.', [
            'result' => $rerunResult,
            'formData' => [
                'prompt' => (string) $labResult->question,
                'previous_prompts' => '',
                'continue_session' => '',
            ],
        ]));
    }

    public function saveObservation(Request $request, ChatbotTestResult $result): RedirectResponse
    {
        $labResult = $this->labResultOrFail($result);

        $data = $request->validate([
            'observation' => ['nullable', 'string', 'max:5000'],
            'action' => ['nullable', 'in:save,resolve'],
        ]);

        $observation = trim((string) ($data['observation'] ?? ''));
        $action = (string) ($data['action'] ?? 'save');

        $updates = [
            'admin_feedback' => $observation !== '' ? $observation : null,
        ];

        if ($action === 'resolve') {
            $updates['retrain_status'] = 'done';
        } elseif ($observation !== '') {
            $updates['retrain_status'] = 'pending';
        }

        $labResult->update($updates);

        return redirect()
            ->back()
            ->with('status', $action === 'resolve' ? 'Observation saved and marked resolved.' : 'Observation saved.');
    }

    public function promoteResult(
        Request $request,
        ChatbotTestResult $result,
        ChatbotTrainingCaseService $trainingCaseService
    ): RedirectResponse
    {
        $labResult = $this->labResultOrFail($result);

        if (!$trainingCaseService->isReady()) {
            return redirect()
                ->back()
                ->with('warning', 'Training cases table is not ready. Run migrations first.');
        }

        $trainingCase = $trainingCaseService->createFromResult($labResult, $request->user()?->id);

        return redirect()
            ->back()
            ->with('status', 'Training case ready: #' . $trainingCase->id . '.');
    }

    public function promoteAndRerunResult(
        Request $request,
        ChatbotTestResult $result,
        ChatbotLabService $labService,
        ChatbotTrainingCaseService $trainingCaseService
    ): View|RedirectResponse
    {
        $labResult = $this->labResultOrFail($result);

        if (!$trainingCaseService->isReady()) {
            return redirect()
                ->back()
                ->with('warning', 'Training cases table is not ready. Run migrations first.');
        }

        $data = $request->validate([
            'retry_strategy' => ['required', 'in:same,constrained'],
        ]);

        $trainingCase = $trainingCaseService->createFromResult($labResult, $request->user()?->id);
        $retryContext = $this->retryContextFromLabResult($labResult);
        $retryContext['expected_summary'] = $trainingCase->expected_intent
            ? 'Intent: ' . $trainingCase->expected_intent
            : ($retryContext['expected_summary'] ?? null);
        $retryContext['intent'] = $trainingCase->expected_intent ?: ($retryContext['intent'] ?? null);
        $retryContext['entities'] = array_merge(
            is_array($retryContext['entities'] ?? null) ? $retryContext['entities'] : [],
            [
                'product_slug_hint' => $trainingCase->expected_product_slugs_json[0] ?? null,
            ]
        );

        $rerunResult = $labService->runRetriedManualTest(
            (string) $labResult->question,
            '',
            (string) $data['retry_strategy'],
            $retryContext,
            null,
            false
        );

        $rerunResult['retry']['promoted_case_id'] = $trainingCase->id;
        $rerunResult['retry']['promoted_case_title'] = $trainingCase->title;

        return view('admin.chatbot-lab.index', $this->manualPageData($trainingCaseService, 'ქეისი მზადაა: #' . $trainingCase->id . '. შედეგი ჩაიტვირთა ხელახალი ტესტისთვის: #' . $labResult->id . '.', [
            'result' => $rerunResult,
            'formData' => [
                'prompt' => (string) $labResult->question,
                'previous_prompts' => '',
                'continue_session' => '',
            ],
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function manualPageData(
        ChatbotTrainingCaseService $trainingCaseService,
        ?string $status,
        array $data,
        ?array $sessionState = null
    ): array
    {
        return array_merge($data, [
            'labStats' => $trainingCaseService->stats(),
            'casesReady' => $trainingCaseService->isReady(),
            'statusMessage' => $status,
            'sessionState' => $sessionState,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTrainingCase(Request $request, bool $strict = true): array
    {
        return $request->validate([
            'title' => [$strict ? 'required' : 'nullable', 'string', 'max:150'],
            'prompt' => [$strict ? 'required' : 'nullable', 'string', 'max:2000'],
            'conversation_context' => ['nullable', 'string', 'max:5000'],
            'expected_intent' => ['nullable', 'string', 'max:100'],
            'expected_keywords' => ['nullable', 'string', 'max:2000'],
            'expected_product_slugs' => ['nullable', 'string', 'max:2000'],
            'expected_price_behavior' => ['nullable', 'string', 'max:100'],
            'expected_stock_behavior' => ['nullable', 'string', 'max:100'],
            'reviewer_notes' => ['nullable', 'string', 'max:5000'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'source' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'in:1'],
        ]);
    }

    private function labResultOrFail(ChatbotTestResult $result): ChatbotTestResult
    {
        $testRun = $result->testRun;

        abort_if(!$testRun || $testRun->triggered_by !== 'chatbot_lab', Response::HTTP_NOT_FOUND);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeRetryContext(string $rawContext): array
    {
        if (trim($rawContext) === '') {
            return [];
        }

        $decoded = json_decode($rawContext, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function retryContextFromLabResult(ChatbotTestResult $result): array
    {
        $intentJson = is_array($result->intent_json ?? null) ? $result->intent_json : [];

        return [
            'expected_summary' => $result->expected_summary,
            'intent' => $result->intent_type ?: ($intentJson['intent'] ?? null),
            'entities' => is_array($intentJson['entities'] ?? null) ? $intentJson['entities'] : [],
            'keyword_match' => $result->keyword_match,
            'price_match' => $result->price_match,
            'stock_match' => $result->stock_match,
            'georgian_passed' => $result->georgian_qa_passed,
            'intent_match' => $result->intent_match,
            'entity_match' => $result->entity_match,
            'llm_notes' => $result->llm_notes,
        ];
    }
}
