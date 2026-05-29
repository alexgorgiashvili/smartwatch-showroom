{{-- Shared facebook post form partial --}}
<div class="row g-3">
    <div class="col-lg-8">
        {{-- Main Form --}}
        <div class="card mb-3">
            <div class="card-header bg-transparent border-bottom">
                <h6 class="mb-0">Post Details</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="product_id" class="form-label">Product (optional)</label>
                        <select class="form-select @error('product_id') is-invalid @enderror" id="product_id" name="product_id">
                            <option value="">— No product —</option>
                            @foreach($products as $product)
                            <option value="{{ $product->id }}" {{ old('product_id', $post?->product_id) == $product->id ? 'selected' : '' }}>
                                {{ $product->name_en ?: $product->name_ka }} — GEL {{ number_format($product->sale_price ?? $product->price, 2) }}
                            </option>
                            @endforeach
                        </select>
                        @error('product_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        @php
                            $mediaTypeValue = old('media_type', $post?->media_type ?? ($post?->video_url ? 'video' : ($post?->image_url ? 'image' : 'none')));
                        @endphp
                        <label for="media_type" class="form-label">Media Type</label>
                        <select class="form-select @error('media_type') is-invalid @enderror" id="media_type" name="media_type">
                            <option value="none" {{ $mediaTypeValue === 'none' ? 'selected' : '' }}>None (Text only)</option>
                            <option value="image" {{ $mediaTypeValue === 'image' ? 'selected' : '' }}>Image</option>
                            <option value="video" {{ $mediaTypeValue === 'video' ? 'selected' : '' }}>Video</option>
                        </select>
                        @error('media_type') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror

                        <div class="mt-2" id="image_url_group">
                            <label for="image_url" class="form-label">Image URL</label>
                        <div class="input-group">
                            <input type="url" class="form-control @error('image_url') is-invalid @enderror"
                                   id="image_url" name="image_url" value="{{ old('image_url', $post?->image_url) }}" placeholder="https://...">
                            <button type="button" class="btn btn-outline-secondary" id="btn-preview-image" title="Preview Image">
                                <i data-feather="external-link" style="width:14px;height:14px;"></i>
                            </button>
                            <button type="button" class="btn btn-outline-primary" id="btn-image-manager" title="Open Image Manager">
                                <i data-feather="image" style="width:14px;height:14px;"></i> Manager
                            </button>
                        </div>
                        @error('image_url') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>

                        <div class="mt-2 d-none" id="video_url_group">
                            <label for="video_url" class="form-label">Video URL</label>
                            <div class="input-group">
                                <input type="url" class="form-control @error('video_url') is-invalid @enderror"
                                       id="video_url" name="video_url" value="{{ old('video_url', $post?->video_url) }}" placeholder="https://...">
                                <button type="button" class="btn btn-outline-secondary" id="btn-preview-video" title="Preview Video">
                                    <i data-feather="external-link" style="width:14px;height:14px;"></i>
                                </button>
                                <button type="button" class="btn btn-outline-primary" id="btn-upload-video" title="Upload Video">
                                    <i data-feather="upload-cloud" style="width:14px;height:14px;"></i> Upload
                                </button>
                            </div>
                            @error('video_url') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            <div class="progress mt-2 d-none" id="video-upload-progress-container" style="height: 10px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" id="video-upload-progress-bar" role="progressbar" style="width: 0%"></div>
                            </div>
                            <input type="file" id="video-upload-input" class="d-none" accept="video/mp4">
                        </div>
                    </div>
                    <div class="col-12">
                        <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                        <textarea class="form-control @error('message') is-invalid @enderror"
                                  id="message" name="message" rows="10" required>{{ old('message', $post?->message) }}</textarea>
                        @error('message') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <span class="form-text" id="message-char-count">0 characters</span>
                            <button type="button" class="btn btn-xs btn-outline-secondary border-0 p-0 px-1" id="btn-suggest-hashtags" style="font-size:11px;">
                                <i data-feather="hash" style="width:11px;height:11px;"></i> # ჰეშთეგები
                            </button>
                        </div>
                        <div id="hashtag-chips" class="d-flex flex-wrap gap-1 mt-1"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label d-block">Platforms</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="post_to_facebook" name="post_to_facebook" value="1"
                                   {{ old('post_to_facebook', $post?->post_to_facebook ?? true) ? 'checked' : '' }}
                                   {{ !$fbConfigured ? 'disabled' : '' }}>
                            <label class="form-check-label" for="post_to_facebook">
                                Facebook {{ !$fbConfigured ? '(not configured)' : '' }}
                            </label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="post_to_instagram" name="post_to_instagram" value="1"
                                   {{ old('post_to_instagram', $post?->post_to_instagram ?? true) ? 'checked' : '' }}
                                   {{ !$igConfigured ? 'disabled' : '' }}>
                            <label class="form-check-label" for="post_to_instagram">
                                Instagram {{ !$igConfigured ? '(not configured)' : '' }}
                            </label>
                        </div>
                    </div>

                    <input type="hidden" name="ai_prompt" id="ai_prompt" value="{{ old('ai_prompt', $post?->ai_prompt) }}">
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <div class="row g-2 align-items-end mb-2">
                    <div class="col-md-5">
                        <label class="form-label small fw-bold mb-1">
                            <i data-feather="clock" style="width:13px;height:13px;"></i> განრიგი <span class="text-muted">(სურვ.)</span>
                        </label>
                        <input type="datetime-local" class="form-control form-control-sm" id="scheduled_at" name="scheduled_at"
                               value="{{ old('scheduled_at', $post?->scheduled_at?->format('Y-m-d\TH:i')) }}">
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <button type="submit" name="action" value="{{ $post ? 'save' : 'draft' }}" class="btn btn-outline-warning btn-sm" id="btn-save-draft">
                        <i data-feather="file-text" style="width:14px;height:14px;"></i> დრაფტი
                    </button>
                    <button type="submit" name="action" value="schedule" class="btn btn-outline-primary btn-sm" id="btn-schedule-post">
                        <i data-feather="clock" style="width:14px;height:14px;"></i> განრიგი
                    </button>
                    <button type="submit" name="action" value="{{ $post ? 'publish' : 'publish' }}" class="btn btn-primary btn-sm" id="btn-publish-now">
                        <i data-feather="send" style="width:14px;height:14px;"></i> გამოქვეყნება
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        {{-- AI Generator Panel --}}
        <div class="card mb-3 border-primary">
            <div class="card-header bg-primary text-white d-flex align-items-center gap-2">
                <i data-feather="zap" style="width:16px;height:16px;"></i>
                <h6 class="mb-0 text-white">AI Content Generator</h6>
            </div>
            <div class="card-body bg-light">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Language</label>
                    <select class="form-select form-select-sm" id="ai_language">
                        <option value="ka">Georgian</option>
                        <option value="en">English</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Generation Mode</label>
                    <select class="form-select form-select-sm" id="ai_mode">
                        <option value="custom">Custom (Follow Instructions)</option>
                        <option value="autonomous">Autonomous (AI decides best angles)</option>
                    </select>
                </div>
                <div class="mb-3" id="ai_tone_container">
                    <label class="form-label small fw-bold">Tone</label>
                    <select class="form-select form-select-sm" id="ai_tone">
                        <option value="professional">Professional</option>
                        <option value="casual">Casual / Friendly</option>
                        <option value="exciting">Exciting / Energetic</option>
                        <option value="urgent">Urgent (Sales)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold d-flex justify-content-between">
                        Custom Instructions (Optional)
                        <button type="button" class="btn btn-xs btn-outline-info p-0 px-1 border-0" id="btn-enhance-prompt" title="Enhance Georgian Prompt" style="font-size: 10px;">
                            <i data-feather="edit-3" style="width:10px;height:10px;"></i> Enhance Prompt
                        </button>
                    </label>
                    <textarea class="form-control form-control-sm" id="ai_description" rows="3" placeholder="e.g. Highlight the battery life..."></textarea>
                    <div id="enhance-feedback" class="small text-success mt-1 d-none">Prompt enhanced successfully!</div>
                </div>
                <button type="button" class="btn btn-primary btn-sm w-100 d-flex justify-content-center align-items-center gap-2" id="btn-ai-generate">
                    <i data-feather="cpu" style="width:14px;height:14px;"></i> Generate 3 Variants
                </button>
            </div>
        </div>

        {{-- AI Results --}}
        <div id="ai-results-container" class="d-none">
            <h6 class="mb-2 small fw-bold text-muted text-uppercase">Generated Variants</h6>
            <div id="ai-variants-list" class="d-flex flex-column gap-2">
                <!-- Variants will be rendered here -->
            </div>
        </div>
    </div>
</div>

{{-- Image Manager Modal --}}
<div class="modal fade" id="image-manager-modal" tabindex="-1" aria-labelledby="imageManagerModalLabel" inert>
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageManagerModalLabel">Image Manager</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs mb-3" id="imageManagerTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="gallery-tab" data-bs-toggle="tab" data-bs-target="#gallery-tab-pane" type="button" role="tab" aria-controls="gallery-tab-pane" aria-selected="true">Global Gallery</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="upload-tab" data-bs-toggle="tab" data-bs-target="#upload-tab-pane" type="button" role="tab" aria-controls="upload-tab-pane" aria-selected="false">Upload & Crop</button>
                    </li>
                </ul>
                <div class="tab-content" id="imageManagerTabContent">
                    {{-- Gallery Tab --}}
                    <div class="tab-pane fade show active" id="gallery-tab-pane" role="tabpanel" aria-labelledby="gallery-tab" tabindex="0">
                        <div class="row mb-3 g-2">
                            <div class="col-md-4">
                                <select class="form-select form-select-sm" id="gallery-filter-product">
                                    <option value="">All Products</option>
                                    @foreach($products as $prod)
                                        <option value="{{ $prod->id }}">{{ $prod->name_en ?: $prod->name_ka }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select form-select-sm" id="gallery-filter-time">
                                    <option value="">All Time</option>
                                    <option value="today">Today</option>
                                    <option value="week">Past Week</option>
                                    <option value="month">Past Month</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="btn-gallery-refresh">
                                    <i data-feather="refresh-cw" style="width:12px;height:12px;"></i> Filter
                                </button>
                            </div>
                        </div>

                        <div id="gallery-loading" class="text-center py-4 d-none">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                        <div id="gallery-empty" class="text-center text-muted py-4 d-none">
                            No images found with the current filters.
                        </div>
                        <div id="gallery-grid" class="row g-2" style="max-height: 500px; overflow-y: auto; overflow-x: hidden;">
                            <!-- Images injected via JS -->
                        </div>
                        <div class="text-center mt-3 d-none" id="gallery-load-more-container">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-gallery-load-more">Load More</button>
                        </div>
                    </div>

                    {{-- Upload & Crop Tab --}}
                    <div class="tab-pane fade" id="upload-tab-pane" role="tabpanel" aria-labelledby="upload-tab" tabindex="0">
                        <div class="row">
                            <div class="col-md-8">
                                <div id="upload-zone" class="border border-2 border-dashed rounded p-4 text-center cursor-pointer mb-3 bg-light">
                                    <i data-feather="upload-cloud" style="width:32px;height:32px;" class="text-muted mb-2"></i>
                                    <p class="mb-0">Drag and drop an image here or click to browse</p>
                                    <p class="small text-muted">Supports JPG, PNG, WEBP up to 5MB</p>
                                    <input type="file" id="standalone-image-upload" class="d-none" accept="image/jpeg,image/png,image/webp">
                                </div>
                                <div id="cropper-container" class="d-none mb-3" style="max-height: 400px;">
                                    <img id="cropper-image" src="" style="max-width: 100%;">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div id="cropper-controls" class="d-none">
                                    <h6 class="mb-3">Editing Tools</h6>

                                    <div class="mb-3">
                                        <label class="form-label small">Aspect Ratio</label>
                                        <select class="form-select form-select-sm" id="cropper-ratio">
                                            <option value="NaN">Free</option>
                                            <option value="1">1:1 (Instagram)</option>
                                            <option value="1.91">1.91:1 (Facebook Post)</option>
                                            <option value="1.33333">4:3</option>
                                            <option value="1.77777">16:9</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label small d-block">Rotate</label>
                                        <div class="btn-group btn-group-sm w-100">
                                            <button type="button" class="btn btn-outline-secondary" id="btn-crop-rotate-left" title="Rotate Left">
                                                <i data-feather="rotate-ccw" style="width:14px;height:14px;"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" id="btn-crop-rotate-right" title="Rotate Right">
                                                <i data-feather="rotate-cw" style="width:14px;height:14px;"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label small d-block">Flip</label>
                                        <div class="btn-group btn-group-sm w-100">
                                            <button type="button" class="btn btn-outline-secondary" id="btn-crop-flip-h" title="Flip Horizontal">
                                                <i data-feather="code" style="width:14px;height:14px;transform: rotate(90deg);"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" id="btn-crop-flip-v" title="Flip Vertical">
                                                <i data-feather="code" style="width:14px;height:14px;"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <hr>

                                    <button type="button" class="btn btn-primary w-100 mb-2" id="btn-crop-save">
                                        <i data-feather="check" style="width:14px;height:14px;"></i> Crop & Upload
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary w-100" id="btn-crop-cancel">
                                        Cancel
                                    </button>

                                    <div class="progress mt-3 d-none" id="upload-progress-container" style="height: 10px;">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" id="upload-progress-bar" role="progressbar" style="width: 0%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="btn-select-gallery-image" disabled>Use Selected Image</button>
            </div>
        </div>
    </div>
</div>
