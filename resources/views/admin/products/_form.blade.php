{{-- Shared product form partial used by create.blade.php and edit.blade.php --}}

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-basic" role="tab">Basic Info</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-descriptions" role="tab">Descriptions</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-specs" role="tab">Specifications</a></li>
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
                        <label for="battery_life_hours" class="form-label">Battery Life (hours)</label>
                        <input type="number" min="1" max="1000" class="form-control" id="battery_life_hours" name="battery_life_hours" value="{{ old('battery_life_hours', $product->battery_life_hours) }}">
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
