<div class="row">
	<div class="col-lg-6 mb-3">
		<label for="name_en" class="form-label">Name (EN)</label>
		<input
			type="text"
			name="name_en"
			id="name_en"
			class="form-control"
			value="{{ old('name_en', $product->name_en) }}"
			required
		>
		<div class="invalid-feedback" data-error-for="name_en"></div>
	</div>
	<div class="col-lg-6 mb-3">
		<label for="name_ka" class="form-label">Name (KA)</label>
		<input
			type="text"
			name="name_ka"
			id="name_ka"
			class="form-control"
			value="{{ old('name_ka', $product->name_ka) }}"
			required
		>
		<div class="invalid-feedback" data-error-for="name_ka"></div>
	</div>
	<div class="col-lg-6 mb-3">
		<label for="slug" class="form-label">Slug</label>
		<input
			type="text"
			name="slug"
			id="slug"
			class="form-control"
			value="{{ old('slug', $product->slug) }}"
		>
		<div class="invalid-feedback" data-error-for="slug"></div>
	</div>
	<div class="col-lg-3 mb-3">
		<label for="price" class="form-label">Price (GEL)</label>
		<input
			type="number"
			name="price"
			id="price"
			class="form-control"
			step="0.01"
			min="0"
			value="{{ old('price', $product->price) }}"
		>
		<div class="invalid-feedback" data-error-for="price"></div>
	</div>
	<div class="col-lg-3 mb-3">
		<label for="sale_price" class="form-label">Sale Price (GEL)</label>
		<input
			type="number"
			name="sale_price"
			id="sale_price"
			class="form-control"
			step="0.01"
			min="0"
			value="{{ old('sale_price', $product->sale_price) }}"
		>
		<div class="invalid-feedback" data-error-for="sale_price"></div>
	</div>
	<div class="col-lg-3 mb-3">
		<label for="currency" class="form-label">Currency</label>
		<input
			type="text"
			name="currency"
			id="currency"
			class="form-control"
			maxlength="3"
			value="{{ old('currency', $product->currency ?: 'GEL') }}"
		>
		<div class="invalid-feedback" data-error-for="currency"></div>
	</div>
	<div class="col-lg-9 mb-3">
		<label for="short_description_en" class="form-label">Short Description (EN)</label>
		<input
			type="text"
			name="short_description_en"
			id="short_description_en"
			class="form-control"
			value="{{ old('short_description_en', $product->short_description_en) }}"
		>
		<div class="invalid-feedback" data-error-for="short_description_en"></div>
	</div>
	<div class="col-lg-12 mb-3">
		<label for="short_description_ka" class="form-label">Short Description (KA)</label>
		<input
			type="text"
			name="short_description_ka"
			id="short_description_ka"
			class="form-control"
			value="{{ old('short_description_ka', $product->short_description_ka) }}"
		>
		<div class="invalid-feedback" data-error-for="short_description_ka"></div>
	</div>
	<div class="col-lg-6 mb-3">
		<label for="description_en" class="form-label">Description (EN)</label>
		<textarea
			name="description_en"
			id="description_en"
			rows="5"
			class="form-control"
		>{{ old('description_en', $product->description_en) }}</textarea>
		<div class="invalid-feedback" data-error-for="description_en"></div>
	</div>
	<div class="col-lg-6 mb-3">
		<label for="description_ka" class="form-label">Description (KA)</label>
		<textarea
			name="description_ka"
			id="description_ka"
			rows="5"
			class="form-control"
		>{{ old('description_ka', $product->description_ka) }}</textarea>
		<div class="invalid-feedback" data-error-for="description_ka"></div>
	</div>
	<div class="col-lg-4 mb-3">
		<label for="water_resistant" class="form-label">Water Resistance</label>
		<input
			type="text"
			name="water_resistant"
			id="water_resistant"
			class="form-control"
			value="{{ old('water_resistant', $product->water_resistant) }}"
		>
		<div class="invalid-feedback" data-error-for="water_resistant"></div>
	</div>
	<div class="col-lg-4 mb-3">
		<label for="battery_life_hours" class="form-label">Battery Life (hours)</label>
		<input
			type="number"
			name="battery_life_hours"
			id="battery_life_hours"
			class="form-control"
			min="1"
			max="1000"
			value="{{ old('battery_life_hours', $product->battery_life_hours) }}"
		>
		<div class="invalid-feedback" data-error-for="battery_life_hours"></div>
	</div>
	<div class="col-lg-4 mb-3">
		<label for="warranty_months" class="form-label">Warranty (months)</label>
		<input
			type="number"
			name="warranty_months"
			id="warranty_months"
			class="form-control"
			min="0"
			max="120"
			value="{{ old('warranty_months', $product->warranty_months) }}"
		>
		<div class="invalid-feedback" data-error-for="warranty_months"></div>
	</div>
	<div class="col-lg-3 mb-3">
		<label for="operating_system" class="form-label">Operating System</label>
		<input
			type="text"
			name="operating_system"
			id="operating_system"
			class="form-control"
			value="{{ old('operating_system', $product->operating_system) }}"
		>
		<div class="invalid-feedback" data-error-for="operating_system"></div>
	</div>
	<div class="col-lg-3 mb-3">
		<label for="screen_size" class="form-label">Screen Size</label>
		<input
			type="text"
			name="screen_size"
			id="screen_size"
			class="form-control"
			value="{{ old('screen_size', $product->screen_size) }}"
		>
		<div class="invalid-feedback" data-error-for="screen_size"></div>
	</div>
	<div class="col-lg-3 mb-3">
		<label for="display_type" class="form-label">Display Type</label>
		<input
			type="text"
			name="display_type"
			id="display_type"
			class="form-control"
			value="{{ old('display_type', $product->display_type) }}"
		>
		<div class="invalid-feedback" data-error-for="display_type"></div>
	</div>
	<div class="col-lg-3 mb-3">
		<label for="screen_resolution" class="form-label">Screen Resolution</label>
		<input
			type="text"
			name="screen_resolution"
			id="screen_resolution"
			class="form-control"
			value="{{ old('screen_resolution', $product->screen_resolution) }}"
		>
		<div class="invalid-feedback" data-error-for="screen_resolution"></div>
	</div>
	<div class="col-lg-3 mb-3">
		<label for="battery_capacity_mah" class="form-label">Battery Capacity (mAh)</label>
		<input
			type="number"
			name="battery_capacity_mah"
			id="battery_capacity_mah"
			class="form-control"
			min="1"
			max="100000"
			value="{{ old('battery_capacity_mah', $product->battery_capacity_mah) }}"
		>
		<div class="invalid-feedback" data-error-for="battery_capacity_mah"></div>
	</div>
	<div class="col-lg-3 mb-3">
		<label for="charging_time_hours" class="form-label">Charging Time (hours)</label>
		<input
			type="number"
			name="charging_time_hours"
			id="charging_time_hours"
			class="form-control"
			step="0.1"
			min="0"
			max="999.9"
			value="{{ old('charging_time_hours', $product->charging_time_hours) }}"
		>
		<div class="invalid-feedback" data-error-for="charging_time_hours"></div>
	</div>
	<div class="col-lg-3 mb-3">
		<label for="case_material" class="form-label">Case Material</label>
		<input
			type="text"
			name="case_material"
			id="case_material"
			class="form-control"
			value="{{ old('case_material', $product->case_material) }}"
		>
		<div class="invalid-feedback" data-error-for="case_material"></div>
	</div>
	<div class="col-lg-3 mb-3">
		<label for="band_material" class="form-label">Band Material</label>
		<input
			type="text"
			name="band_material"
			id="band_material"
			class="form-control"
			value="{{ old('band_material', $product->band_material) }}"
		>
		<div class="invalid-feedback" data-error-for="band_material"></div>
	</div>
	<div class="col-lg-4 mb-3">
		<label for="camera" class="form-label">Camera</label>
		<input
			type="text"
			name="camera"
			id="camera"
			class="form-control"
			value="{{ old('camera', $product->camera) }}"
		>
		<div class="invalid-feedback" data-error-for="camera"></div>
	</div>
	<div class="col-lg-8 mb-3">
		<label for="functions" class="form-label">Functions (comma separated)</label>
		<textarea
			name="functions"
			id="functions"
			rows="2"
			class="form-control"
		>{{ old('functions', is_array($product->functions) ? implode(', ', $product->functions) : $product->functions) }}</textarea>
		<div class="invalid-feedback" data-error-for="functions"></div>
	</div>
	<div class="col-12">
		<div class="row">
			<div class="col-sm-6 col-lg-3 mb-2">
				<div class="form-check">
					<input
						class="form-check-input"
						type="checkbox"
						name="sim_support"
						id="sim_support"
						value="1"
						@checked(old('sim_support', $product->sim_support ?? true))
					>
					<label class="form-check-label" for="sim_support">SIM Support</label>
					<div class="invalid-feedback" data-error-for="sim_support"></div>
				</div>
			</div>
			<div class="col-sm-6 col-lg-3 mb-2">
				<div class="form-check">
					<input
						class="form-check-input"
						type="checkbox"
						name="gps_features"
						id="gps_features"
						value="1"
						@checked(old('gps_features', $product->gps_features ?? true))
					>
					<label class="form-check-label" for="gps_features">GPS Features</label>
					<div class="invalid-feedback" data-error-for="gps_features"></div>
				</div>
			</div>
			<div class="col-sm-6 col-lg-3 mb-2">
				<div class="form-check">
					<input
						class="form-check-input"
						type="checkbox"
						name="is_active"
						id="is_active"
						value="1"
						@checked(old('is_active', $product->is_active ?? true))
					>
					<label class="form-check-label" for="is_active">Active</label>
					<div class="invalid-feedback" data-error-for="is_active"></div>
				</div>
			</div>
			<div class="col-sm-6 col-lg-3 mb-2">
				<div class="form-check">
					<input
						class="form-check-input"
						type="checkbox"
						name="featured"
						id="featured"
						value="1"
						@checked(old('featured', $product->featured ?? false))
					>
					<label class="form-check-label" for="featured">Featured</label>
					<div class="invalid-feedback" data-error-for="featured"></div>
				</div>
			</div>
		</div>
	</div>
