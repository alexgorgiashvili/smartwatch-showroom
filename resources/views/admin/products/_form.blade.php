{{-- Shared product form partial used by create.blade.php and edit.blade.php --}}

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-basic" role="tab">Basic Info</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-descriptions" role="tab">Descriptions</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-specs" role="tab">Specifications</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-gift-builder" role="tab">Gift Builder</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-seo" role="tab">SEO</a></li>
</ul>

<div class="tab-content">

    {{-- ══ Tab 1: Basic Info ══ --}}
    <div class="tab-pane fade show active" id="tab-basic" role="tabpanel">
        <div class="card">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name_en" class="form-label">Name (EN) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('name_en') is-invalid @enderror"
                               id="name_en" name="name_en" value="{{ old('name_en', $product->name_en) }}" required>
                        @error('name_en') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="name_ka" class="form-label">Name (KA) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('name_ka') is-invalid @enderror"
                               id="name_ka" name="name_ka" value="{{ old('name_ka', $product->name_ka) }}" required>
                        @error('name_ka') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="slug" class="form-label">Slug</label>
                        <input type="text" class="form-control @error('slug') is-invalid @enderror"
                               id="slug" name="slug" value="{{ old('slug', $product->slug) }}" placeholder="Auto-generated from name">
                        @error('slug') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="price" class="form-label">Price (GEL)</label>
                        <input type="number" step="0.01" min="0" class="form-control @error('price') is-invalid @enderror"
                               id="price" name="price" value="{{ old('price', $product->price) }}">
                        @error('price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="sale_price" class="form-label">Sale Price (GEL)</label>
                        <input type="number" step="0.01" min="0" class="form-control @error('sale_price') is-invalid @enderror"
                               id="sale_price" name="sale_price" value="{{ old('sale_price', $product->sale_price) }}">
                        @error('sale_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="fulfillment_mode" class="form-label">Fulfillment Mode</label>
                        <select class="form-select @error('fulfillment_mode') is-invalid @enderror" id="fulfillment_mode" name="fulfillment_mode">
                            <option value="local_stock" {{ old('fulfillment_mode', $product->fulfillment_mode ?? 'local_stock') === 'local_stock' ? 'selected' : '' }}>Local Stock</option>
                            <option value="dropship_bridge" {{ old('fulfillment_mode', $product->fulfillment_mode) === 'dropship_bridge' ? 'selected' : '' }}>Dropship Bridge</option>
                        </select>
                        @error('fulfillment_mode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="bridge_product_id" class="form-label">Bridge Product ID</label>
                        <input type="text" class="form-control @error('bridge_product_id') is-invalid @enderror"
                               id="bridge_product_id" name="bridge_product_id" value="{{ old('bridge_product_id', $product->bridge_product_id) }}">
                        @error('bridge_product_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="product_sync_status" class="form-label">Bridge Sync Status</label>
                        <select class="form-select @error('product_sync_status') is-invalid @enderror" id="product_sync_status" name="product_sync_status">
                            <option value="">Not set</option>
                            @foreach(['pending_review', 'synced', 'stale', 'sync_failed'] as $syncStatus)
                                <option value="{{ $syncStatus }}" {{ old('product_sync_status', $product->product_sync_status) === $syncStatus ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $syncStatus)) }}</option>
                            @endforeach
                        </select>
                        @error('product_sync_status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <label for="bridge_product_permalink" class="form-label">Bridge Product Permalink</label>
                        <input type="url" class="form-control @error('bridge_product_permalink') is-invalid @enderror"
                               id="bridge_product_permalink" name="bridge_product_permalink" value="{{ old('bridge_product_permalink', $product->bridge_product_permalink) }}">
                        @error('bridge_product_permalink') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        @if($product->product_synced_at)
                            <div class="form-text">Last synced: {{ $product->product_synced_at->format('Y-m-d H:i') }}</div>
                        @endif
                    </div>
                    <div class="col-md-3">
                        <div class="form-check form-switch mt-4">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                                   {{ old('is_active', $product->is_active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check form-switch mt-4">
                            <input class="form-check-input" type="checkbox" id="featured" name="featured" value="1"
                                   {{ old('featured', $product->featured) ? 'checked' : '' }}>
                            <label class="form-check-label" for="featured">Featured</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="home_sort_order" class="form-label">Home sort order</label>
                        <input type="number" min="0" class="form-control @error('home_sort_order') is-invalid @enderror"
                               id="home_sort_order" name="home_sort_order" value="{{ old('home_sort_order', $product->home_sort_order ?? 0) }}">
                        <div class="form-text">Lower numbers appear earlier on the homepage slider.</div>
                        @error('home_sort_order') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <div class="form-check form-switch mt-4">
                            <input class="form-check-input" type="checkbox" id="sim_support" name="sim_support" value="1"
                                   {{ old('sim_support', $product->sim_support) ? 'checked' : '' }}>
                            <label class="form-check-label" for="sim_support">SIM Support</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check form-switch mt-4">
                            <input class="form-check-input" type="checkbox" id="gps_features" name="gps_features" value="1"
                                   {{ old('gps_features', $product->gps_features) ? 'checked' : '' }}>
                            <label class="form-check-label" for="gps_features">GPS Features</label>
                        </div>
                    </div>

                    {{-- Images upload (create only) --}}
                    @if(!$product->exists)
                    <div class="col-12">
                        <label for="images" class="form-label">Product Images</label>
                        <input type="file" class="form-control @error('images') is-invalid @enderror @error('images.*') is-invalid @enderror"
                               id="images" name="images[]" multiple accept="image/*">
                        <div class="form-text">Up to 8 images, max 4MB each. First image becomes primary. You can also use Image Manager after creation.</div>
                        @error('images') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        @error('images.*') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="alt_en" class="form-label">Image Alt (EN)</label>
                        <input type="text" class="form-control" id="alt_en" name="alt_en" value="{{ old('alt_en') }}">
                    </div>
                    <div class="col-md-6">
                        <label for="alt_ka" class="form-label">Image Alt (KA)</label>
                        <input type="text" class="form-control" id="alt_ka" name="alt_ka" value="{{ old('alt_ka') }}">
                    </div>
                    @else
                    <div class="col-12">
                        <div class="alert alert-info py-2 d-flex justify-content-between align-items-center">
                            <span class="small">Use the standalone Image Manager below or the Gallery tab (on edit page) to manage product images.</span>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-image-manager-product" title="Open Image Manager">
                                <i data-feather="image" style="width:14px;height:14px;"></i> Open Manager
                            </button>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Tab 2: Descriptions ══ --}}
    <div class="tab-pane fade" id="tab-descriptions" role="tabpanel">
        <div class="card">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="short_description_en" class="form-label">Short Description (EN)</label>
                        <textarea class="form-control @error('short_description_en') is-invalid @enderror"
                                  id="short_description_en" name="short_description_en" rows="2" maxlength="255">{{ old('short_description_en', $product->short_description_en) }}</textarea>
                        @error('short_description_en') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="short_description_ka" class="form-label">Short Description (KA)</label>
                        <textarea class="form-control @error('short_description_ka') is-invalid @enderror"
                                  id="short_description_ka" name="short_description_ka" rows="2" maxlength="255">{{ old('short_description_ka', $product->short_description_ka) }}</textarea>
                        @error('short_description_ka') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="description_en" class="form-label">Full Description (EN)</label>
                        <textarea class="form-control @error('description_en') is-invalid @enderror"
                                  id="description_en" name="description_en" rows="8">{{ old('description_en', $product->description_en) }}</textarea>
                        @error('description_en') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="description_ka" class="form-label">Full Description (KA)</label>
                        <textarea class="form-control @error('description_ka') is-invalid @enderror"
                                  id="description_ka" name="description_ka" rows="8">{{ old('description_ka', $product->description_ka) }}</textarea>
                        @error('description_ka') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <label for="functions" class="form-label">Functions / Features</label>
                        <textarea class="form-control @error('functions') is-invalid @enderror"
                                  id="functions" name="functions" rows="4"
                                  placeholder="One per line or comma-separated">{{ old('functions', is_array($product->functions) ? implode("\n", $product->functions) : $product->functions) }}</textarea>
                        <div class="form-text">Enter one feature per line (e.g., GPS, SOS Button, Video Call).</div>
                        @error('functions') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Tab 3: Specifications ══ --}}
    <div class="tab-pane fade" id="tab-specs" role="tabpanel">
        <div class="card">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="brand" class="form-label">Brand</label>
                        <input type="text" class="form-control" id="brand" name="brand" value="{{ old('brand', $product->brand) }}">
                    </div>
                    <div class="col-md-4">
                        <label for="model" class="form-label">Model</label>
                        <input type="text" class="form-control" id="model" name="model" value="{{ old('model', $product->model) }}">
                    </div>
                    <div class="col-md-4">
                        <label for="operating_system" class="form-label">Operating System</label>
                        <input type="text" class="form-control" id="operating_system" name="operating_system" value="{{ old('operating_system', $product->operating_system) }}">
                    </div>
                    <div class="col-md-3">
                        <label for="screen_size" class="form-label">Screen Size</label>
                        <input type="text" class="form-control" id="screen_size" name="screen_size" value="{{ old('screen_size', $product->screen_size) }}">
                    </div>
                    <div class="col-md-3">
                        <label for="display_type" class="form-label">Display Type</label>
                        <input type="text" class="form-control" id="display_type" name="display_type" value="{{ old('display_type', $product->display_type) }}">
                    </div>
                    <div class="col-md-3">
                        <label for="screen_resolution" class="form-label">Screen Resolution</label>
                        <input type="text" class="form-control" id="screen_resolution" name="screen_resolution" value="{{ old('screen_resolution', $product->screen_resolution) }}">
                    </div>
                    <div class="col-md-3">
                        <label for="camera" class="form-label">Camera</label>
                        <input type="text" class="form-control" id="camera" name="camera" value="{{ old('camera', $product->camera) }}">
                    </div>
                    <div class="col-md-3">
                        <label for="battery_life_range" class="form-label">Battery Life Range (days)</label>
                        <input type="text"
                               class="form-control"
                               id="battery_life_range"
                               name="battery_life_range"
                               value="{{ old('battery_life_range', $product->battery_life_range) }}"
                               placeholder="1-3">
                        <div class="form-text">Enter the numeric range only. The unit is added automatically by locale.</div>
                    </div>
                    <div class="col-md-3">
                        <label for="battery_capacity_mah" class="form-label">Battery (mAh)</label>
                        <input type="number" min="1" class="form-control" id="battery_capacity_mah" name="battery_capacity_mah" value="{{ old('battery_capacity_mah', $product->battery_capacity_mah) }}">
                    </div>
                    <div class="col-md-3">
                        <label for="charging_time_hours" class="form-label">Charging Time (hours)</label>
                        <input type="number" step="0.1" min="0" class="form-control" id="charging_time_hours" name="charging_time_hours" value="{{ old('charging_time_hours', $product->charging_time_hours) }}">
                    </div>
                    <div class="col-md-3">
                        <label for="warranty_months" class="form-label">Warranty (months)</label>
                        <input type="number" min="0" max="120" class="form-control" id="warranty_months" name="warranty_months" value="{{ old('warranty_months', $product->warranty_months) }}">
                    </div>
                    <div class="col-md-3">
                        <label for="water_resistant" class="form-label">Water Resistance</label>
                        <input type="text" class="form-control" id="water_resistant" name="water_resistant" value="{{ old('water_resistant', $product->water_resistant) }}" placeholder="e.g., IP67">
                    </div>
                    <div class="col-md-3">
                        <label for="case_material" class="form-label">Case Material</label>
                        <input type="text" class="form-control" id="case_material" name="case_material" value="{{ old('case_material', $product->case_material) }}">
                    </div>
                    <div class="col-md-3">
                        <label for="band_material" class="form-label">Band Material</label>
                        <input type="text" class="form-control" id="band_material" name="band_material" value="{{ old('band_material', $product->band_material) }}">
                    </div>
                    <div class="col-md-3">
                        <label for="memory_size" class="form-label">Memory Size</label>
                        <input type="text" class="form-control" id="memory_size" name="memory_size" value="{{ old('memory_size', $product->memory_size) }}">
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Tab 4: SEO ══ --}}
    <div class="tab-pane fade" id="tab-gift-builder" role="tabpanel">
        @php
            $recipientTags = old('gift_recipient_tags', is_array($product->gift_recipient_tags) ? implode("\n", $product->gift_recipient_tags) : $product->gift_recipient_tags);
            $occasionTags = old('gift_occasion_tags', is_array($product->gift_occasion_tags) ? implode("\n", $product->gift_occasion_tags) : $product->gift_occasion_tags);
            $compatibilityTags = old('gift_compatibility_tags', is_array($product->gift_compatibility_tags) ? implode("\n", $product->gift_compatibility_tags) : $product->gift_compatibility_tags);
            $recipientOptions = config('gift_builder.recipients', []);
            $occasionOptions = config('gift_builder.occasions', []);
            $budgetOptions = config('gift_builder.budget_bands', []);
            $recommendationOptions = config('gift_builder.recommendation_priorities', []);
            $recommendationTags = collect(old('gift_recommendation_tags', $product->gift_recommendation_tags ?? []))->filter()->all();
        @endphp

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                    <div>
                        <h6 class="mb-1">Gift Box Builder visibility</h6>
                        <p class="text-muted small mb-0">Main gifts must be active. Add-on only products may stay inactive in the regular storefront and still appear exclusively in Gift Builder.</p>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="gift_builder_enabled" name="gift_builder_enabled" value="1"
                               {{ old('gift_builder_enabled', $product->gift_builder_enabled) ? 'checked' : '' }}>
                        <label class="form-check-label" for="gift_builder_enabled">Enabled</label>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="gift_builder_role" class="form-label">Builder role</label>
                        <select class="form-select @error('gift_builder_role') is-invalid @enderror" id="gift_builder_role" name="gift_builder_role">
                            @foreach(['none' => 'Not used', 'main' => 'Main gift only', 'addon' => 'Add-on only', 'both' => 'Main or add-on'] as $value => $label)
                                <option value="{{ $value }}" {{ old('gift_builder_role', $product->gift_builder_role ?? 'none') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('gift_builder_role') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="gift_budget_band" class="form-label">Budget band</label>
                        <select class="form-select @error('gift_budget_band') is-invalid @enderror" id="gift_budget_band" name="gift_budget_band">
                            @foreach($budgetOptions as $value => $option)
                                <option value="{{ $value }}" {{ old('gift_budget_band', $product->gift_budget_band ?? 'all') === $value ? 'selected' : '' }}>
                                    {{ $option['label_en'] ?? $option['label_ka'] ?? $value }}
                                </option>
                            @endforeach
                        </select>
                        @error('gift_budget_band') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-2">
                        <label for="gift_capacity_units" class="form-label">Capacity units</label>
                        <input type="number" min="1" max="20" class="form-control @error('gift_capacity_units') is-invalid @enderror"
                               id="gift_capacity_units" name="gift_capacity_units" value="{{ old('gift_capacity_units', $product->gift_capacity_units ?: 1) }}">
                        @error('gift_capacity_units') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-2">
                        <label for="gift_sort_order" class="form-label">Sort order</label>
                        <input type="number" min="0" class="form-control @error('gift_sort_order') is-invalid @enderror"
                               id="gift_sort_order" name="gift_sort_order" value="{{ old('gift_sort_order', $product->gift_sort_order ?: 0) }}">
                        @error('gift_sort_order') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="gift_recipient_tags" class="form-label">Recipient tags</label>
                        <textarea class="form-control @error('gift_recipient_tags') is-invalid @enderror"
                                  id="gift_recipient_tags" name="gift_recipient_tags" rows="5"
                                  placeholder="{{ implode(', ', array_keys($recipientOptions)) }}">{{ $recipientTags }}</textarea>
                        <div class="form-text">Use one key per line. Empty means all recipients.</div>
                        @error('gift_recipient_tags') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="gift_occasion_tags" class="form-label">Occasion tags</label>
                        <textarea class="form-control @error('gift_occasion_tags') is-invalid @enderror"
                                  id="gift_occasion_tags" name="gift_occasion_tags" rows="5"
                                  placeholder="{{ implode(', ', array_keys($occasionOptions)) }}">{{ $occasionTags }}</textarea>
                        <div class="form-text">Use one key per line. Empty means all occasions.</div>
                        @error('gift_occasion_tags') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="gift_compatibility_tags" class="form-label">Compatibility tags</label>
                        <textarea class="form-control @error('gift_compatibility_tags') is-invalid @enderror"
                                  id="gift_compatibility_tags" name="gift_compatibility_tags" rows="5"
                                  placeholder="gps, camera, school, starter">{{ $compatibilityTags }}</textarea>
                        <div class="form-text">Add-ons with tags must overlap the main gift tags. Empty add-on tags are compatible with all.</div>
                        @error('gift_compatibility_tags') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label">Gift recommendation priorities</label>
                        <div class="d-flex flex-wrap gap-3 rounded border p-3">
                            @foreach($recommendationOptions as $value => $option)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="gift_recommendation_tags[]" value="{{ $value }}" id="gift-recommendation-{{ $value }}" @checked(in_array($value, $recommendationTags, true))>
                                    <label class="form-check-label" for="gift-recommendation-{{ $value }}">{{ $option['label_en'] ?? $value }}</label>
                                </div>
                            @endforeach
                        </div>
                        <div class="form-text">Fixed tags used by the private Gift Match recommender. Empty means neutral priority.</div>
                        @error('gift_recommendation_tags') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="gift_badge_en" class="form-label">Badge (EN)</label>
                        <input type="text" maxlength="80" class="form-control @error('gift_badge_en') is-invalid @enderror"
                               id="gift_badge_en" name="gift_badge_en" value="{{ old('gift_badge_en', $product->gift_badge_en) }}">
                        @error('gift_badge_en') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="gift_badge_ka" class="form-label">Badge (KA)</label>
                        <input type="text" maxlength="80" class="form-control @error('gift_badge_ka') is-invalid @enderror"
                               id="gift_badge_ka" name="gift_badge_ka" value="{{ old('gift_badge_ka', $product->gift_badge_ka) }}">
                        @error('gift_badge_ka') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="gift_builder_note_en" class="form-label">Builder note (EN)</label>
                        <textarea maxlength="255" rows="3" class="form-control @error('gift_builder_note_en') is-invalid @enderror"
                                  id="gift_builder_note_en" name="gift_builder_note_en">{{ old('gift_builder_note_en', $product->gift_builder_note_en) }}</textarea>
                        @error('gift_builder_note_en') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="gift_builder_note_ka" class="form-label">Builder note (KA)</label>
                        <textarea maxlength="255" rows="3" class="form-control @error('gift_builder_note_ka') is-invalid @enderror"
                                  id="gift_builder_note_ka" name="gift_builder_note_ka">{{ old('gift_builder_note_ka', $product->gift_builder_note_ka) }}</textarea>
                        @error('gift_builder_note_ka') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-seo" role="tabpanel">
        <div class="card">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="meta_title_en" class="form-label">Meta Title (EN)</label>
                        <input type="text" class="form-control" id="meta_title_en" name="meta_title_en" value="{{ old('meta_title_en', $product->meta_title_en) }}" maxlength="160">
                    </div>
                    <div class="col-md-6">
                        <label for="meta_title_ka" class="form-label">Meta Title (KA)</label>
                        <input type="text" class="form-control" id="meta_title_ka" name="meta_title_ka" value="{{ old('meta_title_ka', $product->meta_title_ka) }}" maxlength="160">
                    </div>
                    <div class="col-md-6">
                        <label for="meta_description_en" class="form-label">Meta Description (EN)</label>
                        <textarea class="form-control" id="meta_description_en" name="meta_description_en" rows="2" maxlength="160">{{ old('meta_description_en', $product->meta_description_en) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label for="meta_description_ka" class="form-label">Meta Description (KA)</label>
                        <textarea class="form-control" id="meta_description_ka" name="meta_description_ka" rows="2" maxlength="160">{{ old('meta_description_ka', $product->meta_description_ka) }}</textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
