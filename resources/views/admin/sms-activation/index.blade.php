@extends('admin.layout')

@section('title', 'SMS Activation — Admin')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/vendors/select2/select2.min.css') }}">
<style>
/* Select2 container */
.select2-container .select2-selection--single {
    height: 38px;
    border: 1px solid #e9ecef;
    padding: 0 12px;
}

/* Placeholder and selected text - vertically centered */
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 38px;
    padding-left: 0;
    padding-right: 40px; /* Space for clear + arrow */
}

/* Arrow */
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 38px;
    right: 8px;
}

/* Clear button (X) - positioned before arrow */
.select2-container--default .select2-selection--single .select2-selection__clear {
    position: absolute;
    right: 32px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 18px;
    line-height: 1;
    padding: 0 4px;
    z-index: 1;
}

/* Dropdown results */
.select2-results__option {
    padding: 8px 12px;
    line-height: 1.5;
}

/* Search input in dropdown */
.select2-search--dropdown .select2-search__field {
    padding: 8px 12px;
    border: 1px solid #e9ecef;
    border-radius: 4px;
}

/* Dropdown container */
.select2-dropdown {
    border: 1px solid #e9ecef;
    border-radius: 4px;
}
</style>
@endpush

@section('content')
@fragment('content')
<div data-page-title="SMS Activation">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div><h4 class="mb-3 mb-md-0">SMS Activation (Grizzly SMS)</h4></div>
        <div>
            @if($configured)
                <span class="badge bg-success">
                    <i data-feather="dollar-sign" style="width:14px;height:14px;"></i>
                    Balance: {{ number_format($balance, 2) }} RUB
                </span>
            @else
                <span class="badge bg-warning">
                    <i data-feather="alert-circle" style="width:14px;height:14px;"></i>
                    Not Configured
                </span>
            @endif
        </div>
    </div>

    @if(!$configured)
        <div class="alert alert-warning" role="alert">
            <i data-feather="alert-triangle" style="width:18px;height:18px;"></i>
            <strong>Grizzly SMS not configured.</strong> Add GRIZZLY_SMS_API_KEY to your .env file.
        </div>
    @endif

    <!-- Get New Number Form -->
    @if($configured)
    <div class="card mb-3">
        <div class="card-body">
            <h6 class="mb-3">📞 ახალი ნომრის მიღება</h6>
            <form id="getNumberForm">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                    <label class="form-label small">ქვეყანა</label>
                    <select id="countrySelect" class="form-select" required>
                        <option value="">აირჩიე ქვეყანა...</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">სერვისი</label>
                    <select id="serviceSelect" class="form-select" required disabled>
                        <option value="">ჯერ აირჩიე ქვეყანა...</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100" id="btnSubmitNumber" disabled>
                        <i data-feather="phone" style="width:16px;height:16px;"></i> ნომრის მიღება
                    </button>
                </div>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Stats Cards -->
    <div class="row mb-3">
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="card">
                <div class="card-body text-center">
                    <div class="text-muted small">სულ აქტივაციები</div>
                    <div class="h4 mb-0 mt-1">{{ number_format($stats['total']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <div class="text-muted small">მოლოდინში</div>
                    <div class="h4 mb-0 mt-1 text-warning">{{ number_format($stats['pending']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="card border-success">
                <div class="card-body text-center">
                    <div class="text-muted small">დასრულებული</div>
                    <div class="h4 mb-0 mt-1 text-success">{{ number_format($stats['completed']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <div class="text-muted small">ხარჯი (RUB)</div>
                    <div class="h4 mb-0 mt-1 text-info">{{ number_format($stats['total_cost'], 2) }} ₽</div>
                </div>
            </div>
        </div>
    </div>


    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th class="small">ნომერი</th>
                            <th class="small">სერვისი</th>
                            <th class="small">სტატუსი</th>
                            <th class="small">SMS კოდი</th>
                            <th class="small">ფასი</th>
                            <th class="small">თარიღი</th>
                            <th class="small" style="width:150px;">მოქმედებები</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($activations as $activation)
                        <tr data-activation-row="{{ $activation->id }}" class="{{ in_array($activation->status, ['pending', 'ready']) ? 'table-warning' : '' }}">
                            <td>
                                <div class="fw-bold font-monospace">{{ $activation->phone_number }}</div>
                                <small class="text-muted">{{ $activation->country_name ?: $activation->country }}</small>
                            </td>
                            <td class="small">{{ $activation->service_name ?: $activation->service }}</td>
                            <td>
                                <span class="badge bg-{{ \App\Models\SmsActivation::statusColor($activation->status) }}">
                                    {{ ucfirst($activation->status) }}
                                </span>
                            </td>
                            <td>
                                @if($activation->sms_code)
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-bold text-success font-monospace fs-5">{{ $activation->sms_code }}</span>
                                        <button type="button" class="btn btn-sm btn-outline-secondary p-1 btn-copy-sms" data-code="{{ $activation->sms_code }}" title="Copy">
                                            <i data-feather="copy" style="width:12px;height:12px;"></i>
                                        </button>
                                    </div>
                                @else
                                    <span class="text-muted small">მოლოდინში...</span>
                                @endif
                            </td>
                            <td class="small">{{ $activation->cost ? number_format($activation->cost, 2) . ' ₽' : '—' }}</td>
                            <td class="small text-muted">{{ $activation->created_at->diffForHumans() }}</td>
                            <td>
                                @if(in_array($activation->status, ['pending', 'ready', 'code_received']))
                                    <div class="d-flex flex-wrap gap-1">
                                        <button type="button" class="btn btn-sm btn-primary btn-check-status" data-id="{{ $activation->id }}" title="შეამოწმე კოდი">
                                            <i data-feather="refresh-cw" style="width:14px;height:14px;"></i>
                                        </button>
                                        @if($activation->status === 'pending')
                                            <button type="button" class="btn btn-sm btn-outline-info btn-set-status" data-id="{{ $activation->id }}" data-status="1" title="მზადაა">
                                                <i data-feather="play" style="width:14px;height:14px;"></i>
                                            </button>
                                        @endif
                                        @if(in_array($activation->status, ['ready', 'code_received']))
                                            <button type="button" class="btn btn-sm btn-outline-warning btn-set-status" data-id="{{ $activation->id }}" data-status="3" title="მოითხოვე ახალი SMS">
                                                <i data-feather="rotate-cw" style="width:14px;height:14px;"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-success btn-set-status" data-id="{{ $activation->id }}" data-status="6" title="დაასრულე">
                                                <i data-feather="check-circle" style="width:14px;height:14px;"></i>
                                            </button>
                                        @endif
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-set-status" data-id="{{ $activation->id }}" data-status="8" title="გაუქმე">
                                            <i data-feather="x-circle" style="width:14px;height:14px;"></i>
                                        </button>
                                    </div>
                                @else
                                    <span class="text-muted small">დასრულებული</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">აქტივაციები არ არის</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($activations->hasPages())
            <div class="mt-3">{{ $activations->links() }}</div>
            @endif
        </div>
    </div>
</div>

@php
    $smsActivationConfig = [
        'getServicesUrl' => route('admin.sms.get-services'),
        'getNumberUrl' => route('admin.sms.get-number'),
        'setStatusUrl' => url('/admin/sms/{id}/set-status'),
        'checkStatusUrl' => url('/admin/sms/{id}/check-status'),
        'countries' => $countries,
    ];
@endphp
<script id="sms-activation-config" type="application/json">{!! json_encode($smsActivationConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endfragment
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!document.getElementById('sms-activation-config')) return;

    const config = JSON.parse(document.getElementById('sms-activation-config').textContent);
    const pageRoot = document.querySelector('[data-page-title="SMS Activation"]');
    const countrySelect = document.getElementById('countrySelect');
    const serviceSelect = document.getElementById('serviceSelect');
    const btnSubmit = document.getElementById('btnSubmitNumber');
    const form = document.getElementById('getNumberForm');
    let liveUpdateTimer = null;

    const formatUsd = (value) => `$${Number(value || 0).toFixed(2)}`;
    const formatRub = (value) => `${Number(value || 0).toFixed(2)} ₽`;

    const resetSubmitButton = () => {
        btnSubmit.disabled = !serviceSelect?.value;
        btnSubmit.innerHTML = '<i data-feather="phone" style="width:16px;height:16px;"></i> ნომრის მიღება';
        feather.replace();
    };

    const copyToClipboard = async (text, triggerButton = null) => {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
            } else {
                const tempInput = document.createElement('textarea');
                tempInput.value = text;
                tempInput.setAttribute('readonly', 'readonly');
                tempInput.style.position = 'absolute';
                tempInput.style.left = '-9999px';
                document.body.appendChild(tempInput);
                tempInput.select();
                document.execCommand('copy');
                document.body.removeChild(tempInput);
            }

            if (triggerButton) {
                triggerButton.innerHTML = '<i data-feather="check" style="width:12px;height:12px;"></i>';
                feather.replace();
                setTimeout(() => {
                    triggerButton.innerHTML = '<i data-feather="copy" style="width:12px;height:12px;"></i>';
                    feather.replace();
                }, 1500);
            }

            if (window.AdminHelpers) {
                window.AdminHelpers.showToast('SMS კოდი დაკოპირდა', 'success');
            }
        } catch (error) {
            if (window.AdminHelpers) {
                window.AdminHelpers.showToast('კოდის კოპირება ვერ მოხერხდა', 'error');
            }
        }
    };

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const statusBadgeClass = (status) => {
        switch (status) {
            case 'pending': return 'warning';
            case 'ready': return 'info';
            case 'code_received': return 'success';
            case 'cancelled': return 'danger';
            case 'completed':
            case 'expired':
            default:
                return 'gray';
        }
    };

    const addActivationRow = (activation) => {
        if (!activation) {
            return;
        }

        const tbody = document.querySelector('table tbody');
        if (!tbody) {
            return;
        }

        const row = document.createElement('tr');
        row.dataset.activationRow = activation.id;
        row.classList.add('table-warning');

        const statusBadgeClass = (status) => {
            switch (status) {
                case 'pending': return 'warning';
                case 'ready': return 'info';
                case 'code_received': return 'success';
                case 'cancelled': return 'danger';
                case 'completed':
                case 'expired':
                default:
                    return 'gray';
            }
        };

        const formatUsd = (value) => `$${Number(value || 0).toFixed(2)}`;

        const actionButtons = [];
        actionButtons.push(`<button type="button" class="btn btn-sm btn-primary btn-check-status" data-id="${activation.id}" title="შეამოწმე კოდი"><i data-feather="refresh-cw" style="width:14px;height:14px;"></i></button>`);
        actionButtons.push(`<button type="button" class="btn btn-sm btn-outline-info btn-set-status" data-id="${activation.id}" data-status="1" title="მზადაა"><i data-feather="play" style="width:14px;height:14px;"></i></button>`);
        actionButtons.push(`<button type="button" class="btn btn-sm btn-outline-danger btn-set-status" data-id="${activation.id}" data-status="8" title="გაუქმე"><i data-feather="x-circle" style="width:14px;height:14px;"></i></button>`);

        row.innerHTML = `
            <td>${escapeHtml(activation.id)}</td>
            <td>${escapeHtml(activation.phone_number || '—')}</td>
            <td><span class="badge bg-${statusBadgeClass(activation.status)}">${escapeHtml((activation.status || '').replace(/_/g, ' ').replace(/\b\w/g, (m) => m.toUpperCase()))}</span></td>
            <td><span class="text-muted small">მოლოდინში...</span></td>
            <td>${activation.cost ? formatUsd(activation.cost) : '—'}</td>
            <td>${escapeHtml(activation.service_name || activation.service || '—')}</td>
            <td><div class="d-flex flex-wrap gap-1">${actionButtons.join('')}</div></td>
        `;

        // Insert at the top of the table
        tbody.insertBefore(row, tbody.firstChild);
        feather.replace();
    };

    const updateActivationRow = (activation) => {
        if (!activation) {
            return;
        }

        const row = document.querySelector(`tr[data-activation-row="${activation.id}"]`);
        if (!row) {
            return;
        }

        row.classList.toggle('table-warning', ['pending', 'ready'].includes(activation.status));

        const cells = row.querySelectorAll('td');
        if (cells.length < 7) {
            return;
        }

        cells[2].innerHTML = `<span class="badge bg-${statusBadgeClass(activation.status)}">${escapeHtml((activation.status || '').replace(/_/g, ' ').replace(/\b\w/g, (m) => m.toUpperCase()))}</span>`;

        if (activation.sms_code) {
            cells[3].innerHTML = `<div class="d-flex align-items-center gap-2"><span class="fw-bold text-success font-monospace fs-5">${escapeHtml(activation.sms_code)}</span><button type="button" class="btn btn-sm btn-outline-secondary p-1 btn-copy-sms" data-code="${escapeHtml(activation.sms_code)}" title="Copy"><i data-feather="copy" style="width:12px;height:12px;"></i></button></div>`;
        } else {
            cells[3].innerHTML = '<span class="text-muted small">მოლოდინში...</span>';
        }

        cells[4].textContent = activation.cost ? `$${Number(activation.cost).toFixed(2)}` : '—';

        if (['pending', 'ready', 'code_received'].includes(activation.status)) {
            const actionButtons = [];
            actionButtons.push(`<button type="button" class="btn btn-sm btn-primary btn-check-status" data-id="${activation.id}" title="შეამოწმე კოდი"><i data-feather="refresh-cw" style="width:14px;height:14px;"></i></button>`);

            if (activation.status === 'pending') {
                actionButtons.push(`<button type="button" class="btn btn-sm btn-outline-info btn-set-status" data-id="${activation.id}" data-status="1" title="მზადაა"><i data-feather="play" style="width:14px;height:14px;"></i></button>`);
            }

            if (['ready', 'code_received'].includes(activation.status)) {
                actionButtons.push(`<button type="button" class="btn btn-sm btn-outline-warning btn-set-status" data-id="${activation.id}" data-status="3" title="მოითხოვე ახალი SMS"><i data-feather="rotate-cw" style="width:14px;height:14px;"></i></button>`);
                actionButtons.push(`<button type="button" class="btn btn-sm btn-outline-success btn-set-status" data-id="${activation.id}" data-status="6" title="დაასრულე"><i data-feather="check-circle" style="width:14px;height:14px;"></i></button>`);
            }

            actionButtons.push(`<button type="button" class="btn btn-sm btn-outline-danger btn-set-status" data-id="${activation.id}" data-status="8" title="გაუქმე"><i data-feather="x-circle" style="width:14px;height:14px;"></i></button>`);
            cells[6].innerHTML = `<div class="d-flex flex-wrap gap-1">${actionButtons.join('')}</div>`;
        } else {
            cells[6].innerHTML = '<span class="text-muted small">დასრულებული</span>';
        }

        feather.replace();
    };

    const restartLiveUpdates = () => {
        if (liveUpdateTimer) {
            clearInterval(liveUpdateTimer);
            liveUpdateTimer = null;
        }

        if (!document.querySelector('.btn-check-status')) {
            return;
        }

        liveUpdateTimer = setInterval(async () => {
            const buttons = Array.from(document.querySelectorAll('.btn-check-status'));
            for (const button of buttons) {
                if (button.disabled) {
                    continue;
                }

                const id = button.dataset.id;
                const url = config.checkStatusUrl.replace('{id}', id);

                try {
                    const res = await axios.get(url);
                    if (res.data.success && res.data.activation) {
                        updateActivationRow(res.data.activation);
                    }
                } catch (e) {
                    console.error('Live SMS update error:', e);
                }
            }

            if (!document.querySelector('.btn-check-status') && liveUpdateTimer) {
                clearInterval(liveUpdateTimer);
                liveUpdateTimer = null;
            }
        }, 10000);
    };

    const handleStatusAction = async (button) => {
        const id = button.dataset.id;
        const status = button.dataset.status;
        const url = config.setStatusUrl.replace('{id}', id);
        const originalHtml = button.innerHTML;

        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';

        try {
            const res = await axios.post(url, { status });
            if (res.data.success) {
                if (window.AdminHelpers) {
                    window.AdminHelpers.showToast('სტატუსი განახლდა', 'success');
                }
                if (res.data.activation) {
                    updateActivationRow(res.data.activation);
                }
                restartLiveUpdates();
                return;
            }

            throw new Error(res.data.error || 'სტატუსის განახლება ვერ მოხერხდა');
        } catch (e) {
            button.disabled = false;
            button.innerHTML = originalHtml;
            feather.replace();
            if (window.AdminHelpers) {
                window.AdminHelpers.showToast(e.response?.data?.error || e.message || 'შეცდომა', 'error');
            }
        }
    };

    // Populate countries
    if (countrySelect && config.countries) {
        config.countries.forEach(c => {
            const option = document.createElement('option');
            option.value = c.id;
            option.textContent = c.name;
            countrySelect.appendChild(option);
        });

        // Initialize Select2 on country select
        $(countrySelect).select2({
            placeholder: 'აირჩიე ქვეყანა...',
            allowClear: true,
            width: '100%'
        }).on('select2:open', function() {
            // Auto-focus search input when dropdown opens
            setTimeout(() => {
                document.querySelector('.select2-search__field').focus();
            }, 100);
        });
    }

    // Country change - load services
    if (countrySelect) {
        $(countrySelect).on('change', async (e) => {
            const country = e.target.value;

            // Destroy Select2 on service select if exists
            if ($(serviceSelect).hasClass("select2-hidden-accessible")) {
                $(serviceSelect).select2('destroy');
            }

            serviceSelect.disabled = true;
            serviceSelect.innerHTML = '<option value="">იტვირთება...</option>';
            btnSubmit.disabled = true;

            if (!country) {
                serviceSelect.innerHTML = '<option value="">ჯერ აირჩიე ქვეყანა...</option>';
                return;
            }

            try {
                const res = await axios.post(config.getServicesUrl, { country });
                const services = res.data.services || [];

                serviceSelect.innerHTML = '<option value="">აირჩიე სერვისი...</option>';

                if (services.length === 0) {
                    serviceSelect.innerHTML = '<option value="">სერვისები არ არის ხელმისაწვდომი</option>';
                    return;
                }

                services.forEach(s => {
                    const option = document.createElement('option');
                    option.value = s.code;
                    const name = s.name && s.name !== s.code.toUpperCase() ? s.name : s.code.toUpperCase();
                    option.textContent = `${name} — ${formatUsd(s.cost)} (${s.count} ხელმისაწვდომი)`;
                    option.dataset.cost = s.cost;
                    option.dataset.count = s.count;
                    serviceSelect.appendChild(option);
                });

                serviceSelect.disabled = false;

                // Initialize Select2 on service select with live search
                $(serviceSelect).select2({
                    placeholder: 'აირჩიე სერვისი...',
                    allowClear: true,
                    width: '100%'
                }).on('select2:open', function() {
                    // Auto-focus search input when dropdown opens
                    setTimeout(() => {
                        document.querySelector('.select2-search__field').focus();
                    }, 100);
                });
            } catch (e) {
                serviceSelect.innerHTML = '<option value="">შეცდომა სერვისების ჩატვირთვისას</option>';
                console.error(e);
            }
        });
    }

    // Service change - enable submit
    if (serviceSelect) {
        $(serviceSelect).on('change', (e) => {
            btnSubmit.disabled = !e.target.value;
        });
    }

    // Form submit - get number
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const country = countrySelect.value;
            const service = serviceSelect.value;

            if (!country || !service) return;

            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm"></span> იღებს ნომერს...';

            try {
                const res = await axios.post(config.getNumberUrl, { country, service });

                if (res.data.success) {
                    const activation = res.data.activation;
                    if (window.AdminHelpers) {
                        window.AdminHelpers.showToast(`✅ ნომერი მიღებულია: ${activation?.phone_number}`, 'success');
                    }

                    // Reset form
                    form.reset();
                    serviceSelect.disabled = true;
                    serviceSelect.innerHTML = '<option value="">ჯერ აირჩიე ქვეყანა...</option>';

                    // Add new activation to table immediately
                    if (activation) {
                        addActivationRow(activation);
                        restartLiveUpdates();
                    }
                } else {
                    if (window.AdminHelpers) {
                        window.AdminHelpers.showToast(res.data.error || 'ნომრის მიღება ვერ მოხერხდა', 'error');
                    }
                    resetSubmitButton();
                }
            } catch (e) {
                if (window.AdminHelpers) {
                    window.AdminHelpers.showToast(e.response?.data?.message || 'შეცდომა', 'error');
                }
                resetSubmitButton();
            }
        });
    }

    pageRoot?.addEventListener('click', async (event) => {
        const copyButton = event.target.closest('.btn-copy-sms');
        if (copyButton) {
            await copyToClipboard(copyButton.dataset.code || '', copyButton);
            return;
        }

        const statusButton = event.target.closest('.btn-set-status');
        if (statusButton) {
            await handleStatusAction(statusButton);
            return;
        }

        const btn = event.target.closest('.btn-check-status');
        if (btn) {
            const id = btn.dataset.id;
            const url = config.checkStatusUrl.replace('{id}', id);
            const originalHtml = btn.innerHTML;

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> შემოწმება...';

            try {
                const res = await axios.get(url);
                if (res.data.success) {
                    const activation = res.data.activation;

                    if (activation.sms_code) {
                        // Show SMS code in toast
                        Swal.fire({
                            icon: 'success',
                            title: '📨 SMS კოდი მიღებულია!',
                            html: `<div class="text-center">
                                <h2 class="text-success font-monospace mb-3">${activation.sms_code}</h2>
                                <button class="btn btn-outline-primary btn-sm swal-copy-sms" data-code="${activation.sms_code}">
                                    <i data-feather="copy"></i> კოდის კოპირება
                                </button>
                            </div>`,
                            confirmButtonText: 'კარგი',
                            didOpen: () => {
                                feather.replace();
                                const copyButton = Swal.getHtmlContainer()?.querySelector('.swal-copy-sms');
                                if (copyButton) {
                                    copyButton.addEventListener('click', () => copyToClipboard(activation.sms_code, copyButton));
                                }
                            }
                        });

                        // Update row in table
                        updateActivationRow(activation);
                        restartLiveUpdates();
                    } else {
                        if (window.AdminHelpers) window.AdminHelpers.showToast('კოდი ჯერ არ არის მიღებული', 'info');
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                        feather.replace();
                    }
                }
            } catch (e) {
                if (window.AdminHelpers) window.AdminHelpers.showToast(e.response?.data?.error || 'შეცდომა', 'error');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                feather.replace();
            }
        }
    });

    restartLiveUpdates();
});
</script>
@endpush
