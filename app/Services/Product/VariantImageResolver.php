<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VariantImageResolver
{
    /**
     * Cache by product id for the lifetime of the request.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $cache = [];

    /**
     * Resolve the best image assignment for every color variant on a product.
     *
     * Explicit text matches win first. If there are no usable clues and the
     * gallery is compact enough, we fall back to a conservative ordered match.
     */
    public function resolve(Product $product): array
    {
        $productId = (int) $product->getKey();

        if (isset($this->cache[$productId])) {
            return $this->cache[$productId];
        }

        $product->loadMissing(['primaryImage', 'images', 'variants.images']);

        $images = $this->prepareImages($product);
        $variants = $this->colorVariants($product);
        $defaultImage = $this->defaultImage($product, $images);
        $useOrderedFallback = $this->shouldUseOrderedFallback($variants, $images);

        $variantImages = [];
        $usedImageIds = [];

        foreach ($variants->values() as $variantIndex => $variant) {
            $assignment = $this->matchMappedImage($variant, $images, $usedImageIds);

            if (! $assignment) {
                $assignment = $this->matchExplicitImage($variant, $images, $usedImageIds);
            }

            if (! $assignment && $useOrderedFallback) {
                $assignment = $this->matchOrderedImage(
                    $images,
                    (int) $variantIndex,
                    $variants->count(),
                    $usedImageIds
                );
            }

            if (! $assignment && $defaultImage) {
                $assignment = $this->formatImage($defaultImage, 'default');
            }

            if ($assignment !== null && isset($assignment['id'])) {
                $usedImageIds[] = (int) $assignment['id'];
            }

            $variantImages[(int) $variant->id] = $assignment;
        }

        return $this->cache[$productId] = [
            'default_image' => $defaultImage ? $this->formatImage($defaultImage, 'default') : null,
            'variant_images' => $variantImages,
            'image_count' => $images->count(),
            'variant_count' => $variants->count(),
            'uses_ordered_fallback' => $useOrderedFallback,
        ];
    }

    public function imageForVariant(Product $product, ProductVariant $variant): ?array
    {
        $resolved = $this->resolve($product);
        $variantId = (int) $variant->getKey();

        return $resolved['variant_images'][$variantId]
            ?? $resolved['default_image']
            ?? null;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function prepareImages(Product $product): Collection
    {
        $thumbnailPaths = $product->images
            ->map(function (ProductImage $image): ?string {
                $thumbnailPath = ltrim((string) ($image->thumbnail_path ?? ''), '/');

                if ($thumbnailPath === '') {
                    return null;
                }

                return str_starts_with($thumbnailPath, 'storage/')
                    ? substr($thumbnailPath, 8)
                    : $thumbnailPath;
            })
            ->filter()
            ->values()
            ->all();

        return $product->images
            ->filter(function (ProductImage $image) use ($thumbnailPaths): bool {
                $path = (string) ($image->path ?? '');
                if ($path === '') {
                    return false;
                }

                $normalizedPath = ltrim($path, '/');
                $filename = strtolower(pathinfo($normalizedPath, PATHINFO_FILENAME));

                if (str_ends_with($filename, '_thumb')) {
                    return false;
                }

                $storagePath = str_starts_with($normalizedPath, 'storage/')
                    ? substr($normalizedPath, 8)
                    : $normalizedPath;

                if (in_array($storagePath, $thumbnailPaths, true)) {
                    return false;
                }

                if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                    return true;
                }

                if (str_starts_with($normalizedPath, 'storage/')) {
                    $normalizedPath = substr($normalizedPath, 8);
                }

                return Storage::disk('public')->exists($normalizedPath);
            })
            ->sortBy('sort_order')
            ->unique(fn (ProductImage $image): string => ltrim((string) ($image->path ?? ''), '/'))
            ->values()
            ->map(function (ProductImage $image, int $index) use ($product): array {
                $path = (string) $image->path;
                $thumbnailPath = (string) ($image->thumbnail_path ?: $image->path);
                $searchText = $this->buildSearchText([
                    $image->alt_en,
                    $image->alt_ka,
                    $path,
                    $thumbnailPath,
                    basename($path),
                    basename($thumbnailPath),
                ]);

                return [
                    'id' => (int) $image->id,
                    'index' => $index,
                    'path' => $path,
                    'thumbnail_path' => $image->thumbnail_path,
                    'url' => $image->url,
                    'thumbnail_url' => $image->thumbnail_url,
                    'alt' => $image->alt ?: $product->name,
                    'search_text' => $searchText,
                    'compact_search_text' => str_replace(' ', '', $searchText),
                ];
            });
    }

    /**
     * @return Collection<int, ProductVariant>
     */
    private function colorVariants(Product $product): Collection
    {
        return $product->variants
            ->filter(fn (ProductVariant $variant): bool => filled($variant->color_name) && filled($variant->color_hex))
            ->unique(fn (ProductVariant $variant): string => strtoupper((string) $variant->color_hex) . '|' . mb_strtolower((string) $variant->color_name))
            ->values();
    }

    /**
     * @param Collection<int, array<string, mixed>> $images
     */
    private function defaultImage(Product $product, Collection $images): ?array
    {
        $primaryImage = $product->primaryImage;

        if ($primaryImage instanceof ProductImage) {
            $matchedPrimary = $images->firstWhere('id', (int) $primaryImage->id);
            if (is_array($matchedPrimary)) {
                return $matchedPrimary;
            }
        }

        return $images->first();
    }

    /**
     * @param Collection<int, ProductVariant> $variants
     * @param Collection<int, array<string, mixed>> $images
     */
    private function shouldUseOrderedFallback(Collection $variants, Collection $images): bool
    {
        $variantCount = $variants->count();
        $imageCount = $images->count();

        if ($variantCount < 2 || $imageCount < 2) {
            return false;
        }

        return $imageCount <= ($variantCount * 3);
    }

    /**
     * @param Collection<int, array<string, mixed>> $images
     * @param array<int> $usedImageIds
     */
    private function matchMappedImage(ProductVariant $variant, Collection $images, array $usedImageIds): ?array
    {
        $mappedImages = $variant->relationLoaded('images')
            ? $variant->images
            : $variant->images()->get();

        if ($mappedImages->isEmpty()) {
            return null;
        }

        foreach ($mappedImages as $mappedImage) {
            $candidate = $images->firstWhere('id', (int) $mappedImage->id);
            if (! is_array($candidate)) {
                continue;
            }

            if (in_array((int) ($candidate['id'] ?? 0), $usedImageIds, true)) {
                continue;
            }

            return $this->formatImage($candidate, 'mapped');
        }

        return null;
    }

    /**
     * @param Collection<int, array<string, mixed>> $images
     * @param array<int> $usedImageIds
     */
    private function matchExplicitImage(ProductVariant $variant, Collection $images, array $usedImageIds): ?array
    {
        $aliases = $this->variantAliases($variant);
        if ($aliases === []) {
            return null;
        }

        $best = null;

        foreach ($images as $image) {
            if (in_array((int) ($image['id'] ?? 0), $usedImageIds, true)) {
                continue;
            }

            $score = $this->matchScore($aliases, (string) ($image['search_text'] ?? ''), (string) ($image['compact_search_text'] ?? ''));
            if ($score <= 0) {
                continue;
            }

            if ($best === null || $score > $best['score'] || ($score === $best['score'] && (int) ($image['index'] ?? 0) < (int) ($best['image']['index'] ?? 0))) {
                $best = [
                    'score' => $score,
                    'image' => $image,
                ];
            }
        }

        if ($best === null) {
            return null;
        }

        return $this->formatImage($best['image'], 'explicit');
    }

    /**
     * @param Collection<int, array<string, mixed>> $images
     * @param array<int> $usedImageIds
     */
    private function matchOrderedImage(Collection $images, int $variantIndex, int $variantCount, array $usedImageIds): ?array
    {
        if ($images->isEmpty()) {
            return null;
        }

        $targetIndex = min(
            $images->count() - 1,
            (int) floor(($variantIndex * $images->count()) / max(1, $variantCount))
        );

        $image = $this->nearestAvailableImage($images, $targetIndex, $usedImageIds);

        return $image ? $this->formatImage($image, 'ordered') : null;
    }

    /**
     * @param Collection<int, array<string, mixed>> $images
     * @param array<int> $usedImageIds
     */
    private function nearestAvailableImage(Collection $images, int $targetIndex, array $usedImageIds): ?array
    {
        $count = $images->count();
        $candidateOffsets = [0];

        for ($offset = 1; $offset < $count; $offset++) {
            $candidateOffsets[] = -$offset;
            $candidateOffsets[] = $offset;
        }

        foreach ($candidateOffsets as $offset) {
            $index = $targetIndex + $offset;
            if ($index < 0 || $index >= $count) {
                continue;
            }

            $image = $images->get($index);
            if (! is_array($image)) {
                continue;
            }

            if (! in_array((int) ($image['id'] ?? 0), $usedImageIds, true)) {
                return $image;
            }
        }

        $fallback = $images->get($targetIndex);

        return is_array($fallback) ? $fallback : null;
    }

    private function formatImage(array $image, string $strategy): array
    {
        return [
            'id' => (int) ($image['id'] ?? 0),
            'index' => isset($image['index']) ? (int) $image['index'] : null,
            'path' => (string) ($image['path'] ?? ''),
            'thumbnail_path' => $image['thumbnail_path'] ?? null,
            'url' => (string) ($image['url'] ?? ''),
            'thumbnail_url' => (string) ($image['thumbnail_url'] ?? ''),
            'alt' => (string) ($image['alt'] ?? ''),
            'strategy' => $strategy,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function variantAliases(ProductVariant $variant): array
    {
        $aliases = array_filter([
            $variant->name,
            $variant->color_name,
        ]);

        $hex = strtoupper(trim((string) $variant->color_hex));
        if ($hex !== '' && isset(self::HEX_TO_GROUP[$hex])) {
            $group = self::HEX_TO_GROUP[$hex];
            $aliases = array_merge($aliases, self::COLOR_GROUPS[$group] ?? []);
        }

        $normalized = [];
        foreach ($aliases as $alias) {
            $alias = $this->normalizeText((string) $alias);
            if ($alias !== '') {
                $normalized[] = $alias;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param array<int, string> $aliases
     */
    private function matchScore(array $aliases, string $searchText, string $compactSearchText): int
    {
        $score = 0;

        foreach ($aliases as $alias) {
            $compactAlias = str_replace(' ', '', $alias);

            if ($alias !== '' && Str::contains($searchText, $alias)) {
                $score = max($score, 100 + strlen($alias));
            }

            if ($compactAlias !== '' && Str::contains($compactSearchText, $compactAlias)) {
                $score = max($score, 90 + strlen($compactAlias));
            }
        }

        return $score;
    }

    private function buildSearchText(array $parts): string
    {
        $normalized = [];

        foreach ($parts as $part) {
            $text = $this->normalizeText((string) $part);
            if ($text !== '') {
                $normalized[] = $text;
            }
        }

        return trim(implode(' ', $normalized));
    }

    private function normalizeText(string $value): string
    {
        $value = Str::ascii(mb_strtolower($value));
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    private const COLOR_GROUPS = [
        'black' => ['black', 'shavi'],
        'blue' => ['blue', 'lurji', 'dark blue', 'midnight blue'],
        'brown' => ['brown', 'qavisferi'],
        'beige' => ['beige', 'bej', 'bejuri'],
        'gold' => ['gold', 'oqrosferi', 'oqro'],
        'gray' => ['gray', 'grey', 'natsrisferi', 'ruhi', 'rukhi'],
        'green' => ['green', 'mtsvane'],
        'navy' => ['navy', 'dark navy', 'mukhlurji', 'mukhi lurji'],
        'orange' => ['orange', 'narinjisferi'],
        'pink' => ['pink', 'vardisperi', 'rose'],
        'purple' => ['purple', 'violet', 'iisferi'],
        'red' => ['red', 'tsiteli'],
        'silver' => ['silver', 'vertskhlisferi', 'vertskhli'],
        'teal' => ['teal', 'turquoise', 'firuzisferi'],
        'white' => ['white', 'tetri'],
        'yellow' => ['yellow', 'qviteli'],
    ];

    private const HEX_TO_GROUP = [
        '#000000' => 'black',
        '#0000FF' => 'blue',
        '#A52A2A' => 'brown',
        '#F5F5DC' => 'beige',
        '#FFD700' => 'gold',
        '#808080' => 'gray',
        '#D3D3D3' => 'gray',
        '#008000' => 'green',
        '#000080' => 'navy',
        '#FFA500' => 'orange',
        '#FFC0CB' => 'pink',
        '#FF69B4' => 'pink',
        '#800080' => 'purple',
        '#FF0000' => 'red',
        '#C0C0C0' => 'silver',
        '#008080' => 'teal',
        '#40E0D0' => 'teal',
        '#FFFFFF' => 'white',
        '#FFFF00' => 'yellow',
    ];
}
