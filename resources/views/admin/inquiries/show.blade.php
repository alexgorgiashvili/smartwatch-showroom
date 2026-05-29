@extends('admin.layout')

@section('title', 'მოთხოვნა #' . $inquiry->id . ' — Admin')

@section('content')
@fragment('content')
<div data-page-title="მოთხოვნის დეტალები">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div>
            <h4 class="mb-3 mb-md-0">მოთხოვნა #{{ $inquiry->id }}</h4>
            <p class="text-muted small mb-0">{{ $inquiry->created_at->format('M d, Y H:i') }}</p>
        </div>
        <div>
            <a href="{{ route('admin.inquiries.index') }}" class="btn btn-outline-secondary btn-sm" data-pjax>
                <i data-feather="arrow-left" style="width:14px;height:14px;"></i> უკან
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Customer Information -->
        <div class="col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <h6 class="mb-0">მომხმარებლის ინფორმაცია</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted small" style="width:150px;">სახელი:</td>
                                <td class="fw-medium">{{ $inquiry->name }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted small">ტელეფონი:</td>
                                <td class="fw-medium">{{ $inquiry->phone }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted small">ემაილი:</td>
                                <td>{{ $inquiry->email ?: '—' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted small">სასურველი კონტაქტი:</td>
                                <td>
                                    @if($inquiry->preferred_contact)
                                        @if($inquiry->preferred_contact === 'phone')
                                            <span class="badge bg-primary">ტელეფონი</span>
                                        @elseif($inquiry->preferred_contact === 'email')
                                            <span class="badge bg-info">ემაილი</span>
                                        @elseif($inquiry->preferred_contact === 'whatsapp')
                                            <span class="badge bg-success">WhatsApp</span>
                                        @else
                                            <span class="badge bg-secondary">{{ $inquiry->preferred_contact }}</span>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted small">ენა:</td>
                                <td><span class="badge bg-secondary">{{ strtoupper($inquiry->locale) }}</span></td>
                            </tr>
                            @if($inquiry->selected_color)
                            <tr>
                                <td class="text-muted small">არჩეული ფერი:</td>
                                <td>{{ $inquiry->selected_color }}</td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Product Information -->
        <div class="col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <h6 class="mb-0">პროდუქტი</h6>
                </div>
                <div class="card-body">
                    @if($inquiry->product)
                        <div class="mb-3">
                            <h6 class="mb-2">{{ $inquiry->product->name_ka }}</h6>
                            @if($inquiry->product->name_en)
                                <p class="text-muted small mb-2">{{ $inquiry->product->name_en }}</p>
                            @endif
                        </div>
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                <tr>
                                    <td class="text-muted small" style="width:100px;">ფასი:</td>
                                    <td class="fw-medium">{{ number_format($inquiry->product->price, 2) }} {{ $inquiry->product->currency }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted small">Slug:</td>
                                    <td class="font-monospace small">{{ $inquiry->product->slug }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="mt-3">
                            <a href="{{ route('products.show', $inquiry->product->slug) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i data-feather="external-link" style="width:14px;height:14px;"></i> პროდუქტის ნახვა
                            </a>
                        </div>
                    @else
                        <div class="text-center text-muted py-4">
                            <i data-feather="package" style="width:48px;height:48px;"></i>
                            <p class="mt-2 mb-0">პროდუქტი არ არის მითითებული</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Message -->
    @if($inquiry->message)
    <div class="card">
        <div class="card-header bg-light">
            <h6 class="mb-0">შეტყობინება</h6>
        </div>
        <div class="card-body">
            <div class="p-3 bg-light rounded">
                <p class="mb-0" style="white-space: pre-wrap;">{{ $inquiry->message }}</p>
            </div>
        </div>
    </div>
    @endif
</div>
@endfragment
@endsection
