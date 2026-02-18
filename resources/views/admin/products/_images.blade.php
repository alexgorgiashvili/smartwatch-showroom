<div class="card">
	<div class="card-body">
		<h5 class="card-title mb-3">Product Images</h5>
		<form method="POST" action="{{ route('admin.products.images.store', $product) }}" enctype="multipart/form-data" class="mb-4" id="image-upload-form" data-async="true">
			@csrf
			<div class="row g-3 align-items-end">
				<div class="col-lg-6">
					<label for="images" class="form-label">Upload Images (max 8)</label>
					<input type="file" name="images[]" id="images" class="form-control" multiple required accept="image/*">
				</div>
				<div class="col-lg-3">
					<label for="alt_en" class="form-label">Alt Text (EN)</label>
					<input type="text" name="alt_en" id="alt_en" class="form-control" value="{{ old('alt_en') }}">
				</div>
				<div class="col-lg-3">
					<label for="alt_ka" class="form-label">Alt Text (KA)</label>
					<input type="text" name="alt_ka" id="alt_ka" class="form-control" value="{{ old('alt_ka') }}">
				</div>
				<div class="col-12">
					<button type="submit" class="btn btn-primary">Upload Images</button>
				</div>
			</div>
		</form>

		<div class="row g-3" id="product-images-grid" data-product-id="{{ $product->id }}">
			@if ($product->images->isEmpty())
				<div class="col-12">
					<p class="text-muted mb-0">No images uploaded yet.</p>
				</div>
			@else
				@foreach ($product->images as $image)
					<div class="col-sm-6 col-md-4 col-lg-3">
						<div class="border rounded p-2 h-100">
							<div class="position-relative" style="aspect-ratio: 1; overflow: hidden;">
								<img src="{{ $image->url }}" alt="{{ $image->alt ?? $product->name }}" class="rounded w-100 h-100" style="object-fit: cover;">
								@if ($image->is_primary)
									<span class="badge bg-success position-absolute" style="top: 8px; left: 8px;">Primary</span>
								@endif
							</div>
							<div class="mt-2">
								<div class="fw-semibold small text-truncate">{{ basename($image->path) }}</div>
								<div class="text-muted small text-truncate">{{ $image->alt_en ?: 'No alt text' }}</div>
							</div>
							<div class="d-flex gap-2 mt-2">
								@if (! $image->is_primary)
									<form method="POST" action="{{ route('admin.products.images.primary', [$product, $image]) }}" data-async-action="primary">
										@csrf
										<button type="submit" class="btn btn-outline-primary btn-sm">Primary</button>
									</form>
								@endif
								<form method="POST" action="{{ route('admin.products.images.destroy', [$product, $image]) }}" data-async-action="delete">
									@csrf
									@method('DELETE')
									<button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
								</form>
							</div>
						</div>
					</div>
				@endforeach
			@endif
		</div>
	</div>
</div>
