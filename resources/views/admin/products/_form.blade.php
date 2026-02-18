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
