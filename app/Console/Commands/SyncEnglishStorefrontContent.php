<?php

namespace App\Console\Commands;

use App\Models\Faq;
use App\Models\Product;
use App\Services\Chatbot\ChatbotContentSyncService;
use App\Support\GeorgianTransliterator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncEnglishStorefrontContent extends Command
{
    protected $signature = 'storefront:sync-english-content
        {--with-embeddings : Also update configured Pinecone embeddings}';

    protected $description = 'Rebuild bilingual chatbot documents from storefront products, FAQs, contacts, and static pages.';

    public function handle(ChatbotContentSyncService $contentSync): int
    {
        if (! Schema::hasColumn('chatbot_documents', 'content_en')
            || ! Schema::hasColumn('faqs', 'question_en')
            || ! Schema::hasColumn('product_variants', 'name_en')) {
            $this->error('Run the English storefront migration before syncing content.');

            return self::FAILURE;
        }

        $withEmbeddings = (bool) $this->option('with-embeddings');
        $failed = 0;
        $translatedCities = 0;

        $this->components->task('Filling missing English city names', function () use (&$failed, &$translatedCities): bool {
            [$translatedCities, $unresolvedCities] = $this->syncMissingCityNames();
            $failed += $unresolvedCities;

            return $unresolvedCities === 0;
        });

        $this->components->task('Syncing contact and static-page documents', function () use ($contentSync, $withEmbeddings, &$failed): bool {
            $contactsSynced = $contentSync->syncContacts(syncEmbedding: $withEmbeddings);
            $staticPagesSynced = $contentSync->syncStaticPages(syncEmbedding: $withEmbeddings);
            $success = $contactsSynced && $staticPagesSynced;
            $failed += $success ? 0 : 1;

            return $success;
        });

        Faq::query()->orderBy('id')->chunkById(100, function ($faqs) use ($contentSync, $withEmbeddings, &$failed): void {
            foreach ($faqs as $faq) {
                if (! $contentSync->syncFaq($faq, $withEmbeddings)) {
                    $failed++;
                }
            }
        });

        if (! $contentSync->syncLegacyFaqDocuments($withEmbeddings)) {
            $failed++;
        }

        Product::query()->with(['variants', 'primaryImage'])->orderBy('id')->chunkById(50, function ($products) use ($contentSync, $withEmbeddings, &$failed): void {
            foreach ($products as $product) {
                if (! $contentSync->syncProduct($product, $withEmbeddings)) {
                    $failed++;
                }
            }
        });

        if ($failed > 0) {
            $this->error("Content sync finished with {$failed} failed operation(s).");

            return self::FAILURE;
        }

        $this->info('English storefront chatbot content is synchronized.');
        $this->line("Filled {$translatedCities} missing English city name(s).");
        if (! $withEmbeddings) {
            $this->line('Embeddings were not changed. Re-run with --with-embeddings when remote vector updates are intended.');
        }

        return self::SUCCESS;
    }

    /** @return array{int, int} */
    private function syncMissingCityNames(): array
    {
        $translated = 0;
        $unresolved = 0;

        \App\Models\City::query()
            ->where(fn ($query) => $query->whereNull('name_en')->orWhere('name_en', ''))
            ->select(['id', 'name'])
            ->orderBy('id')
            ->chunkById(200, function ($cities) use (&$translated, &$unresolved): void {
                foreach ($cities as $city) {
                    $nameEn = GeorgianTransliterator::transliterate((string) $city->name);

                    if ($nameEn === '' || preg_match('/\p{Georgian}/u', $nameEn) === 1) {
                        $unresolved++;
                        continue;
                    }

                    DB::table('cities')->where('id', $city->id)->update(['name_en' => $nameEn]);
                    $translated++;
                }
            });

        return [$translated, $unresolved];
    }
}
