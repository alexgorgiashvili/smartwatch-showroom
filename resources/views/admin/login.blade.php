@extends('admin.layout')

@section('title', 'შესვლა - KidsWatch ადმინი')

@push('styles')
<style>
    body:has(.admin-login-page) {
        background: #f4f7fb;
    }

    .page-wrapper.full-page .page-content {
        min-height: 100vh;
        padding: 0;
    }

    .page-wrapper.full-page .page-content > .w-100 {
        min-height: 100vh;
    }

    .admin-login-page {
        min-height: 100vh;
        width: 100%;
        display: grid;
        place-items: center;
        padding: 32px 18px;
        background:
            radial-gradient(circle at top left, rgba(34, 116, 255, .16), transparent 34rem),
            linear-gradient(135deg, #f8fbff 0%, #eef4fb 48%, #f7fafc 100%);
    }

    .admin-login-card {
        width: min(100%, 420px);
        border: 1px solid rgba(15, 23, 42, .08);
        border-radius: 8px;
        background: rgba(255, 255, 255, .96);
        box-shadow: 0 22px 70px rgba(15, 23, 42, .12);
        overflow: hidden;
    }

    .admin-login-card__body {
        padding: 34px;
    }

    .admin-login-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 28px;
    }

    .admin-login-brand__mark {
        width: 44px;
        height: 44px;
        display: inline-grid;
        place-items: center;
        border-radius: 8px;
        color: #fff;
        background: linear-gradient(135deg, #2563eb, #14b8a6);
        box-shadow: 0 12px 24px rgba(37, 99, 235, .24);
    }

    .admin-login-brand__title {
        margin: 0;
        color: #0f172a;
        font-size: 20px;
        font-weight: 800;
        line-height: 1.15;
    }

    .admin-login-brand__text {
        margin: 3px 0 0;
        color: #64748b;
        font-size: 13px;
    }

    .admin-login-page .form-label {
        color: #334155;
        font-weight: 700;
        font-size: 13px;
    }

    .admin-login-page .form-control {
        height: 46px;
        border-radius: 8px;
        border-color: #d7dee9;
        color: #0f172a;
        background: #fff;
        box-shadow: none;
    }

    .admin-login-page .form-control:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, .12);
    }

    .admin-login-page .form-check {
        display: flex;
        align-items: center;
        gap: 8px;
        min-height: 20px;
    }

    .admin-login-page .form-check-input {
        margin: 0;
        border-color: #cbd5e1;
    }

    .admin-login-page .form-check-label {
        color: #475569;
        font-size: 13px;
        font-weight: 600;
    }

    .admin-login-page .btn-primary {
        height: 46px;
        width: 100%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 8px;
        font-weight: 800;
        background: linear-gradient(135deg, #2563eb, #14b8a6);
        box-shadow: 0 14px 30px rgba(37, 99, 235, .22);
    }

    .admin-login-page .btn-primary:hover,
    .admin-login-page .btn-primary:focus {
        background: linear-gradient(135deg, #1d4ed8, #0f9f90);
    }

    @media (max-width: 480px) {
        .admin-login-card__body {
            padding: 26px;
        }
    }
</style>
@endpush

@section('content')
<div class="admin-login-page">
    <div class="admin-login-card">
        <div class="admin-login-card__body">
            <div class="admin-login-brand">
                <div class="admin-login-brand__mark">
                    <i data-feather="watch"></i>
                </div>
                <div>
                    <h1 class="admin-login-brand__title">KidsWatch Admin</h1>
                    <p class="admin-login-brand__text">შედით, რომ მართოთ შოურუმი</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.login.submit') }}">
                @csrf

                <div class="mb-3">
                    <label for="email" class="form-label">ელფოსტის მისამართი</label>
                    <input type="email"
                           class="form-control @error('email') is-invalid @enderror"
                           id="email"
                           name="email"
                           value="{{ old('email') }}"
                           placeholder="admin@kidswatch.ge"
                           required
                           autofocus>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">პაროლი</label>
                    <input type="password"
                           class="form-control @error('password') is-invalid @enderror"
                           id="password"
                           name="password"
                           placeholder="შეიყვანეთ პაროლი"
                           required>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-check mb-4">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember" {{ old('remember') ? 'checked' : '' }}>
                    <label class="form-check-label" for="remember">დამიმახსოვრე</label>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">შესვლა</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
