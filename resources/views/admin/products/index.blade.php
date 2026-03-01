@extends('admin.layout')

@section('title', 'Products')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-0">Products</h3>
            <p class="text-muted">Manage your catalog and pricing.</p>
        </div>
        <a href="{{ route('admin.products.create') }}" class="btn btn-primary">Add Product</a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            @php
                                $price = $product->price !== null ? number_format((float) $product->price, 2) : null;
                                $salePrice = $product->sale_price !== null ? number_format((float) $product->sale_price, 2) : null;
                                $primaryImage = $product->primaryImage?->thumbnail_url;
                                $fallbackImage = asset('assets/images/others/placeholder.jpg');
                            @endphp
                            <tr>
                                <td>
                                    <button
                                        type="button"
                                        class="btn btn-link p-0"
                                        data-bs-toggle="modal"
                                        data-bs-target="#product-images-{{ $product->id }}"
                                    >
                                        <img
                                            src="{{ $primaryImage ?: $fallbackImage }}"
                                            alt="{{ $product->name_ka }}"
                                            class="rounded"
                                            style="width: 48px; height: 48px; object-fit: cover;"
                                        >
                                    </button>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $product->name_ka }}</div>
                                    <div class="text-muted small">{{ $product->name_en }}</div>
                                    <div class="text-muted small">Slug: {{ $product->slug }}</div>
                                </td>
                                <td>
                                    @if ($salePrice)
                                        <div class="fw-semibold">{{ $salePrice }} {{ $product->currency }}</div>
                                        <div class="text-muted text-decoration-line-through small">{{ $price }} {{ $product->currency }}</div>
                                    @elseif ($price)
                                        <div class="fw-semibold">{{ $price }} {{ $product->currency }}</div>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($product->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                    @if ($product->featured)
                                        <span class="badge bg-primary">Featured</span>
                                    @endif
                                </td>
                                <td>{{ $product->updated_at?->format('Y-m-d') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-outline-primary btn-sm">Edit</a>
                                    <form method="POST" action="{{ route('admin.products.destroy', $product) }}" class="d-inline" onsubmit="return confirm('Delete this product?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">No products found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($products->isNotEmpty())
        @foreach ($products as $product)
            <div class="modal fade" id="product-images-{{ $product->id }}" tabindex="-1" aria-labelledby="productImagesLabel-{{ $product->id }}" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="productImagesLabel-{{ $product->id }}">Images: {{ $product->name_ka }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            @if ($product->images->isEmpty())
                                <p class="text-muted mb-0">No images uploaded yet.</p>
                            @else
                                <div id="productCarousel-{{ $product->id }}" class="carousel slide product-carousel" data-bs-ride="carousel" data-bs-interval="false">
                                    <div class="carousel-inner">
                                        @foreach ($product->images as $image)
                                            <div class="carousel-item @if ($loop->first) active @endif">
                                                <div class="d-flex flex-column align-items-center">
                                                    <img src="{{ $image->url }}" alt="{{ $image->alt ?? $product->name_ka }}" class="d-block w-100 rounded" style="height: auto;">
                                                    <div class="d-flex align-items-center justify-content-between w-100 mt-2">
                                                        <span class="text-muted small text-truncate">{{ $image->alt ?? 'No alt text' }}</span>
                                                        @if ($image->is_primary)
                                                            <span class="badge bg-success">Primary</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <button class="carousel-control-prev" type="button" data-bs-target="#productCarousel-{{ $product->id }}" data-bs-slide="prev">
                                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                        <span class="visually-hidden">Previous</span>
                                    </button>
                                    <button class="carousel-control-next" type="button" data-bs-target="#productCarousel-{{ $product->id }}" data-bs-slide="next">
                                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                        <span class="visually-hidden">Next</span>
                                    </button>
                                </div>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-outline-primary">Manage Images</a>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
@endsection

@push('styles')
    <style>
        .product-carousel .carousel-control-prev-icon,
        .product-carousel .carousel-control-next-icon {
            background-color: rgba(0, 0, 0, 0.6);
            border-radius: 50%;
            background-size: 60% 60%;
            width: 40px;
            height: 40px;
        }
        .product-carousel .carousel-control-prev,
        .product-carousel .carousel-control-next {
            width: 12%;
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.modal').forEach((modal) => {
                modal.addEventListener('shown.bs.modal', () => {
                    const carousel = modal.querySelector('.carousel');
                    if (carousel && window.bootstrap) {
                        window.bootstrap.Carousel.getOrCreateInstance(carousel, {
                            interval: false,
                            ride: false,
                        });
                    }
                });
            });
        });
    </script>
@endpush
