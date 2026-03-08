@extends('admin.layout')

@section('title', 'ჩატბოტ ლაბი')

@section('content')
@if (session('status'))
    <div class="alert alert-success" role="alert">{{ session('status') }}</div>
@endif

@if (session('warning'))
    <div class="alert alert-warning" role="alert">{{ session('warning') }}</div>
@endif

@if (!empty($statusMessage))
    <div class="alert alert-info" role="alert">{{ $statusMessage }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-warning" role="alert">გთხოვთ, მონიშნული ველები გადაამოწმოთ.</div>
@endif

@unless ($casesReady ?? false)
    <div class="alert alert-warning" role="alert">ქეისების განყოფილება მზად არის, მაგრამ საჭირო ცხრილი ჯერ არ არსებობს. გაუშვით <code>php artisan migrate</code>, რომ ქეისების შენახვა ჩაირთოს.</div>
@endunless

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="mb-1">ჩატბოტ ლაბი</h4>
        <p class="text-muted mb-0">ხელით დატესტეთ პრომპტები, ნახეთ დიაგნოსტიკა და გააგრძელეთ იგივე სესია საჭიროების შემთხვევაში.</p>
    </div>
    <div class="d-flex gap-2">
        <span class="badge bg-primary">ხელით ტესტი</span>
        <a href="{{ route('admin.chatbot-lab.cases.index') }}" class="badge bg-light text-dark border text-decoration-none">ქეისები: {{ $labStats['total'] ?? 0 }}</a>
        <span class="badge {{ $sessionState ? 'bg-success' : 'bg-light text-dark border' }}">სესია: {{ $sessionState ? 'აქტიური' : 'არ არის' }}</span>
    </div>
</div>

<div class="d-flex gap-2 mb-4">
    <a href="{{ route('admin.chatbot-lab.index') }}" class="btn btn-primary btn-sm">ხელით ტესტი</a>
    <a href="{{ route('admin.chatbot-lab.cases.index') }}" class="btn btn-outline-primary btn-sm">სატესტო ქეისები</a>
    <a href="{{ route('admin.chatbot-lab.runs.index') }}" class="btn btn-outline-primary btn-sm">სატესტო გაშვებები</a>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-1">აქტიური ლაბ სესია</h6>
                        @if ($sessionState)
                            <div class="small text-muted">საუბარი #{{ $sessionState['conversation_id'] }} · ნაბიჯები: {{ $sessionState['turn_count'] ?? 0 }}</div>
                            <div class="small text-muted">ბოლო აქტივობა: {{ $sessionState['last_active'] ?? '—' }}</div>
                        @else
                            <div class="small text-muted">მუდმივი სესია აქტიური არ არის. თუ სესიას არ ჩართავთ, თითოეული ტესტი დამოუკიდებელ საუბრად გაეშვება.</div>
                        @endif
                    </div>
                    @if ($sessionState)
                        <form method="POST" action="{{ route('admin.chatbot-lab.manual.reset') }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger">სესიის გასუფთავება</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-3">ხელით ტესტირება</h5>
                <form method="POST" action="{{ route('admin.chatbot-lab.manual.run') }}" class="d-flex flex-column gap-3">
                    @csrf
                    <div>
                        <label for="prompt" class="form-label">მომხმარებლის კითხვა</label>
                        <textarea id="prompt" name="prompt" rows="5" class="form-control @error('prompt') is-invalid @enderror" placeholder="მაგ: 200 ლარამდე რას მირჩევთ?" required>{{ old('prompt', $formData['prompt'] ?? '') }}</textarea>
                        @error('prompt')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label for="previous_prompts" class="form-label">წინა მომხმარებლის შეტყობინებები</label>
                        <textarea id="previous_prompts" name="previous_prompts" rows="5" class="form-control @error('previous_prompts') is-invalid @enderror" placeholder="ერთი წინა user prompt თითო ხაზზე">{{ old('previous_prompts', $formData['previous_prompts'] ?? '') }}</textarea>
                        <div class="form-text">თუ ტესტი ერთჯერად რეჟიმში გადის, თითო ხაზი ჩაითვლება წინა user prompt-ად. აქტიური სესიის რეჟიმში ეს ველი იგნორირდება.</div>
                        @error('previous_prompts')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-check">
                        <input id="continue_session" class="form-check-input" type="checkbox" name="continue_session" value="1" {{ old('continue_session', $formData['continue_session'] ?? ($sessionState ? '1' : '')) ? 'checked' : '' }}>
                        <label class="form-check-label" for="continue_session">გააგრძელე აქტიური სესია</label>
                        <div class="form-text">ჩართვის შემთხვევაში იგივე საუბარი გაგრძელდება და კონტექსტი/მეხსიერება შემდეგ ნაბიჯზეც დარჩება.</div>
                    </div>

                    <button type="submit" class="btn btn-primary">ტესტის გაშვება</button>
                </form>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-body">
                <h6 class="mb-3">რას ნახავთ აქ</h6>
                <ul class="mb-0 text-muted">
                    <li>ხელით პრომპტების სწრაფ ტესტირება</li>
                    <li>ინტენტის და validator-ის შედეგები</li>
                    <li>fallback მიზეზების ნახვა</li>
                    <li>პროდუქტის/card-ის preview მონაცემები</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-3">შედეგი</h5>

                @if (!$result)
                    <p class="text-muted mb-0">გაუშვით კითხვა, რომ ნახოთ ჩატბოტის პასუხი, დიაგნოსტიკა და არჩეული პროდუქტები.</p>
                @else
                    @php
                        $retryContext = [
                            'intent' => $result['debug']['intent'] ?? null,
                            'expected_summary' => $result['debug']['intent'] ? 'Intent: ' . $result['debug']['intent'] : null,
                            'validation_violations' => $result['debug']['validation_violations'] ?? [],
                            'fallback_reason' => $result['debug']['fallback_reason'] ?? null,
                            'keyword_match' => $result['debug']['validation_passed'] ?? true,
                            'price_match' => null,
                            'stock_match' => null,
                            'georgian_passed' => $result['debug']['georgian_passed'] ?? true,
                            'intent_match' => true,
                            'entity_match' => true,
                            'llm_notes' => null,
                            'recommended_action' => $result['debug']['recommended_action'] ?? null,
                        ];
                    @endphp
                    <div class="mb-4">
                        <h6>სესიის რეჟიმი</h6>
                        <div class="border rounded p-3 bg-light">
                            <div><strong>რეჟიმი:</strong> {{ !empty($result['session']['persistent']) ? 'მუდმივი სესია' : 'ერთჯერადი გაშვება' }}</div>
                            <div><strong>საუბარი:</strong> {{ $result['session']['conversation_id'] ?? '—' }}</div>
                            <div><strong>ნაბიჯები სესიაში:</strong> {{ $result['session']['turn_count'] ?? 0 }}</div>
                        </div>
                    </div>

                    @if (!empty($result['retry']))
                        <div class="mb-4">
                            <h6>ხელახალი გაშვების კონტექსტი</h6>
                            <div class="border rounded p-3 bg-light">
                                <div><strong>სტრატეგია:</strong> {{ $result['retry']['strategy_label'] ?? 'Retry' }}</div>
                                <div><strong>საწყისი კითხვა:</strong> {{ $result['retry']['source_prompt'] ?? $result['prompt'] }}</div>
                                @if (!empty($result['retry']['promoted_case_id'] ?? null))
                                    <div><strong>გადაყვანილი ქეისი:</strong> #{{ $result['retry']['promoted_case_id'] }}{{ !empty($result['retry']['promoted_case_title'] ?? null) ? ' · ' . $result['retry']['promoted_case_title'] : '' }}</div>
                                @endif
                                @if (($result['retry']['effective_prompt'] ?? '') !== ($result['retry']['source_prompt'] ?? ''))
                                    <div class="mt-2 small text-muted">რეალურად გაშვებული prompt</div>
                                    <div style="white-space: pre-wrap;">{{ $result['retry']['effective_prompt'] }}</div>
                                @endif
                                @if (($result['retry']['constraint_hints'] ?? []) !== [])
                                    <div class="mt-2 small text-muted">გამოყენებული შეზღუდვები</div>
                                    <ul class="mb-0 mt-1 small">
                                        @foreach ($result['retry']['constraint_hints'] as $hint)
                                            <li>{{ $hint }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                    @endif

                    <div class="mb-4">
                        <h6>საბოლოო პასუხი</h6>
                        <div class="border rounded p-3 bg-light" style="white-space: pre-wrap;">{{ $result['response'] }}</div>
                    </div>

                    <div class="card border-0 bg-light mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <h6 class="mb-1">სწრაფი ხელახალი გაშვება</h6>
                                    <div class="small text-muted">იგივე კითხვა ახლავე თავიდან გაუშვით ან მიმდინარე დიაგნოსტიკიდან მიღებული შეზღუდვებით სცადეთ.</div>
                                </div>
                                <div class="d-flex gap-2">
                                    <form method="POST" action="{{ route('admin.chatbot-lab.manual.retry') }}">
                                        @csrf
                                        <input type="hidden" name="prompt" value="{{ $result['prompt'] }}">
                                        <input type="hidden" name="previous_prompts" value="{{ $formData['previous_prompts'] ?? '' }}">
                                        <input type="hidden" name="continue_session" value="{{ old('continue_session', $formData['continue_session'] ?? ($sessionState ? '1' : '')) ? '1' : '' }}">
                                        <input type="hidden" name="retry_strategy" value="same">
                                        <input type="hidden" name="retry_context" value="{{ json_encode($retryContext, JSON_UNESCAPED_UNICODE) }}">
                                        <button type="submit" class="btn btn-outline-primary">იგივე კითხვის ხელახალი გაშვება</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.chatbot-lab.manual.retry') }}">
                                        @csrf
                                        <input type="hidden" name="prompt" value="{{ $result['prompt'] }}">
                                        <input type="hidden" name="previous_prompts" value="{{ $formData['previous_prompts'] ?? '' }}">
                                        <input type="hidden" name="continue_session" value="{{ old('continue_session', $formData['continue_session'] ?? ($sessionState ? '1' : '')) ? '1' : '' }}">
                                        <input type="hidden" name="retry_strategy" value="constrained">
                                        <input type="hidden" name="retry_context" value="{{ json_encode($retryContext, JSON_UNESCAPED_UNICODE) }}">
                                        <button type="submit" class="btn btn-primary">შეზღუდვებით ხელახალი გაშვება</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6>კითხვის შეჯამება</h6>
                        <dl class="row mb-0">
                            <dt class="col-sm-4">საწყისი კითხვა</dt>
                            <dd class="col-sm-8">{{ $result['prompt'] }}</dd>
                            <dt class="col-sm-4">ნორმალიზებული კითხვა</dt>
                            <dd class="col-sm-8">{{ $result['normalized_prompt'] }}</dd>
                        </dl>
                    </div>

                    @if (($result['transcript'] ?? []) !== [])
                        <div class="mb-4">
                            <h6>{{ !empty($result['session']['persistent']) ? 'სესიის ისტორია' : 'წინა ნაბიჯები' }}</h6>
                            <div class="d-flex flex-column gap-3">
                                @foreach ($result['transcript'] as $turn)
                                    <div class="border rounded p-3">
                                        <div class="small text-muted mb-2">მომხმარებლის კითხვა</div>
                                        <div class="mb-3">{{ $turn['prompt'] }}</div>
                                        <div class="small text-muted mb-2">ასისტენტის პასუხი</div>
                                        <div style="white-space: pre-wrap;">{{ $turn['response'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <h6 class="mb-3">pipeline-ის მოკლე სურათი</h6>
                                <dl class="row mb-0 small">
                                    <dt class="col-sm-6">Intent</dt>
                                    <dd class="col-sm-6">{{ $result['debug']['intent'] ?? '—' }}</dd>
                                    <dt class="col-sm-6">Intent Confidence</dt>
                                    <dd class="col-sm-6">{{ $result['debug']['intent_confidence'] ?? '—' }}</dd>
                                    <dt class="col-sm-6">Intent Fallback</dt>
                                    <dd class="col-sm-6">{{ ($result['debug']['intent_fallback'] ?? null) === null ? '—' : (($result['debug']['intent_fallback'] ?? false) ? 'yes' : 'no') }}</dd>
                                    <dt class="col-sm-6">Standalone Query</dt>
                                    <dd class="col-sm-6">{{ $result['debug']['standalone_query'] ?? '—' }}</dd>
                                    <dt class="col-sm-6">Response Time</dt>
                                    <dd class="col-sm-6">{{ $result['debug']['response_time_ms'] ?? 0 }} ms</dd>
                                    <dt class="col-sm-6">Regeneration Attempted</dt>
                                    <dd class="col-sm-6">{{ ($result['debug']['regeneration_attempted'] ?? false) ? 'yes' : 'no' }}</dd>
                                    <dt class="col-sm-6">Regeneration Succeeded</dt>
                                    <dd class="col-sm-6">{{ ($result['debug']['regeneration_succeeded'] ?? false) ? 'yes' : 'no' }}</dd>
                                    <dt class="col-sm-6">Fallback Reason</dt>
                                    <dd class="col-sm-6">
                                        @if (!empty($result['debug']['fallback_label'] ?? null))
                                            <div>{{ $result['debug']['fallback_label'] }}</div>
                                            <div class="text-muted">{{ $result['debug']['fallback_reason'] }}</div>
                                        @else
                                            —
                                        @endif
                                    </dd>
                                </dl>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <h6 class="mb-3">მთავარი სიგნალი</h6>
                                <dl class="row mb-3 small">
                                    <dt class="col-sm-6">წყარო</dt>
                                    <dd class="col-sm-6 text-capitalize">{{ $result['debug']['signal_group'] ?? 'healthy' }}</dd>
                                    <dt class="col-sm-6">სიგნალი</dt>
                                    <dd class="col-sm-6">{{ $result['debug']['signal_label'] ?? 'მნიშვნელოვანი პრობლემა არ დაფიქსირდა' }}</dd>
                                    <dt class="col-sm-6">სიმძიმე</dt>
                                    <dd class="col-sm-6 text-capitalize">{{ $result['debug']['signal_severity'] ?? 'low' }}</dd>
                                </dl>

                                <div class="small text-muted mb-2">შემდეგი რეკომენდებული ნაბიჯი</div>
                                <div class="small mb-3">{{ $result['debug']['recommended_action'] ?? 'თუ მეტი სიღრმე გჭირდებათ, raw pipeline payload გადაამოწმეთ.' }}</div>

                                <h6 class="mb-3">ვალიდაცია</h6>
                                <dl class="row mb-3 small">
                                    <dt class="col-sm-6">Guard Allowed</dt>
                                    <dd class="col-sm-6">{{ ($result['debug']['guard_allowed'] ?? false) ? 'yes' : 'no' }}</dd>
                                    <dt class="col-sm-6">Georgian Passed</dt>
                                    <dd class="col-sm-6">{{ ($result['debug']['georgian_passed'] ?? false) ? 'yes' : 'no' }}</dd>
                                    <dt class="col-sm-6">Validation Passed</dt>
                                    <dd class="col-sm-6">{{ ($result['debug']['validation_passed'] ?? false) ? 'yes' : 'no' }}</dd>
                                </dl>

                                <div class="small text-muted mb-2">ვალიდაციის შეჯამება</div>
                                @if (($result['debug']['validation_issue_labels'] ?? []) === [])
                                    <div class="small">არ არის</div>
                                @else
                                    <ul class="small mb-0 ps-3">
                                        @foreach (($result['debug']['validation_issue_labels'] ?? []) as $issue)
                                            <li>{{ $issue }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6>ნაპოვნი პროდუქტები</h6>
                        @if (($result['debug']['products'] ?? []) === [])
                            <p class="text-muted mb-0">ვალიდაციის კონტექსტში პროდუქტი ვერ მოიძებნა.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Price</th>
                                            <th>Sale Price</th>
                                            <th>In Stock</th>
                                            <th>URL</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($result['debug']['products'] as $product)
                                            <tr>
                                                <td>{{ $product['name'] ?? '—' }}</td>
                                                <td>{{ $product['price'] ?? '—' }}</td>
                                                <td>{{ $product['sale_price'] ?? '—' }}</td>
                                                <td>{{ !empty($product['is_in_stock']) ? 'yes' : 'no' }}</td>
                                                <td class="text-break">{{ $product['url'] ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    <details>
                        <summary class="fw-semibold">raw pipeline-ის დეტალები</summary>
                        <pre class="bg-light border rounded p-3 mt-3 small">{{ json_encode($result['raw_pipeline'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </details>

                    <div class="card border-0 bg-light mt-4">
                        <div class="card-body">
                            <h6 class="mb-3">ქეისად შენახვა</h6>
                            <form method="POST" action="{{ route('admin.chatbot-lab.cases.store') }}" class="row g-3">
                                @csrf
                                <input type="hidden" name="prompt" value="{{ $result['prompt'] }}">
                                <input type="hidden" name="conversation_context" value="{{ $formData['previous_prompts'] ?? '' }}">
                                <input type="hidden" name="expected_intent" value="{{ $result['debug']['intent'] ?? '' }}">
                                <input type="hidden" name="source" value="manual_test">
                                <div class="col-md-8">
                                    <label class="form-label">ქეისის სათაური</label>
                                    <input type="text" name="title" class="form-control" value="{{ old('title', $result['prompt']) }}" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Tags</label>
                                    <input type="text" name="tags" class="form-control" placeholder="greeting, fallback">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Expected Keywords</label>
                                    <input type="text" name="expected_keywords" class="form-control" placeholder="comma or line separated">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Expected Product Slugs</label>
                                    <input type="text" name="expected_product_slugs" class="form-control" placeholder="comma or line separated">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Expected Price Behavior</label>
                                    <input type="text" name="expected_price_behavior" class="form-control" placeholder="optional">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Expected Stock Behavior</label>
                                    <input type="text" name="expected_stock_behavior" class="form-control" placeholder="optional">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Reviewer Notes</label>
                                    <textarea name="reviewer_notes" rows="3" class="form-control" placeholder="What should this case protect against next time?"></textarea>
                                </div>
                                <div class="col-12 d-flex justify-content-between align-items-center">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                                        <label class="form-check-label">Active case</label>
                                    </div>
                                    <button type="submit" class="btn btn-primary">ქეისად შენახვა</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
