{{-- Reusable Tailwind product card for landing pages and blog.
   Requires: $product (App\Models\Product) with primaryImage, images, variants loaded.
--}}
@php
    $image = $product->primaryImage ?? $product->images->first();
    $imageUrl = $image?->thumbnail_url ?: asset('storage/images/home/smart-watch3.jpg');
    $cur = $product->currency === 'GEL' ? '₾' : $product->currency;
    $base = $product->price;
    $sale = $product->sale_price ?? null;
    $disc = ($sale !== null && $base !== null && $sale < $base);
    $pct  = $disc ? (int) round((($base - $sale) / $base) * 100) : null;
    $badges = array_slice(array_filter([
        $product->sim_support ? 'SIM' : null,
        $product->gps_features ? 'GPS' : null,
        $product->water_resistant ?: null,
        $product->battery_capacity_mah ? $product->battery_capacity_mah . 'mAh' : null,
    ]), 0, 3);
    $firstInStock = $product->variants->firstWhere('quantity', '>', 0) ?? null;
@endphp
<div class="group overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_10px_28px_rgba(15,23,42,0.08)] transition duration-300 hover:-translate-y-1 hover:shadow-[0_18px_40px_rgba(15,23,42,0.14)]">
    <a href="{{ route('products.show', $product) }}" class="block">
        <div class="relative isolate overflow-hidden">
            <div class="pointer-events-none absolute inset-x-0 top-0 z-10 flex items-start justify-between p-2">
                <div>@if($product->featured)<span class="rounded-full bg-slate-900/80 px-2 py-1 text-[10px] font-medium text-white">Featured</span>@endif</div>
                @if($disc)<span class="rounded-full bg-rose-50 border border-rose-200 px-2 py-1 text-[10px] font-semibold text-rose-700">-{{ $pct }}%</span>@endif
            </div>
            <img src="{{ $imageUrl }}" alt="{{ $image?->alt ?: $product->name }}"
                  class="h-44 w-full object-contain transition duration-500 group-hover:scale-[1.05]" />
        </div>
        <div class="space-y-2 p-3 sm:p-4">
            <h3 class="line-clamp-2 text-sm font-semibold text-slate-900 sm:text-base group-hover:text-primary-700">{{ $product->name }}</h3>
            @if(!empty($badges))
                <div class="flex flex-wrap gap-1">
                    @foreach($badges as $b)<span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-medium text-slate-600">{{ $b }}</span>@endforeach
                </div>
            @endif
            <div class="border-t border-slate-100 pt-2">
                @if($disc)
                    <span class="text-lg font-extrabold text-slate-900">{{ number_format($sale, 2) }} {{ $cur }}</span>
                    <span class="ml-1 text-xs text-slate-400 line-through">{{ number_format($base, 2) }}</span>
                @else
                    <span class="text-lg font-extrabold text-slate-900">{{ $base ? number_format($base, 2) . ' ' . $cur : __('ui.price_on_request') }}</span>
                @endif
            </div>
        </div>
    </a>
    <div class="px-3 pb-3">
        @if($firstInStock)
            <form method="POST" action="{{ route('cart.add') }}" data-cart-form>
                @csrf
                <input type="hidden" name="variant_id" value="{{ $firstInStock->id }}">
                <input type="hidden" name="quantity" value="1">
                <button type="submit" class="inline-flex w-full items-center justify-center gap-1.5 rounded-full bg-gray-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-primary-600">
                    <i class="fa-solid fa-cart-shopping text-[10px]"></i>
                    {{ app()->getLocale() === 'ka' ? 'კალათაში' : 'Add to Cart' }}
                </button>
            </form>
        @else
            <a href="{{ route('products.show', $product) }}" class="inline-flex w-full items-center justify-center rounded-full border border-gray-300 px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                {{ app()->getLocale() === 'ka' ? 'დეტალები' : 'View Details' }}
            </a>
        @endif
    </div>
</div>
