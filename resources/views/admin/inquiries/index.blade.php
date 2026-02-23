@extends('admin.layout')

@section('title', 'Inquiries')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-0">Inquiries</h3>
            <p class="text-muted">Customer requests and product questions.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Product</th>
                            <th>Color</th>
                            <th>Message</th>
                            <th>Preferred</th>
                            <th>Date</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($inquiries as $inquiry)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $inquiry->name }}</div>
                                    <div class="text-muted small">{{ $inquiry->email ?: 'No email' }}</div>
                                </td>
                                <td>
                                    <div>{{ $inquiry->phone }}</div>
                                    <div class="text-muted small">{{ $inquiry->locale }}</div>
                                </td>
                                <td>
                                    @if ($inquiry->product)
                                        <a href="{{ route('admin.products.edit', $inquiry->product) }}" class="text-decoration-none">
                                            {{ $inquiry->product->name_ka }}
                                        </a>
                                        <div class="text-muted small">{{ $inquiry->product->name_en }}</div>
                                    @else
                                        <span class="text-muted">General</span>
                                    @endif
                                </td>
                                <td>{{ $inquiry->selected_color ?: '-' }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($inquiry->message ?: 'No message', 80) }}</td>
                                <td>{{ $inquiry->preferred_contact ?: '-' }}</td>
                                <td>{{ $inquiry->created_at?->format('Y-m-d') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.inquiries.show', $inquiry) }}" class="btn btn-outline-primary btn-sm">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted">No inquiries found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $inquiries->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection
