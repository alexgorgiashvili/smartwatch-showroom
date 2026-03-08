@extends('admin.layout')

@section('title', 'ჩატბოტ ლაბი - ქეისები')

@section('content')
@if (session('status'))
    <div class="alert alert-success" role="alert">{{ session('status') }}</div>
@endif

@if (session('warning'))
    <div class="alert alert-warning" role="alert">{{ session('warning') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-warning" role="alert">გთხოვთ, ქეისის ველები გადაამოწმოთ და ისევ სცადოთ.</div>
@endif

@unless ($casesReady ?? false)
    <div class="alert alert-warning" role="alert">ქეისების ცხრილი ჯერ არ არსებობს. ქეისის შექმნამდე ან ჩასწორებამდე გაუშვით <code>php artisan migrate</code>.</div>
@endunless

@if (($caseDiagnostics ?? []) !== [])
    @php
        $blockingCases = collect($caseDiagnostics)->filter(fn ($diagnostic) => ($diagnostic['health'] ?? 'healthy') === 'blocking')->count();
        $warningCases = collect($caseDiagnostics)->filter(fn ($diagnostic) => ($diagnostic['health'] ?? 'healthy') === 'warning')->count();
    @endphp

    @if ($blockingCases > 0 || $warningCases > 0)
        <div class="alert {{ $blockingCases > 0 ? 'alert-warning' : 'alert-info' }}" role="alert">
            <strong>ქეისების მოკლე შეჯამება:</strong>
            კრიტიკული: {{ $blockingCases }},
            გაფრთხილება: {{ $warningCases }}.
            პრობლემიანი ქეისები გადაამოწმეთ, სანამ მასობრივ გაშვებას დაიწყებთ.
        </div>
    @endif
@endif

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="mb-1">სატესტო ქეისები</h4>
        <p class="text-muted mb-0">აქ ინახება ჩატბოტის ქეისები ძიებით, tag-ებით და აქტიური/არააქტიური სტატუსით.</p>
    </div>
    <div class="d-flex gap-2">
        <span class="badge bg-light text-dark border">სულ: {{ $labStats['total'] ?? 0 }}</span>
        <span class="badge bg-success-subtle text-success border">აქტიური: {{ $labStats['active'] ?? 0 }}</span>
        <span class="badge bg-secondary-subtle text-secondary border">არააქტიური: {{ $labStats['inactive'] ?? 0 }}</span>
    </div>
</div>

<div class="d-flex gap-2 mb-4">
    <a href="{{ route('admin.chatbot-lab.index') }}" class="btn btn-outline-primary btn-sm">ხელით ტესტი</a>
    <a href="{{ route('admin.chatbot-lab.cases.index') }}" class="btn btn-primary btn-sm">სატესტო ქეისები</a>
    <a href="{{ route('admin.chatbot-lab.runs.index') }}" class="btn btn-outline-primary btn-sm">სატესტო გაშვებები</a>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-3">ახალი ქეისი</h5>

                <form method="POST" action="{{ route('admin.chatbot-lab.cases.store') }}" class="d-flex flex-column gap-3" data-case-diagnostics-form="1" data-preview-url="{{ route('admin.chatbot-lab.cases.preview-diagnostics') }}">
                    @csrf
                    <input type="hidden" name="search" value="{{ $filters['search'] ?? '' }}">
                    <input type="hidden" name="status" value="{{ $filters['status'] ?? 'all' }}">
                    <input type="hidden" name="tag" value="{{ $filters['tag'] ?? '' }}">
                    <input type="hidden" name="page" value="{{ $cases->currentPage() }}">
                    <div>
                        <label class="form-label">სათაური</label>
                        <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" required>
                    </div>
                    <div>
                        <label class="form-label">მთავარი კითხვა</label>
                        <textarea name="prompt" rows="4" class="form-control @error('prompt') is-invalid @enderror" required>{{ old('prompt') }}</textarea>
                    </div>
                    <div>
                        <label class="form-label">საუბრის კონტექსტი</label>
                        <textarea name="conversation_context" rows="4" class="form-control @error('conversation_context') is-invalid @enderror" placeholder="თითო წინა user prompt ცალკე ხაზზე">{{ old('conversation_context') }}</textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">მოსალოდნელი intent</label>
                            <input type="text" name="expected_intent" class="form-control" value="{{ old('expected_intent') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ტეგები</label>
                            <input type="text" name="tags" class="form-control" value="{{ old('tags') }}" placeholder="budget, greeting">
                        </div>
                    </div>
                    <div>
                        <label class="form-label">მოსალოდნელი საკვანძო სიტყვები</label>
                        <textarea name="expected_keywords" rows="2" class="form-control" placeholder="მძიმით ან ახალი ხაზით გაყოფილი">{{ old('expected_keywords') }}</textarea>
                    </div>
                    <div>
                        <label class="form-label">მოსალოდნელი პროდუქტის slug-ები</label>
                        <textarea name="expected_product_slugs" rows="2" class="form-control" placeholder="მძიმით ან ახალი ხაზით გაყოფილი">{{ old('expected_product_slugs') }}</textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">ფასთან დაკავშირებული მოლოდინი</label>
                            <input type="text" name="expected_price_behavior" class="form-control" value="{{ old('expected_price_behavior') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">მარაგთან დაკავშირებული მოლოდინი</label>
                            <input type="text" name="expected_stock_behavior" class="form-control" value="{{ old('expected_stock_behavior') }}">
                        </div>
                    </div>
                    <div>
                        <label class="form-label">შენიშვნები</label>
                        <textarea name="reviewer_notes" rows="3" class="form-control">{{ old('reviewer_notes') }}</textarea>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                            <label class="form-check-label">აქტიური ქეისი</label>
                        </div>
                        <button type="submit" class="btn btn-primary">ქეისის დამატება</button>
                    </div>

                    <div class="border rounded bg-light p-3 small d-none" data-case-diagnostics-preview>
                        <div class="fw-semibold mb-2">ქეისის სწრაფი დიაგნოსტიკა</div>
                        <div data-case-diagnostics-health class="mb-2"></div>
                        <div data-case-diagnostics-blocking></div>
                        <div data-case-diagnostics-warning></div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.chatbot-lab.cases.index') }}" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label">ძებნა</label>
                        <input type="text" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="სათაური, კითხვა ან შენიშვნა">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">სტატუსი</label>
                        <select name="status" class="form-select">
                            <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>ყველა</option>
                            <option value="active" @selected(($filters['status'] ?? '') === 'active')>აქტიური</option>
                            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>არააქტიური</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">ტეგი</label>
                        <input type="text" name="tag" class="form-control" value="{{ $filters['tag'] ?? '' }}" placeholder="budget">
                    </div>
                    <div class="col-md-1 d-grid">
                        <button type="submit" class="btn btn-outline-primary">ძებნა</button>
                    </div>
                </form>
            </div>
        </div>

        @forelse ($cases as $case)
            @php
                $diagnostic = $caseDiagnostics[$case->id] ?? ['health' => 'healthy', 'blocking_issues' => [], 'warning_issues' => [], 'duplicate_case_ids' => []];
                $healthBadgeClass = match ($diagnostic['health']) {
                    'blocking' => 'bg-danger-subtle text-danger border',
                    'warning' => 'bg-warning-subtle text-warning border',
                    default => 'bg-success-subtle text-success border',
                };
                $healthLabel = match ($diagnostic['health']) {
                    'blocking' => 'კრიტიკული',
                    'warning' => 'გასაფრთხილებელი',
                    default => 'ჯანმრთელი',
                };
            @endphp
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <h5 class="mb-1">{{ $case->title }}</h5>
                            <div class="text-muted small">წყარო: {{ $case->source ?? 'manual' }}@if($case->source_reference) ({{ $case->source_reference }})@endif | განახლდა: {{ optional($case->updated_at)->format('Y-m-d H:i') }}</div>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <span class="badge {{ $case->is_active ? 'bg-success-subtle text-success border' : 'bg-secondary-subtle text-secondary border' }}">{{ $case->is_active ? 'აქტიური' : 'არააქტიური' }}</span>
                            <span class="badge {{ $healthBadgeClass }}">{{ $healthLabel }}</span>
                        </div>
                    </div>

                    @if (($diagnostic['blocking_issues'] ?? []) !== [] || ($diagnostic['warning_issues'] ?? []) !== [])
                        <div class="border rounded bg-light p-3 mb-3 small">
                            @foreach (($diagnostic['blocking_issues'] ?? []) as $issue)
                                <div class="text-danger mb-1"><strong>კრიტიკული:</strong> {{ $issue }}</div>
                            @endforeach
                            @foreach (($diagnostic['warning_issues'] ?? []) as $issue)
                                <div class="text-warning mb-1"><strong>გაფრთხილება:</strong> {{ $issue }}</div>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.chatbot-lab.cases.update', $case) }}" class="row g-3" data-case-diagnostics-form="1" data-preview-url="{{ route('admin.chatbot-lab.cases.preview-diagnostics-existing', $case) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="search" value="{{ $filters['search'] ?? '' }}">
                        <input type="hidden" name="status" value="{{ $filters['status'] ?? 'all' }}">
                        <input type="hidden" name="tag" value="{{ $filters['tag'] ?? '' }}">
                        <input type="hidden" name="page" value="{{ $cases->currentPage() }}">

                        <div class="col-md-6">
                            <label class="form-label">სათაური</label>
                            <input type="text" name="title" class="form-control" value="{{ $case->title }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">მოსალოდნელი intent</label>
                            <input type="text" name="expected_intent" class="form-control" value="{{ $case->expected_intent }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">ტეგები</label>
                            <input type="text" name="tags" class="form-control" value="{{ implode(', ', $case->tags_json ?? []) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">მთავარი კითხვა</label>
                            <textarea name="prompt" rows="3" class="form-control" required>{{ $case->prompt }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">საუბრის კონტექსტი</label>
                            <textarea name="conversation_context" rows="3" class="form-control">{{ implode("\n", $case->conversation_context_json ?? []) }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">შენიშვნები</label>
                            <textarea name="reviewer_notes" rows="3" class="form-control">{{ $case->reviewer_notes }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">მოსალოდნელი საკვანძო სიტყვები</label>
                            <textarea name="expected_keywords" rows="2" class="form-control">{{ implode("\n", $case->expected_keywords_json ?? []) }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">მოსალოდნელი პროდუქტის slug-ები</label>
                            <textarea name="expected_product_slugs" rows="2" class="form-control">{{ implode("\n", $case->expected_product_slugs_json ?? []) }}</textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">ფასთან დაკავშირებული მოლოდინი</label>
                            <input type="text" name="expected_price_behavior" class="form-control" value="{{ $case->expected_price_behavior }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">მარაგთან დაკავშირებული მოლოდინი</label>
                            <input type="text" name="expected_stock_behavior" class="form-control" value="{{ $case->expected_stock_behavior }}">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" {{ $case->is_active ? 'checked' : '' }}>
                                <label class="form-check-label">აქტიური ქეისი</label>
                            </div>
                        </div>
                        <div class="col-12 d-flex justify-content-between align-items-center">
                            <div class="small text-muted">შემქმნელი: {{ optional($case->creator)->name ?? 'უცნობი' }}</div>
                            <button type="submit" class="btn btn-success">ცვლილების შენახვა</button>
                        </div>

                        <div class="col-12">
                            <div class="border rounded bg-light p-3 small d-none" data-case-diagnostics-preview>
                                <div class="fw-semibold mb-2">ქეისის სწრაფი დიაგნოსტიკა</div>
                                <div data-case-diagnostics-health class="mb-2"></div>
                                <div data-case-diagnostics-blocking></div>
                                <div data-case-diagnostics-warning></div>
                            </div>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('admin.chatbot-lab.cases.destroy', $case) }}" class="mt-3" onsubmit="return confirm('Delete this training case?');">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="search" value="{{ $filters['search'] ?? '' }}">
                        <input type="hidden" name="status" value="{{ $filters['status'] ?? 'all' }}">
                        <input type="hidden" name="tag" value="{{ $filters['tag'] ?? '' }}">
                        <input type="hidden" name="page" value="{{ $cases->currentPage() }}">
                        <button type="submit" class="btn btn-outline-danger">წაშლა</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-0">ქეისები ჯერ არ არის. დაამატეთ ხელით ან შეინახეთ manual test-ის შედეგი ქეისად.</p>
                </div>
            </div>
        @endforelse

        @if (method_exists($cases, 'links'))
            <div class="mt-4">{{ $cases->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const forms = Array.from(document.querySelectorAll('[data-case-diagnostics-form="1"]'));
    if (forms.length === 0) {
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const renderPreview = (container, diagnostics) => {
        if (!container) {
            return;
        }

        const health = container.querySelector('[data-case-diagnostics-health]');
        const blocking = container.querySelector('[data-case-diagnostics-blocking]');
        const warning = container.querySelector('[data-case-diagnostics-warning]');
        const healthLabel = diagnostics.health || 'healthy';
        const blockingIssues = diagnostics.blocking_issues || [];
        const warningIssues = diagnostics.warning_issues || [];

        container.classList.remove('d-none');

        if (health) {
            const badgeClass = healthLabel === 'blocking'
                ? 'text-danger'
                : (healthLabel === 'warning' ? 'text-warning' : 'text-success');
            const translatedHealth = healthLabel === 'blocking'
                ? 'კრიტიკული'
                : (healthLabel === 'warning' ? 'გასაფრთხილებელი' : 'ჯანმრთელი');
            health.innerHTML = `<strong>მდგომარეობა:</strong> <span class="${badgeClass}">${translatedHealth}</span>`;
        }

        if (blocking) {
            blocking.innerHTML = blockingIssues.length > 0
                ? blockingIssues.map((issue) => `<div class="text-danger mb-1"><strong>კრიტიკული:</strong> ${issue}</div>`).join('')
                : '';
        }

        if (warning) {
            warning.innerHTML = warningIssues.length > 0
                ? warningIssues.map((issue) => `<div class="text-warning mb-1"><strong>გაფრთხილება:</strong> ${issue}</div>`).join('')
                : (blockingIssues.length === 0 ? '<div class="text-success">აშკარა პრობლემა არ დაფიქსირდა.</div>' : '');
        }
    };

    forms.forEach((form) => {
        const previewUrl = form.dataset.previewUrl;
        const preview = form.querySelector('[data-case-diagnostics-preview]');
        let timeoutId = null;

        const syncPreview = async () => {
            const formData = new FormData(form);

            try {
                const response = await fetch(previewUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                if (payload.diagnostics) {
                    renderPreview(preview, payload.diagnostics);
                }
            } catch (error) {
            }
        };

        const schedulePreview = () => {
            window.clearTimeout(timeoutId);
            timeoutId = window.setTimeout(syncPreview, 350);
        };

        form.querySelectorAll('input, textarea, select').forEach((field) => {
            field.addEventListener('input', schedulePreview);
            field.addEventListener('change', schedulePreview);
        });
    });
});
</script>
@endpush
