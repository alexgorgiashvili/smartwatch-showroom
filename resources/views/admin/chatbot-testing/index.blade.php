@extends('admin.layout')

@section('title', 'ჩატბოტის ტესტირება — Admin')

@section('content')
@fragment('content')
<div data-page-title="ჩატბოტის ტესტირება">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div><h4 class="mb-3 mb-md-0">ჩატბოტის ტესტირება</h4></div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-warning" id="btnResetCircuitBreaker">
                <i data-feather="refresh-cw" style="width:14px;height:14px;"></i> Reset Circuit Breaker
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger" id="btnFlushCache">
                <i data-feather="trash-2" style="width:14px;height:14px;"></i> Flush Cache
            </button>
        </div>
    </div>

    <div class="row">
        <!-- Chat Interface (Left Column) -->
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">ჩატის ინტერფეისი</h6>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="cacheBypass">
                        <label class="form-check-label small" for="cacheBypass">Cache Bypass</label>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div id="chatConversation" class="p-3" style="height: 500px; overflow-y: auto; background: #f8f9fa;">
                        <div class="text-center text-muted py-5">
                            <i data-feather="message-circle" style="width:48px;height:48px;"></i>
                            <p class="mt-2">შეტყობინების გაგზავნით დაიწყება ტესტირება</p>
                        </div>
                    </div>
                    <div class="p-3 border-top bg-white">
                        <form id="chatForm" class="d-flex gap-2">
                            <input type="text" id="messageInput" class="form-control" placeholder="შეიყვანეთ შეტყობინება..." autocomplete="off" required>
                            <button type="submit" class="btn btn-primary" id="sendBtn">
                                <i data-feather="send" style="width:16px;height:16px;"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metrics & Debug (Right Column) -->
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h6 class="mb-0">მეტრიკები</h6>
                </div>
                <div class="card-body">
                    <div id="metricsContent" class="text-muted small">
                        <p>შეტყობინების გაგზავნის შემდეგ გამოჩნდება მეტრიკები</p>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h6 class="mb-0">შესრულების გზა</h6>
                </div>
                <div class="card-body">
                    <div id="executionPathContent" class="text-muted small">
                        <p>შეტყობინების გაგზავნის შემდეგ გამოჩნდება შესრულების გზა</p>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h6 class="mb-0">Debug ინფორმაცია</h6>
                </div>
                <div class="card-body">
                    <div id="debugInfoContent" class="text-muted small">
                        <p>შეტყობინების გაგზავნის შემდეგ გამოჩნდება debug მონაცემები</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0">სისტემის სტატუსი</h6>
                </div>
                <div class="card-body small">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-medium">Circuit Breaker:</span>
                            <span class="badge bg-{{ $circuitBreakerStats['state'] === 'closed' ? 'success' : 'danger' }}">
                                {{ strtoupper($circuitBreakerStats['state']) }}
                            </span>
                        </div>
                        <div class="text-muted small">
                            Failures: {{ $circuitBreakerStats['failures'] }}/{{ $circuitBreakerStats['threshold'] }}
                        </div>
                    </div>
                    <div>
                        <div class="fw-medium mb-1">Cache Stats:</div>
                        <div class="text-muted small">
                            Hits: {{ $cacheStats['hits'] ?? 0 }} | Misses: {{ $cacheStats['misses'] ?? 0 }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@php
    $chatConfig = [
        'sendMessageUrl' => route('admin.chatbot-testing.send'),
        'resetCircuitBreakerUrl' => route('admin.chatbot-testing.reset-circuit-breaker'),
        'flushCacheUrl' => route('admin.chatbot-testing.flush-cache'),
    ];
@endphp
<script id="chatbot-testing-config" type="application/json">{!! json_encode($chatConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endfragment
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!document.getElementById('chatbot-testing-config')) return;

    const config = JSON.parse(document.getElementById('chatbot-testing-config').textContent);
    const chatForm = document.getElementById('chatForm');
    const messageInput = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendBtn');
    const chatConversation = document.getElementById('chatConversation');
    const cacheBypass = document.getElementById('cacheBypass');
    const metricsContent = document.getElementById('metricsContent');
    const executionPathContent = document.getElementById('executionPathContent');
    const debugInfoContent = document.getElementById('debugInfoContent');

    let conversationId = null;

    function addMessage(role, content, cached = false) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `mb-3 ${role === 'user' ? 'text-end' : ''}`;
        
        const bubble = document.createElement('div');
        bubble.className = `d-inline-block p-2 rounded ${role === 'user' ? 'bg-primary text-white' : 'bg-white border'}`;
        bubble.style.maxWidth = '80%';
        bubble.textContent = content;
        
        if (cached) {
            const badge = document.createElement('span');
            badge.className = 'badge bg-info ms-2';
            badge.textContent = 'CACHED';
            bubble.appendChild(badge);
        }
        
        messageDiv.appendChild(bubble);
        chatConversation.appendChild(messageDiv);
        chatConversation.scrollTop = chatConversation.scrollHeight;
    }

    function updateMetrics(metrics) {
        const isFast = metrics.total_latency_ms < 3000;
        metricsContent.innerHTML = `
            <dl class="mb-0">
                <div class="d-flex justify-content-between mb-2">
                    <dt>სრული დრო:</dt>
                    <dd class="mb-0 font-monospace fw-bold ${isFast ? 'text-success' : 'text-danger'}">${metrics.total_latency_ms}ms</dd>
                </div>
                ${metrics.cache_hit !== undefined ? `
                <div class="d-flex justify-content-between mb-2">
                    <dt>Cache:</dt>
                    <dd class="mb-0 font-monospace ${metrics.cache_hit ? 'text-success' : 'text-muted'}">${metrics.cache_hit ? 'HIT (' + (metrics.cache_layer || 'unknown') + ')' : 'MISS'}</dd>
                </div>
                ` : ''}
                ${metrics.intent_analysis_ms !== undefined ? `
                <div class="d-flex justify-content-between mb-2">
                    <dt>Intent Analysis:</dt>
                    <dd class="mb-0 font-monospace">${metrics.intent_analysis_ms}ms</dd>
                </div>
                ` : ''}
                ${metrics.supervisor_ms !== undefined ? `
                <div class="d-flex justify-content-between mb-2">
                    <dt>Supervisor:</dt>
                    <dd class="mb-0 font-monospace">${metrics.supervisor_ms}ms</dd>
                </div>
                ` : ''}
            </dl>
        `;
    }

    function updateExecutionPath(path) {
        executionPathContent.innerHTML = path.map(step => {
            const bgClass = step.status === 'success' ? 'bg-success-subtle' : (step.status === 'hit' ? 'bg-info-subtle' : 'bg-light');
            return `
                <div class="d-flex justify-content-between align-items-center p-2 mb-2 rounded ${bgClass}">
                    <span class="fw-medium">${step.step}</span>
                    <span class="font-monospace small">${step.duration_ms}ms</span>
                </div>
            `;
        }).join('');
    }

    function updateDebugInfo(info) {
        debugInfoContent.innerHTML = Object.entries(info).map(([key, value]) => `
            <div class="mb-2">
                <dt class="fw-medium small">${key}:</dt>
                <dd class="font-monospace small bg-light p-2 rounded mb-0">${typeof value === 'object' ? JSON.stringify(value, null, 2) : value}</dd>
            </div>
        `).join('');
    }

    chatForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const message = messageInput.value.trim();
        if (!message) return;

        // Clear first-time placeholder
        if (chatConversation.querySelector('.text-muted')) {
            chatConversation.innerHTML = '';
        }

        addMessage('user', message);
        messageInput.value = '';
        sendBtn.disabled = true;

        try {
            const res = await axios.post(config.sendMessageUrl, {
                message: message,
                conversation_id: conversationId,
                cache_bypass: cacheBypass.checked,
            });

            if (res.data.success) {
                addMessage('assistant', res.data.response, res.data.cached);
                if (res.data.metrics) updateMetrics(res.data.metrics);
                if (res.data.execution_path) updateExecutionPath(res.data.execution_path);
                if (res.data.debug_info) updateDebugInfo(res.data.debug_info);
            } else {
                addMessage('assistant', 'Error: ' + (res.data.error || 'Unknown error'));
            }
        } catch (e) {
            addMessage('assistant', 'Request failed: ' + (e.response?.data?.error || e.message));
        } finally {
            sendBtn.disabled = false;
            messageInput.focus();
        }
    });

    document.getElementById('btnResetCircuitBreaker').addEventListener('click', async () => {
        if (!confirm('Are you sure you want to reset the circuit breaker?')) return;
        try {
            const res = await axios.post(config.resetCircuitBreakerUrl);
            if (window.AdminHelpers) window.AdminHelpers.showToast(res.data.message, 'success');
            location.reload();
        } catch (e) {
            if (window.AdminHelpers) window.AdminHelpers.showToast(e.response?.data?.error || 'Failed', 'error');
        }
    });

    document.getElementById('btnFlushCache').addEventListener('click', async () => {
        if (!confirm('Are you sure you want to flush all cache layers?')) return;
        try {
            const res = await axios.post(config.flushCacheUrl);
            if (window.AdminHelpers) window.AdminHelpers.showToast(res.data.message, 'success');
            location.reload();
        } catch (e) {
            if (window.AdminHelpers) window.AdminHelpers.showToast(e.response?.data?.error || 'Failed', 'error');
        }
    });
});
</script>
@endpush
