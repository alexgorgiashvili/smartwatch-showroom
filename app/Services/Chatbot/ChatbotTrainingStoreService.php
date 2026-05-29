<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Str;

class ChatbotTrainingStoreService
{
    public function dashboardSnapshot(): array
    {
        $requests = $this->listGenerationRequests();
        $batches = $this->listBatches();
        $runs = $this->listRuns();
        $reviews = $this->listReviews();
        $historySummary = $this->questionHistorySummary();

        $pendingRequests = array_values(array_filter($requests, static fn (array $request): bool => ($request['status'] ?? 'pending') === 'pending'));
        $pendingReviews = array_values(array_filter($reviews, static fn (array $review): bool => ($review['status'] ?? 'pending') === 'pending'));
        $approvedReviews = array_values(array_filter($reviews, static fn (array $review): bool => ($review['status'] ?? '') === 'approved'));

        return [
            'request_count' => count($requests),
            'batch_count' => count($batches),
            'run_count' => count($runs),
            'review_count' => count($reviews),
            'pending_generation_request_count' => count($pendingRequests),
            'pending_review_count' => count($pendingReviews),
            'approved_review_count' => count($approvedReviews),
            'question_history_count' => (int) ($historySummary['total_questions'] ?? 0),
            'latest_request' => $requests[0] ?? null,
            'latest_batch' => $batches[0] ?? null,
            'latest_run' => $runs[0] ?? null,
            'latest_review' => $reviews[0] ?? null,
        ];
    }

    public function questionHistorySummary(): array
    {
        $history = $this->readQuestionHistory();

        return [
            'total_questions' => count($history['fingerprints'] ?? []),
            'updated_at' => $history['updated_at'] ?? null,
        ];
    }

    public function listGenerationRequests(): array
    {
        return $this->readJsonDirectory($this->directoryPath('generation-requests'));
    }

    public function listBatches(): array
    {
        return $this->readJsonDirectory($this->directoryPath('batches'));
    }

    public function listRuns(): array
    {
        return $this->readJsonDirectory($this->directoryPath('runs'));
    }

    public function listReviews(): array
    {
        return $this->readJsonDirectory($this->directoryPath('reviews'));
    }

    public function getGenerationRequest(string $requestId): ?array
    {
        return $this->readJsonFile($this->directoryPath('generation-requests') . DIRECTORY_SEPARATOR . $requestId . '.json');
    }

    public function getBatch(string $batchId): ?array
    {
        return $this->readJsonFile($this->directoryPath('batches') . DIRECTORY_SEPARATOR . $batchId . '.json');
    }

    public function getRun(string $runId): ?array
    {
        return $this->readJsonFile($this->directoryPath('runs') . DIRECTORY_SEPARATOR . $runId . '.json');
    }

    public function getReview(string $reviewId): ?array
    {
        return $this->readJsonFile($this->directoryPath('reviews') . DIRECTORY_SEPARATOR . $reviewId . '.json');
    }

    public function createGenerationRequest(string $name, int $count, array $categories = [], string $notes = ''): array
    {
        $requestId = 'request_' . now()->format('Ymd_His') . '_' . Str::lower(Str::random(6));
        $historySummary = $this->questionHistorySummary();

        $request = [
            'id' => $requestId,
            'name' => trim($name) !== '' ? trim($name) : 'Cascade Generation Request ' . now()->format('Y-m-d H:i'),
            'created_at' => now()->toIso8601String(),
            'status' => 'pending',
            'source' => 'cascade',
            'count_requested' => $count,
            'categories' => array_values($categories),
            'notes' => trim($notes),
            'history_summary' => $historySummary,
            'storage_paths' => [
                'request' => 'storage/app/chatbot-training/generation-requests/' . $requestId . '.json',
                'question_history' => 'storage/app/chatbot-training/question-history.json',
                'batch_directory' => 'storage/app/chatbot-training/batches',
            ],
            'imported_batch_id' => null,
            'cascade_prompt' => $this->buildCascadeGenerationPrompt($requestId),
        ];

        $this->writeJsonFile($this->directoryPath('generation-requests') . DIRECTORY_SEPARATOR . $requestId . '.json', $request);

        return $request;
    }

