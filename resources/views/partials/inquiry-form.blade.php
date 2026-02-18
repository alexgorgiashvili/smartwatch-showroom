<div class="card">
    <div class="card-body">
        @if (session('status'))
            <div class="alert alert-success" role="alert">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('inquiries.store') }}">
            @csrf
            @if (!empty($product))
                <input type="hidden" name="product_id" value="{{ $product->id }}">
            @endif

            <div class="mb-3">
                <label class="form-label fw-semibold">{{ __('ui.form_name') }}</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">{{ __('ui.form_phone') }}</label>
                <input type="text" name="phone" value="{{ old('phone') }}" required class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">{{ __('ui.form_email') }}</label>
                <input type="email" name="email" value="{{ old('email') }}" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">{{ __('ui.form_message') }}</label>
                <textarea name="message" rows="4" class="form-control">{{ old('message') }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary w-100">
                {{ __('ui.form_submit') }}
            </button>
        </form>
    </div>
</div>
