<div class="col-md-6 col-lg-4">
    @php
        $image = $product->primaryImage ?? $product->images->first();
    @endphp

    <a href="{{ route('products.show', $product) }}" class="text-decoration-none">
        <div class="card h-100">
            @if ($image)
                <img src="{{ $image->url }}" alt="{{ $image->alt ?? $product->name }}" class="card-img-top" style="height: 240px; object-fit: cover;">
            @else
                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 240px;">
                    <small class="text-muted text-uppercase">{{ __('ui.catalog_title') }}</small>
                </div>
            @endif

            <div class="card-body">
                <h5 class="card-title fw-semibold mb-2">{{ $product->name }}</h5>
                <p class="text-primary fw-bold mb-3">
                    @if ($product->price)
                        {{ number_format((float) $product->price, 2) }} {{ $product->currency }}
                    @else
                        {{ __('ui.price_on_request') }}
                    @endif
                </p>

                <div class="d-flex gap-3 small text-muted">
                    <div class="d-flex align-items-center gap-1">
                        <i class="bi bi-sim"></i>
                        <span>{{ $product->sim_support ? __('ui.yes') : __('ui.no') }}</span>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <i class="bi bi-geo-alt"></i>
                        <span>{{ $product->gps_features ? 'GPS' : '-' }}</span>
                    </div>
                    @if ($product->warranty_months)
                        <div class="d-flex align-items-center gap-1">
                            <i class="bi bi-shield-check"></i>
                            <span>{{ $product->warranty_months }}m</span>
                        </div>
                    @endif
                    @if ($product->battery_capacity_mah)
                        <div class="d-flex align-items-center gap-1">
                            <i class="bi bi-battery-half"></i>
                            <span>{{ $product->battery_capacity_mah }}mAh</span>
                        </div>
                    @endif
                    @if ($product->screen_size)
                        <div class="d-flex align-items-center gap-1">
                            <i class="bi bi-smartwatch"></i>
                            <span>{{ $product->screen_size }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </a>
</div>