    public function createGeneratedBatch(string $name, int $count, array $categories = []): array
    {
        $questions = array_values(array_map(function (array $question): array {
            $question['fingerprint'] = $this->fingerprintQuestion((string) ($question['question'] ?? ''));
            $question['source'] = 'template';

            return $question;
        }, $this->generateQuestions($count, $categories)));
        $batchId = 'batch_' . now()->format('Ymd_His') . '_' . Str::lower(Str::random(6));

        $batch = [
            'id' => $batchId,
            'name' => trim($name) !== '' ? trim($name) : 'Training Batch ' . now()->format('Y-m-d H:i'),
            'created_at' => now()->toIso8601String(),
            'status' => 'generated',
            'source' => 'template',
            'categories' => array_values(array_unique(array_map(static fn (array $question): string => (string) $question['category'], $questions))),
            'question_count' => count($questions),
            'questions' => $questions,
        ];

        $this->writeJsonFile($this->directoryPath('batches') . DIRECTORY_SEPARATOR . $batchId . '.json', $batch);
        $this->registerQuestionsInHistory($batch, $questions);

        return $batch;
    }

    public function importGeneratedBatch(string $requestId, string $payload, string $batchName = ''): array
    {
        $request = $this->getGenerationRequest($requestId);

        if ($request === null) {
            throw new \RuntimeException('Generation request not found.');
        }

        $rawQuestions = $this->extractGeneratedQuestions($payload);
        $history = $this->readQuestionHistory();
        $knownFingerprints = is_array($history['fingerprints'] ?? null) ? $history['fingerprints'] : [];
        $importFingerprints = [];
        $questions = [];
        $skipped = [];

        foreach ($rawQuestions as $rawQuestion) {
            $normalized = $this->normalizeImportedQuestion($rawQuestion);
            if ($normalized === null) {
                continue;
            }

            $fingerprint = $this->fingerprintQuestion($normalized['question']);

            if (isset($knownFingerprints[$fingerprint]) || isset($importFingerprints[$fingerprint])) {
                $skipped[] = [
                    'question' => $normalized['question'],
                    'fingerprint' => $fingerprint,
                    'reason' => 'duplicate_question',
                ];
                continue;
            }

            $importFingerprints[$fingerprint] = true;
            $questions[] = [
                'id' => 'q_' . str_pad((string) (count($questions) + 1), 3, '0', STR_PAD_LEFT),
                'category' => $normalized['category'],
                'difficulty' => $normalized['difficulty'],
                'question' => $normalized['question'],
                'fingerprint' => $fingerprint,
                'source' => 'cascade',
                'generation_request_id' => $requestId,
            ];
        }

        if ($questions === []) {
            throw new \RuntimeException('ყველა კითხვა დუბლიკატი აღმოჩნდა ან payload ვერ დამუშავდა.');
        }

        $batchId = 'batch_' . now()->format('Ymd_His') . '_' . Str::lower(Str::random(6));
        $batch = [
            'id' => $batchId,
            'name' => trim($batchName) !== '' ? trim($batchName) : ((string) ($request['name'] ?? 'Cascade Batch')),
            'created_at' => now()->toIso8601String(),
            'status' => 'generated',
            'source' => 'cascade',
            'request_id' => $requestId,
            'categories' => array_values(array_unique(array_map(static fn (array $question): string => (string) $question['category'], $questions))),
            'question_count' => count($questions),
            'dedupe_summary' => [
                'imported_count' => count($questions),
                'skipped_count' => count($skipped),
            ],
            'skipped_duplicates' => $skipped,
            'questions' => $questions,
        ];

        $this->writeJsonFile($this->directoryPath('batches') . DIRECTORY_SEPARATOR . $batchId . '.json', $batch);
        $this->registerQuestionsInHistory($batch, $questions);

        $request['status'] = 'completed';
        $request['imported_batch_id'] = $batchId;
        $request['imported_at'] = now()->toIso8601String();
        $request['imported_count'] = count($questions);
        $request['skipped_count'] = count($skipped);
        $this->writeJsonFile($this->directoryPath('generation-requests') . DIRECTORY_SEPARATOR . $requestId . '.json', $request);

        return $batch;
    }

