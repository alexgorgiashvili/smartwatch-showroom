@extends('admin.layout')

@section('title', 'ახალი Facebook პოსტი')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-0">ახალი პოსტი</h3>
            <p class="text-muted mb-0">AI-ით გენერაცია ან ხელით წერა — Facebook & Instagram</p>
        </div>
        <a href="{{ route('admin.facebook-posts.index') }}" class="btn btn-outline-secondary">
            <i data-feather="arrow-left"></i> უკან
        </a>
    </div>

    @if(!$fbConfigured)
        <div class="alert alert-warning">
            <strong>Facebook:</strong> API არ არის კონფიგურირებული.
            დაამატეთ <code>FACEBOOK_PAGE_ID</code> და <code>FACEBOOK_PAGE_ACCESS_TOKEN</code> .env ფაილში.
        </div>
    @endif
    @if(!$igConfigured)
        <div class="alert alert-warning">
            <strong>Instagram:</strong> API არ არის კონფიგურირებული.
            დაამატეთ <code>INSTAGRAM_BUSINESS_ACCOUNT_ID</code> .env ფაილში.
        </div>
    @endif

    {{-- AI Generator Section --}}
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i data-feather="cpu" style="width:18px;height:18px"></i> AI გენერატორი</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="ai-product" class="form-label">პროდუქტი (არასავალდებულო)</label>
                    <select id="ai-product" class="form-select">
                        <option value="">-- თავისუფალი თემა --</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}">
                                {{ $product->name_ka ?? $product->name_en }} - {{ $product->sale_price ?? $product->price }}₾
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="ai-language" class="form-label">ენა</label>
                    <select id="ai-language" class="form-select">
                        <option value="ka">ქართული</option>
                        <option value="en">English</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="ai-tone" class="form-label">ტონი</label>
                    <select id="ai-tone" class="form-select">
                        <option value="professional">პროფესიონალური</option>
                        <option value="casual">მეგობრული</option>
                        <option value="exciting">აღტაცებული</option>
                        <option value="urgent">გადაუდებელი / აქცია</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" id="btn-generate" class="btn btn-success w-100">
                        <span id="generate-text">გენერაცია</span>
                        <span id="generate-spinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                    </button>
                </div>
            </div>
            <div id="ai-custom-desc-wrapper" class="mt-3">
                <label for="ai-description" class="form-label">აღწერა (თავისუფალი თემისთვის)</label>
                <textarea id="ai-description" class="form-control" rows="2"
                          placeholder="მაგ: სეზონური ფასდაკლება ყველა სმარტ საათზე..."></textarea>
            </div>
        </div>
    </div>

    {{-- Post Form --}}
    <form method="POST" action="{{ route('admin.facebook-posts.store') }}">
        @csrf
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">პოსტის ტექსტი</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="message" class="form-label">ტექსტი <span class="text-danger">*</span></label>
                    <textarea name="message" id="message" class="form-control @error('message') is-invalid @enderror"
                              rows="8" required>{{ old('message') }}</textarea>
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
                                <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>
                                    {{ $product->name_ka ?? $product->name_en }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="image_url" class="form-label">სურათის URL</label>
                        <input type="url" name="image_url" id="image_url"
                               class="form-control @error('image_url') is-invalid @enderror"
                               value="{{ old('image_url') }}"
                               placeholder="https://mytechnic.ge/storage/...">
                        @error('image_url')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <input type="hidden" name="ai_prompt" id="ai_prompt" value="{{ old('ai_prompt') }}">

                {{-- Platform Selection --}}
                <div class="mt-4">
                    <label class="form-label fw-semibold">პლატფორმა</label>
                    <div class="d-flex gap-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="post_to_facebook"
                                   id="post_to_facebook" value="1" {{ old('post_to_facebook', true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="post_to_facebook">
                                <span class="badge bg-primary">FB</span> Facebook
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="post_to_instagram"
                                   id="post_to_instagram" value="1" {{ old('post_to_instagram') ? 'checked' : '' }}>
                            <label class="form-check-label" for="post_to_instagram">
                                <span class="badge" style="background: linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888);">IG</span> Instagram
                                <small class="text-muted">(სურათი სავალდებულოა)</small>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Image Preview --}}
        <div id="image-preview-card" class="card mb-4 d-none">
            <div class="card-body text-center">
                <img id="image-preview" src="" alt="Preview" class="img-fluid rounded" style="max-height: 300px;">
            </div>
        </div>

        <div class="d-flex gap-2 justify-content-end mb-5">
            <button type="submit" name="action" value="draft" class="btn btn-outline-secondary">
                <i data-feather="save"></i> დრაფტად შენახვა
            </button>
            <button type="submit" name="action" value="publish" class="btn btn-primary"
                    onclick="return confirm('გამოქვეყნდეს არჩეულ პლატფორმებზე?')">
                <i data-feather="send"></i> გამოქვეყნება
            </button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const messageField = document.getElementById('message');
    const charCount = document.getElementById('char-count');
    const btnGenerate = document.getElementById('btn-generate');
    const generateText = document.getElementById('generate-text');
    const generateSpinner = document.getElementById('generate-spinner');
    const aiProduct = document.getElementById('ai-product');
    const aiLanguage = document.getElementById('ai-language');
    const aiTone = document.getElementById('ai-tone');
    const aiDescription = document.getElementById('ai-description');
    const aiCustomWrapper = document.getElementById('ai-custom-desc-wrapper');
    const imageUrlField = document.getElementById('image_url');
    const productIdField = document.getElementById('product_id');
    const aiPromptField = document.getElementById('ai_prompt');
    const previewCard = document.getElementById('image-preview-card');
    const previewImg = document.getElementById('image-preview');

    // Character counter
    messageField.addEventListener('input', function() {
        charCount.textContent = this.value.length;
    });
    charCount.textContent = messageField.value.length;

    // Toggle custom description visibility
    aiProduct.addEventListener('change', function() {
        aiCustomWrapper.style.display = this.value ? 'none' : 'block';
    });

    // Image URL preview
    imageUrlField.addEventListener('input', function() {
        if (this.value) {
            previewImg.src = this.value;
            previewCard.classList.remove('d-none');
        } else {
            previewCard.classList.add('d-none');
        }
    });
    previewImg.addEventListener('error', function() {
        previewCard.classList.add('d-none');
    });

    // AI Generate
    btnGenerate.addEventListener('click', function() {
        generateText.classList.add('d-none');
        generateSpinner.classList.remove('d-none');
        btnGenerate.disabled = true;

        const payload = {
            product_id: aiProduct.value || null,
            description: aiDescription.value || null,
            language: aiLanguage.value,
            tone: aiTone.value,
        };

        fetch('{{ route("admin.facebook-posts.generate") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                messageField.value = data.content;
                charCount.textContent = data.content.length;
                aiPromptField.value = data.prompt || '';

                if (data.image_url) {
                    imageUrlField.value = data.image_url;
                    previewImg.src = data.image_url;
                    previewCard.classList.remove('d-none');
                }

                if (aiProduct.value) {
                    productIdField.value = aiProduct.value;
                }
            } else {
                alert('შეცდომა: ' + (data.error || 'უცნობი შეცდომა'));
            }
        })
        .catch(err => {
            alert('კავშირის შეცდომა: ' + err.message);
        })
        .finally(() => {
            generateText.classList.remove('d-none');
            generateSpinner.classList.add('d-none');
            btnGenerate.disabled = false;
        });
    });
});
</script>
@endpush
