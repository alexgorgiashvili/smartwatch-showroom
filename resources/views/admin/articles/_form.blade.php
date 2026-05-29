{{-- Shared article form partial --}}

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-content" role="tab">Content</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-seo" role="tab">SEO & Meta</a></li>
</ul>

<div class="tab-content">

    {{-- ══ Tab 1: Content ══ --}}
    <div class="tab-pane fade show active" id="tab-content" role="tabpanel">
        <div class="card">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="title_ka" class="form-label">Title (KA) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('title_ka') is-invalid @enderror"
                               id="title_ka" name="title_ka" value="{{ old('title_ka', $article->title_ka) }}" required>
                        @error('title_ka') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="title_en" class="form-label">Title (EN)</label>
                        <input type="text" class="form-control @error('title_en') is-invalid @enderror"
                               id="title_en" name="title_en" value="{{ old('title_en', $article->title_en) }}">
                        @error('title_en') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="slug" class="form-label">Slug</label>
                        <input type="text" class="form-control @error('slug') is-invalid @enderror"
                               id="slug" name="slug" value="{{ old('slug', $article->slug) }}" placeholder="Auto-generated">
                        @error('slug') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="schema_type" class="form-label">Schema Type <span class="text-danger">*</span></label>
                        <select class="form-select @error('schema_type') is-invalid @enderror" id="schema_type" name="schema_type" required>
                            @foreach(['Article', 'HowTo', 'ItemList'] as $type)
                            <option value="{{ $type }}" {{ old('schema_type', $article->schema_type ?? 'Article') === $type ? 'selected' : '' }}>{{ $type }}</option>
                            @endforeach
                        </select>
                        @error('schema_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch mt-4">
                            <input class="form-check-input" type="checkbox" id="is_published" name="is_published" value="1"
                                   {{ old('is_published', $article->is_published) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_published">Published</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label for="cover_image" class="form-label">Cover Image URL</label>
                        @if($article->cover_image)
                            <div class="mb-2">
                                <img src="{{ str_starts_with($article->cover_image, 'http') ? $article->cover_image : asset('storage/' . $article->cover_image) }}" class="rounded" style="max-height:120px;">
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="checkbox" id="remove_cover" name="remove_cover" value="1">
                                    <label class="form-check-label small text-danger" for="remove_cover">Remove cover</label>
                                </div>
                            </div>
                        @endif
                        <div class="input-group">
                            <input type="text" class="form-control @error('cover_image') is-invalid @enderror"
                                   id="cover_image" name="cover_image" value="{{ old('cover_image', $article->cover_image) }}">
                            <button type="button" class="btn btn-outline-primary" id="btn-image-manager-article" title="Open Image Manager">
                                <i data-feather="image" style="width:14px;height:14px;"></i> Manager
                            </button>
                        </div>
                        <div class="form-text">Paste an image URL or use the Manager to upload/crop a new one.</div>
                        @error('cover_image') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="excerpt_ka" class="form-label">Excerpt (KA)</label>
                        <textarea class="form-control @error('excerpt_ka') is-invalid @enderror"
                                  id="excerpt_ka" name="excerpt_ka" rows="2">{{ old('excerpt_ka', $article->excerpt_ka) }}</textarea>
                        @error('excerpt_ka') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="excerpt_en" class="form-label">Excerpt (EN)</label>
                        <textarea class="form-control @error('excerpt_en') is-invalid @enderror"
                                  id="excerpt_en" name="excerpt_en" rows="2">{{ old('excerpt_en', $article->excerpt_en) }}</textarea>
                        @error('excerpt_en') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="body_ka" class="form-label">Body (KA) <span class="text-danger">*</span></label>
                        <textarea class="form-control @error('body_ka') is-invalid @enderror"
                                  id="body_ka" name="body_ka" rows="12">{{ old('body_ka', $article->body_ka) }}</textarea>
                        @error('body_ka') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="body_en" class="form-label">Body (EN)</label>
                        <textarea class="form-control @error('body_en') is-invalid @enderror"
                                  id="body_en" name="body_en" rows="12">{{ old('body_en', $article->body_en) }}</textarea>
                        @error('body_en') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Tab 2: SEO ══ --}}
    <div class="tab-pane fade" id="tab-seo" role="tabpanel">
        <div class="card">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="meta_title_ka" class="form-label">Meta Title (KA)</label>
                        <input type="text" class="form-control" id="meta_title_ka" name="meta_title_ka" value="{{ old('meta_title_ka', $article->meta_title_ka) }}" maxlength="160">
                    </div>
                    <div class="col-md-6">
                        <label for="meta_title_en" class="form-label">Meta Title (EN)</label>
                        <input type="text" class="form-control" id="meta_title_en" name="meta_title_en" value="{{ old('meta_title_en', $article->meta_title_en) }}" maxlength="160">
                    </div>
                    <div class="col-md-6">
                        <label for="meta_description_ka" class="form-label">Meta Description (KA)</label>
                        <textarea class="form-control" id="meta_description_ka" name="meta_description_ka" rows="2" maxlength="160">{{ old('meta_description_ka', $article->meta_description_ka) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label for="meta_description_en" class="form-label">Meta Description (EN)</label>
                        <textarea class="form-control" id="meta_description_en" name="meta_description_en" rows="2" maxlength="160">{{ old('meta_description_en', $article->meta_description_en) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label for="published_at" class="form-label">Publish Date</label>
                        <input type="datetime-local" class="form-control" id="published_at" name="published_at"
                               value="{{ old('published_at', $article->published_at?->format('Y-m-d\TH:i')) }}">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('admin.partials._image_manager_modal')