@extends('admin.layout')

@section('title', 'მოთხოვნები — Admin')

@section('content')
@fragment('content')
<div data-page-title="მოთხოვნები">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div>
            <h4 class="mb-3 mb-md-0">მოთხოვნები (Inquiries)</h4>
            <p class="text-muted small mb-0">სულ: {{ number_format($totalCount) }} | დღეს: {{ number_format($todayCount) }}</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.inquiries.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small">ძებნა</label>
                    <input type="text" name="search" class="form-control form-control-sm" value="{{ $filters['search'] }}" placeholder="სახელი, ტელეფონი, ემაილი...">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">ენა</label>
                    <select name="locale" class="form-select form-select-sm">
                        <option value="">ყველა</option>
                        <option value="ka" {{ $filters['locale'] === 'ka' ? 'selected' : '' }}>ქართული (KA)</option>
                        <option value="en" {{ $filters['locale'] === 'en' ? 'selected' : '' }}>English (EN)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">სასურველი კონტაქტი</label>
                    <select name="preferred_contact" class="form-select form-select-sm">
                        <option value="">ყველა</option>
                        <option value="phone" {{ $filters['preferred_contact'] === 'phone' ? 'selected' : '' }}>ტელეფონი</option>
                        <option value="email" {{ $filters['preferred_contact'] === 'email' ? 'selected' : '' }}>ემაილი</option>
                        <option value="whatsapp" {{ $filters['preferred_contact'] === 'whatsapp' ? 'selected' : '' }}>WhatsApp</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm me-2">გაფილტვრა</button>
                    <a href="{{ route('admin.inquiries.index') }}" class="btn btn-outline-secondary btn-sm">გასუფთავება</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Inquiries Table -->
    <div class="card">
        <div class="card-body">
            @if($inquiries->isEmpty())
                <div class="text-center text-muted py-5">
                    <i data-feather="inbox" style="width:48px;height:48px;"></i>
                    <p class="mt-2">მოთხოვნები არ მოიძებნა</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="small">მომხმარებელი</th>
                                <th class="small">ტელეფონი</th>
                                <th class="small">ემაილი</th>
                                <th class="small">პროდუქტი</th>
                                <th class="small">კონტაქტი</th>
                                <th class="small">შეტყობინება</th>
                                <th class="small">თარიღი</th>
                                <th class="small" style="width:80px;">მოქმედება</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($inquiries as $inquiry)
                            <tr>
                                <td>
                                    <div class="fw-medium">{{ $inquiry->name }}</div>
                                    <span class="badge bg-secondary-subtle text-secondary border small">{{ strtoupper($inquiry->locale) }}</span>
                                </td>
                                <td class="small">{{ $inquiry->phone }}</td>
                                <td class="small">{{ $inquiry->email ?: '—' }}</td>
                                <td class="small">
                                    @if($inquiry->product)
                                        {{ $inquiry->product->name_ka }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($inquiry->preferred_contact)
                                        @if($inquiry->preferred_contact === 'phone')
                                            <span class="badge bg-primary-subtle text-primary border">ტელეფონი</span>
                                        @elseif($inquiry->preferred_contact === 'email')
                                            <span class="badge bg-info-subtle text-info border">ემაილი</span>
                                        @elseif($inquiry->preferred_contact === 'whatsapp')
                                            <span class="badge bg-success-subtle text-success border">WhatsApp</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary border">{{ $inquiry->preferred_contact }}</span>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="small">{{ Str::limit($inquiry->message, 50) ?: '—' }}</td>
                                <td class="small text-nowrap">{{ $inquiry->created_at->format('M d, Y H:i') }}</td>
                                <td>
                                    <a href="{{ route('admin.inquiries.show', $inquiry) }}" class="btn btn-sm btn-outline-primary" data-pjax>
                                        <i data-feather="eye" style="width:14px;height:14px;"></i>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($inquiries->hasPages())
                <div class="mt-3">
                    {{ $inquiries->appends($filters)->links() }}
                </div>
                @endif
            @endif
        </div>
    </div>
</div>
@endfragment
@endsection
