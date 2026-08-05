@extends('admin.layout')

@section('title', 'Chatbot Content — Admin')

@section('content')
@fragment('content')
@php
    $faqHasErrors = $errors->hasAny(['question', 'question_en', 'answer', 'answer_en', 'category', 'category_en', 'sort_order', 'is_active', 'faq_id']);
    $oldFaqId = (int) old('faq_id', 0);
    $oldFaq = $oldFaqId ? $faqs->firstWhere('id', $oldFaqId) : null;
    $faqRestore = [
        'id' => $oldFaqId ?: null,
        'question' => old('question', ''),
        'question_en' => old('question_en', ''),
        'answer' => old('answer', ''),
        'answer_en' => old('answer_en', ''),
        'category' => old('category', ''),
        'category_en' => old('category_en', ''),
        'sort_order' => old('sort_order', 0),
        'is_active' => (bool) old('is_active', false),
    ];
    $faqRestoreUrl = $oldFaq ? route('admin.chatbot-content.faqs.update', $oldFaq) : route('admin.chatbot-content.faqs.store');
@endphp

<div data-page-title="Chatbot Content">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 grid-margin">
        <div>
            <h4 class="mb-2 mb-md-0">Chatbot Content</h4>
            <p class="text-muted mb-0 mt-1">
                Control the frontend contact details and the chatbot knowledge source from one admin page.
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('contact') }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary btn-sm">
                Open Contact Page
            </a>
            <form method="POST" action="{{ route('admin.chatbot-content.static-pages.sync') }}" class="d-inline">
                @csrf
                <button
                    type="submit"
                    class="btn btn-outline-success btn-sm"
                    onclick="return confirm('Sync About, Privacy, and Terms pages into chatbot content now?')"
                >
                    Sync Site Pages
                </button>
            </form>
            <a href="{{ route('admin.chatbot-testing') }}" data-pjax class="btn btn-outline-primary btn-sm">
                Test Chatbot
            </a>
            <button type="button" class="btn btn-primary btn-sm btn-add-faq">
                <i data-feather="plus" style="width:16px;height:16px;"></i> Add FAQ
            </button>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            {{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="alert alert-info border-0 shadow-sm">
        <div class="d-flex gap-3">
            <div class="flex-shrink-0">
                <i data-feather="database" style="width:20px;height:20px;"></i>
            </div>
            <div class="flex-grow-1">
                <strong>One source of truth</strong>
                <div class="mt-1">
                    These values power the frontend footer, contact page, and the chatbot support document.
                    FAQs and contact settings sync when you save them, and the about/privacy/terms pages can be synced with the button above.
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="card-title mb-1">Contact Settings</h6>
                <p class="text-muted mb-0 small">
                    Update the public contact details and the chatbot support profile in one place.
                </p>
            </div>
            @if($contactDocument)
                <span class="badge {{ $contactDocument->is_active ? 'bg-success' : 'bg-secondary' }}">
                    contact-main #{{ $contactDocument->id }}
                </span>
            @endif
        </div>

        <div class="card-body">
            <form method="POST" action="{{ route('admin.chatbot-content.contacts.update') }}">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    @foreach($contactFields as $field)
                        @php
                            $fieldValue = old($field['name'], data_get($contactSettings, $field['name']));
                            $fieldValue = is_null($fieldValue) ? '' : $fieldValue;
                            $fieldCol = $field['col'] ?? 'col-md-6';
                        @endphp

                        <div class="{{ $fieldCol }}">
                            <label for="{{ $field['name'] }}" class="form-label">{{ $field['label'] }}</label>

                            <input
                                type="{{ $field['type'] }}"
                                name="{{ $field['name'] }}"
                                id="{{ $field['name'] }}"
                                value="{{ $fieldValue }}"
                                placeholder="{{ $field['placeholder'] ?? '' }}"
                                class="form-control @error($field['name']) is-invalid @enderror"
                            >

                            @if(!empty($field['help']))
                                <div class="form-text">{{ $field['help'] }}</div>
                            @endif

                            @error($field['name'])
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    @endforeach
                </div>

                <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                    <a href="{{ route('contact') }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary">
                        Preview Contact Page
                    </a>
                    <button type="submit" class="btn btn-primary">
                        Save & Sync Chatbot
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="card-title mb-1">FAQs ({{ $faqs->count() }})</h6>
                <p class="text-muted mb-0 small">
                    These FAQs are one of the chatbot's direct answer sources alongside contact settings and synced site pages.
                </p>
            </div>
            <button type="button" class="btn btn-primary btn-sm btn-add-faq">
                <i data-feather="plus" style="width:16px;height:16px;"></i> Add FAQ
            </button>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:80px;">Order</th>
                            <th style="width:180px;">Category</th>
                            <th>Question</th>
                            <th>Answer Preview</th>
                            <th style="width:110px;">Status</th>
                            <th style="width:120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($faqs as $faq)
                            @php
                                $faqPayload = [
                                    'id' => $faq->id,
                                    'question' => $faq->question,
                                    'question_en' => $faq->question_en,
                                    'answer' => $faq->answer,
                                    'answer_en' => $faq->answer_en,
                                    'category' => $faq->category,
                                    'category_en' => $faq->category_en,
                                    'sort_order' => $faq->sort_order,
                                    'is_active' => (bool) $faq->is_active,
                                ];
                            @endphp

                            <tr>
                                <td>{{ $faq->sort_order }}</td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        {{ $faq->category ?: '—' }}
                                    </span>
                                </td>
                                <td class="fw-semibold">{{ $faq->question }}</td>
                                <td class="text-muted">
                                    {{ \Illuminate\Support\Str::limit((string) $faq->answer, 120) }}
                                </td>
                                <td>
                                    @if($faq->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <button
                                            type="button"
                                            class="btn btn-outline-primary btn-sm p-1 btn-edit-faq"
                                            data-faq='@json($faqPayload)'
                                            data-update-url="{{ route('admin.chatbot-content.faqs.update', $faq) }}"
                                            title="Edit"
                                        >
                                            <i data-feather="edit-2" style="width:14px;height:14px;"></i>
                                        </button>

                                        <form method="POST" action="{{ route('admin.chatbot-content.faqs.destroy', $faq) }}" class="d-inline" onsubmit="return confirm('Delete this FAQ?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm p-1" title="Delete">
                                                <i data-feather="trash-2" style="width:14px;height:14px;"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    No FAQs yet. Add the first one so the chatbot has a stronger answer source.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="faqModal" tabindex="-1" aria-labelledby="faqModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('admin.chatbot-content.faqs.store') }}" class="modal-content" id="faqForm" data-store-url="{{ route('admin.chatbot-content.faqs.store') }}">
            @csrf
            <input type="hidden" name="_method" id="faqMethod" value="">
            <input type="hidden" name="faq_id" id="faqId" value="{{ old('faq_id') }}">

            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="faqModalLabel">Add FAQ</h5>
                    <p class="text-muted mb-0 small">
                        Keep the answer concise and useful. This text can be reused by the chatbot.
                    </p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label for="faqQuestion" class="form-label">Question</label>
                        <input
                            type="text"
                            name="question"
                            id="faqQuestion"
                            value="{{ old('question') }}"
                            class="form-control @error('question') is-invalid @enderror"
                            maxlength="255"
                            required
                        >
                        @error('question')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label for="faqQuestionEn" class="form-label">Question (English)</label>
                        <input
                            type="text"
                            name="question_en"
                            id="faqQuestionEn"
                            value="{{ old('question_en') }}"
                            class="form-control @error('question_en') is-invalid @enderror"
                            maxlength="255"
                            required
                        >
                        @error('question_en')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label for="faqAnswer" class="form-label">Answer</label>
                        <textarea
                            name="answer"
                            id="faqAnswer"
                            rows="6"
                            class="form-control @error('answer') is-invalid @enderror"
                            required
                        >{{ old('answer') }}</textarea>
                        @error('answer')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label for="faqAnswerEn" class="form-label">Answer (English)</label>
                        <textarea
                            name="answer_en"
                            id="faqAnswerEn"
                            rows="6"
                            class="form-control @error('answer_en') is-invalid @enderror"
                            required
                        >{{ old('answer_en') }}</textarea>
                        @error('answer_en')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="faqCategory" class="form-label">Category</label>
                        <input
                            type="text"
                            name="category"
                            id="faqCategory"
                            value="{{ old('category') }}"
                            class="form-control @error('category') is-invalid @enderror"
                            maxlength="120"
                            placeholder="e.g. Shipping, Warranty, Contact"
                            required
                        >
                        @error('category')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="faqCategoryEn" class="form-label">Category (English)</label>
                        <input
                            type="text"
                            name="category_en"
                            id="faqCategoryEn"
                            value="{{ old('category_en') }}"
                            class="form-control @error('category_en') is-invalid @enderror"
                            maxlength="120"
                            placeholder="e.g. Delivery, Warranty, Contact"
                            required
                        >
                        @error('category_en')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="faqSortOrder" class="form-label">Sort order</label>
                        <input
                            type="number"
                            name="sort_order"
                            id="faqSortOrder"
                            value="{{ old('sort_order', 0) }}"
                            class="form-control @error('sort_order') is-invalid @enderror"
                            min="0"
                            max="99999"
                        >
                        @error('sort_order')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check form-switch mb-2">
                            <input type="hidden" name="is_active" value="0">
                            <input
                                type="checkbox"
                                name="is_active"
                                id="faqIsActive"
                                value="1"
                                class="form-check-input"
                                {{ old('is_active', true) ? 'checked' : '' }}
                            >
                            <label class="form-check-label" for="faqIsActive">Active</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="faqSubmitButton">Save FAQ</button>
            </div>
        </form>
    </div>
</div>

@endfragment
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const faqModalEl = document.getElementById('faqModal');
    const faqForm = document.getElementById('faqForm');
    const faqMethod = document.getElementById('faqMethod');
    const faqId = document.getElementById('faqId');
    const faqQuestion = document.getElementById('faqQuestion');
    const faqQuestionEn = document.getElementById('faqQuestionEn');
    const faqAnswer = document.getElementById('faqAnswer');
    const faqAnswerEn = document.getElementById('faqAnswerEn');
    const faqCategory = document.getElementById('faqCategory');
    const faqCategoryEn = document.getElementById('faqCategoryEn');
    const faqSortOrder = document.getElementById('faqSortOrder');
    const faqIsActive = document.getElementById('faqIsActive');
    const faqModalLabel = document.getElementById('faqModalLabel');
    const faqSubmitButton = document.getElementById('faqSubmitButton');
    const faqStoreUrl = faqForm.dataset.storeUrl;
    const modal = faqModalEl && window.bootstrap ? new bootstrap.Modal(faqModalEl) : null;

    function openFaqModal(faq, actionUrl) {
        if (!faqForm) {
            return;
        }

        faqForm.action = actionUrl || faqStoreUrl;
        faqMethod.value = faq && faq.id ? 'PATCH' : '';
        faqId.value = faq && faq.id ? faq.id : '';
        faqQuestion.value = faq && faq.question ? faq.question : '';
        faqQuestionEn.value = faq && faq.question_en ? faq.question_en : '';
        faqAnswer.value = faq && faq.answer ? faq.answer : '';
        faqAnswerEn.value = faq && faq.answer_en ? faq.answer_en : '';
        faqCategory.value = faq && faq.category ? faq.category : '';
        faqCategoryEn.value = faq && faq.category_en ? faq.category_en : '';
        faqSortOrder.value = faq && typeof faq.sort_order !== 'undefined' ? faq.sort_order : 0;
        faqIsActive.checked = !!(faq && faq.is_active);
        faqModalLabel.textContent = faq && faq.id ? 'Edit FAQ' : 'Add FAQ';
        faqSubmitButton.textContent = faq && faq.id ? 'Update FAQ' : 'Save FAQ';

        modal?.show();
    }

    document.querySelectorAll('.btn-add-faq').forEach((button) => {
        button.addEventListener('click', () => {
            openFaqModal({}, faqStoreUrl);
        });
    });

    document.querySelectorAll('.btn-edit-faq').forEach((button) => {
        button.addEventListener('click', () => {
            const faq = JSON.parse(button.dataset.faq || '{}');
            openFaqModal(faq, button.dataset.updateUrl || faqStoreUrl);
        });
    });

    const faqHasErrors = @json($faqHasErrors);
    const faqRestore = @json($faqRestore);
    const faqRestoreUrl = @json($faqRestoreUrl);

    if (faqHasErrors) {
        openFaqModal(faqRestore, faqRestoreUrl);
    }
});
</script>
@endpush
