<?php

namespace Tests\Feature;

use App\Jobs\RunChatbotLabRunJob;
use App\Models\ChatbotTestResult;
use App\Models\ChatbotTestRun;
use App\Models\ChatbotTrainingCase;
use App\Models\User;
use App\Services\Chatbot\ChatbotLabService;
use App\Services\Chatbot\ChatbotLabRunService;
use App\Services\Chatbot\TestRunnerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ChatbotLabWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'is_admin' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testAdminCanCreateUpdateAndDeleteTrainingCase(): void
    {
        $createResponse = $this->actingAs($this->admin)->post(route('admin.chatbot-lab.cases.store'), [
            'title' => 'Budget recommendation case',
            'prompt' => '200 ლარამდე რას მირჩევ?',
            'conversation_context' => "ბავშვისთვის მინდა\nGPS უნდა ჰქონდეს",
            'expected_intent' => 'recommendation',
            'expected_keywords' => "GPS\nლარი",
            'expected_product_slugs' => 'wonlex-kt20-4g-waterproof-smart-watch',
            'expected_price_behavior' => 'mention_price_in_lari',
            'expected_stock_behavior' => 'mention_stock',
            'reviewer_notes' => 'Should recommend a suitable watch for a child budget.',
            'tags' => 'budget, child',
            'is_active' => '1',
            'search' => 'budget',
            'status' => 'active',
            'tag' => 'child',
            'page' => 2,
        ]);

        $createResponse->assertRedirect(route('admin.chatbot-lab.cases.index', [
            'search' => 'budget',
            'status' => 'active',
            'tag' => 'child',
            'page' => 2,
        ]));

        $trainingCase = ChatbotTrainingCase::query()->firstOrFail();

        $this->assertSame('Budget recommendation case', $trainingCase->title);
        $this->assertSame(['ბავშვისთვის მინდა', 'GPS უნდა ჰქონდეს'], $trainingCase->conversation_context_json);
        $this->assertSame(['GPS', 'ლარი'], $trainingCase->expected_keywords_json);
        $this->assertSame(['budget', 'child'], $trainingCase->tags_json);
        $this->assertTrue($trainingCase->is_active);

        $updateResponse = $this->actingAs($this->admin)->patch(route('admin.chatbot-lab.cases.update', $trainingCase), [
            'title' => 'Updated budget case',
            'prompt' => '250 ლარამდე რას მირჩევ?',
            'conversation_context' => '',
            'expected_intent' => 'price_query',
            'expected_keywords' => 'ლარი',
            'expected_product_slugs' => '',
            'expected_price_behavior' => 'exact_price:250',
            'expected_stock_behavior' => '',
            'reviewer_notes' => 'Updated expectation.',
            'tags' => 'updated',
            'source' => 'manual',
            'search' => 'budget',
            'status' => 'active',
            'tag' => 'child',
            'page' => 2,
        ]);

        $updateResponse->assertRedirect(route('admin.chatbot-lab.cases.index', [
            'search' => 'budget',
            'status' => 'active',
            'tag' => 'child',
            'page' => 2,
        ]));

        $trainingCase->refresh();

        $this->assertSame('Updated budget case', $trainingCase->title);
        $this->assertSame('price_query', $trainingCase->expected_intent);
        $this->assertSame(['updated'], $trainingCase->tags_json);
        $this->assertFalse($trainingCase->is_active);

        $deleteResponse = $this->actingAs($this->admin)->delete(route('admin.chatbot-lab.cases.destroy', $trainingCase), [
            'search' => 'budget',
            'status' => 'active',
            'tag' => 'child',
            'page' => 2,
        ]);

        $deleteResponse->assertRedirect(route('admin.chatbot-lab.cases.index', [
            'search' => 'budget',
            'status' => 'active',
            'tag' => 'child',
            'page' => 2,
        ]));
        $this->assertDatabaseMissing('chatbot_training_cases', [
            'id' => $trainingCase->id,
        ]);
    }

    public function testCasesPageRendersBootstrapPaginationAndKeepsFilters(): void
    {
        foreach (range(1, 13) as $index) {
            ChatbotTrainingCase::query()->create([
                'title' => 'ქეისი ' . $index,
                'prompt' => 'Prompt ' . $index,
                'conversation_context_json' => [],
                'expected_intent' => 'general',
                'expected_keywords_json' => ['keyword'],
                'expected_product_slugs_json' => [],
                'expected_price_behavior' => null,
                'expected_stock_behavior' => null,
                'reviewer_notes' => 'note',
                'tags_json' => ['budget'],
                'is_active' => true,
                'source' => 'manual',
                'created_by' => $this->admin->id,
            ]);
        }

        $response = $this->actingAs($this->admin)->get(route('admin.chatbot-lab.cases.index', [
            'search' => 'ქეისი',
            'status' => 'active',
            'tag' => 'budget',
            'page' => 2,
        ]));

        $response->assertOk();
        $response->assertSee('pagination', false);
        $response->assertSee('?search=%E1%83%A5%E1%83%94%E1%83%98%E1%83%A1%E1%83%98&amp;status=active&amp;tag=budget&amp;page=1', false);
    }

    public function testCasesPageShowsHealthDiagnosticsForWeakAndDuplicateCases(): void
    {
        ChatbotTrainingCase::query()->create([
            'title' => 'Weak case',
            'prompt' => 'Help me',
            'conversation_context_json' => [],
            'expected_intent' => null,
            'expected_keywords_json' => [],
            'expected_product_slugs_json' => [],
            'expected_price_behavior' => null,
            'expected_stock_behavior' => null,
            'reviewer_notes' => null,
            'tags_json' => ['weak'],
            'is_active' => true,
            'source' => 'manual',
            'created_by' => $this->admin->id,
        ]);

        ChatbotTrainingCase::query()->create([
            'title' => 'Duplicate 1',
            'prompt' => 'ზუსტი ფასი მითხარი',
            'conversation_context_json' => [],
            'expected_intent' => 'price_query',
            'expected_keywords_json' => ['ფასი'],
            'expected_product_slugs_json' => [],
            'expected_price_behavior' => 'mention_price_in_lari',
            'expected_stock_behavior' => null,
            'reviewer_notes' => 'Price check.',
            'tags_json' => ['price'],
            'is_active' => true,
            'source' => 'manual',
            'created_by' => $this->admin->id,
        ]);

        ChatbotTrainingCase::query()->create([
            'title' => 'Duplicate 2',
            'prompt' => 'ზუსტი ფასი მითხარი',
            'conversation_context_json' => [],
            'expected_intent' => 'price_query',
            'expected_keywords_json' => ['ფასი'],
            'expected_product_slugs_json' => [],
            'expected_price_behavior' => 'mention_price_in_lari',
            'expected_stock_behavior' => null,
            'reviewer_notes' => 'Same prompt.',
            'tags_json' => ['price'],
            'is_active' => true,
            'source' => 'manual',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.chatbot-lab.cases.index'));

        $response->assertOk();
        $response->assertSee('ქეისების მოკლე შეჯამება');
        $response->assertSee('No expected intent, keywords, product slugs, price behavior, or stock behavior is configured.');
        $response->assertSee('Possible duplicate prompt detected');
    }

    public function testCasesPageFlagsNearDuplicatePromptsAfterNormalization(): void
    {
        ChatbotTrainingCase::query()->create([
            'title' => 'Normalized duplicate 1',
            'prompt' => 'Q21 საათის ზუსტი ფასი მითხარი',
            'conversation_context_json' => [],
            'expected_intent' => 'price_query',
            'expected_keywords_json' => ['ფასი'],
            'expected_product_slugs_json' => [],
            'expected_price_behavior' => 'mention_price_in_lari',
            'expected_stock_behavior' => null,
            'reviewer_notes' => 'Price lookup',
            'tags_json' => ['price'],
            'is_active' => true,
            'source' => 'manual',
            'created_by' => $this->admin->id,
        ]);

        ChatbotTrainingCase::query()->create([
            'title' => 'Normalized duplicate 2',
            'prompt' => 'q21 საათის, ზუსტი ფასი მითხარი!',
            'conversation_context_json' => [],
            'expected_intent' => 'price_query',
            'expected_keywords_json' => ['ფასი'],
            'expected_product_slugs_json' => [],
            'expected_price_behavior' => 'mention_price_in_lari',
            'expected_stock_behavior' => null,
            'reviewer_notes' => 'Same prompt with punctuation',
            'tags_json' => ['price'],
            'is_active' => true,
            'source' => 'manual',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.chatbot-lab.cases.index'));

        $response->assertOk();
        $response->assertSee('Possible duplicate prompt detected');
    }

    public function testAdminCanPreviewCaseDiagnosticsBeforeSaving(): void
    {
        $existingCase = ChatbotTrainingCase::query()->create([
            'title' => 'Existing case',
            'prompt' => 'Q21 საათის ზუსტი ფასი მითხარი',
            'conversation_context_json' => [],
            'expected_intent' => 'price_query',
            'expected_keywords_json' => ['ფასი'],
            'expected_product_slugs_json' => [],
            'expected_price_behavior' => 'mention_price_in_lari',
            'expected_stock_behavior' => null,
            'reviewer_notes' => 'Existing note',
            'tags_json' => ['price'],
            'is_active' => true,
            'source' => 'manual',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.chatbot-lab.cases.preview-diagnostics'), [
                'title' => 'Draft case',
                'prompt' => 'q21 საათის, ზუსტი ფასი მითხარი!',
                'expected_intent' => 'price_query',
                'expected_keywords' => '',
                'expected_product_slugs' => '',
                'expected_price_behavior' => '',
                'expected_stock_behavior' => '',
                'reviewer_notes' => '',
            ]);

        $response->assertOk();
        $response->assertJsonPath('diagnostics.health', 'warning');
        $response->assertJsonPath('diagnostics.duplicate_case_ids.0', $existingCase->id);
    }

    public function testExistingCasePreviewIgnoresItselfDuringDuplicateCheck(): void
    {
        $trainingCase = ChatbotTrainingCase::query()->create([
            'title' => 'Existing case',
            'prompt' => 'ზუსტი ფასი მითხარი',
            'conversation_context_json' => [],
            'expected_intent' => 'price_query',
            'expected_keywords_json' => ['ფასი'],
            'expected_product_slugs_json' => [],
            'expected_price_behavior' => 'mention_price_in_lari',
            'expected_stock_behavior' => null,
            'reviewer_notes' => 'Note',
            'tags_json' => ['price'],
            'is_active' => true,
            'source' => 'manual',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.chatbot-lab.cases.preview-diagnostics-existing', $trainingCase), [
                'title' => 'Existing case',
                'prompt' => 'ზუსტი ფასი მითხარი',
                'expected_intent' => 'price_query',
                'expected_keywords' => 'ფასი',
                'expected_product_slugs' => '',
                'expected_price_behavior' => 'mention_price_in_lari',
                'expected_stock_behavior' => '',
                'reviewer_notes' => 'Note',
            ]);

        $response->assertOk();
        $response->assertJsonPath('diagnostics.duplicate_case_ids', []);
        $response->assertJsonPath('diagnostics.health', 'healthy');
    }

    public function testAdminCanOpenLabAndRunManualTest(): void
    {
        $indexResponse = $this->actingAs($this->admin)->get(route('admin.chatbot-lab.index'));

        $indexResponse->assertOk();
        $indexResponse->assertSee('ჩატბოტ ლაბი');
        $indexResponse->assertSee('ხელით ტესტი');

        $labServiceMock = Mockery::mock(ChatbotLabService::class);
        $labServiceMock->shouldReceive('runManualTest')
            ->once()
            ->with('200 ლარამდე რას მირჩევ?', 'ბავშვისთვის მინდა', null, false)
            ->andReturn([
                'prompt' => '200 ლარამდე რას მირჩევ?',
                'normalized_prompt' => '200 ლარამდე რას მირჩევ?',
                'previous_prompts' => ['ბავშვისთვის მინდა'],
                'transcript' => [
                    [
                        'prompt' => 'ბავშვისთვის მინდა',
                        'response' => 'გასაგებია, ბავშვისთვის ვარიანტებს შევარჩევ.',
                    ],
                ],
                'response' => 'გირჩევთ GPS საათს 200 ლარამდე.',
                'debug' => [
                    'intent' => 'recommendation',
                    'intent_confidence' => 0.91,
                    'intent_fallback' => false,
                    'standalone_query' => '200 ლარამდე ბავშვის GPS საათი',
                    'validation_passed' => true,
                    'validation_violations' => [],
                    'guard_allowed' => true,
                    'guard_reason' => null,
                    'georgian_passed' => true,
                    'response_time_ms' => 42,
                    'products_found' => 1,
                    'products' => [
                        [
                            'name' => 'Wonlex KT20',
                            'price' => 199,
                            'sale_price' => null,
                            'is_in_stock' => true,
                            'url' => '/products/wonlex-kt20',
                        ],
                    ],
                    'fallback_reason' => null,
                ],
                'raw_pipeline' => ['response' => 'გირჩევთ GPS საათს 200 ლარამდე.'],
                'session' => [
                    'conversation_id' => 501,
                    'turn_count' => 1,
                    'persistent' => false,
                ],
            ]);
        $labServiceMock->shouldReceive('getSessionState')
            ->never();

        $this->app->instance(ChatbotLabService::class, $labServiceMock);

        $manualResponse = $this->actingAs($this->admin)->post(route('admin.chatbot-lab.manual.run'), [
            'prompt' => '200 ლარამდე რას მირჩევ?',
            'previous_prompts' => 'ბავშვისთვის მინდა',
        ]);

        $manualResponse->assertOk();
        $manualResponse->assertSee('საბოლოო პასუხი');
        $manualResponse->assertSee('გირჩევთ GPS საათს 200 ლარამდე.');
        $manualResponse->assertSee('Wonlex KT20');
        $manualResponse->assertSee('ერთჯერადი გაშვება');
        $manualResponse->assertSee('მთავარი სიგნალი');
        $manualResponse->assertSee('მნიშვნელოვანი პრობლემა არ დაფიქსირდა');
        $manualResponse->assertSee('იგივე კითხვის ხელახალი გაშვება');
        $manualResponse->assertSee('შეზღუდვებით ხელახალი გაშვება');
    }

    public function testAdminCanRetryManualResultWithConstraints(): void
    {
        $retryContext = [
            'intent' => 'recommendation',
            'expected_summary' => 'Intent: recommendation',
            'validation_violations' => [
                ['type' => 'price', 'message' => 'Price must be grounded in product data.'],
            ],
            'fallback_reason' => 'validator_failed',
            'recommended_action' => 'Tighten grounding.',
        ];

        $labServiceMock = Mockery::mock(ChatbotLabService::class);
        $labServiceMock->shouldReceive('runRetriedManualTest')
            ->once()
            ->with('200 ლარამდე რას მირჩევ?', 'ბავშვისთვის მინდა', 'constrained', $retryContext, null, false)
            ->andReturn([
                'prompt' => '200 ლარამდე რას მირჩევ?',
                'normalized_prompt' => '200 ლარამდე რას მირჩევ?',
                'previous_prompts' => ['ბავშვისთვის მინდა'],
                'transcript' => [],
                'response' => 'გირჩევთ GPS საათს ზუსტი ფასით.',
                'debug' => [
                    'intent' => 'recommendation',
                    'intent_confidence' => 0.93,
                    'intent_fallback' => false,
                    'standalone_query' => '200 ლარამდე GPS საათი',
                    'validation_passed' => true,
                    'validation_violations' => [],
                    'guard_allowed' => true,
                    'guard_reason' => null,
                    'georgian_passed' => true,
                    'response_time_ms' => 30,
                    'products_found' => 1,
                    'products' => [],
                    'fallback_reason' => null,
                    'signal_group' => 'healthy',
                    'signal_label' => 'No major issue detected',
                    'signal_severity' => 'low',
                    'recommended_action' => 'Inspect prompt wording if needed.',
                ],
                'raw_pipeline' => ['response' => 'გირჩევთ GPS საათს ზუსტი ფასით.'],
                'retry' => [
                    'strategy' => 'constrained',
                    'strategy_label' => 'Retry with constraints',
                    'source_prompt' => '200 ლარამდე რას მირჩევ?',
                    'effective_prompt' => "200 ლარამდე რას მირჩევ?\n\nRetry guidance:\n- უპასუხე მხოლოდ ქართულად და არ დაამატო დაუდასტურებელი ფაქტები.",
                    'constraint_hints' => ['უპასუხე მხოლოდ ქართულად და არ დაამატო დაუდასტურებელი ფაქტები.'],
                ],
                'session' => [
                    'conversation_id' => 700,
                    'turn_count' => 1,
                    'persistent' => false,
                ],
            ]);
        $labServiceMock->shouldReceive('getSessionState')->never();

        $this->app->instance(ChatbotLabService::class, $labServiceMock);

        $response = $this->actingAs($this->admin)->post(route('admin.chatbot-lab.manual.retry'), [
            'prompt' => '200 ლარამდე რას მირჩევ?',
            'previous_prompts' => 'ბავშვისთვის მინდა',
            'retry_strategy' => 'constrained',
            'retry_context' => json_encode($retryContext, JSON_UNESCAPED_UNICODE),
        ]);

        $response->assertOk();
        $response->assertSee('ხელახალი გაშვება დასრულდა.');
        $response->assertSee('ხელახალი გაშვების კონტექსტი');
        $response->assertSee('Retry with constraints');
        $response->assertSee('გამოყენებული შეზღუდვები');
    }

    public function testAdminCanContinueAndResetManualLabSession(): void
    {
        $labServiceMock = Mockery::mock(ChatbotLabService::class);
        $labServiceMock->shouldReceive('runManualTest')
            ->once()
            ->with('შემდეგი კითხვა მაქვს', '', 42, true)
            ->andReturn([
                'prompt' => 'შემდეგი კითხვა მაქვს',
                'normalized_prompt' => 'შემდეგი კითხვა მაქვს',
                'previous_prompts' => [],
                'transcript' => [
                    [
                        'prompt' => 'პირველი კითხვა',
                        'response' => 'პირველი პასუხი',
                    ],
                ],
                'response' => 'ეს არის ახალი პასუხი.',
                'debug' => [
                    'intent' => 'general',
                    'intent_confidence' => 0.75,
                    'intent_fallback' => false,
                    'standalone_query' => 'შემდეგი კითხვა მაქვს',
                    'validation_passed' => true,
                    'validation_violations' => [],
                    'guard_allowed' => true,
                    'guard_reason' => null,
                    'georgian_passed' => true,
                    'response_time_ms' => 20,
                    'products_found' => 0,
                    'products' => [],
                    'fallback_reason' => null,
                ],
                'raw_pipeline' => ['response' => 'ეს არის ახალი პასუხი.'],
                'session' => [
                    'conversation_id' => 42,
                    'turn_count' => 2,
                    'persistent' => true,
                ],
            ]);
        $labServiceMock->shouldReceive('getSessionState')
            ->once()
            ->with(42)
            ->andReturn([
                'conversation_id' => 42,
                'status' => 'active',
                'turn_count' => 2,
                'last_active' => now()->toIso8601String(),
                'transcript' => [
                    [
                        'prompt' => 'პირველი კითხვა',
                        'response' => 'პირველი პასუხი',
                    ],
                ],
            ]);
        $labServiceMock->shouldReceive('resetSession')
            ->once()
            ->with(42);

        $this->app->instance(ChatbotLabService::class, $labServiceMock);

        $manualResponse = $this->actingAs($this->admin)
            ->withSession(['chatbot_lab.active_conversation_id' => 42])
            ->post(route('admin.chatbot-lab.manual.run'), [
                'prompt' => 'შემდეგი კითხვა მაქვს',
                'previous_prompts' => 'ეს უნდა დაიგნოროს',
                'continue_session' => '1',
            ]);

        $manualResponse->assertOk();
        $manualResponse->assertSee('მუდმივი სესია');
        $manualResponse->assertSee('სესიის ისტორია');
        $manualResponse->assertSessionHas('chatbot_lab.active_conversation_id', 42);
        $manualResponse->assertSee('მთავარი სიგნალი');
        $manualResponse->assertSee('მნიშვნელოვანი პრობლემა არ დაფიქსირდა');

        $resetResponse = $this->actingAs($this->admin)
            ->withSession(['chatbot_lab.active_conversation_id' => 42])
            ->post(route('admin.chatbot-lab.manual.reset'));

        $resetResponse->assertRedirect(route('admin.chatbot-lab.index'));
        $resetResponse->assertSessionHas('status', 'ლაბის მიმდინარე სესია გასუფთავდა.');
        $resetResponse->assertSessionMissing('chatbot_lab.active_conversation_id');
    }

    public function testLabRunServiceStartsSelectedCasesWithDeterministicScoring(): void
    {
        foreach (range(1, 2) as $index) {
            ChatbotTrainingCase::query()->create([
                'title' => 'Case ' . $index,
                'prompt' => 'Prompt ' . $index,
                'conversation_context_json' => [],
                'expected_intent' => 'general',
                'expected_keywords_json' => ['keyword'],
                'expected_product_slugs_json' => [],
                'expected_price_behavior' => null,
                'expected_stock_behavior' => null,
                'reviewer_notes' => 'note',
                'tags_json' => ['tag'],
                'is_active' => true,
                'source' => 'manual',
                'created_by' => $this->admin->id,
            ]);
        }

        $storedCases = ChatbotTrainingCase::query()->orderBy('id')->get();

        $runnerMock = Mockery::mock(TestRunnerService::class);
        $runnerMock->shouldReceive('executeCase')
            ->twice()
            ->withArgs(function (array $case, int $runId, array $options): bool {
                return str_starts_with((string) ($case['id'] ?? ''), 'training-case-')
                    && $runId > 0
                    && $options === ['use_llm_judge' => false];
            })
            ->andReturnUsing(function (array $case, int $runId): ChatbotTestResult {
                return ChatbotTestResult::query()->create([
                    'test_run_id' => $runId,
                    'case_id' => (string) ($case['id'] ?? ''),
                    'category' => (string) ($case['category'] ?? 'training_case'),
                    'question' => (string) ($case['question'] ?? ''),
                    'expected_summary' => 'Expected',
                    'actual_response' => 'Actual response',
                    'rag_context' => '',
                    'status' => 'pass',
                    'keyword_match' => true,
                    'price_match' => null,
                    'stock_match' => null,
                    'guardrail_passed' => true,
                    'georgian_qa_passed' => true,
                    'intent_match' => true,
                    'entity_match' => null,
                    'llm_notes' => 'LLM judge disabled for this run.',
                    'response_time_ms' => 10,
                    'created_at' => now(),
                ]);
            });

        $runnerMock->shouldReceive('finalizeRun')
            ->once()
            ->andReturnUsing(function (int $runId): void {
                ChatbotTestRun::findOrFail($runId)->update([
                    'status' => 'completed',
                    'total_cases' => 2,
                    'passed_cases' => 2,
                    'failed_cases' => 0,
                    'skipped_cases' => 0,
                    'accuracy_pct' => 100.00,
                    'guardrail_pass_rate' => 100.00,
                    'duration_seconds' => 0.25,
                    'completed_at' => now(),
                ]);
            });

        $this->app->instance(TestRunnerService::class, $runnerMock);

        $run = $this->app->make(ChatbotLabRunService::class)->startRun($storedCases->pluck('id')->all(), false);

        $this->assertSame('chatbot_lab', $run->triggered_by);
        $this->assertSame('completed', $run->status);
        $this->assertSame(['lab' => true, 'case_ids' => $storedCases->pluck('id')->map(fn ($id): string => (string) $id)->all(), 'use_llm_judge' => false], $run->filters);
        $this->assertDatabaseCount('chatbot_test_results', 2);
    }

    public function testAdminCanQueueRunFromLab(): void
    {
        Queue::fake();

        $trainingCase = ChatbotTrainingCase::query()->create([
            'title' => 'Queued case',
            'prompt' => 'Queued prompt',
            'conversation_context_json' => [],
            'expected_intent' => 'general',
            'expected_keywords_json' => ['keyword'],
            'expected_product_slugs_json' => [],
            'expected_price_behavior' => null,
            'expected_stock_behavior' => null,
            'reviewer_notes' => 'note',
            'tags_json' => ['tag'],
            'is_active' => true,
            'source' => 'manual',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.chatbot-lab.runs.start'), [
            'case_ids' => [$trainingCase->id],
        ]);

        $run = ChatbotTestRun::query()->latest('id')->firstOrFail();

        $response->assertRedirect(route('admin.chatbot-lab.runs.show', $run));
        $this->assertSame('pending', $run->status);
        $this->assertSame(['lab' => true, 'case_ids' => [(string) $trainingCase->id], 'use_llm_judge' => false], $run->filters);

        Queue::assertPushed(RunChatbotLabRunJob::class, function (RunChatbotLabRunJob $job) use ($run): bool {
            return $job->runId === $run->id;
        });
    }

    public function testRunStartIsBlockedWhenSelectedCasesHaveBlockingDiagnostics(): void
    {
        $trainingCase = ChatbotTrainingCase::query()->create([
            'title' => 'Weak queued case',
            'prompt' => 'Hi',
            'conversation_context_json' => [],
            'expected_intent' => null,
            'expected_keywords_json' => [],
            'expected_product_slugs_json' => [],
            'expected_price_behavior' => null,
            'expected_stock_behavior' => null,
            'reviewer_notes' => null,
            'tags_json' => ['weak'],
            'is_active' => true,
            'source' => 'manual',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.chatbot-lab.runs.start'), [
            'case_ids' => [$trainingCase->id],
        ]);

        $response->assertRedirect(route('admin.chatbot-lab.runs.index'));
        $response->assertSessionHas('warning');
        $this->assertDatabaseCount('chatbot_test_runs', 0);
    }

    public function testRunsPageShowsStructuredPreflightBreakdown(): void
    {
        ChatbotTrainingCase::query()->create([
            'title' => 'Blocking case',
            'prompt' => 'Hi',
            'conversation_context_json' => [],
            'expected_intent' => null,
            'expected_keywords_json' => [],
            'expected_product_slugs_json' => [],
            'expected_price_behavior' => null,
            'expected_stock_behavior' => null,
            'reviewer_notes' => null,
            'tags_json' => ['weak'],
            'is_active' => true,
            'source' => 'manual',
            'created_by' => $this->admin->id,
        ]);

        ChatbotTrainingCase::query()->create([
            'title' => 'Warning case',
            'prompt' => 'რეკომენდაცია მჭირდება ბავშვისთვის',
            'conversation_context_json' => [],
            'expected_intent' => 'recommendation',
            'expected_keywords_json' => [],
            'expected_product_slugs_json' => [],
            'expected_price_behavior' => null,
            'expected_stock_behavior' => null,
            'reviewer_notes' => null,
            'tags_json' => ['recommendation'],
            'is_active' => true,
            'source' => 'manual',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.chatbot-lab.runs.index'));

        $response->assertOk();
        $response->assertSee('Blocking cases:');
        $response->assertSee('Warning cases:');
        $response->assertSee('Top blocking issues:');
    }

    public function testRunsPageShowsQueueDriverWarningWhenSyncDriverIsActive(): void
    {
        config()->set('queue.default', 'sync');

        $run = ChatbotTestRun::query()->create([
            'status' => 'running',
            'triggered_by' => 'chatbot_lab',
            'filters' => ['lab' => true, 'case_ids' => ['training-case-4'], 'use_llm_judge' => false],
            'total_cases' => 1,
            'passed_cases' => 0,
            'failed_cases' => 0,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.chatbot-lab.runs.index'));

        $response->assertOk();
        $response->assertSeeText('Driver sync');
        $response->assertSeeText('execute inline and do not continue in the background');
        $response->assertSee(route('admin.chatbot-lab.runs.status', $run), false);
        $response->assertSee('data-run-card="1"', false);
    }

    public function testRunsPageShowsOperationalSnapshotMetrics(): void
    {
        $date = now()->toDateString();
        config()->set('chatbot-monitoring.thresholds.fallback_alert_rate', 20.0);
        config()->set('chatbot-monitoring.thresholds.slow_response_alert_rate', 15.0);
        config()->set('chatbot-monitoring.thresholds.provider_incident_alert_rate', 5.0);
        Cache::put("chatbot_quality:{$date}:widget_response_total", 10, now()->addMinutes(10));
        Cache::put("chatbot_quality:{$date}:widget_response_fallback_total", 3, now()->addMinutes(10));
        Cache::put("chatbot_quality:{$date}:widget_response_slow_total", 4, now()->addMinutes(10));
        Cache::put("chatbot_quality:{$date}:widget_response_fallback_provider_total", 2, now()->addMinutes(10));
        Cache::put("chatbot_quality:{$date}:widget_response_fallback_validator_total", 2, now()->addMinutes(10));
        Cache::put("chatbot_quality:{$date}:widget_response_regeneration_attempt_total", 3, now()->addMinutes(10));
        Cache::put("chatbot_quality:{$date}:widget_response_regeneration_success_total", 1, now()->addMinutes(10));
        Cache::put("chatbot_quality:{$date}:omnichannel_provider_incident_total", 2, now()->addMinutes(10));

        $run = ChatbotTestRun::query()->create([
            'status' => 'completed',
            'triggered_by' => 'chatbot_lab',
            'filters' => ['lab' => true, 'case_ids' => ['training-case-ops-1', 'training-case-ops-2'], 'use_llm_judge' => false],
            'total_cases' => 2,
            'passed_cases' => 1,
            'failed_cases' => 1,
            'duration_seconds' => 18,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now()->subMinutes(4),
        ]);

        ChatbotTestResult::query()->create([
            'test_run_id' => $run->id,
            'case_id' => 'training-case-ops-1',
            'category' => 'general',
            'question' => 'Prompt 1',
            'expected_summary' => 'Expected',
            'actual_response' => 'Actual 1',
            'rag_context' => 'Context',
            'status' => 'pass',
            'keyword_match' => true,
            'price_match' => null,
            'stock_match' => null,
            'guardrail_passed' => true,
            'georgian_qa_passed' => true,
            'intent_match' => true,
            'entity_match' => true,
            'response_time_ms' => 9200,
            'fallback_reason' => 'provider_unavailable',
            'regeneration_attempted' => true,
            'regeneration_succeeded' => false,
            'created_at' => now()->subMinutes(4),
        ]);

        ChatbotTestResult::query()->create([
            'test_run_id' => $run->id,
            'case_id' => 'training-case-ops-2',
            'category' => 'general',
            'question' => 'Prompt 2',
            'expected_summary' => 'Expected',
            'actual_response' => 'Actual 2',
            'rag_context' => 'Context',
            'status' => 'fail',
            'keyword_match' => false,
            'price_match' => null,
            'stock_match' => null,
            'guardrail_passed' => true,
            'georgian_qa_passed' => true,
            'intent_match' => false,
            'entity_match' => false,
            'response_time_ms' => 4000,
            'fallback_reason' => 'validator_retry_failed',
            'regeneration_attempted' => true,
            'regeneration_succeeded' => true,
            'created_at' => now()->subMinutes(4),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.chatbot-lab.runs.index'));

        $response->assertOk();
        $response->assertSee('Operational Snapshot');
        $response->assertSee('Average Response Time');
        $response->assertSee('Fallback Pressure');
        $response->assertSee('Run Duration By Case Count');
        $response->assertSee('Top Fallback Reasons');
        $response->assertSee('Alert Thresholds');
        $response->assertSee('Daily Quality Trend');
        $response->assertSee($date);
        $response->assertSee('provider_unavailable');
        $response->assertSee('Global provider fallback rate');
        $response->assertSee('Global provider incident rate');
        $response->assertSee('Provider incident alert');
        $response->assertSee('Monitoring alerts:');
        $response->assertSee('Global fallback rate is elevated.');
        $response->assertSee('Provider incident rate is elevated.');
    }

    public function testRunStartIsBlockedWhenDatabaseQueueIsMissingJobsTable(): void
    {
        config()->set('queue.default', 'database');

        $trainingCase = ChatbotTrainingCase::query()->create([
            'title' => 'Database queue case',
            'prompt' => 'Queued prompt',
            'conversation_context_json' => [],
            'expected_intent' => 'general',
            'expected_keywords_json' => ['keyword'],
            'expected_product_slugs_json' => [],
            'expected_price_behavior' => null,
            'expected_stock_behavior' => null,
            'reviewer_notes' => 'note',
            'tags_json' => ['tag'],
            'is_active' => true,
            'source' => 'manual',
            'created_by' => $this->admin->id,
        ]);

        \Illuminate\Support\Facades\Schema::drop('jobs');

        $response = $this->actingAs($this->admin)->post(route('admin.chatbot-lab.runs.start'), [
            'case_ids' => [$trainingCase->id],
        ]);

        $response->assertRedirect(route('admin.chatbot-lab.runs.index'));
        $response->assertSessionHas('warning');
        $this->assertDatabaseCount('chatbot_test_runs', 0);
    }

    public function testAdminCanSaveObservationAndPromoteLabRunResultToTrainingCase(): void
    {
        $run = ChatbotTestRun::query()->create([
            'status' => 'completed',
            'triggered_by' => 'chatbot_lab',
            'filters' => ['lab' => true, 'case_ids' => ['training-case-1'], 'use_llm_judge' => false],
            'total_cases' => 1,
            'passed_cases' => 0,
            'failed_cases' => 1,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $result = ChatbotTestResult::query()->create([
            'test_run_id' => $run->id,
            'case_id' => 'training-case-1',
            'category' => 'price_query',
            'question' => 'Q21 საათი რა ღირს?',
            'expected_summary' => 'Intent: price_query',
            'actual_response' => 'ფასი არის 199 ლარი.',
            'rag_context' => 'Product context',
            'intent_json' => [
                'intent' => 'price_query',
                'entities' => [
                    'product_slug_hint' => 'children-smartwatch',
                ],
            ],
            'intent_type' => 'price_query',
            'status' => 'fail',
            'keyword_match' => false,
            'price_match' => null,
            'stock_match' => null,
            'guardrail_passed' => true,
            'georgian_qa_passed' => true,
            'intent_match' => true,
            'entity_match' => true,
            'llm_notes' => 'Needs a clearer product answer.',
            'response_time_ms' => 15,
            'created_at' => now(),
        ]);

        $observationResponse = $this->actingAs($this->admin)->post(route('admin.chatbot-lab.results.observation', $result), [
            'observation' => 'Answer should mention exact product and avoid ambiguity.',
            'action' => 'resolve',
        ]);

        $observationResponse->assertRedirect();

        $result->refresh();
        $this->assertSame('Answer should mention exact product and avoid ambiguity.', $result->admin_feedback);
        $this->assertSame('done', $result->retrain_status);

        $promoteResponse = $this->actingAs($this->admin)->post(route('admin.chatbot-lab.results.promote', $result));

        $promoteResponse->assertRedirect();

        $this->assertDatabaseHas('chatbot_training_cases', [
            'source' => 'lab_run_result',
            'source_reference' => (string) $result->id,
            'expected_intent' => 'price_query',
            'created_by' => $this->admin->id,
        ]);
    }

    public function testAdminCanRerunEvaluationResultInManualLab(): void
    {
        $run = ChatbotTestRun::query()->create([
            'status' => 'completed',
            'triggered_by' => 'chatbot_lab',
            'filters' => ['lab' => true, 'case_ids' => ['training-case-22'], 'use_llm_judge' => false],
            'total_cases' => 1,
            'passed_cases' => 0,
            'failed_cases' => 1,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $result = ChatbotTestResult::query()->create([
            'test_run_id' => $run->id,
            'case_id' => 'training-case-22',
            'category' => 'price_query',
            'question' => 'Q21 საათი რა ღირს?',
            'expected_summary' => 'Intent: price_query',
            'actual_response' => 'ზუსტი ფასი არ ჩანს.',
            'rag_context' => 'Product context',
            'intent_json' => [
                'intent' => 'price_query',
                'entities' => [
                    'product_slug_hint' => 'q21',
                ],
            ],
            'intent_type' => 'price_query',
            'status' => 'fail',
            'keyword_match' => false,
            'price_match' => false,
            'stock_match' => null,
            'guardrail_passed' => true,
            'georgian_qa_passed' => true,
            'intent_match' => true,
            'entity_match' => false,
            'llm_notes' => 'Mention the exact product price.',
            'response_time_ms' => 18,
            'created_at' => now(),
        ]);

        $labServiceMock = Mockery::mock(ChatbotLabService::class);
        $labServiceMock->shouldReceive('runRetriedManualTest')
            ->once()
            ->withArgs(function (string $prompt, string $previousPrompts, string $strategy, array $context, ?int $conversationId, bool $persistent): bool {
                return $prompt === 'Q21 საათი რა ღირს?'
                    && $previousPrompts === ''
                    && $strategy === 'constrained'
                    && ($context['expected_summary'] ?? null) === 'Intent: price_query'
                    && ($context['intent'] ?? null) === 'price_query'
                    && ($context['price_match'] ?? null) === false
                    && ($context['entity_match'] ?? null) === false
                    && ($context['llm_notes'] ?? null) === 'Mention the exact product price.'
                    && $conversationId === null
                    && $persistent === false;
            })
            ->andReturn([
                'prompt' => 'Q21 საათი რა ღირს?',
                'normalized_prompt' => 'Q21 საათი რა ღირს?',
                'previous_prompts' => [],
                'transcript' => [],
                'response' => 'Q21 საათი ღირს 199 ლარი.',
                'debug' => [
                    'intent' => 'price_query',
                    'intent_confidence' => 0.94,
                    'intent_fallback' => false,
                    'standalone_query' => 'Q21 საათის ფასი',
                    'validation_passed' => true,
                    'validation_violations' => [],
                    'guard_allowed' => true,
                    'guard_reason' => null,
                    'georgian_passed' => true,
                    'response_time_ms' => 16,
                    'products_found' => 1,
                    'products' => [],
                    'fallback_reason' => null,
                    'signal_group' => 'healthy',
                    'signal_label' => 'No major issue detected',
                    'signal_severity' => 'low',
                    'recommended_action' => 'Inspect prompt wording if needed.',
                ],
                'raw_pipeline' => ['response' => 'Q21 საათი ღირს 199 ლარი.'],
                'retry' => [
                    'strategy' => 'constrained',
                    'strategy_label' => 'Retry with constraints',
                    'source_prompt' => 'Q21 საათი რა ღირს?',
                    'effective_prompt' => 'Q21 საათი რა ღირს?',
                    'constraint_hints' => ['თუ ფასს ახსენებ, დააფუძნე იგი მხოლოდ რეალურ, კონტექსტში არსებულ ფასზე.'],
                ],
                'session' => [
                    'conversation_id' => 900,
                    'turn_count' => 1,
                    'persistent' => false,
                ],
            ]);

        $this->app->instance(ChatbotLabService::class, $labServiceMock);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.chatbot-lab.results.rerun', $result), [
                'retry_strategy' => 'constrained',
            ]);

        $response->assertOk();
        $response->assertSee('შედეგი გადაიტანეს ხელახალი ტესტისთვის: #' . $result->id . '.');
        $response->assertSee('Q21 საათი ღირს 199 ლარი.');
        $response->assertSee('ხელახალი გაშვების კონტექსტი');
    }

    public function testAdminCanPromoteAndRerunEvaluationResult(): void
    {
        $run = ChatbotTestRun::query()->create([
            'status' => 'completed',
            'triggered_by' => 'chatbot_lab',
            'filters' => ['lab' => true, 'case_ids' => ['training-case-23'], 'use_llm_judge' => false],
            'total_cases' => 1,
            'passed_cases' => 0,
            'failed_cases' => 1,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $result = ChatbotTestResult::query()->create([
            'test_run_id' => $run->id,
            'case_id' => 'training-case-23',
            'category' => 'price_query',
            'question' => 'Q23 საათი რა ღირს?',
            'expected_summary' => 'Intent: price_query',
            'actual_response' => 'დაზუსტება სჭირდება.',
            'rag_context' => 'Product context',
            'intent_json' => [
                'intent' => 'price_query',
                'entities' => [
                    'product_slug_hint' => 'q23-watch',
                ],
            ],
            'intent_type' => 'price_query',
            'status' => 'fail',
            'keyword_match' => false,
            'price_match' => false,
            'stock_match' => null,
            'guardrail_passed' => true,
            'georgian_qa_passed' => true,
            'intent_match' => true,
            'entity_match' => true,
            'llm_notes' => 'Use the exact grounded price.',
            'response_time_ms' => 19,
            'created_at' => now(),
        ]);

        $labServiceMock = Mockery::mock(ChatbotLabService::class);
        $labServiceMock->shouldReceive('runRetriedManualTest')
            ->once()
            ->withArgs(function (string $prompt, string $previousPrompts, string $strategy, array $context, ?int $conversationId, bool $persistent): bool {
                return $prompt === 'Q23 საათი რა ღირს?'
                    && $previousPrompts === ''
                    && $strategy === 'constrained'
                    && ($context['intent'] ?? null) === 'price_query'
                    && (($context['entities']['product_slug_hint'] ?? null) === 'q23-watch')
                    && $conversationId === null
                    && $persistent === false;
            })
            ->andReturn([
                'prompt' => 'Q23 საათი რა ღირს?',
                'normalized_prompt' => 'Q23 საათი რა ღირს?',
                'previous_prompts' => [],
                'transcript' => [],
                'response' => 'Q23 საათი ღირს 249 ლარი.',
                'debug' => [
                    'intent' => 'price_query',
                    'intent_confidence' => 0.96,
                    'intent_fallback' => false,
                    'standalone_query' => 'Q23 საათის ფასი',
                    'validation_passed' => true,
                    'validation_violations' => [],
                    'guard_allowed' => true,
                    'guard_reason' => null,
                    'georgian_passed' => true,
                    'response_time_ms' => 17,
                    'products_found' => 1,
                    'products' => [],
                    'fallback_reason' => null,
                    'signal_group' => 'healthy',
                    'signal_label' => 'No major issue detected',
                    'signal_severity' => 'low',
                    'recommended_action' => 'Inspect prompt wording if needed.',
                ],
                'raw_pipeline' => ['response' => 'Q23 საათი ღირს 249 ლარი.'],
                'retry' => [
                    'strategy' => 'constrained',
                    'strategy_label' => 'Retry with constraints',
                    'source_prompt' => 'Q23 საათი რა ღირს?',
                    'effective_prompt' => 'Q23 საათი რა ღირს?',
                    'constraint_hints' => ['თუ ფასს ახსენებ, დააფუძნე იგი მხოლოდ რეალურ, კონტექსტში არსებულ ფასზე.'],
                ],
                'session' => [
                    'conversation_id' => 901,
                    'turn_count' => 1,
                    'persistent' => false,
                ],
            ]);

        $this->app->instance(ChatbotLabService::class, $labServiceMock);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.chatbot-lab.results.promote-rerun', $result), [
                'retry_strategy' => 'constrained',
            ]);

        $createdTrainingCase = ChatbotTrainingCase::query()
            ->where('source', 'lab_run_result')
            ->where('source_reference', (string) $result->id)
            ->firstOrFail();

        $response->assertOk();
        $response->assertSee('ქეისი მზადაა: #' . $createdTrainingCase->id . '. შედეგი ჩაიტვირთა ხელახალი ტესტისთვის: #' . $result->id . '.');
        $response->assertSee('Q23 საათი ღირს 249 ლარი.');
        $response->assertSee('გადაყვანილი ქეისი:');

        $this->assertDatabaseHas('chatbot_training_cases', [
            'source' => 'lab_run_result',
            'source_reference' => (string) $result->id,
            'expected_intent' => 'price_query',
            'created_by' => $this->admin->id,
        ]);
    }

    public function testNonAdminIsRedirectedAwayFromChatbotLabRoutes(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $trainingCase = ChatbotTrainingCase::query()->create([
            'title' => 'Protected case',
            'prompt' => 'ზუსტი ფასი მითხარი',
            'conversation_context_json' => [],
            'expected_intent' => 'price_query',
            'expected_keywords_json' => ['ფასი'],
            'expected_product_slugs_json' => [],
            'expected_price_behavior' => 'mention_price_in_lari',
            'expected_stock_behavior' => null,
            'reviewer_notes' => 'Needs admin access',
            'tags_json' => ['protected'],
            'is_active' => true,
            'source' => 'manual',
            'created_by' => $this->admin->id,
        ]);

        $run = ChatbotTestRun::query()->create([
            'status' => 'completed',
            'triggered_by' => 'chatbot_lab',
            'filters' => ['lab' => true, 'case_ids' => [(string) $trainingCase->id], 'use_llm_judge' => false],
            'total_cases' => 1,
            'passed_cases' => 1,
            'failed_cases' => 0,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $result = ChatbotTestResult::query()->create([
            'test_run_id' => $run->id,
            'case_id' => (string) $trainingCase->id,
            'category' => 'price_query',
            'question' => 'ზუსტი ფასი მითხარი',
            'expected_summary' => 'Intent: price_query',
            'actual_response' => '79 ლარი',
            'rag_context' => 'Product context',
            'status' => 'pass',
            'keyword_match' => true,
            'price_match' => true,
            'stock_match' => null,
            'guardrail_passed' => true,
            'georgian_qa_passed' => true,
            'intent_match' => true,
            'entity_match' => true,
            'response_time_ms' => 10,
            'created_at' => now(),
        ]);

        $casePayload = [
            'title' => 'Blocked create',
            'prompt' => '200 ლარამდე რას მირჩევ?',
            'conversation_context' => '',
            'expected_intent' => 'recommendation',
            'expected_keywords' => 'GPS',
            'expected_product_slugs' => '',
            'expected_price_behavior' => '',
            'expected_stock_behavior' => '',
            'reviewer_notes' => 'Blocked',
            'tags' => 'blocked',
            'is_active' => '1',
        ];

        $this->actingAs($user)
            ->get(route('admin.chatbot-lab.index'))
            ->assertRedirect(route('admin.login'));

        $this->actingAs($user)
            ->post(route('admin.chatbot-lab.manual.run'), [
                'prompt' => 'ტესტი',
                'previous_prompts' => '',
            ])
            ->assertRedirect(route('admin.login'));

        $this->actingAs($user)
            ->post(route('admin.chatbot-lab.manual.retry'), [
                'prompt' => 'ტესტი',
                'previous_prompts' => '',
                'retry_strategy' => 'same',
            ])
            ->assertRedirect(route('admin.login'));

        $this->actingAs($user)
            ->post(route('admin.chatbot-lab.manual.reset'))
            ->assertRedirect(route('admin.login'));

        $this->actingAs($user)
            ->get(route('admin.chatbot-lab.cases.index'))
            ->assertRedirect(route('admin.login'));

        $this->actingAs($user)
            ->post(route('admin.chatbot-lab.cases.preview-diagnostics'), $casePayload)
            ->assertRedirect(route('admin.login'));

        $this->actingAs($user)
            ->post(route('admin.chatbot-lab.cases.preview-diagnostics-existing', $trainingCase), $casePayload)
            ->assertRedirect(route('admin.login'));

        $this->actingAs($user)
            ->post(route('admin.chatbot-lab.cases.store'), $casePayload)
            ->assertRedirect(route('admin.login'));

        $this->actingAs($user)
            ->patch(route('admin.chatbot-lab.cases.update', $trainingCase), [
                'title' => 'Blocked update',
                'prompt' => 'განახლებული prompt',
                'conversation_context' => '',
                'expected_intent' => 'general',
                'expected_keywords' => '',
                'expected_product_slugs' => '',
                'expected_price_behavior' => '',
                'expected_stock_behavior' => '',
                'reviewer_notes' => 'Blocked update',
                'tags' => 'blocked',
            ])
            ->assertRedirect(route('admin.login'));

        $this->actingAs($user)
            ->delete(route('admin.chatbot-lab.cases.destroy', $trainingCase))
            ->assertRedirect(route('admin.login'));

        $this->actingAs($user)
            ->get(route('admin.chatbot-lab.runs.index'))
            ->assertRedirect(route('admin.login'));

        $this->actingAs($user)
            ->post(route('admin.chatbot-lab.runs.start'), [
                'case_ids' => [$trainingCase->id],
            ])
            ->assertRedirect(route('admin.login'));

        $this->actingAs($user)
            ->get(route('admin.chatbot-lab.runs.show', $run))
            ->assertRedirect(route('admin.login'));

        $this->actingAs($user)
            ->get(route('admin.chatbot-lab.runs.status', $run))
            ->assertRedirect(route('admin.login'));

        $this->actingAs($user)
            ->post(route('admin.chatbot-lab.runs.cancel', $run))
            ->assertRedirect(route('admin.login'));

        $this->actingAs($user)
            ->get(route('admin.chatbot-lab.runs.export', $run))
            ->assertRedirect(route('admin.login'));

        $this->actingAs($user)
            ->post(route('admin.chatbot-lab.results.observation', $result), [
                'observation' => 'Blocked observation',
                'action' => 'resolve',
            ])
            ->assertRedirect(route('admin.login'));

        $this->actingAs($user)
            ->post(route('admin.chatbot-lab.results.rerun', $result), [
                'retry_strategy' => 'constrained',
            ])
            ->assertRedirect(route('admin.login'));

        $this->actingAs($user)
            ->post(route('admin.chatbot-lab.results.promote-rerun', $result), [
                'retry_strategy' => 'constrained',
            ])
            ->assertRedirect(route('admin.login'));

        $this->actingAs($user)
            ->post(route('admin.chatbot-lab.results.promote', $result))
            ->assertRedirect(route('admin.login'));

        $trainingCase->refresh();
        $result->refresh();

        $this->assertSame('Protected case', $trainingCase->title);
        $this->assertSame('completed', $run->fresh()->status);
        $this->assertSame('pass', $result->status);
        $this->assertNull($result->admin_feedback);
        $this->assertSame('none', $result->retrain_status);
        $this->assertDatabaseMissing('chatbot_training_cases', [
            'title' => 'Blocked create',
        ]);
        $this->assertDatabaseMissing('chatbot_training_cases', [
            'title' => 'Blocked update',
        ]);
        $this->assertDatabaseMissing('chatbot_training_cases', [
            'source' => 'lab_run_result',
            'source_reference' => (string) $result->id,
        ]);
    }

    public function testAdminCanViewRunDetailPage(): void
    {
        $run = ChatbotTestRun::query()->create([
            'status' => 'completed',
            'triggered_by' => 'chatbot_lab',
            'filters' => ['lab' => true, 'case_ids' => ['training-case-9'], 'use_llm_judge' => false],
            'total_cases' => 1,
            'passed_cases' => 1,
            'failed_cases' => 0,
            'accuracy_pct' => 100,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        ChatbotTestResult::query()->create([
            'test_run_id' => $run->id,
            'case_id' => 'training-case-9',
            'category' => 'recommendation',
            'question' => '200 ლარამდე რას მირჩევ?',
            'expected_summary' => 'Intent: recommendation',
            'actual_response' => 'გირჩევთ GPS საათს 200 ლარამდე.',
            'rag_context' => 'Product context',
            'intent_json' => [
                'intent' => 'recommendation',
                'confidence' => 0.91,
                'entities' => [
                    'brand' => 'Wonlex',
                ],
            ],
            'intent_type' => 'recommendation',
            'status' => 'pass',
            'keyword_match' => true,
            'price_match' => null,
            'stock_match' => null,
            'guardrail_passed' => true,
            'georgian_qa_passed' => true,
            'intent_match' => true,
            'entity_match' => true,
            'llm_notes' => 'Looks good.',
            'response_time_ms' => 22,
            'fallback_reason' => 'validator_retry_failed',
            'regeneration_attempted' => true,
            'regeneration_succeeded' => true,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.chatbot-lab.runs.show', $run));

        $response->assertOk();
        $response->assertSee('Evaluation Run #' . $run->id);
        $response->assertSee('training-case-9');
        $response->assertSee('Reviewer Workflow');
        $response->assertSee('Export CSV');
        $response->assertSee('Run Progress');
        $response->assertSee('Run Health Snapshot');
        $response->assertSee('Average Response Time');
        $response->assertSee('validator_retry_failed');
        $response->assertSee('Actionable Signal');
        $response->assertSee('No major issue detected');
        $response->assertSee('Rerun Same Prompt');
        $response->assertSee('Rerun With Constraints');
        $response->assertSee('Promote And Rerun');
    }

    public function testAdminCanFetchRunStatusSnapshot(): void
    {
        $run = ChatbotTestRun::query()->create([
            'status' => 'running',
            'triggered_by' => 'chatbot_lab',
            'filters' => ['lab' => true, 'case_ids' => ['training-case-1', 'training-case-2', 'training-case-3'], 'use_llm_judge' => false],
            'total_cases' => 3,
            'passed_cases' => 0,
            'failed_cases' => 0,
            'skipped_cases' => 0,
            'started_at' => now(),
        ]);

        ChatbotTestResult::query()->create([
            'test_run_id' => $run->id,
            'case_id' => 'training-case-1',
            'category' => 'general',
            'question' => 'Prompt 1',
            'expected_summary' => 'Expected',
            'actual_response' => 'Actual',
            'rag_context' => '',
            'status' => 'pass',
            'keyword_match' => true,
            'price_match' => null,
            'stock_match' => null,
            'guardrail_passed' => true,
            'georgian_qa_passed' => true,
            'intent_match' => true,
            'entity_match' => true,
            'response_time_ms' => 11,
            'created_at' => now(),
        ]);

        ChatbotTestResult::query()->create([
            'test_run_id' => $run->id,
            'case_id' => 'training-case-2',
            'category' => 'general',
            'question' => 'Prompt 2',
            'expected_summary' => 'Expected',
            'actual_response' => 'Actual',
            'rag_context' => '',
            'status' => 'fail',
            'keyword_match' => false,
            'price_match' => null,
            'stock_match' => null,
            'guardrail_passed' => true,
            'georgian_qa_passed' => true,
            'intent_match' => false,
            'entity_match' => false,
            'response_time_ms' => 12,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.chatbot-lab.runs.status', $run));

        $response->assertOk();
        $response->assertJsonPath('run.status', 'running');
        $response->assertJsonPath('run.total_cases', 3);
        $response->assertJsonPath('run.processed_cases', 2);
        $response->assertJsonPath('run.remaining_cases', 1);
        $response->assertJsonPath('run.passed_cases', 1);
        $response->assertJsonPath('run.failed_cases', 1);
        $response->assertJsonPath('run.skipped_cases', 0);
        $response->assertJsonPath('run.is_terminal', false);
        $response->assertJsonPath('run.can_cancel', true);
    }

    public function testAdminCanCancelPendingRun(): void
    {
        $run = ChatbotTestRun::query()->create([
            'status' => 'pending',
            'triggered_by' => 'chatbot_lab',
            'filters' => ['lab' => true, 'case_ids' => ['training-case-10'], 'use_llm_judge' => false],
            'total_cases' => 1,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.chatbot-lab.runs.cancel', $run));

        $response->assertRedirect(route('admin.chatbot-lab.runs.show', $run));
        $response->assertSessionHas('status', 'Evaluation run cancelled.');

        $run->refresh();
        $this->assertSame('cancelled', $run->status);
        $this->assertSame('Run cancelled by admin.', $run->error_message);
        $this->assertNotNull($run->completed_at);
    }

    public function testAdminCannotCancelCompletedRun(): void
    {
        $run = ChatbotTestRun::query()->create([
            'status' => 'completed',
            'triggered_by' => 'chatbot_lab',
            'filters' => ['lab' => true, 'case_ids' => ['training-case-11'], 'use_llm_judge' => false],
            'total_cases' => 1,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.chatbot-lab.runs.cancel', $run));

        $response->assertRedirect(route('admin.chatbot-lab.runs.show', $run));
        $response->assertSessionHas('warning', 'This run is already finished and cannot be cancelled.');

        $run->refresh();
        $this->assertSame('completed', $run->status);
    }

    public function testQueuedExecutionStopsAfterRunIsCancelled(): void
    {
        $trainingCaseOne = ChatbotTrainingCase::query()->create([
            'title' => 'Case 1',
            'prompt' => 'Prompt 1',
            'conversation_context_json' => [],
            'expected_intent' => 'general',
            'expected_keywords_json' => ['keyword'],
            'expected_product_slugs_json' => [],
            'expected_price_behavior' => null,
            'expected_stock_behavior' => null,
            'reviewer_notes' => 'note',
            'tags_json' => ['tag'],
            'is_active' => true,
            'source' => 'manual',
            'created_by' => $this->admin->id,
        ]);

        $trainingCaseTwo = ChatbotTrainingCase::query()->create([
            'title' => 'Case 2',
            'prompt' => 'Prompt 2',
            'conversation_context_json' => [],
            'expected_intent' => 'general',
            'expected_keywords_json' => ['keyword'],
            'expected_product_slugs_json' => [],
            'expected_price_behavior' => null,
            'expected_stock_behavior' => null,
            'reviewer_notes' => 'note',
            'tags_json' => ['tag'],
            'is_active' => true,
            'source' => 'manual',
            'created_by' => $this->admin->id,
        ]);

        $run = ChatbotTestRun::query()->create([
            'status' => 'pending',
            'triggered_by' => 'chatbot_lab',
            'filters' => [
                'lab' => true,
                'case_ids' => [(string) $trainingCaseOne->id, (string) $trainingCaseTwo->id],
                'use_llm_judge' => false,
            ],
            'total_cases' => 2,
        ]);

        $runnerMock = Mockery::mock(TestRunnerService::class);
        $runnerMock->shouldReceive('executeCase')
            ->once()
            ->andReturnUsing(function (array $case, int $runId): ChatbotTestResult {
                $result = ChatbotTestResult::query()->create([
                    'test_run_id' => $runId,
                    'case_id' => (string) ($case['id'] ?? ''),
                    'category' => (string) ($case['category'] ?? 'training_case'),
                    'question' => (string) ($case['question'] ?? ''),
                    'expected_summary' => 'Expected',
                    'actual_response' => 'Actual response',
                    'rag_context' => '',
                    'status' => 'pass',
                    'keyword_match' => true,
                    'price_match' => null,
                    'stock_match' => null,
                    'guardrail_passed' => true,
                    'georgian_qa_passed' => true,
                    'intent_match' => true,
                    'entity_match' => true,
                    'response_time_ms' => 10,
                    'created_at' => now(),
                ]);

                ChatbotTestRun::findOrFail($runId)->update([
                    'status' => 'cancelled',
                    'completed_at' => now(),
                    'error_message' => 'Run cancelled by admin.',
                ]);

                return $result;
            });
        $runnerMock->shouldReceive('finalizeRun')->never();

        $this->app->instance(TestRunnerService::class, $runnerMock);

        $resultRun = $this->app->make(ChatbotLabRunService::class)->executeQueuedRun($run->id);

        $this->assertSame('cancelled', $resultRun->fresh()->status);
        $this->assertDatabaseCount('chatbot_test_results', 1);
    }

    public function testAdminCanExportRunCsv(): void
    {
        $run = ChatbotTestRun::query()->create([
            'status' => 'completed',
            'triggered_by' => 'chatbot_lab',
            'filters' => ['lab' => true, 'case_ids' => ['training-case-3'], 'use_llm_judge' => false],
            'total_cases' => 1,
            'passed_cases' => 1,
            'failed_cases' => 0,
            'accuracy_pct' => 100,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        ChatbotTestResult::query()->create([
            'test_run_id' => $run->id,
            'case_id' => 'training-case-3',
            'category' => 'price_query',
            'question' => 'MyTechnic Ultra რა ღირს?',
            'expected_summary' => 'Intent: price_query',
            'actual_response' => 'MyTechnic Ultra ღირს 79 ₾.',
            'rag_context' => 'Product context',
            'status' => 'pass',
            'keyword_match' => true,
            'price_match' => true,
            'stock_match' => null,
            'guardrail_passed' => true,
            'georgian_qa_passed' => true,
            'intent_match' => true,
            'entity_match' => true,
            'llm_overall' => 5.0,
            'response_time_ms' => 13,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.chatbot-lab.runs.export', $run));

        $response->assertOk();
        $response->assertDownload('chatbot-lab-run-' . $run->id . '.csv');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('case_id,category,question,status', $csv);
        $this->assertStringContainsString('training-case-3,price_query,', $csv);
        $this->assertStringContainsString('"MyTechnic Ultra რა ღირს?",pass,1,1,,1,1,5.0,13,', $csv);
    }
}
