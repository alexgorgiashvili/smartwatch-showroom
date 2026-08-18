<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ReadyGiftBox;
use App\Services\GiftBuilder\GiftBuilderDiscountService;
use App\Services\GiftBuilder\ReadyGiftBoxAvailabilityService;
use App\Services\GiftBuilder\ReadyGiftBoxManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReadyGiftBoxController extends Controller
{
    public function index(
        Request $request,
        ReadyGiftBoxAvailabilityService $availability,
        GiftBuilderDiscountService $discounts,
    ) {
        $boxes = ReadyGiftBox::query()
            ->with(['items.product.primaryImage', 'items.product.variants', 'items.defaultVariant'])
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = trim($request->string('q')->value());
                $query->where(function ($nested) use ($search): void {
                    $nested->where('title_ka', 'like', "%{$search}%")
                        ->orWhere('title_en', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($request->string('status')->value() === 'active', fn ($query) => $query->where('is_active', true))
            ->when($request->string('status')->value() === 'draft', fn ($query) => $query->where('is_active', false))
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->paginate(20)
            ->appends($request->query());

        $reports = $boxes->getCollection()->mapWithKeys(
            fn (ReadyGiftBox $box): array => [$box->id => $availability->report($box)]
        );
        $totals = $boxes->getCollection()->mapWithKeys(function (ReadyGiftBox $box) use ($discounts): array {
            $itemsSubtotal = (float) $box->items->sum(fn ($item): float => (float) ($item->product?->sale_price ?? $item->product?->price ?? 0));
            $packagingAmount = max(0, (float) config("gift_builder.packaging.{$box->packaging_slug}.price", 0));
            $discountAmount = $discounts->calculate($box->discount_type, (float) $box->discount_value, $itemsSubtotal);

            return [$box->id => [
                'original' => $itemsSubtotal + $packagingAmount,
                'discount' => $discountAmount,
                'total' => max(0, $itemsSubtotal + $packagingAmount - $discountAmount),
            ]];
        });

        $view = view('admin.gift-boxes.index', [
            'boxes' => $boxes,
            'reports' => $reports,
            'totals' => $totals,
            'q' => $request->string('q')->value(),
            'status' => $request->string('status')->value(),
        ]);

        return $this->renderPjaxView($request, $view);
    }

    public function create(Request $request)
    {
        $view = view('admin.gift-boxes.create', [
            'box' => new ReadyGiftBox([
                'theme_key' => 'grape',
                'packaging_slug' => 'standard',
                'discount_type' => 'none',
            ]),
            'products' => $this->products(),
            'report' => null,
        ]);

        return $this->renderPjaxView($request, $view);
    }

    public function store(Request $request, ReadyGiftBoxManager $manager): RedirectResponse
    {
        $data = $this->validateBox($request);
        $newImagePath = $this->storeHeroImage($request);
        $data['cover_image_path'] = $newImagePath;

        try {
            $box = $manager->create($data);
        } catch (\Throwable $exception) {
            if ($newImagePath) {
                Storage::disk('public')->delete($newImagePath);
            }
            throw $exception;
        }

        return redirect()->route('admin.gift-boxes.edit', $box)
            ->with('status', 'სასაჩუქრე ყუთი შეიქმნა.');
    }

    public function edit(Request $request, ReadyGiftBox $giftBox, ReadyGiftBoxAvailabilityService $availability)
    {
        $giftBox->load(['items.product.primaryImage', 'items.product.variants', 'items.defaultVariant']);
        $view = view('admin.gift-boxes.edit', [
            'box' => $giftBox,
            'products' => $this->products($giftBox),
            'report' => $availability->report($giftBox),
        ]);

        return $this->renderPjaxView($request, $view);
    }

    public function update(Request $request, ReadyGiftBox $giftBox, ReadyGiftBoxManager $manager): RedirectResponse
    {
        $data = $this->validateBox($request, $giftBox);
        $oldImagePath = $giftBox->cover_image_path;
        $newImagePath = $this->storeHeroImage($request);
        $removeImage = $request->boolean('remove_hero_image');
        $data['cover_image_path'] = $newImagePath ?: ($removeImage ? null : $oldImagePath);

        try {
            $box = $manager->update($giftBox, $data);
        } catch (\Throwable $exception) {
            if ($newImagePath) {
                Storage::disk('public')->delete($newImagePath);
            }
            throw $exception;
        }

        if (($newImagePath || $removeImage) && $oldImagePath && $oldImagePath !== $newImagePath) {
            Storage::disk('public')->delete($oldImagePath);
        }

        return redirect()->route('admin.gift-boxes.edit', $box)
            ->with('status', 'სასაჩუქრე ყუთი განახლდა.');
    }

    public function toggleStatus(ReadyGiftBox $giftBox, ReadyGiftBoxManager $manager): RedirectResponse
    {
        try {
            $manager->setActive($giftBox, ! $giftBox->is_active);
        } catch (ValidationException $exception) {
            return redirect()->route('admin.gift-boxes.edit', $giftBox)->withErrors($exception->errors());
        }

        return redirect()->route('admin.gift-boxes.index')
            ->with('status', $giftBox->fresh()->is_active ? 'ყუთი გააქტიურდა.' : 'ყუთი გადავიდა draft-ში.');
    }

    public function destroy(ReadyGiftBox $giftBox): RedirectResponse
    {
        $coverImagePath = $giftBox->cover_image_path;
        $giftBox->forceDelete();

        if ($coverImagePath) {
            Storage::disk('public')->delete($coverImagePath);
        }

        return redirect()->route('admin.gift-boxes.index')->with('status', 'სასაჩუქრე ყუთი წაიშალა.');
    }

    public function preview(Request $request): RedirectResponse
    {
        $this->grantPreviewAccess($request);

        return redirect()->route('gift-builder.boxes');
    }

    public function previewBox(Request $request, ReadyGiftBox $giftBox): RedirectResponse
    {
        $this->grantPreviewAccess($request);

        if ($giftBox->is_active) {
            return redirect()->route('gift-builder.show', ['box' => $giftBox->slug]);
        }

        return redirect()->route('gift-builder.boxes');
    }

    /** @return array<string, mixed> */
    private function validateBox(Request $request, ?ReadyGiftBox $box = null): array
    {
        $packaging = implode(',', array_keys((array) config('gift_builder.packaging', [])));

        $data = $request->validate([
            'slug' => ['required', 'alpha_dash', 'max:160', Rule::unique('ready_gift_boxes', 'slug')->ignore($box?->id)],
            'title_ka' => ['required', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'short_description_ka' => ['nullable', 'string', 'max:1500'],
            'short_description_en' => ['nullable', 'string', 'max:1500'],
            'badge_ka' => ['nullable', 'string', 'max:120'],
            'badge_en' => ['nullable', 'string', 'max:120'],
            'theme_key' => ['required', Rule::in(['grape', 'coral', 'mint'])],
            'packaging_slug' => ['required', "in:{$packaging}"],
            'discount_type' => ['required', Rule::in(['none', 'fixed', 'percent'])],
            'discount_value' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'main_product_id' => ['required', 'integer', 'exists:products,id'],
            'main_default_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'addons' => ['nullable', 'array', 'max:3'],
            'addons.*.product_id' => ['nullable', 'integer', 'distinct', 'exists:products,id'],
            'addons.*.default_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'hero_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'remove_hero_image' => ['nullable', 'boolean'],
        ]);

        if (($data['discount_type'] ?? 'none') === 'percent' && (float) ($data['discount_value'] ?? 0) > 100) {
            throw ValidationException::withMessages(['discount_value' => 'პროცენტული ფასდაკლება 100%-ს ვერ გადააჭარბებს.']);
        }

        $data['is_active'] = $request->boolean('is_active');
        $data['is_featured'] = $request->boolean('is_featured');

        return $data;
    }

    private function storeHeroImage(Request $request): ?string
    {
        return $request->hasFile('hero_image')
            ? $request->file('hero_image')->store('ready-gift-boxes', 'public')
            : null;
    }

    private function grantPreviewAccess(Request $request): void
    {
        abort_unless(config('gift_builder.enabled', false), 404);
        $request->session()->put('gift_builder_preview_access', true);
    }

    private function products(?ReadyGiftBox $box = null)
    {
        $existingProductIds = $box?->items->pluck('product_id')->all() ?? [];

        return Product::query()
            ->with('variants')
            ->where(function ($query) use ($existingProductIds): void {
                $query->where(function ($eligible): void {
                    $eligible->where('fulfillment_mode', 'local_stock')
                        ->where('is_active', true)
                        ->where('gift_builder_enabled', true)
                        ->whereIn('gift_builder_role', ['main', 'addon', 'both']);
                });

                if ($existingProductIds !== []) {
                    $query->orWhereIn('id', $existingProductIds);
                }
            })
            ->orderBy('name_en')
            ->orderBy('name_ka')
            ->get();
    }
}
