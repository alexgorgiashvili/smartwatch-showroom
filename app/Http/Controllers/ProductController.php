<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Product\VariantImageResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request, VariantImageResolver $variantImageResolver): View|JsonResponse
    {
        $query = Product::active()->with(['primaryImage', 'images', 'variants']);
        $search = (string) $request->input('search', '');
        $generation = (string) $request->input('generation', 'all');
        $sort = (string) $request->input('sort', 'featured');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name_en', 'like', "%{$search}%")
                    ->orWhere('name_ka', 'like', "%{$search}%")
                    ->orWhere('short_description_en', 'like', "%{$search}%")
                    ->orWhere('short_description_ka', 'like', "%{$search}%");
            });
        }

        match ($sort) {
            'price_low' => $query
                ->orderByRaw('CASE WHEN COALESCE(sale_price, price) IS NULL THEN 1 ELSE 0 END ASC')
                ->orderByRaw('COALESCE(sale_price, price) ASC')
                ->orderBy('name_en'),
            'price_high' => $query
                ->orderByRaw('CASE WHEN COALESCE(sale_price, price) IS NULL THEN 1 ELSE 0 END ASC')
                ->orderByRaw('COALESCE(sale_price, price) DESC')
                ->orderBy('name_en'),
            'discount' => $query
                ->orderByRaw('CASE WHEN sale_price IS NOT NULL AND price IS NOT NULL AND sale_price < price THEN 0 ELSE 1 END ASC')
                ->orderByRaw('CASE WHEN sale_price IS NOT NULL AND price IS NOT NULL AND price > 0 THEN ((price - sale_price) / price) ELSE 0 END DESC')
                ->orderBy('name_en'),
            'newest' => $query->latest(),
            default => $query->orderBy('featured', 'desc')->orderBy('name_en'),
        };

        $products = $query->get();

        if ($generation !== 'all') {
            $products = $products
                ->filter(fn (Product $product): bool => $this->productMatchesGeneration($product, $generation))
                ->values();
        }

        $displayItems = $this->buildCatalogDisplayItems($products, $variantImageResolver);

        if ($request->boolean('ajax') || $request->expectsJson()) {
            return response()->json([
                'html' => view('products._grid', [
                    'products' => $products,
                    'displayItems' => $displayItems,
                    'search' => $search,
                    'generation' => $generation,
                    'sort' => $sort,
                ])->render(),
            ]);
        }

        return view('products.index', [
            'products' => $products,
            'displayItems' => $displayItems,
            'search' => $search,
            'generation' => $generation,
            'sort' => $sort,
        ]);
    }

    public function show(Product $product, VariantImageResolver $variantImageResolver): View
    {
        if (! $product->is_active) {
            abort(404);
        }

        $product->load(['primaryImage', 'images', 'variants']);
        $variantImages = $variantImageResolver->resolve($product);
        $galleryImages = $product->images
            ->map(function ($image, $index) use ($product) {
                return [
                    'url' => $image->url,
                    'thumbnail_url' => $image->thumbnail_url ?? $image->url,
                    'alt' => $image->alt ?: $product->name,
                    'index' => $index,
                ];
            })
            ->values();

        if ($galleryImages->isEmpty()) {
            $defaultImageUrl = asset('storage/images/home/smart-watch3.jpg');
            $galleryImages = collect([[
                'url' => $defaultImageUrl,
                'thumbnail_url' => $defaultImageUrl,
                'alt' => $product->name,
                'index' => 0,
            ]]);
        }

        $relatedProducts = Product::active()
            ->whereKeyNot($product->getKey())
            ->with(['primaryImage'])
            ->orderByDesc('featured')
            ->orderByDesc('updated_at')
            ->limit(4)
            ->get();

        return view('products.show', [
            'product' => $product,
            'relatedProducts' => $relatedProducts,
            'variantImageMap' => $variantImages['variant_images'] ?? [],
            'galleryDefaultImage' => $variantImages['default_image'] ?? null,
        ]);
    }

    public function quickReview(Product $product, VariantImageResolver $variantImageResolver): JsonResponse
    {
        if (! $product->is_active) {
            abort(404);
        }

        $product->loadMissing(['primaryImage', 'images', 'variants']);
        $variantImages = $variantImageResolver->resolve($product);
        $galleryImages = $product->images
            ->map(function ($image, int $index) use ($product) {
                return [
                    'url' => $image->url,
                    'thumbnail_url' => $image->thumbnail_url ?? $image->url,
                    'alt' => $image->alt ?: $product->name,
                    'index' => $index,
                ];
            })
            ->values();

        $availableVariants = $product->variants
            ->filter(fn (ProductVariant $variant): bool => $variant->available_quantity > 0)
            ->values();

        $variants = $availableVariants
            ->map(function ($variant) use ($variantImages, $product) {
                $image = $variantImages['variant_images'][$variant->id] ?? $variantImages['default_image'] ?? null;
                $imageUrl = $image['thumbnail_url'] ?? $image['url'] ?? null;

                return [
                    'id' => (int) $variant->id,
                    'name' => $variant->localizedName(),
                    'color_name' => $variant->localizedColorName(),
                    'color_hex' => $variant->color_hex ? strtoupper((string) $variant->color_hex) : null,
                    'label' => $this->variantLabel($variant->localizedName(), $variant->localizedColorName()),
                    'stock' => (int) $variant->available_quantity,
                    'in_stock' => $variant->available_quantity > 0,
                    'image_url' => $imageUrl,
                    'image_alt' => $image['alt'] ?? $product->name,
                    'image_index' => $image['index'] ?? null,
                ];
            })
            ->values();

        $defaultVariant = $variants->first();
        $defaultVariantImage = $defaultVariant
            ? ($variantImages['variant_images'][$defaultVariant['id']] ?? $variantImages['default_image'] ?? null)
            : ($variantImages['default_image'] ?? null);
        $price = $product->sale_price ?? $product->price;
        $basePrice = $product->price;
        $defaultImageUrl = $defaultVariantImage['thumbnail_url']
            ?? $defaultVariantImage['url']
            ?? $product->primaryImage?->thumbnail_url
            ?? $product->primaryImage?->url
            ?? $product->images->first()?->thumbnail_url
            ?? asset('storage/images/home/smart-watch3.jpg');
        if ($galleryImages->isEmpty()) {
            $galleryImages = collect([[
                'url' => $defaultImageUrl,
                'thumbnail_url' => $defaultImageUrl,
                'alt' => $product->name,
                'index' => 0,
            ]]);
        }
        $currency = $product->currency === 'GEL' ? '₾' : $product->currency;
        $hasDiscount = $product->sale_price !== null
            && $product->price !== null
            && $product->sale_price < $product->price;

        return response()->json([
            'product' => [
                'id' => (int) $product->id,
                'name' => $product->name,
                'url' => route('products.show', $product),
                'image' => $defaultImageUrl,
                'image_alt' => $defaultVariantImage['alt'] ?? $product->name,
                'short_description' => $product->short_description,
                'price' => $price,
                'price_formatted' => $price ? number_format((float) $price, 2) . ' ' . $currency : __('ui.price_on_request'),
                'base_price_formatted' => $hasDiscount && $basePrice
                    ? number_format((float) $basePrice, 2) . ' ' . $currency
                    : null,
                'currency' => $product->currency ?: 'GEL',
                'has_discount' => $hasDiscount,
            ],
            'gallery_images' => $galleryImages,
            'variants' => $variants,
            'default_variant_id' => $defaultVariant['id'] ?? null,
            'max_quantity' => max(1, min(10, (int) ($defaultVariant['stock'] ?? 1))),
            'add_to_cart_url' => route('cart.add'),
        ]);
    }

    private function productMatchesGeneration(Product $product, string $generation): bool
    {
        $generation = strtolower(trim($generation));

        if (! in_array($generation, ['2g', '4g'], true)) {
            return true;
        }

        $haystack = mb_strtolower(implode(' ', array_filter([
            (string) $product->name,
            (string) $product->name_en,
            (string) $product->name_ka,
            (string) $product->slug,
            (string) $product->brand,
            (string) $product->model,
            (string) $product->short_description_en,
            (string) $product->short_description_ka,
            (string) $product->description_en,
            (string) $product->description_ka,
            is_array($product->functions)
                ? implode(' ', array_map(static fn ($item): string => (string) $item, $product->functions))
                : '',
        ])));

        $patterns = $generation === '2g'
            ? [
                '/(?:^|\s)2\s*g(?:\s|$)/u',
                '/(?:^|\s)2\s*გ(?:\s|$)/u',
                '/(?:^|\s)2გ(?:\s|$)/u',
            ]
            : [
                '/(?:^|\s)4\s*g(?:\s|$)/u',
                '/(?:^|\s)4\s*გ(?:\s|$)/u',
                '/(?:^|\s)4გ(?:\s|$)/u',
            ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $haystack) === 1) {
                return true;
            }
        }

        return Str::contains($haystack, $generation);
    }

    private function variantLabel(?string $name, ?string $colorName): string
    {
        $name = trim((string) $name);
        $colorName = trim((string) $colorName);

        if ($name !== '' && $colorName !== '') {
            return Str::contains(mb_strtolower($name), mb_strtolower($colorName))
                ? $name
                : "{$name} • {$colorName}";
        }

        return $colorName !== '' ? $colorName : ($name !== '' ? $name : __('storefront.common.color_variant'));
    }

    /**
     * @param Collection<int, Product> $products
     * @return Collection<int, array<string, mixed>>
     */
    private function buildCatalogDisplayItems(Collection $products, VariantImageResolver $variantImageResolver): Collection
    {
        $items = collect();

        foreach ($products as $product) {
            $availableVariants = $product->variants
                ->filter(fn (ProductVariant $variant): bool => $variant->available_quantity > 0)
                ->values();

            $listedVariants = $availableVariants
                ->filter(fn (ProductVariant $variant): bool => (bool) $variant->is_listed_separately)
                ->values();

            if ($listedVariants->isEmpty()) {
                $items->push([
                    'type' => 'product',
                    'product' => $product,
                ]);

                continue;
            }

            $variantImages = $variantImageResolver->resolve($product);

            foreach ($listedVariants as $variant) {
                $items->push([
                    'type' => 'variant',
                    'product' => $product,
                    'variant' => $variant,
                    'variant_label' => $this->variantLabel($variant->localizedName(), $variant->localizedColorName()),
                    'variant_image' => $variantImages['variant_images'][$variant->id] ?? $variantImages['default_image'] ?? null,
                ]);
            }
        }

        return $items;
    }
}
