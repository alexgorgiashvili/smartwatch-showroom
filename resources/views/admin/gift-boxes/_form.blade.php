@php
    $mainItem = $box->exists ? $box->items->firstWhere('role', 'main') : null;
    $addonItems = $box->exists ? $box->items->where('role', 'addon')->values() : collect();
    $selectedMainProduct = (int) old('main_product_id', $mainItem?->product_id);
    $selectedMainVariant = (int) old('main_default_variant_id', $mainItem?->default_variant_id);
@endphp

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card mb-3"><div class="card-body">
            <h6 class="card-title">კონტენტი</h6>
            <div class="row g-3">
                <div class="col-md-8"><label class="form-label">ქართული სათაური *</label><input name="title_ka" class="form-control" required maxlength="255" value="{{ old('title_ka', $box->title_ka) }}"></div>
                <div class="col-md-4"><label class="form-label">Slug *</label><input name="slug" class="form-control" required maxlength="160" value="{{ old('slug', $box->slug) }}" placeholder="smart-start"></div>
                <div class="col-md-8"><label class="form-label">English title</label><input name="title_en" class="form-control" maxlength="255" value="{{ old('title_en', $box->title_en) }}"></div>
                <div class="col-md-4"><label class="form-label">Badge (KA)</label><input name="badge_ka" class="form-control" maxlength="120" value="{{ old('badge_ka', $box->badge_ka) }}"></div>
                <div class="col-md-6"><label class="form-label">აღწერა (KA)</label><textarea name="short_description_ka" class="form-control" rows="4">{{ old('short_description_ka', $box->short_description_ka) }}</textarea></div>
                <div class="col-md-6"><label class="form-label">Description (EN)</label><textarea name="short_description_en" class="form-control" rows="4">{{ old('short_description_en', $box->short_description_en) }}</textarea></div>
                <div class="col-md-6"><label class="form-label">Badge (EN)</label><input name="badge_en" class="form-control" maxlength="120" value="{{ old('badge_en', $box->badge_en) }}"></div>
                <div class="col-md-6">
                    <label class="form-label">Cover image</label>
                    <input type="file" name="hero_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                    <div class="form-text">JPG, PNG ან WebP; მაქს. 5 MB.</div>
                    @if($box->hero_image_url)
                        <div class="mt-2 d-flex align-items-center gap-2"><img src="{{ $box->hero_image_url }}" alt="" width="96" height="72" class="rounded object-fit-cover"><label><input type="checkbox" name="remove_hero_image" value="1"> წაშლა</label></div>
                    @endif
                </div>
            </div>
        </div></div>

        <div class="card"><div class="card-body">
            <h6 class="card-title">პროდუქტები</h6>
            <div class="row g-2 align-items-end gift-box-item-row">
                <div class="col-md-7">
                    <label class="form-label">მთავარი საჩუქარი *</label>
                    <select name="main_product_id" class="form-select js-gift-product" required>
                        <option value="">აირჩიე პროდუქტი</option>
                        @foreach($products as $product)
                            @continue(!in_array($product->gift_builder_role, ['main', 'both'], true) && $selectedMainProduct !== (int) $product->id)
                            <option value="{{ $product->id }}" @selected($selectedMainProduct === (int) $product->id)>{{ $product->name }} · {{ $product->gift_builder_role ?: 'none' }} · მარაგი {{ $product->stock_quantity }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">ნაგულისხმევი ფერი</label>
                    <select name="main_default_variant_id" class="form-select js-gift-variant">
                        <option value="">ავტომატურად</option>
                        @foreach($products as $product) @foreach($product->variants as $variant)
                            <option value="{{ $variant->id }}" data-product-id="{{ $product->id }}" @selected($selectedMainVariant === (int) $variant->id)>{{ $variant->localizedName() }} · მარაგი {{ $variant->available_quantity }}</option>
                        @endforeach @endforeach
                    </select>
                </div>
            </div>

            <hr><h6>დამატებები (მაქს. 3)</h6>
            @for($index = 0; $index < 3; $index++)
                @php
                    $existing = $addonItems->get($index);
                    $selectedProduct = (int) old("addons.$index.product_id", $existing?->product_id);
                    $selectedVariant = (int) old("addons.$index.default_variant_id", $existing?->default_variant_id);
                @endphp
                <div class="row g-2 align-items-end mb-2 gift-box-item-row">
                    <div class="col-md-7">
                        <label class="form-label">დამატება {{ $index + 1 }}</label>
                        <select name="addons[{{ $index }}][product_id]" class="form-select js-gift-product">
                            <option value="">არ არის</option>
                            @foreach($products as $product)
                                @continue(!in_array($product->gift_builder_role, ['addon', 'both'], true) && $selectedProduct !== (int) $product->id)
                                <option value="{{ $product->id }}" @selected($selectedProduct === (int) $product->id)>{{ $product->name }} · {{ $product->gift_builder_role ?: 'none' }} · მარაგი {{ $product->stock_quantity }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">ნაგულისხმევი ფერი</label>
                        <select name="addons[{{ $index }}][default_variant_id]" class="form-select js-gift-variant">
                            <option value="">ავტომატურად</option>
                            @foreach($products as $product) @foreach($product->variants as $variant)
                                <option value="{{ $variant->id }}" data-product-id="{{ $product->id }}" @selected($selectedVariant === (int) $variant->id)>{{ $variant->localizedName() }} · მარაგი {{ $variant->available_quantity }}</option>
                            @endforeach @endforeach
                        </select>
                    </div>
                </div>
            @endfor
        </div></div>
    </div>

    <div class="col-xl-4">
        <div class="card mb-3"><div class="card-body">
            <h6 class="card-title">შეთავაზება</h6>
            <div class="mb-3"><label class="form-label">შეფუთვა</label><select name="packaging_slug" class="form-select">@foreach(config('gift_builder.packaging', []) as $slug => $package)<option value="{{ $slug }}" @selected(old('packaging_slug', $box->packaging_slug ?: 'standard') === $slug)>{{ $package['label_ka'] ?? $slug }} · {{ number_format((float)($package['price'] ?? 0), 2) }} ₾</option>@endforeach</select></div>
            <div class="row g-2">
                <div class="col-6"><label class="form-label">ფასდაკლება</label><select name="discount_type" class="form-select"><option value="none" @selected(old('discount_type', $box->discount_type ?: 'none') === 'none')>არა</option><option value="fixed" @selected(old('discount_type', $box->discount_type) === 'fixed')>ფიქსირებული</option><option value="percent" @selected(old('discount_type', $box->discount_type) === 'percent')>პროცენტი</option></select></div>
                <div class="col-6"><label class="form-label">მნიშვნელობა</label><input type="number" name="discount_value" min="0" step="0.01" class="form-control" value="{{ old('discount_value', $box->discount_value ?? 0) }}"></div>
            </div>
            <div class="form-text mt-2">ფასდაკლება ითვლება მხოლოდ პროდუქტებზე; შეფუთვა არ იკლებს.</div>
        </div></div>
        <div class="card"><div class="card-body">
            <h6 class="card-title">გამოჩენა</h6>
            <div class="mb-3"><label class="form-label">თემა</label><select name="theme_key" class="form-select"><option value="grape" @selected(old('theme_key', $box->theme_key ?: 'grape') === 'grape')>Grape</option><option value="coral" @selected(old('theme_key', $box->theme_key) === 'coral')>Coral</option><option value="mint" @selected(old('theme_key', $box->theme_key) === 'mint')>Mint</option></select></div>
            <div class="mb-3"><label class="form-label">რიგითობა</label><input type="number" name="sort_order" min="0" class="form-control" value="{{ old('sort_order', $box->sort_order ?? 0) }}"></div>
            <div class="form-check mb-2"><input type="checkbox" name="is_featured" value="1" class="form-check-input" id="gift-featured" @checked(old('is_featured', $box->is_featured))><label for="gift-featured" class="form-check-label">Featured</label></div>
            <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="gift-active" @checked(old('is_active', $box->is_active))><label for="gift-active" class="form-check-label">აქტიური</label></div>
            <div class="form-text">აქტივაცია ვერ მოხდება, თუ პროდუქტი, მარაგი ან შეფუთვა პრობლემურია.</div>
        </div></div>
    </div>
</div>

<script>
(() => {
    document.querySelectorAll('.gift-box-item-row').forEach((row) => {
        const product = row.querySelector('.js-gift-product');
        const variant = row.querySelector('.js-gift-variant');
        if (!product || !variant) return;
        const sync = () => {
            const selectedProduct = product.value;
            Array.from(variant.options).forEach((option) => {
                if (!option.dataset.productId) return;
                option.hidden = option.dataset.productId !== selectedProduct;
                option.disabled = option.dataset.productId !== selectedProduct;
            });
            const selected = variant.options[variant.selectedIndex];
            if (selected?.dataset.productId && selected.dataset.productId !== selectedProduct) variant.value = '';
        };
        product.addEventListener('change', sync);
        sync();
    });
})();
</script>