    public function saveRun(array $run): array
    {
        $this->writeJsonFile($this->directoryPath('runs') . DIRECTORY_SEPARATOR . $run['id'] . '.json', $run);

        return $run;
    }

    public function createQueuedRun(array $batch): array
    {
        $run = [
            'id' => 'run_' . now()->format('Ymd_His') . '_' . Str::lower(Str::random(6)),
            'batch_id' => (string) ($batch['id'] ?? ''),
            'batch_name' => (string) ($batch['name'] ?? ''),
            'created_at' => now()->toIso8601String(),
            'queued_at' => now()->toIso8601String(),
            'started_at' => null,
            'completed_at' => null,
            'status' => 'queued',
            'summary' => [
                'total_questions' => count($batch['questions'] ?? []),
                'processed_questions' => 0,
                'passed_count' => 0,
                'needs_review_count' => 0,
                'avg_response_time_ms' => 0,
                'total_duration_ms' => 0,
            ],
            'results' => [],
            'error' => null,
        ];

        return $this->saveRun($run);
    }

    public function markRunStarted(string $runId): array
    {
        $run = $this->getRun($runId);

        if ($run === null) {
            throw new \RuntimeException('Run not found.');
        }

        $run['status'] = 'running';
        $run['started_at'] = now()->toIso8601String();
        $run['error'] = null;

        return $this->saveRun($run);
    }

    public function completeRun(string $runId, array $executedRun): array
    {
        $run = $this->getRun($runId);

        if ($run === null) {
            throw new \RuntimeException('Run not found.');
        }

        $results = array_values($executedRun['results'] ?? []);
        $summary = $this->summarizeRunResults($results, (int) (($executedRun['summary']['total_duration_ms'] ?? 0)));

        $run['status'] = 'completed';
        $run['completed_at'] = now()->toIso8601String();
        $run['summary'] = $summary;
        $run['results'] = $results;
        $run['error'] = null;

        return $this->saveRun($run);
    }

    public function failRun(string $runId, string $message): array
    {
        $run = $this->getRun($runId);

        if ($run === null) {
            throw new \RuntimeException('Run not found.');
        }

        $run['status'] = 'failed';
        $run['completed_at'] = now()->toIso8601String();
        $run['error'] = $message;

        return $this->saveRun($run);
    }

    public function createReviewRequest(string $runId): array
    {
        $run = $this->getRun($runId);

        if ($run === null) {
            throw new \RuntimeException('Run not found.');
        }

        if (($run['status'] ?? 'queued') !== 'completed') {
            throw new \RuntimeException('Review request მხოლოდ დასრულებული run-ისთვის შეიძლება შეიქმნას.');
        }

        $candidates = array_values(array_filter(
            $run['results'] ?? [],
            static fn (array $result): bool => (bool) ($result['needs_review'] ?? false)
        ));

        if ($candidates === []) {
            $candidates = array_slice($run['results'] ?? [], 0, min(5, count($run['results'] ?? [])));
        }

        $reviewId = 'review_' . now()->format('Ymd_His') . '_' . Str::lower(Str::random(6));
        $review = [
            'id' => $reviewId,
            'run_id' => (string) ($run['id'] ?? ''),
            'batch_id' => (string) ($run['batch_id'] ?? ''),
            'created_at' => now()->toIso8601String(),
            'status' => 'pending',
            'analysis_status' => 'pending',
            'item_count' => count($candidates),
            'result_ids' => array_values(array_map(static fn (array $result): string => (string) ($result['question_id'] ?? ''), $candidates)),
            'storage_paths' => [
                'run' => 'storage/app/chatbot-training/runs/' . ($run['id'] ?? '') . '.json',
                'review' => 'storage/app/chatbot-training/reviews/' . $reviewId . '.json',
            ],
            'cascade_prompt' => $this->buildCascadeReviewPrompt($run, $reviewId),
            'analysis_summary' => null,
            'items' => array_values(array_map(function (array $result): array {
                return [
                    'question_id' => (string) ($result['question_id'] ?? ''),
                    'question' => (string) ($result['question'] ?? ''),
                    'response' => (string) ($result['response'] ?? ''),
                    'category' => (string) ($result['category'] ?? ''),
                    'trace_id' => (string) ($result['trace_id'] ?? ''),
                    'question_fingerprint' => (string) ($result['question_fingerprint'] ?? ''),
                    'fallback_reason' => $result['fallback_reason'] ?? null,
                    'validation_passed' => (bool) ($result['validation_passed'] ?? false),
                    'review_reasons' => array_values($result['review_reasons'] ?? []),
                    'analysis' => null,
                ];
            }, $candidates)),
        ];

        $this->writeJsonFile($this->directoryPath('reviews') . DIRECTORY_SEPARATOR . $reviewId . '.json', $review);

        return $review;
    }

