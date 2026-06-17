@extends('admin.layout')

@section('title', 'Users — Admin')

@section('content')
@fragment('content')
<div data-page-title="მომხმარებლები">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div><h4 class="mb-3 mb-md-0">მომხმარებლები</h4></div>
        <div>
            <button type="button" class="btn btn-primary btn-sm" id="btnCreateUser">
                <i data-feather="plus" style="width:16px;height:16px;"></i> მომხმარებლის დამატება
            </button>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th>სახელი</th>
                            <th>ელფოსტა</th>
                            <th>როლი</th>
                            <th>რეგისტრაცია</th>
                            <th style="width:100px;">მოქმედებები</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                        <tr>
                            <td class="fw-bold">{{ $user->name }}</td>
                            <td class="text-muted">{{ $user->email }}</td>
                            <td>
                                @if($user->is_admin)
                                    <span class="badge bg-primary">ადმინი</span>
                                @else
                                    <span class="badge bg-secondary">მომხმარებელი</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $user->created_at->format('M d, Y') }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.users.toggle-admin', $user) }}" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-outline-{{ $user->is_admin ? 'warning' : 'primary' }} btn-sm p-1"
                                            title="{{ $user->is_admin ? 'ადმინის მოხსნა' : 'ადმინის მინიჭება' }}"
                                            onclick="return confirm('{{ $user->is_admin ? 'მოვხსნათ ადმინის როლი?' : 'მივანიჭოთ ადმინის როლი?' }}')">
                                        <i data-feather="{{ $user->is_admin ? 'shield-off' : 'shield' }}" style="width:14px;height:14px;"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">მომხმარებლები ვერ მოიძებნა</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($users->hasPages())
            <div class="mt-3">{{ $users->links() }}</div>
            @endif
        </div>
    </div>
</div>

{{-- Create User Modal (SweetAlert2 driven from JS) --}}
@php
    $createUserConfig = ['url' => route('admin.users.store')];
@endphp
<script id="create-user-url" type="application/json">{!! json_encode($createUserConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endfragment
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('btnCreateUser');
    if (!btn || typeof Swal === 'undefined') return;

    btn.addEventListener('click', () => {
        const config = JSON.parse(document.getElementById('create-user-url').textContent);
        Swal.fire({
            title: 'მომხმარებლის დამატება',
            html: `
                <div class="text-start">
                    <div class="mb-2"><label class="form-label small">სახელი <span class="text-danger">*</span></label>
                        <input type="text" id="swal-name" class="form-control form-control-sm" required></div>
                    <div class="mb-2"><label class="form-label small">ელფოსტა <span class="text-danger">*</span></label>
                        <input type="email" id="swal-email" class="form-control form-control-sm" required></div>
                    <div class="mb-2"><label class="form-label small">პაროლი <span class="text-danger">*</span></label>
                        <input type="password" id="swal-pass" class="form-control form-control-sm" required></div>
                    <div class="mb-2"><label class="form-label small">გაიმეორეთ პაროლი <span class="text-danger">*</span></label>
                        <input type="password" id="swal-pass-c" class="form-control form-control-sm" required></div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="swal-admin" value="1">
                        <label class="form-check-label" for="swal-admin">ადმინი</label>
                    </div>
                </div>`,
            showCancelButton: true,
            confirmButtonText: 'შექმნა',
            preConfirm: () => {
                const data = {
                    name: document.getElementById('swal-name').value,
                    email: document.getElementById('swal-email').value,
                    password: document.getElementById('swal-pass').value,
                    password_confirmation: document.getElementById('swal-pass-c').value,
                    is_admin: document.getElementById('swal-admin').checked ? 1 : 0,
                };
                if (!data.name || !data.email || !data.password) {
                    Swal.showValidationMessage('ყველა ველი სავალდებულოა');
                    return false;
                }
                return axios.post(config.url, data)
                    .then(r => r.data)
                    .catch(e => {
                        const msg = e.response?.data?.message || Object.values(e.response?.data?.errors || {}).flat().join(', ') || 'შეცდომა';
                        Swal.showValidationMessage(msg);
                        return false;
                    });
            },
        }).then(r => { if (r.isConfirmed) location.reload(); });
    });
});
</script>
@endpush
