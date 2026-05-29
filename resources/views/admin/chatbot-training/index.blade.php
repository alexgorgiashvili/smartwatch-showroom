@extends('admin.layout')

@section('title', 'Chatbot Training — Admin')

@section('content')
@fragment('content')
<div data-page-title="Chatbot Training">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin gap-2">
        <div>
            <h4 class="mb-1">Chatbot Training</h4>
            <p class="text-muted mb-0">ფაილური კონტროლის პანელი training batch-ების, review queue-ს და დეტალური flow inspector-ისთვის.</p>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link {{ $activeTab === 'overview' ? 'active' : '' }}" data-pjax href="{{ route('admin.chatbot-training', ['tab' => 'overview']) }}">Overview</a></li>
        <li class="nav-item"><a class="nav-link {{ $activeTab === 'batches' ? 'active' : '' }}" data-pjax href="{{ route('admin.chatbot-training', ['tab' => 'batches']) }}">Batches</a></li>
        <li class="nav-item"><a class="nav-link {{ $activeTab === 'runs' ? 'active' : '' }}" data-pjax href="{{ route('admin.chatbot-training', ['tab' => 'runs']) }}">Runs</a></li>
        <li class="nav-item"><a class="nav-link {{ $activeTab === 'reviews' ? 'active' : '' }}" data-pjax href="{{ route('admin.chatbot-training', ['tab' => 'reviews']) }}">Reviews</a></li>
        <li class="nav-item"><a class="nav-link {{ $activeTab === 'flow' ? 'active' : '' }}" data-pjax href="{{ route('admin.chatbot-training', ['tab' => 'flow']) }}">Flow Inspector</a></li>
    </ul>

    <div class="row g-3 mb-3">
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card"><div class="card-body text-center"><div class="text-muted small">Cascade Requests</div><div class="h3 mt-2 mb-0">{{ number_format($snapshot['request_count'] ?? 0) }}</div></div></div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card"><div class="card-body text-center"><div class="text-muted small">Batch-ები</div><div class="h3 mt-2 mb-0">{{ number_format($snapshot['batch_count'] ?? 0) }}</div></div></div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card"><div class="card-body text-center"><div class="text-muted small">Run-ები</div><div class="h3 mt-2 mb-0">{{ number_format($snapshot['run_count'] ?? 0) }}</div></div></div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card border-warning"><div class="card-body text-center"><div class="text-muted small">Pending Review</div><div class="h3 mt-2 mb-0 text-warning">{{ number_format($snapshot['pending_review_count'] ?? 0) }}</div></div></div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card border-success"><div class="card-body text-center"><div class="text-muted small">Approved Review</div><div class="h3 mt-2 mb-0 text-success">{{ number_format($snapshot['approved_review_count'] ?? 0) }}</div></div></div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card border-info"><div class="card-body text-center"><div class="text-muted small">Question History</div><div class="h3 mt-2 mb-0 text-info">{{ number_format($snapshot['question_history_count'] ?? 0) }}</div></div></div>
        </div>
    </div>

    @if($activeTab === 'overview')
        <div class="row g-3">
            <div class="col-lg-5">
                <div class="card mb-3">
                    <div class="card-header bg-light"><h6 class="mb-0">Cascade Question Request</h6></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.chatbot-training.request-generation') }}" class="row g-3">
                            @csrf
                            <div class="col-12">
                                <label class="form-label small">სათაური</label>
                                <input type="text" name="name" class="form-control" placeholder="მაგ: Cascade Batch 01">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">რაოდენობა</label>
                                <input type="number" name="count" class="form-control" min="3" max="100" value="20" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small d-block">კატეგორიები</label>
                                <div class="d-flex flex-wrap gap-3">
                                    @foreach(['product_discovery' => 'პროდუქტის მოძებნა', 'comparison' => 'შედარება', 'pricing_stock' => 'ფასი/მარაგი', 'delivery_warranty' => 'მიტანა/გარანტია', 'vague_georgian' => 'ბუნდოვანი ქართული'] as $value => $label)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="categories[]" value="{{ $value }}" id="cat_{{ $value }}">
                                            <label class="form-check-label small" for="cat_{{ $value }}">{{ $label }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small">დამატებითი მითითება Cascade-სთვის</label>
                                <textarea name="notes" class="form-control" rows="3" placeholder="მაგ: აქცენტი გააკეთე ბუნდოვან ქართულ კითხვებზე, მშობლის რეალურ ენაზე, ნუ გაიმეორებ ძველ ფორმულირებებს."></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Create Cascade Request</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header bg-light"><h6 class="mb-0">Template Batch</h6></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.chatbot-training.generate-batch') }}" class="row g-3">
                            @csrf
                            <div class="col-12">
                                <label class="form-label small">სათაური</label>
                                <input type="text" name="name" class="form-control" placeholder="მაგ: Quick Template Batch">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">რაოდენობა</label>
                                <input type="number" name="count" class="form-control" min="5" max="100" value="20" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small d-block">კატეგორიები</label>
                                <div class="d-flex flex-wrap gap-3">
                                    @foreach(['product_discovery' => 'პროდუქტის მოძებნა', 'comparison' => 'შედარება', 'pricing_stock' => 'ფასი/მარაგი', 'delivery_warranty' => 'მიტანა/გარანტია', 'vague_georgian' => 'ბუნდოვანი ქართული'] as $value => $label)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="categories[]" value="{{ $value }}" id="template_cat_{{ $value }}">
                                            <label class="form-check-label small" for="template_cat_{{ $value }}">{{ $label }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-outline-secondary">Generate Template Questions</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card mb-3">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Cascade Requests</h6>
                        <span class="small text-muted">History: {{ number_format($historySummary['total_questions'] ?? 0) }}</span>
                    </div>
                    <div class="card-body p-0">
                        @if(empty($generationRequests))
                            <div class="p-4 text-muted">ჯერ Cascade generation request არ შექმნილა.</div>
                        @else
                            @foreach(array_slice($generationRequests, 0, 5) as $request)
                                <div class="border-bottom p-3">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                                        <div>
                                            <div class="fw-semibold">{{ $request['name'] ?? $request['id'] }}</div>
                                            <div class="text-muted small font-monospace">{{ $request['id'] ?? '—' }}</div>
                                            <div class="text-muted small">{{ $request['count_requested'] ?? 0 }} კითხვა · {{ implode(', ', $request['categories'] ?? []) ?: 'ყველა კატეგორია' }}</div>
                                        </div>
                                        <div>
                                            @php $requestStatus = $request['status'] ?? 'pending'; @endphp
                                            <span class="badge {{ $requestStatus === 'completed' ? 'bg-success-subtle text-success border' : 'bg-warning-subtle text-warning border' }}">{{ $requestStatus }}</span>
                                        </div>
                                    </div>

                                    @if(!empty($request['notes']))
                                        <div class="small text-muted mb-3">{{ $request['notes'] }}</div>
                                    @endif

                                    <div class="mb-3">
                                        <label class="form-label small">Cascade Prompt</label>
                                        <textarea class="form-control form-control-sm" rows="5" readonly>{{ $request['cascade_prompt'] ?? '' }}</textarea>
                                    </div>

                                    @if(($request['status'] ?? 'pending') !== 'completed')
                                        <form method="POST" action="{{ route('admin.chatbot-training.import-generated-batch', $request['id']) }}" class="row g-3">
                                            @csrf
                                            <div class="col-12">
                                                <label class="form-label small">Paste Cascade JSON</label>
                                                <textarea name="payload" class="form-control form-control-sm" rows="6" placeholder="{&quot;questions&quot;:[{&quot;question&quot;:&quot;...&quot;,&quot;category&quot;:&quot;product_discovery&quot;,&quot;difficulty&quot;:&quot;easy&quot;}]}" required></textarea>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small">Batch name</label>
                                                <input type="text" name="batch_name" class="form-control form-control-sm" placeholder="Optional batch title">
                                            </div>
                                            <div class="col-md-6 d-flex align-items-end">
                                                <button type="submit" class="btn btn-sm btn-outline-primary">Import Generated Batch</button>
                                            </div>
                                        </form>
                                    @else
                                        <div class="small text-success">დაიმპორტდა batch: {{ $request['imported_batch_id'] ?? '—' }}</div>
                                    @endif
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header bg-light"><h6 class="mb-0">Manual Flow Run</h6></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.chatbot-training.manual-flow') }}" class="row g-3">
                            @csrf
                            <div class="col-12">
                                <label class="form-label small">ტესტური კითხვა</label>
                                <textarea name="question" class="form-control" rows="3" placeholder="მაგ: 8 წლის ბავშვისთვის GPS საათი მინდა, რას მირჩევ?" required></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">კატეგორია</label>
                                <input type="text" name="category" class="form-control" value="manual">
                            </div>
                            <div class="col-md-8 d-flex align-items-end">
                                <button type="submit" class="btn btn-outline-primary">Run and Open Flow Inspector</button>
                            </div>
                        </form>
                        @if(!config('chatbot-monitoring.widget_trace.enabled'))
                            <div class="alert alert-warning py-2 px-3 small mt-3 mb-0">
                                Flow Inspector სრულად მუშაობს მაშინ, როცა `CHATBOT_WIDGET_TRACE_ENABLED=true` არის ჩართული.
                            </div>
                        @endif
                    </div>
                </div>
                <div class="card">
                    <div class="card-header bg-light"><h6 class="mb-0">როგორ მუშაობს</h6></div>
                    <div class="card-body small">
                        <div class="mb-3">
                            <div class="fw-semibold">1. Create Cascade Request</div>
                            <div class="text-muted">UI-დან ქმნი მოთხოვნას, მე ვკითხულობ request JSON-ს და ვაგენერირებ ახალ უნიკალურ კითხვებს.</div>
                        </div>
                        <div class="mb-3">
                            <div class="fw-semibold">2. Import Generated Batch</div>
                            <div class="text-muted">ჩემგან მიღებულ JSON-ს აკოპირებ UI-ში, სისტემა დუბლიკატებს გაფილტრავს და მხოლოდ ახალ კითხვებს შეინახავს history-ით.</div>
                        </div>
                        <div class="mb-3">
                            <div class="fw-semibold">3. Run Batch + Create Review</div>
                            <div class="text-muted">ბაჩი დაემატება queue-ში და გაეშვება background-ზე, ამიტომ შეგიძლია რამდენიმე batch ერთდროულად გაუშვა timeout-ის გარეშე.</div>
                        </div>
                        <div>
                            <div class="fw-semibold">4. Approve Training Decisions</div>
                            <div class="text-muted">reviews-ში ნახავ issue summary-ს, why wrong-ს, suggested answer-ს და მერე გადაწყვეტ approve / needs edit / reject-ს.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @elseif($activeTab === 'batches')
        <div class="card">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Training Batch-ები</h6>
                <a href="{{ route('admin.chatbot-training', ['tab' => 'overview']) }}" data-pjax class="btn btn-sm btn-outline-secondary">ახალი batch</a>
            </div>
            <div class="card-body p-0">
                @if(empty($batches))
                    <div class="p-4 text-muted">ჯერ batch არ შექმნილა.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Batch</th>
                                    <th>წყარო</th>
                                    <th>კატეგორიები</th>
                                    <th>რაოდენობა</th>
                                    <th>შექმნილია</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($batches as $batch)
                                    <tr>
                                        <td class="small">
                                            <div class="fw-semibold">{{ $batch['name'] ?? $batch['id'] }}</div>
                                            <div class="text-muted font-monospace">{{ $batch['id'] ?? '—' }}</div>
                                        </td>
                                        <td class="small">
                                            <span class="badge {{ ($batch['source'] ?? 'template') === 'cascade' ? 'bg-primary-subtle text-primary border' : 'bg-secondary-subtle text-secondary border' }}">{{ $batch['source'] ?? 'template' }}</span>
                                        </td>
                                        <td class="small">{{ implode(', ', $batch['categories'] ?? []) }}</td>
                                        <td class="small">
                                            <div>{{ $batch['question_count'] ?? count($batch['questions'] ?? []) }}</div>
                                            @if(!empty($batch['dedupe_summary']['skipped_count']))
                                                <div class="text-muted">Skipped {{ $batch['dedupe_summary']['skipped_count'] }}</div>
                                            @endif
                                        </td>
                                        <td class="small text-nowrap">{{ \Carbon\Carbon::parse($batch['created_at'])->format('Y-m-d H:i') }}</td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('admin.chatbot-training.run-batch', $batch['id']) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-primary">Queue Batch</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @elseif($activeTab === 'runs')
        <div class="card">
            <div class="card-header bg-light"><h6 class="mb-0">Batch Run-ები</h6></div>
            <div class="card-body p-0">
                @if(empty($runs))
                    <div class="p-4 text-muted">ჯერ run არ შესრულებულა.</div>
                @else
                    @foreach($runs as $run)
                        <div class="border-bottom p-3">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                                <div>
                                    <div class="fw-semibold">{{ $run['batch_name'] ?? $run['batch_id'] }}</div>
                                    <div class="text-muted small font-monospace">{{ $run['id'] ?? '—' }}</div>
                                    <div class="text-muted small">{{ \Carbon\Carbon::parse($run['created_at'])->format('Y-m-d H:i:s') }}</div>
                                    <div class="text-muted small">
                                        @if(!empty($run['queued_at']))
                                            queued: {{ \Carbon\Carbon::parse($run['queued_at'])->format('Y-m-d H:i:s') }}
                                        @endif
                                        @if(!empty($run['started_at']))
                                            | started: {{ \Carbon\Carbon::parse($run['started_at'])->format('Y-m-d H:i:s') }}
                                        @endif
                                        @if(!empty($run['completed_at']))
                                            | completed: {{ \Carbon\Carbon::parse($run['completed_at'])->format('Y-m-d H:i:s') }}
                                        @endif
                                    </div>
                                </div>
                                <div class="d-flex gap-2 align-items-center">
                                    @php $runStatus = $run['status'] ?? 'queued'; @endphp
                                    <span class="badge {{ $runStatus === 'completed' ? 'bg-success-subtle text-success border' : ($runStatus === 'failed' ? 'bg-danger-subtle text-danger border' : 'bg-warning-subtle text-warning border') }}">{{ $runStatus }}</span>
                                    <span class="badge bg-success-subtle text-success border">Pass {{ $run['summary']['passed_count'] ?? 0 }}</span>
                                    <span class="badge bg-warning-subtle text-warning border">Review {{ $run['summary']['needs_review_count'] ?? 0 }}</span>
                                    @if($runStatus === 'completed')
                                        <form method="POST" action="{{ route('admin.chatbot-training.create-review', $run['id']) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-primary">Create Review</button>
                                        </form>
                                    @else
                                        <span class="small text-muted">Refresh to monitor progress</span>
                                    @endif
                                </div>
                            </div>
                            @if(!empty($run['error']))
                                <div class="alert alert-danger py-2 px-3 small">{{ $run['error'] }}</div>
                            @endif
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Question</th>
                                            <th>Intent</th>
                                            <th>Agent</th>
                                            <th>Validation</th>
                                            <th>Trace</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse(array_slice($run['results'] ?? [], 0, 8) as $result)
                                            <tr>
                                                <td class="small">
                                                    <div>{{ $result['question'] ?? '—' }}</div>
                                                    <div class="text-muted">{{ \Illuminate\Support\Str::limit($result['response'] ?? '', 120) }}</div>
                                                </td>
                                                <td class="small">{{ $result['intent'] ?? '—' }}</td>
                                                <td class="small">{{ $result['agent_used'] ?? '—' }}</td>
                                                <td class="small">
                                                    @if(!empty($result['validation_passed']))
                                                        <span class="badge bg-success-subtle text-success border">pass</span>
                                                    @else
                                                        <span class="badge bg-danger-subtle text-danger border">fail</span>
                                                    @endif
                                                </td>
                                                <td class="small font-monospace">
                                                    <a data-pjax href="{{ route('admin.chatbot-training', ['tab' => 'flow', 'trace_id' => $result['trace_id'] ?? '', 'trace_hours' => 72]) }}">{{ \Illuminate\Support\Str::limit($result['trace_id'] ?? '—', 16) }}</a>
                                                </td>
                                                <td class="small">
                                                    @if(!empty($result['needs_review']))
                                                        <span class="badge bg-warning-subtle text-warning border">needs review</span>
                                                    @else
                                                        <span class="badge bg-success-subtle text-success border">passed</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="small text-muted py-3">Run შედეგები ჯერ არ არის ხელმისაწვდომი. background job დასრულების შემდეგ გამოჩნდება.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    @elseif($activeTab === 'reviews')
        <div class="card">
            <div class="card-header bg-light"><h6 class="mb-0">Review Requests</h6></div>
            <div class="card-body p-0">
                @if(empty($reviews))
                    <div class="p-4 text-muted">ჯერ review request არ შექმნილა.</div>
                @else
                    @foreach($reviews as $review)
                        <div class="border-bottom p-3">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                                <div>
                                    <div class="fw-semibold">{{ $review['id'] ?? 'review' }}</div>
                                    <div class="text-muted small">Run: {{ $review['run_id'] ?? '—' }} | Batch: {{ $review['batch_id'] ?? '—' }}</div>
                                    <div class="text-muted small">სტატუსი: {{ $review['status'] ?? 'pending' }}</div>
                                    <div class="text-muted small">Cascade analysis: {{ $review['analysis_status'] ?? 'pending' }}</div>
                                </div>
                                <div class="d-flex gap-2">
                                    <form method="POST" action="{{ route('admin.chatbot-training.reviews.decision', $review['id']) }}">
                                        @csrf
                                        <input type="hidden" name="decision" value="approved">
                                        <button type="submit" class="btn btn-sm btn-outline-success">Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.chatbot-training.reviews.decision', $review['id']) }}">
                                        @csrf
                                        <input type="hidden" name="decision" value="needs_edit">
                                        <button type="submit" class="btn btn-sm btn-outline-warning">Needs Edit</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.chatbot-training.reviews.decision', $review['id']) }}">
                                        @csrf
                                        <input type="hidden" name="decision" value="rejected">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                                    </form>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Cascade Prompt</label>
                                <textarea class="form-control form-control-sm" rows="4" readonly>{{ $review['cascade_prompt'] ?? '' }}</textarea>
                            </div>
                            @if(!empty($review['analysis_summary']['summary']))
                                <div class="alert alert-info py-2 px-3 small">
                                    {{ $review['analysis_summary']['summary'] }}
                                </div>
                            @endif
                            <div class="card border-light mb-3">
                                <div class="card-body">
                                    <form method="POST" action="{{ route('admin.chatbot-training.reviews.import-analysis', $review['id']) }}" class="row g-3">
                                        @csrf
                                        <div class="col-12">
                                            <label class="form-label small">Paste Cascade Review JSON</label>
                                            <textarea name="payload" class="form-control form-control-sm" rows="6" placeholder="{&quot;summary&quot;:&quot;...&quot;,&quot;items&quot;:[{&quot;question_id&quot;:&quot;q_001&quot;,&quot;issue_summary&quot;:&quot;...&quot;,&quot;why_wrong&quot;:&quot;...&quot;,&quot;suggested_answer&quot;:&quot;...&quot;,&quot;severity&quot;:&quot;high&quot;,&quot;training_action&quot;:&quot;...&quot;,&quot;should_train&quot;:true}]}" required></textarea>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-sm btn-outline-primary">Import Cascade Analysis</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0 align-middle">
                                    <thead>
                                        <tr>
                                            <th>Question</th>
                                            <th>Trace</th>
                                            <th>რატომ მოხვდა review-ში</th>
                                            <th>Cascade Analysis</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($review['items'] ?? [] as $item)
                                            <tr>
                                                <td class="small">
                                                    <div>{{ $item['question'] ?? '—' }}</div>
                                                    <div class="text-muted">{{ \Illuminate\Support\Str::limit($item['response'] ?? '', 120) }}</div>
                                                </td>
                                                <td class="small font-monospace">
                                                    <a data-pjax href="{{ route('admin.chatbot-training', ['tab' => 'flow', 'trace_id' => $item['trace_id'] ?? '', 'trace_hours' => 72]) }}">{{ \Illuminate\Support\Str::limit($item['trace_id'] ?? '—', 18) }}</a>
                                                </td>
                                                <td class="small">{{ implode(', ', $item['review_reasons'] ?? []) }}</td>
                                                <td class="small">
                                                    @if(!empty($item['analysis']))
                                                        <div class="fw-semibold">{{ $item['analysis']['issue_summary'] ?? '' }}</div>
                                                        <div class="text-muted mb-1">{{ $item['analysis']['why_wrong'] ?? '' }}</div>
                                                        <div class="small border rounded bg-light p-2 mb-1" style="white-space: pre-wrap;">{{ $item['analysis']['suggested_answer'] ?? '' }}</div>
                                                        <div class="d-flex flex-wrap gap-2">
                                                            <span class="badge bg-danger-subtle text-danger border">{{ $item['analysis']['severity'] ?? 'medium' }}</span>
                                                            @if(!empty($item['analysis']['should_train']))
                                                                <span class="badge bg-success-subtle text-success border">train</span>
                                                            @else
                                                                <span class="badge bg-secondary-subtle text-secondary border">no train</span>
                                                            @endif
                                                        </div>
                                                    @else
                                                        <span class="text-muted">ჯერ არ არის იმპორტირებული.</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    @elseif($activeTab === 'flow')
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.chatbot-training') }}" class="row g-3 align-items-end">
                    <input type="hidden" name="tab" value="flow">
                    <div class="col-md-3">
                        <label class="form-label small">დროის ფანჯარა</label>
                        <select name="trace_hours" class="form-select form-select-sm">
                            @foreach($hourOptions as $value => $label)
                                <option value="{{ $value }}" {{ (int) $filters['trace_hours'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">ძიება</label>
                        <input type="text" name="trace_search" class="form-control form-control-sm" value="{{ $filters['trace_search'] }}" placeholder="trace id, step, prompt...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">ლიმიტი</label>
                        <input type="number" name="trace_limit" class="form-control form-control-sm" min="10" max="200" value="{{ $filters['trace_limit'] }}">
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary">ფილტრი</button>
                        <a data-pjax href="{{ route('admin.chatbot-training', ['tab' => 'flow']) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Trace Sessions</h6>
                        <div class="small text-muted mt-1">ნაპოვნი session-ები: {{ $flowMeta['session_count'] ?? 0 }}</div>
                    </div>
                    <div class="card-body p-0">
                        @if(empty($flowSessions))
                            <div class="p-4 text-muted">Trace session ვერ მოიძებნა.</div>
                        @else
                            <div class="list-group list-group-flush">
                                @foreach($flowSessions as $session)
                                    <a data-pjax href="{{ route('admin.chatbot-training', ['tab' => 'flow', 'trace_hours' => $filters['trace_hours'], 'trace_limit' => $filters['trace_limit'], 'trace_search' => $filters['trace_search'], 'trace_id' => $session['trace_id']]) }}" class="list-group-item list-group-item-action {{ ($traceDetail['trace_id'] ?? '') === ($session['trace_id'] ?? '') ? 'active' : '' }}">
                                        <div class="d-flex justify-content-between align-items-start gap-2">
                                            <div>
                                                <div class="fw-semibold font-monospace small">{{ \Illuminate\Support\Str::limit($session['trace_id'] ?? '—', 20) }}</div>
                                                <div class="small {{ ($traceDetail['trace_id'] ?? '') === ($session['trace_id'] ?? '') ? 'text-white-50' : 'text-muted' }}">{{ \Illuminate\Support\Str::limit($session['question_preview'] ?? 'Question not captured', 80) }}</div>
                                            </div>
                                            @if(!empty($session['has_error']))
                                                <span class="badge bg-danger-subtle text-danger border">issue</span>
                                            @else
                                                <span class="badge bg-success-subtle text-success border">ok</span>
                                            @endif
                                        </div>
                                        <div class="small mt-2 {{ ($traceDetail['trace_id'] ?? '') === ($session['trace_id'] ?? '') ? 'text-white-50' : 'text-muted' }}">{{ $session['last_step'] ?? '—' }} · {{ $session['step_count'] ?? 0 }} steps</div>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Trace Detail</h6>
                    </div>
                    <div class="card-body">
                        @if(!$traceDetail)
                            <div class="text-muted">აირჩიე trace session მარცხნიდან.</div>
                        @else
                            <div class="mb-3">
                                <div class="fw-semibold font-monospace">{{ $traceDetail['trace_id'] }}</div>
                                <div class="small text-muted">Conv: {{ $traceDetail['summary']['conversation_id'] ?? '—' }} | Steps: {{ $traceDetail['summary']['step_count'] ?? 0 }}</div>
                                <div class="small text-muted">Agents: {{ implode(', ', $traceDetail['summary']['agents'] ?? []) ?: '—' }}</div>
                            </div>

                            @if(!empty($traceDetail['summary']['instruction_preview']))
                                <div class="card mb-3 border-info">
                                    <div class="card-body">
                                        <div class="fw-semibold mb-2">Instruction Snapshot</div>
                                        @foreach($traceDetail['summary']['instruction_preview'] as $key => $value)
                                            <div class="mb-2">
                                                <div class="small text-muted">{{ $key }}</div>
                                                <pre class="bg-light border rounded p-2 small mb-0" style="white-space: pre-wrap;">{{ $value }}</pre>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div class="timeline-entries">
                                @foreach($traceDetail['entries'] as $entry)
                                    <div class="border rounded p-3 mb-3">
                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                                            <div>
                                                <div class="fw-semibold font-monospace small">{{ $entry['step'] }}</div>
                                                <div class="text-muted small">{{ $entry['timestamp_label'] }} · {{ $entry['stage_group'] }}</div>
                                            </div>
                                        </div>
                                        @if(!empty($entry['highlights']))
                                            <pre class="bg-light border rounded p-2 small mb-0" style="white-space: pre-wrap;">{{ $entry['highlights_pretty'] }}</pre>
                                        @else
                                            <pre class="bg-light border rounded p-2 small mb-0" style="white-space: pre-wrap;">{{ $entry['context_pretty'] }}</pre>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endfragment
@endsection
