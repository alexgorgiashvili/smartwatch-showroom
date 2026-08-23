<?php

namespace App\Services\GiftBuilder;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class GiftRecommendationService
{
    public function __construct(
        private readonly GiftBuilderCatalogService $catalog,
        private readonly GiftBuilderPricingService $pricing,
    ) {}

    /** @return array<string, mixed> */
    public function recommend(string $budgetBand, string $priority, array $shownProductIds = []): array
    {
        $shown = collect($shownProductIds)->map(fn ($id): int => (int) $id)->filter()->unique();
        $products = $this->catalog->products(['role' => 'all', 'budget_band' => 'all']);
        $readyBox = $this->readyBox($budgetBand, $priority, $shown);
        $customStart = $this->customStart($products, $budgetBand, $priority, $shown);
        $nextBudget = (! $readyBox && ! $customStart) ? $this->nextBudget($budgetBand) : null;

        return [
            'success' => true,
            'priority' => $priority,
            'ready_box' => $readyBox,
            'custom_start' => $customStart,
            'next_budget_band' => $nextBudget,
            'next_budget_label' => $nextBudget ? $this->budgetLabel($nextBudget) : null,
        ];
    }

    /** @return array<string, mixed>|null */
    private function readyBox(string $budgetBand, string $priority, Collection $shown): ?array
    {
        $max = data_get(config("gift_builder.budget_bands.{$budgetBand}"), 'max');

        return collect($this->catalog->readyBoxes())
            ->filter(fn (array $box): bool => $max === null || (float) $box['total'] <= (float) $max)
            ->filter(function (array $box) use ($priority): bool {
                $tags = collect($box['items'] ?? [])->flatMap(
                    fn (array $item): array => (array) data_get($item, 'product.recommendation_tags', [])
                );

                return $tags->isEmpty() || $tags->contains($priority);
            })
            ->sortByDesc(function (array $box) use ($priority, $shown): int {
                $productIds = collect($box['items'] ?? [])->pluck('product_id')->map(fn ($id): int => (int) $id);
                $tags = collect($box['items'] ?? [])->flatMap(
                    fn (array $item): array => (array) data_get($item, 'product.recommendation_tags', [])
                );

                return ($tags->contains($priority) ? 100 : 0)
                    + ($box['is_featured'] ? 20 : 0)
                    - $productIds->intersect($shown)->count() * 15;
            })
            ->map(function (array $box): array {
                $products = collect($box['items'] ?? [])->map(fn (array $item): array => [
                    'id' => (int) $item['product_id'],
                    'name' => (string) data_get($item, 'product.name', $item['name'] ?? ''),
                    'image' => (string) data_get($item, 'product.image', $item['image'] ?? ''),
                ])->values();

                return [
                    'id' => (int) $box['id'],
                    'slug' => $box['slug'],
                    'title' => $box['title'],
                    'total' => (float) $box['total'],
                    'total_formatted' => $box['total_formatted'],
                    'builder_url' => $box['builder_url'],
                    'product_ids' => $products->pluck('id')->all(),
                    'products' => $products->all(),
                ];
            })
            ->first();
    }

    /** @return array<string, mixed>|null */
    private function customStart(Collection $products, string $budgetBand, string $priority, Collection $shown): ?array
    {
        $mains = $this->ranked($products->filter(fn (array $product): bool => in_array($product['role'], ['main', 'both'], true)), $priority, $shown)->take(10);
        $addons = $this->ranked($products->filter(fn (array $product): bool => in_array($product['role'], ['addon', 'both'], true)), $priority, $shown)->take(10);
        $max = data_get(config("gift_builder.budget_bands.{$budgetBand}"), 'max');
        $best = null;

        foreach ($mains as $main) {
            $compatibleAddons = $addons
                ->reject(fn (array $addon): bool => (int) $addon['id'] === (int) $main['id'])
                ->filter(fn (array $addon): bool => $this->compatible($main, $addon))
                ->values();
            $addonSets = collect([[]]);
            foreach ($compatibleAddons as $addon) {
                $addonSets->push([$addon]);
            }
            for ($first = 0; $first < min(6, $compatibleAddons->count()); $first++) {
                for ($second = $first + 1; $second < min(6, $compatibleAddons->count()); $second++) {
                    $addonSets->push([$compatibleAddons[$first], $compatibleAddons[$second]]);
                }
            }

            foreach (array_keys((array) config('gift_builder.packaging', [])) as $packagingSlug) {
                foreach ($addonSets as $set) {
                    $priced = $this->priceCandidate($main, $set, $budgetBand, $packagingSlug);
                    if (! $priced || ($max !== null && (float) $priced['total'] > (float) $max)) {
                        continue;
                    }

                    $candidateProducts = collect([$main, ...$set]);
                    $score = $candidateProducts->sum(fn (array $product): int => $this->productScore($product, $priority, $shown));
                    $score += count($set) * 8;
                    if ($priority === 'best_price') {
                        $score += max(0, 1000 - (int) round((float) $priced['total']));
                    }
                    if (! $best || $score > $best['score']) {
                        $best = compact('score', 'priced', 'main', 'set', 'packagingSlug');
                    }
                }
            }
        }

        if (! $best) {
            return null;
        }

        $candidateProducts = collect([$best['main'], ...$best['set']]);

        return [
            'title' => app()->getLocale() === 'ka' ? 'შენთვის შერჩეული ყუთი' : 'Your recommended box',
            'reason' => $this->reason($priority),
            'budget_band' => $budgetBand,
            'main_variant_id' => (int) data_get($best, 'main.variants.0.id'),
            'addon_variant_ids' => collect($best['set'])->map(fn (array $addon): int => (int) data_get($addon, 'variants.0.id'))->all(),
            'packaging_slug' => $best['packagingSlug'],
            'total' => (float) $best['priced']['total'],
            'total_formatted' => $best['priced']['total_formatted'],
            'builder_url' => route('gift-builder.show'),
            'product_ids' => $candidateProducts->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            'products' => $candidateProducts->map(fn (array $product): array => [
                'id' => (int) $product['id'],
                'name' => $product['name'],
                'image' => $product['image'],
            ])->all(),
            'canonical_pricing' => $best['priced'],
        ];
    }

