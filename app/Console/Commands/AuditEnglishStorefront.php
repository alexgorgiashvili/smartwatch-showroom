<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\ChatbotDocument;
use App\Models\City;
use App\Models\Faq;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditEnglishStorefront extends Command
{
    protected $signature = 'storefront:audit-english';

    protected $description = 'Audit customer-facing records for missing English content';

    public function handle(): int
    {
        $requiredColumns = [
            'faqs' => ['question_en', 'answer_en', 'category_en'],
            'chatbot_documents' => ['title_en', 'content_en'],
            'product_variants' => ['name_en', 'color_name_en'],
            'cities' => ['name_en'],
        ];

        foreach ($requiredColumns as $table => $columns) {
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    $this->error("Missing {$table}.{$column}; run the English storefront migration first.");

                    return self::FAILURE;
                }
            }
        }

        $issues = [
            'Products' => $this->missingIds(
                Product::query()->where('is_active', true),
                ['name_en', 'short_description_en', 'description_en'],
                'slug'
            ),
            'Articles' => $this->missingIds(
                Article::query()->where('is_published', true),
                ['title_en', 'excerpt_en', 'body_en'],
                'slug'
            ),
            'FAQs' => $this->missingIds(
                Faq::query()->where('is_active', true),
                ['question_en', 'answer_en', 'category_en']
            ),
            'Variants' => $this->missingIds(
                ProductVariant::query()->whereHas('product', fn (Builder $query) => $query->where('is_active', true)),
                ['name_en']
            ),
            'Variant colors' => ProductVariant::query()
                ->whereHas('product', fn (Builder $query) => $query->where('is_active', true))
                ->whereNotNull('color_name')
                ->where('color_name', '<>', '')
                ->where(fn (Builder $query) => $query->whereNull('color_name_en')->orWhere('color_name_en', ''))
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all(),
            'Cities' => $this->missingIds(City::query(), ['name_en']),
            'Chatbot documents' => $this->missingIds(
                ChatbotDocument::query()->where('is_active', true),
                ['title_en', 'content_en'],
                'key'
            ),
        ];

        $contactKeys = ['location_en', 'hours_en', 'faq_support_title_en', 'faq_support_description_en'];
        $presentContactKeys = DB::table('contact_settings')
            ->whereIn('key', $contactKeys)
            ->whereNotNull('value')
            ->where('value', '<>', '')
            ->pluck('key')
            ->all();
        $issues['Contact settings'] = array_values(array_diff($contactKeys, $presentContactKeys));

        $hasIssues = false;
        foreach ($issues as $label => $identifiers) {
            if ($identifiers === []) {
                $this->line("<info>PASS</info> {$label}");
                continue;
            }

            $hasIssues = true;
            $preview = implode(', ', array_slice($identifiers, 0, 20));
            $suffix = count($identifiers) > 20 ? ', …' : '';
            $this->line("<error>FAIL</error> {$label}: {$preview}{$suffix}");
        }

        if ($hasIssues) {
            $this->error('English storefront content audit failed.');

            return self::FAILURE;
        }

        $this->info('English storefront content audit passed.');

        return self::SUCCESS;
    }

    /** @param array<int, string> $columns @return array<int, string> */
    private function missingIds(Builder $query, array $columns, string $identifier = 'id'): array
    {
        $query->where(function (Builder $query) use ($columns): void {
            foreach ($columns as $column) {
                $query->orWhereNull($column)->orWhere($column, '');
            }
        });

        return $query->pluck($identifier)->map(fn ($value) => (string) $value)->all();
    }
}
