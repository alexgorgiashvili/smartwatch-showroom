<?php

namespace App\Console\Commands;

use App\Models\ChatbotDocument;
use App\Models\Faq;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CleanupFaqDuplicates extends Command
{
    protected $signature = 'faq:cleanup-duplicates {--dry-run : Show what would be removed without writing to the database}';

    protected $description = 'Remove duplicate FAQ and FAQ chatbot document records while keeping the canonical newest content.';

    public function handle(): int
    {
        $faqDuplicates = $this->findDuplicateFaqs();
        $documentDuplicates = $this->findDuplicateDocuments();

        if ($faqDuplicates->isEmpty() && $documentDuplicates->isEmpty()) {
            $this->info('No duplicate FAQ records found.');

            return self::SUCCESS;
        }

        $this->line('FAQ duplicates: ' . $faqDuplicates->count());
        foreach ($faqDuplicates as $faq) {
            $this->line(sprintf('- FAQ #%d "%s"', $faq->id, $faq->question));
        }

        $this->line('Chatbot document duplicates: ' . $documentDuplicates->count());
        foreach ($documentDuplicates as $document) {
            $this->line(sprintf('- Doc #%d [%s] "%s"', $document->id, $document->key, $document->title));
        }

        if ($this->option('dry-run')) {
            $this->comment('Dry run only. No records were deleted.');

            return self::SUCCESS;
        }

        Faq::query()->whereKey($faqDuplicates->modelKeys())->delete();
        ChatbotDocument::query()->whereKey($documentDuplicates->modelKeys())->delete();

        $this->info('Duplicate FAQ records removed.');

        return self::SUCCESS;
    }

    private function findDuplicateFaqs(): Collection
    {
        $seen = [];

        return Faq::query()
            ->orderByDesc('id')
            ->get()
            ->filter(function (Faq $faq) use (&$seen): bool {
                $key = $this->faqFingerprint($faq->category, $faq->answer);

                if (isset($seen[$key])) {
                    return true;
                }

                $seen[$key] = $faq->id;

                return false;
            })
            ->values();
    }

    private function findDuplicateDocuments(): Collection
    {
        $seen = [];

        return ChatbotDocument::query()
            ->where('type', 'faq')
            ->orderByDesc('id')
            ->get()
            ->filter(function (ChatbotDocument $document) use (&$seen): bool {
                $normalizedContent = preg_replace('/^კითხვა:.*?\n+პასუხი:\s*/us', '', (string) $document->content_ka) ?? (string) $document->content_ka;
                $category = data_get($document->metadata, 'category', 'სხვა');
                $key = $this->faqFingerprint((string) $category, $normalizedContent);

                if (isset($seen[$key])) {
                    return true;
                }

                $seen[$key] = $document->id;

                return false;
            })
            ->values();
    }

    private function faqFingerprint(?string $category, ?string $answer): string
    {
        $normalized = str_replace(["\\r\\n", "\\n", "\\r", "\r\n", "\r"], "\n", (string) $answer);
        $normalized = preg_replace("/\n{2,}/", "\n\n", $normalized) ?? $normalized;

        return Str::lower(trim(($category ?: 'სხვა') . '|' . trim($normalized)));
    }
}