</div>

{{-- ═══ SEO Section ═══ --}}
<div class="row mt-4">
	<div class="col-12 mb-3">
		<h6 class="fw-bold text-muted border-bottom pb-2">SEO (Search Engine Optimization)</h6>
		<p class="text-muted small">If left blank, the product name and short description are used automatically.</p>
	</div>
	<div class="col-lg-6 mb-3">
		<label for="meta_title_ka" class="form-label">Meta Title (KA) <small class="text-muted">max 160 chars</small></label>
		<input
			type="text"
			name="meta_title_ka"
			id="meta_title_ka"
			class="form-control"
			maxlength="160"
			value="{{ old('meta_title_ka', $product->meta_title_ka) }}"
			placeholder="{{ $product->name_ka ? $product->name_ka . ' — MyTechnic' : '' }}"
		>
		<div class="invalid-feedback" data-error-for="meta_title_ka"></div>
	</div>
	<div class="col-lg-6 mb-3">
		<label for="meta_title_en" class="form-label">Meta Title (EN) <small class="text-muted">max 160 chars</small></label>
		<input
			type="text"
			name="meta_title_en"
			id="meta_title_en"
			class="form-control"
			maxlength="160"
			value="{{ old('meta_title_en', $product->meta_title_en) }}"
			placeholder="{{ $product->name_en ? $product->name_en . ' — MyTechnic' : '' }}"
		>
		<div class="invalid-feedback" data-error-for="meta_title_en"></div>
	</div>
	<div class="col-lg-6 mb-3">
		<label for="meta_description_ka" class="form-label">Meta Description (KA) <small class="text-muted">max 160 chars</small></label>
		<textarea
			name="meta_description_ka"
			id="meta_description_ka"
			rows="3"
			maxlength="160"
			class="form-control"
			placeholder="{{ Str::limit($product->short_description_ka ?? '', 155) }}"
		>{{ old('meta_description_ka', $product->meta_description_ka) }}</textarea>
		<div class="invalid-feedback" data-error-for="meta_description_ka"></div>
	</div>
	<div class="col-lg-6 mb-3">
		<label for="meta_description_en" class="form-label">Meta Description (EN) <small class="text-muted">max 160 chars</small></label>
		<textarea
			name="meta_description_en"
			id="meta_description_en"
			rows="3"
			maxlength="160"
			class="form-control"
			placeholder="{{ Str::limit($product->short_description_en ?? '', 155) }}"
		>{{ old('meta_description_en', $product->meta_description_en) }}</textarea>
		<div class="invalid-feedback" data-error-for="meta_description_en"></div>
	</div>
</div>