    private function ranked(Collection $products, string $priority, Collection $shown): Collection
    {
        return $products->sortByDesc(fn (array $product): int => $this->productScore($product, $priority, $shown))->values();
    }

    private function productScore(array $product, string $priority, Collection $shown): int
    {
        $tags = collect((array) ($product['recommendation_tags'] ?? []));
        $score = $tags->contains($priority) ? 100 : ($tags->isEmpty() ? 20 : 0);
        if (! $shown->contains((int) $product['id'])) {
            $score += 30;
        }
        if ($priority === 'best_price') {
            $score += max(0, 500 - (int) round((float) $product['price']));
        }

        return $score;
    }

    private function compatible(array $main, array $addon): bool
    {
        $mainTags = (array) ($main['compatibility_tags'] ?? []);
        $addonTags = (array) ($addon['compatibility_tags'] ?? []);

        return $addonTags === [] || array_intersect($mainTags, $addonTags) !== [];
    }

    /** @return array<string, mixed>|null */
    private function priceCandidate(array $main, array $addons, string $budgetBand, string $packagingSlug): ?array
    {
        try {
            return $this->pricing->price([
                'recipient_type' => 'other',
                'occasion' => 'just_because',
                'budget_band' => $budgetBand,
                'packaging_slug' => $packagingSlug,
                'message' => '',
                'items' => [
                    ['variant_id' => (int) data_get($main, 'variants.0.id'), 'quantity' => 1, 'role' => 'main'],
                    ...collect($addons)->map(fn (array $addon): array => [
                        'variant_id' => (int) data_get($addon, 'variants.0.id'),
                        'quantity' => 1,
                        'role' => 'addon',
                    ])->all(),
                ],
            ]);
        } catch (ValidationException) {
            return null;
        }
    }

    private function nextBudget(string $current): ?string
    {
        $keys = array_keys((array) config('gift_builder.budget_bands', []));
        $index = array_search($current, $keys, true);

        return $index !== false ? ($keys[$index + 1] ?? null) : null;
    }

    private function budgetLabel(string $slug): string
    {
        $config = (array) config("gift_builder.budget_bands.{$slug}", []);

        return app()->getLocale() === 'ka'
            ? (string) ($config['label_ka'] ?? $config['label_en'] ?? $slug)
            : (string) ($config['label_en'] ?? $slug);
    }

    private function reason(string $priority): string
    {
        $reasons = app()->getLocale() === 'ka'
            ? [
                'safety_connection' => 'შერჩეული პროდუქტები უსაფრთხოებისა და კავშირის ფუნქციებს ანიჭებს უპირატესობას.',
                'music_entertainment' => 'კომბინაცია მუსიკასა და სახალისო ყოველდღიურ გამოყენებაზეა მორგებული.',
                'everyday' => 'პრაქტიკული კომბინაცია ყოველდღიური გამოყენებისთვის.',
                'best_price' => 'ბიუჯეტში ყველაზე ღირებული ხელმისაწვდომი კომბინაცია.',
            ]
            : [
                'safety_connection' => 'These products prioritize safety and connection features.',
                'music_entertainment' => 'This combination is tuned for music and everyday fun.',
                'everyday' => 'A practical combination for everyday use.',
                'best_price' => 'The strongest available value within your budget.',
            ];

        return $reasons[$priority] ?? '';
    }
}