    public function importReviewAnalysis(string $reviewId, string $payload): array
    {
        $review = $this->getReview($reviewId);

        if ($review === null) {
            throw new \RuntimeException('Review request not found.');
        }

        $analysis = $this->extractReviewAnalysis($payload);
        $analysisItems = is_array($analysis['items'] ?? null) ? $analysis['items'] : [];
        $indexedItems = [];

        foreach ($analysisItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $questionId = trim((string) ($item['question_id'] ?? ''));
            $questionText = trim((string) ($item['question'] ?? ''));

            if ($questionId !== '') {
                $indexedItems['id:' . $questionId] = $item;
            }

            if ($questionText !== '') {
                $indexedItems['question:' . $this->normalizeQuestionText($questionText)] = $item;
            }
        }

        $appliedCount = 0;
        $review['items'] = array_values(array_map(function (array $item) use ($indexedItems, &$appliedCount): array {
            $analysisItem = $indexedItems['id:' . ($item['question_id'] ?? '')]
                ?? $indexedItems['question:' . $this->normalizeQuestionText((string) ($item['question'] ?? ''))]
                ?? null;

            if ($analysisItem === null) {
                return $item;
            }

            $appliedCount++;
            $item['analysis'] = [
                'issue_summary' => (string) ($analysisItem['issue_summary'] ?? ''),
                'why_wrong' => (string) ($analysisItem['why_wrong'] ?? ''),
                'suggested_answer' => (string) ($analysisItem['suggested_answer'] ?? ''),
                'severity' => (string) ($analysisItem['severity'] ?? 'medium'),
                'training_action' => (string) ($analysisItem['training_action'] ?? ''),
                'should_train' => (bool) ($analysisItem['should_train'] ?? true),
            ];

            return $item;
        }, $review['items'] ?? []));

        $review['analysis_status'] = 'imported';
        $review['analysis_imported_at'] = now()->toIso8601String();
        $review['analysis_summary'] = [
            'summary' => (string) ($analysis['summary'] ?? ''),
            'applied_count' => $appliedCount,
            'reviewed_by' => 'cascade',
        ];

        $this->writeJsonFile($this->directoryPath('reviews') . DIRECTORY_SEPARATOR . $reviewId . '.json', $review);

        return $review;
    }

    public function updateReviewDecision(string $reviewId, string $decision): array
    {
        $review = $this->getReview($reviewId);

        if ($review === null) {
            throw new \RuntimeException('Review request not found.');
        }

        $review['status'] = $decision;
        $review['reviewed_at'] = now()->toIso8601String();

        $this->writeJsonFile($this->directoryPath('reviews') . DIRECTORY_SEPARATOR . $reviewId . '.json', $review);

        return $review;
    }

    private function buildCascadeReviewPrompt(array $run, string $reviewId): string
    {
        return implode("\n", [
            'შეამოწმე chatbot training run ' . ($run['id'] ?? '') . ' და review request ' . $reviewId . '.',
            'გამოიყენე ფაილები:',
            '- storage/app/chatbot-training/runs/' . ($run['id'] ?? '') . '.json',
            '- storage/app/chatbot-training/reviews/' . $reviewId . '.json',
            'დააბრუნე მხოლოდ სუფთა JSON, markdown/code fence-ის გარეშე, რომ პირდაპირ ჩავსვა admin UI-ში.',
            'მიპასუხე მხოლოდ JSON-ით.',
            'ფორმატი:',
            '{"summary":"...","items":[{"question_id":"...","issue_summary":"...","why_wrong":"...","suggested_answer":"...","severity":"low|medium|high","training_action":"...","should_train":true}]}',
            'შეაფასე ბოტის პასუხები პროფესიონალურად, გამოყავი შეცდომები, მოამზადე სწორი ვერსიები და დასადასტურებელი fix-ები.',
        ]);
    }

    private function buildCascadeGenerationPrompt(string $requestId): string
    {
        return implode("\n", [
            'წაიკითხე generation request: storage/app/chatbot-training/generation-requests/' . $requestId . '.json',
            'მომიმზადე ახალი უნიკალური training კითხვები smartwatch showroom chatbot-ისთვის.',
            'არ გაიმეორო უკვე გამოყენებული ან ძალიან მსგავსი კითხვები.',
            'კითხვები იყოს ბუნებრივი ქართულით და რეალისტური მომხმარებლის ენით.',
            'დააბრუნე მხოლოდ სუფთა JSON, markdown/code fence-ის გარეშე, რომ პირდაპირ მარტივად დავაკოპირო admin UI-ში.',
            'მიპასუხე მხოლოდ JSON-ით.',
            'ფორმატი:',
            '{"questions":[{"question":"...","category":"product_discovery|comparison|pricing_stock|delivery_warranty|vague_georgian","difficulty":"easy|medium|hard"}]}',
        ]);
    }

    private function summarizeRunResults(array $results, int $durationMs): array
    {
        $successful = array_values(array_filter($results, static fn (array $result): bool => ($result['status'] ?? '') === 'passed'));
        $needsReview = array_values(array_filter($results, static fn (array $result): bool => (bool) ($result['needs_review'] ?? false)));
        $latencies = array_values(array_filter(array_map(static fn (array $result): ?int => isset($result['duration_ms']) ? (int) $result['duration_ms'] : null, $results)));

        return [
            'total_questions' => count($results),
            'processed_questions' => count($results),
            'passed_count' => count($successful),
            'needs_review_count' => count($needsReview),
            'avg_response_time_ms' => $latencies !== [] ? (int) round(array_sum($latencies) / count($latencies)) : 0,
            'total_duration_ms' => $durationMs,
        ];
    }

    private function generateQuestions(int $count, array $categories = []): array
    {
        $templates = $this->questionTemplates();
        $selectedKeys = array_values(array_filter(
            $categories !== [] ? $categories : array_keys($templates),
            static fn (string $category): bool => array_key_exists($category, $templates)
        ));

        if ($selectedKeys === []) {
            $selectedKeys = array_keys($templates);
        }

        $questions = [];
        $questionTexts = [];
        $index = 0;

        while (count($questions) < $count) {
            $category = $selectedKeys[$index % count($selectedKeys)];
            $pool = $templates[$category]['questions'];
            $poolIndex = intdiv($index, count($selectedKeys)) % count($pool);
            $text = $pool[$poolIndex];

            if (!isset($questionTexts[$text])) {
                $questionTexts[$text] = true;
                $questions[] = [
                    'id' => 'q_' . str_pad((string) (count($questions) + 1), 3, '0', STR_PAD_LEFT),
                    'category' => $category,
                    'difficulty' => $templates[$category]['difficulty'],
                    'question' => $text,
                ];
            }

            $index++;

            if ($index > 500) {
                break;
            }
        }

        return array_slice($questions, 0, $count);
    }

