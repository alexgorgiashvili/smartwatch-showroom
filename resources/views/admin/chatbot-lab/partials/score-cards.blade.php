@php
    $total = (int) ($run?->total_cases ?? 0);
    $passed = (int) ($run?->passed_cases ?? 0);
    $failed = (int) ($run?->failed_cases ?? 0);
    $accuracy = $run?->accuracy_pct !== null ? (float) $run->accuracy_pct : null;
    $guardrail = $run?->guardrail_pass_rate !== null ? (float) $run->guardrail_pass_rate : null;
    $llm = $run?->avg_llm_score !== null ? (float) $run->avg_llm_score : null;

    $accuracyColor = $accuracy === null ? 'bg-secondary' : ($accuracy >= 80 ? 'bg-success' : ($accuracy >= 60 ? 'bg-warning' : 'bg-danger'));
    $guardrailColor = $guardrail === null ? 'bg-secondary' : ($guardrail >= 80 ? 'bg-success' : ($guardrail >= 60 ? 'bg-warning' : 'bg-danger'));
@endphp

<div class="row g-3 mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <p class="text-muted mb-1">Overall Accuracy</p>
                <h4 class="mb-1">{{ $passed }}/{{ $total }}</h4>
                <div class="small text-muted mb-2">{{ $accuracy !== null ? number_format($accuracy, 2) . '%' : 'N/A' }}</div>
                <div class="progress" style="height: 6px;">
                    <div class="progress-bar {{ $accuracyColor }}" role="progressbar" style="width: {{ $accuracy ?? 0 }}%"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <p class="text-muted mb-1">Guardrail Pass Rate</p>
                <h4 class="mb-1">{{ $guardrail !== null ? number_format($guardrail, 2) . '%' : 'N/A' }}</h4>
                <div class="small text-muted mb-2">Fail cases: {{ $failed }}</div>
                <div class="progress" style="height: 6px;">
                    <div class="progress-bar {{ $guardrailColor }}" role="progressbar" style="width: {{ $guardrail ?? 0 }}%"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <p class="text-muted mb-1">Avg LLM Score</p>
                <h4 class="mb-1">{{ $llm !== null ? number_format($llm, 1) . '/5.0' : 'N/A' }}</h4>
                <div class="small text-muted">Judge quality trend</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <p class="text-muted mb-1">Last Run</p>
                <h6 class="mb-1">{{ $run?->completed_at?->diffForHumans() ?? 'N/A' }}</h6>
                <div class="small text-muted">{{ $run?->duration_seconds !== null ? number_format((float) $run->duration_seconds, 2) . 's' : '' }}</div>
            </div>
        </div>
    </div>
</div>