@extends('admin.layout')

@section('title', 'Inquiry Details')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-0">Inquiry Details</h3>
            <p class="text-muted">Full customer request.</p>
        </div>
        <a href="{{ route('admin.inquiries.index') }}" class="btn btn-outline-secondary">Back to Inquiries</a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h6 class="text-muted">Customer</h6>
                    <p class="fw-semibold mb-1">{{ $inquiry->name }}</p>
                    <p class="mb-1">Phone: <a href="tel:{{ $inquiry->phone }}">{{ $inquiry->phone }}</a></p>
                    <p class="mb-0">Email: {{ $inquiry->email ?: 'N/A' }}</p>
                </div>
                <div class="col-lg-4">
                    <h6 class="text-muted">Product</h6>
                    @if ($inquiry->product)
                        <p class="fw-semibold mb-1">{{ $inquiry->product->name_ka }}</p>
                        <p class="text-muted mb-2">{{ $inquiry->product->name_en }}</p>
                        <a href="{{ route('admin.products.edit', $inquiry->product) }}" class="btn btn-outline-primary btn-sm">Open Product</a>
                    @else
                        <p class="text-muted mb-0">General inquiry</p>
                    @endif
                </div>
                <div class="col-lg-4">
                    <h6 class="text-muted">Meta</h6>
                    <p class="mb-1">Selected Color: {{ $inquiry->selected_color ?: '-' }}</p>
                    <p class="mb-1">Preferred: {{ $inquiry->preferred_contact ?: '-' }}</p>
                    <p class="mb-1">Locale: {{ $inquiry->locale }}</p>
                    <p class="mb-0">Date: {{ $inquiry->created_at?->format('Y-m-d H:i') }}</p>
                </div>
            </div>

            <hr>

            <h6 class="text-muted">Message</h6>
            <p class="mb-0">{{ $inquiry->message ?: 'No message provided.' }}</p>
        </div>
    </div>
@endsection
