@extends('admin.layout')

@section('title', 'Chatbot Content — Admin')

@section('content')
@fragment('content')
<div data-page-title="Chatbot Content">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div><h4 class="mb-3 mb-md-0">Chatbot Content</h4></div>
        <div>
            <button type="button" class="btn btn-primary btn-sm" id="btnAddFaq">
                <i data-feather="plus" style="width:16px;height:16px;"></i> Add FAQ
            </button>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('status') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">{{ session('warning') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    {{-- Contact Settings --}}
    <div class="card mb-3">
        <div class="card-body">
            <h6 class="card-title mb-3">Contact Settings</h6>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Key</th><th>Value</th></tr></thead>
                    <tbody>
                        @foreach($contactSettings as $key => $setting)
                        <tr>
                            <td class="fw-bold">{{ $key }}</td>
                            <td>{{ $setting->value ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- FAQs --}}
    <div class="card">
        <div class="card-body">
            <h6 class="card-title mb-3">FAQs ({{ $faqs->count() }})</h6>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width:50px;">Order</th>
                            <th>Question (KA)</th>
                            <th>Question (EN)</th>
                            <th style="width:120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($faqs as $faq)
                        <tr>
                            <td>{{ $faq->sort_order }}</td>
                            <td>{{ Str::limit($faq->question_ka, 60) }}</td>
                            <td class="text-muted">{{ Str::limit($faq->question_en, 60) ?: '—' }}</td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-outline-primary btn-sm p-1 btn-edit-faq"
                                            data-faq='@json($faq)' title="Edit">
                                        <i data-feather="edit-2" style="width:14px;height:14px;"></i>
                                    </button>
                                    <form method="POST" action="{{ route('admin.chatbot-content.faqs.destroy', $faq) }}" class="d-inline" onsubmit="return confirm('Delete this FAQ?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm p-1" title="Delete">
                                            <i data-feather="trash-2" style="width:14px;height:14px;"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No FAQs yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endfragment
@endsection