    private function questionTemplates(): array
    {
        return [
            'product_discovery' => [
                'difficulty' => 'easy',
                'questions' => [
                    'ბავშვის საათი მინდა ზარებით და GPS-ით, რას მირჩევ?',
                    '7 წლის ბავშვისთვის რომელი მოდელი გაქვთ ყველაზე პრაქტიკული?',
                    'გოგოსთვის ვეძებ ლამაზ და გამძლე სმარტ საათს.',
                    'სკოლისთვის რომელი საათი ჯობია ბავშვს?',
                    'თუ უბრალოდ კარგი ყოველდღიური მოდელი მინდა, რას მირჩევ?',
                ],
            ],
            'comparison' => [
                'difficulty' => 'medium',
                'questions' => [
                    'ამ ორ მოდელს შორის რა განსხვავებაა და რომელი ჯობია?',
                    'GPS და ელემენტი რომ შევადაროთ, რომელი უფრო კარგი გამოდის?',
                    'ფასის და ფუნქციების მიხედვით რომელი სჯობს ბავშვებისთვის?',
                    'მოკლედ შემადარე ორი საუკეთესო ვარიანტი.',
                    'თუ კამერა მინდა და კარგი ხმა, რომელი ვარიანტი ჯობია?',
                ],
            ],
            'pricing_stock' => [
                'difficulty' => 'medium',
                'questions' => [
                    'ფასი რა აქვს და მარაგში თუა?',
                    'ყველაზე იაფიანი GPS საათი რომელი გაქვთ?',
                    '400 ლარამდე რა ვარიანტები გაქვთ მარაგში?',
                    'ფასდაკლებით რომელი მოდელები გაქვთ ახლა?',
                    'რომელი მოდელი ჯდება ჩემს ბიუჯეტში და თან მარაგშიც არის?',
                ],
            ],
            'delivery_warranty' => [
                'difficulty' => 'easy',
                'questions' => [
                    'მიწოდება რამდენ ხანშია თბილისში?',
                    'გარანტია რამდენი აქვს და როგორ მუშაობს?',
                    'რეგიონებში თუ აგზავნით და რა პირობებით?',
                    'თუ გაფუჭდა, რას ვაკეთებ?',
                    'ონლაინ თუ შევუკვეთე, რა ვადაში მივიღებ?',
                ],
            ],
            'vague_georgian' => [
                'difficulty' => 'hard',
                'questions' => [
                    'ბავშვისთვის საათი მინდა რა, ნორმალური რომ იყოს და არ გაფუჭდეს მალე',
                    'ისეთი მინდა ლოკაცია ქონდეს და დარეკვაც შეძლოს',
                    'რამე კარგი მირჩიე რა 10 წლისთვის',
                    'ძალიან ძვირი არ მინდა მარა კარგი იყოს',
                    'რომელი ჯობია საერთოდ თუ ვერ ვერკვევი?',
                ],
            ],
        ];
    }

    private function extractGeneratedQuestions(string $payload): array
    {
        $decoded = json_decode(trim($payload), true);

        if (!is_array($decoded)) {
            throw new \RuntimeException('Generated questions JSON ვერ დაიპარსა.');
        }

        if (array_is_list($decoded)) {
            return $decoded;
        }

        if (is_array($decoded['questions'] ?? null)) {
            return $decoded['questions'];
        }

        throw new \RuntimeException('Generated questions JSON-ში `questions` მასივი ვერ მოიძებნა.');
    }

    private function extractReviewAnalysis(string $payload): array
    {
        $decoded = json_decode(trim($payload), true);

        if (!is_array($decoded)) {
            throw new \RuntimeException('Review analysis JSON ვერ დაიპარსა.');
        }

        if (array_is_list($decoded)) {
            return [
                'summary' => '',
                'items' => $decoded,
            ];
        }

        return $decoded;
    }

