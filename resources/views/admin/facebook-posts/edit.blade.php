@extends('admin.layout')

@section('title', 'პოსტის რედაქტირება')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-0">პოსტის რედაქტირება</h3>
            <p class="text-muted mb-0">
                @switch($post->status)
                    @case('draft') <span class="badge bg-secondary">დრაფტი</span> @break
                    @case('published') <span class="badge bg-success">გამოქვეყნებული</span> @break
                    @case('failed') <span class="badge bg-danger">წარუმატებელი</span> @break
                @endswitch
                &middot; შექმნილი {{ $post->created_at->format('d/m/Y H:i') }}
            </p>
        </div>
        <a href="{{ route('admin.facebook-posts.index') }}" class="btn btn-outline-secondary">
            <i data-feather="arrow-left"></i> უკან
        </a>
    </div>

    @if(!$fbConfigured)
        <div class="alert alert-warning">
            <strong>Facebook:</strong> API არ არის კონფიგურირებული.
        </div>
    @endif
    @if(!$igConfigured)
        <div class="alert alert-warning">
            <strong>Instagram:</strong> API არ არის კონფიგურირებული (INSTAGRAM_BUSINESS_ACCOUNT_ID).
        </div>
    @endif

    @if($post->status === 'failed' && $post->error_message)
        <div class="alert alert-danger">
            <strong>შეცდომა:</strong> {{ $post->error_message }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.facebook-posts.update', $post) }}">
        @csrf
        @method('PUT')

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">პოსტის ტექსტი</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="message" class="form-label">ტექსტი <span class="text-danger">*</span></label>
                    <textarea name="message" id="message" class="form-control @error('message') is-invalid @enderror"
                              rows="8" required>{{ old('message', $post->message) }}</textarea>
                    @error('message')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">
                        <span id="char-count">0</span> სიმბოლო
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="product_id" class="form-label">დაკავშირებული პროდუქტი</label>
                        <select name="product_id" id="product_id" class="form-select">
                            <option value="">-- არცერთი --</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}" @selected(old('product_id', $post->product_id) == $product->id)>
                                    {{ $product->name_ka ?? $product->name_en }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="image_url" class="form-label">სურათის URL</label>
                        <input type="url" name="image_url" id="image_url"
                               class="form-control @error('image_url') is-invalid @enderror"
                               value="{{ old('image_url', $post->image_url) }}"
                               placeholder="https://mytechnic.ge/storage/...">
                        @error('image_url')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- Platform Selection --}}
                <div class="mt-4">
                    <label class="form-label fw-semibold">პლატფორმა</label>
                    <div class="d-flex gap-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="post_to_facebook"
                                   id="post_to_facebook" value="1" {{ old('post_to_facebook', $post->post_to_facebook) ? 'checked' : '' }}>
                            <label class="form-check-label" for="post_to_facebook">
                                <span class="badge bg-primary">FB</span> Facebook
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="post_to_instagram"
                                   id="post_to_instagram" value="1" {{ old('post_to_instagram', $post->post_to_instagram) ? 'checked' : '' }}>
                            <label class="form-check-label" for="post_to_instagram">
                                <span class="badge" style="background: linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888);">IG</span> Instagram
                                <small class="text-muted">(სურათი სავალდებულოა)</small>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($post->image_url)
            <div id="image-preview-card" class="card mb-4">
                <div class="card-body text-center">
                    <img id="image-preview" src="{{ $post->image_url }}" alt="Preview"
                         class="img-fluid rounded" style="max-height: 300px;">
                </div>
            </div>
        @endif

        @if($post->ai_prompt)
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">AI პრომპტი (რომლითაც დაგენერირდა)</h6>
                </div>
                <div class="card-body">
                    <pre class="mb-0" style="white-space: pre-wrap; font-size: 0.85rem;">{{ $post->ai_prompt }}</pre>
                </div>
            </div>
        @endif

        <div class="d-flex gap-2 justify-content-end mb-5">
            <button type="submit" name="action" value="save" class="btn btn-outline-secondary">
                <i data-feather="save"></i> შენახვა
            </button>
            @if($post->status !== 'published')
                <button type="submit" name="action" value="publish" class="btn btn-primary"
                        onclick="return confirm('გამოქვეყნდეს არჩეულ პლატფორმებზე?')">
                    <i data-feather="send"></i> გამოქვეყნება
                </button>
            @endif
        </div>
    </form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const messageField = document.getElementById('message');
    const charCount = document.getElementById('char-count');

    messageField.addEventListener('input', function() {
        charCount.textContent = this.value.length;
    });
    charCount.textContent = messageField.value.length;
});
</script>
@endpush
