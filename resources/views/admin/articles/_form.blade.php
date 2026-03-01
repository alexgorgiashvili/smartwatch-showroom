@php
    $publishedAtValue = old(
        'published_at',
        $article->published_at ? $article->published_at->format('Y-m-d\TH:i') : null
    );
@endphp

<div class="row g-3">
    <div class="col-md-8">
        <label for="slug" class="form-label">Slug</label>
        <input type="text" class="form-control" id="slug" name="slug" value="{{ old('slug', $article->slug) }}" placeholder="auto-generated-if-empty">
    </div>
    <div class="col-md-4">
        <label for="schema_type" class="form-label">Schema Type</label>
        <select id="schema_type" name="schema_type" class="form-select" required>
            <option value="Article" @selected(old('schema_type', $article->schema_type ?: 'Article') === 'Article')>Article</option>
            <option value="HowTo" @selected(old('schema_type', $article->schema_type) === 'HowTo')>HowTo</option>
            <option value="ItemList" @selected(old('schema_type', $article->schema_type) === 'ItemList')>ItemList</option>
        </select>
    </div>

    <div class="col-md-6">
        <label for="title_ka" class="form-label">Title (KA)</label>
        <input type="text" class="form-control" id="title_ka" name="title_ka" value="{{ old('title_ka', $article->title_ka) }}" required>
    </div>
    <div class="col-md-6">
        <label for="title_en" class="form-label">Title (EN)</label>
        <input type="text" class="form-control" id="title_en" name="title_en" value="{{ old('title_en', $article->title_en) }}">
    </div>

    <div class="col-md-6">
        <label for="excerpt_ka" class="form-label">Excerpt (KA)</label>
        <textarea id="excerpt_ka" name="excerpt_ka" rows="3" class="form-control">{{ old('excerpt_ka', $article->excerpt_ka) }}</textarea>
    </div>
    <div class="col-md-6">
        <label for="excerpt_en" class="form-label">Excerpt (EN)</label>
        <textarea id="excerpt_en" name="excerpt_en" rows="3" class="form-control">{{ old('excerpt_en', $article->excerpt_en) }}</textarea>
    </div>

    <div class="col-md-6">
        <label for="body_ka" class="form-label">Body HTML (KA)</label>
        <textarea id="body_ka" name="body_ka" rows="14" class="form-control" required>{{ old('body_ka', $article->body_ka) }}</textarea>
    </div>
    <div class="col-md-6">
        <label for="body_en" class="form-label">Body HTML (EN)</label>
        <textarea id="body_en" name="body_en" rows="14" class="form-control">{{ old('body_en', $article->body_en) }}</textarea>
    </div>

    <div class="col-md-6">
        <label for="meta_title_ka" class="form-label">Meta Title (KA)</label>
        <input type="text" class="form-control" id="meta_title_ka" name="meta_title_ka" maxlength="160" value="{{ old('meta_title_ka', $article->meta_title_ka) }}">
    </div>
    <div class="col-md-6">
        <label for="meta_title_en" class="form-label">Meta Title (EN)</label>
        <input type="text" class="form-control" id="meta_title_en" name="meta_title_en" maxlength="160" value="{{ old('meta_title_en', $article->meta_title_en) }}">
    </div>

    <div class="col-md-6">
        <label for="meta_description_ka" class="form-label">Meta Description (KA)</label>
        <textarea id="meta_description_ka" name="meta_description_ka" rows="3" maxlength="160" class="form-control">{{ old('meta_description_ka', $article->meta_description_ka) }}</textarea>
    </div>
    <div class="col-md-6">
        <label for="meta_description_en" class="form-label">Meta Description (EN)</label>
        <textarea id="meta_description_en" name="meta_description_en" rows="3" maxlength="160" class="form-control">{{ old('meta_description_en', $article->meta_description_en) }}</textarea>
    </div>

    <div class="col-md-6">
        <label for="cover_image" class="form-label">Cover Image</label>
        <input type="file" class="form-control" id="cover_image" name="cover_image" accept="image/*">
        <div class="form-text">JPG, PNG, WEBP. Max 4MB.</div>

        @if ($article->cover_image)
            <div class="mt-2 d-flex align-items-center gap-3">
                <img src="{{ asset('storage/' . $article->cover_image) }}" alt="Cover" style="width: 140px; height: 84px; object-fit: cover;" class="rounded border">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="remove_cover" name="remove_cover">
                    <label class="form-check-label" for="remove_cover">
                        Remove current cover
                    </label>
                </div>
            </div>
        @endif
    </div>

    <div class="col-md-3">
        <label for="published_at" class="form-label">Publish Time</label>
        <input type="datetime-local" class="form-control" id="published_at" name="published_at" value="{{ $publishedAtValue }}">
    </div>
    <div class="col-md-3 d-flex align-items-end">
        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" role="switch" id="is_published" name="is_published" value="1" @checked(old('is_published', $article->is_published ?? true))>
            <label class="form-check-label" for="is_published">Published</label>
        </div>
    </div>
</div>
