<?php

namespace App\Console\Commands;

use App\Services\Chatbot\RuntimeAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ChatbotRuntimeAudit extends Command
{
    protected $signature = 'chatbot:runtime-audit
        {--dataset= : Dataset file name or path}
        {--output= : Optional base output name without extension}';

    protected $description = 'Run real chatbot runtime audit questions in single-turn and session modes, then save JSON and Markdown logs.';

    public function handle(RuntimeAuditService $auditService): int
    {
        $dataset = $auditService->loadDataset($this->option('dataset'));
        $result = $auditService->run($dataset);

        $timestamp = now()->format('Ymd_His');
        $baseName = trim((string) $this->option('output'));
        if ($baseName === '') {
            $baseName = 'runtime_audit_' . $timestamp;
        }

        $directory = storage_path('app/chatbot-training/audits');
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $jsonPath = $directory . DIRECTORY_SEPARATOR . $baseName . '.json';
        $mdPath = $directory . DIRECTORY_SEPARATOR . $baseName . '.md';

        File::put($jsonPath, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        File::put($mdPath, $this->toMarkdown($result));

        $summary = $result['summary'] ?? [];
        $meta = $result['meta'] ?? [];

        $this->info('Runtime audit completed.');
        $this->line('Cases: ' . ($meta['total_cases'] ?? 0));
        $this->line('Passed: ' . ($summary['passed_count'] ?? 0));
        $this->line('Failed: ' . ($summary['failed_count'] ?? 0));
        $this->line('Pass rate: ' . ($summary['pass_rate'] ?? 0) . '%');
        $this->line('JSON: ' . $jsonPath);
        $this->line('Markdown: ' . $mdPath);

        $failedRows = collect($result['results'] ?? [])
            ->filter(fn (array $row): bool => ($row['status'] ?? 'failed') !== 'passed')
            ->map(function (array $row): array {
                return [
                    $row['id'] ?? '',
                    $row['mode'] ?? '',
                    $row['category'] ?? '',
                    implode(' | ', $row['evaluation']['reasons'] ?? []),
                ];
            })
            ->values()
            ->all();

        if ($failedRows !== []) {
            $this->newLine();
            $this->table(['ID', 'Mode', 'Category', 'Reasons'], $failedRows);
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function toMarkdown(array $result): string
    {
        $lines = [];
        $meta = $result['meta'] ?? [];
        $summary = $result['summary'] ?? [];

        $lines[] = '# Chatbot Runtime Audit';
        $lines[] = '';
        $lines[] = '- Generated at: ' . ($meta['generated_at'] ?? '');
        $lines[] = '- Dataset: ' . ($meta['dataset_path'] ?? '');
        $lines[] = '- Total cases: ' . ($meta['total_cases'] ?? 0);
        $lines[] = '- Passed: ' . ($summary['passed_count'] ?? 0);
        $lines[] = '- Failed: ' . ($summary['failed_count'] ?? 0);
        $lines[] = '- Pass rate: ' . ($summary['pass_rate'] ?? 0) . '%';
        $lines[] = '';

        foreach ($result['results'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $lines[] = '## ' . ($row['id'] ?? 'case');
            $lines[] = '';
            $lines[] = '- Mode: ' . ($row['mode'] ?? '');
            $lines[] = '- Category: ' . ($row['category'] ?? '');
            $lines[] = '- Status: ' . ($row['status'] ?? '');
            $lines[] = '- Question: ' . ($row['question'] ?? '');

            $reasons = $row['evaluation']['reasons'] ?? [];
            if (is_array($reasons) && $reasons !== []) {
                $lines[] = '- Review notes: ' . implode(' | ', $reasons);
            }

            $lines[] = '';
            $lines[] = '### Response';
            $lines[] = '';
            $lines[] = (string) ($row['response'] ?? '');
            $lines[] = '';

            $turns = $row['turns'] ?? [];
            if (is_array($turns) && count($turns) > 1) {
                $lines[] = '### Turns';
                $lines[] = '';

                foreach ($turns as $turn) {
                    if (!is_array($turn)) {
                        continue;
                    }

                    $lines[] = '- ' . strtoupper((string) ($turn['role'] ?? 'turn')) . ': ' . (string) ($turn['content'] ?? '');
                }

                $lines[] = '';
            }
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }
}