    private function normalizeImportedQuestion(mixed $rawQuestion): ?array
    {
        if (is_string($rawQuestion)) {
            $question = trim($rawQuestion);

            if ($question === '') {
                return null;
            }

            return [
                'question' => $question,
                'category' => 'cascade_generated',
                'difficulty' => 'medium',
            ];
        }

        if (!is_array($rawQuestion)) {
            return null;
        }

        $question = trim((string) ($rawQuestion['question'] ?? $rawQuestion['text'] ?? $rawQuestion['prompt'] ?? ''));

        if ($question === '') {
            return null;
        }

        return [
            'question' => $question,
            'category' => trim((string) ($rawQuestion['category'] ?? 'cascade_generated')) ?: 'cascade_generated',
            'difficulty' => trim((string) ($rawQuestion['difficulty'] ?? 'medium')) ?: 'medium',
        ];
    }

    private function registerQuestionsInHistory(array $batch, array $questions): void
    {
        $history = $this->readQuestionHistory();
        $history['fingerprints'] = is_array($history['fingerprints'] ?? null) ? $history['fingerprints'] : [];

        foreach ($questions as $question) {
            $fingerprint = (string) ($question['fingerprint'] ?? '');

            if ($fingerprint === '') {
                continue;
            }

            $history['fingerprints'][$fingerprint] = [
                'question' => (string) ($question['question'] ?? ''),
                'normalized_question' => $this->normalizeQuestionText((string) ($question['question'] ?? '')),
                'category' => (string) ($question['category'] ?? ''),
                'difficulty' => (string) ($question['difficulty'] ?? ''),
                'first_seen_at' => $history['fingerprints'][$fingerprint]['first_seen_at'] ?? now()->toIso8601String(),
                'last_seen_at' => now()->toIso8601String(),
                'first_batch_id' => $history['fingerprints'][$fingerprint]['first_batch_id'] ?? (string) ($batch['id'] ?? ''),
                'last_batch_id' => (string) ($batch['id'] ?? ''),
                'usage_count' => (int) ($history['fingerprints'][$fingerprint]['usage_count'] ?? 0) + 1,
            ];
        }

        $history['updated_at'] = now()->toIso8601String();
        $this->writeQuestionHistory($history);
    }

    private function readQuestionHistory(): array
    {
        $history = $this->readJsonFile($this->questionHistoryPath());

        if ($history === null) {
            return [
                'updated_at' => null,
                'fingerprints' => [],
            ];
        }

        $history['fingerprints'] = is_array($history['fingerprints'] ?? null) ? $history['fingerprints'] : [];

        return $history;
    }

    private function writeQuestionHistory(array $history): void
    {
        $this->writeJsonFile($this->questionHistoryPath(), $history);
    }

    private function questionHistoryPath(): string
    {
        return storage_path('app/chatbot-training/question-history.json');
    }

    private function fingerprintQuestion(string $question): string
    {
        return sha1($this->normalizeQuestionText($question));
    }

    private function normalizeQuestionText(string $question): string
    {
        $normalized = Str::lower(trim($question));
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim((string) $normalized);
    }

    private function readJsonDirectory(string $directory): array
    {
        $this->ensureDirectory($directory);
        $files = glob($directory . DIRECTORY_SEPARATOR . '*.json') ?: [];
        $items = [];

        foreach ($files as $file) {
            $decoded = $this->readJsonFile($file);
            if (is_array($decoded)) {
                $items[] = $decoded;
            }
        }

        usort($items, static function (array $left, array $right): int {
            return strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
        });

        return $items;
    }

    private function readJsonFile(string $filePath): ?array
    {
        if (!is_file($filePath)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($filePath), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function writeJsonFile(string $filePath, array $payload): void
    {
        $this->ensureDirectory(dirname($filePath));
        file_put_contents($filePath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function directoryPath(string $segment): string
    {
        $path = storage_path('app/chatbot-training/' . trim($segment, '/'));
        $this->ensureDirectory($path);

        return $path;
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }
}
