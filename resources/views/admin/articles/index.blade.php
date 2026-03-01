@extends('admin.layout')

@section('title', 'Blog Articles')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-0">Blog Articles</h3>
            <p class="text-muted mb-0">Create and manage public blog content.</p>
        </div>
        <a href="{{ route('admin.articles.create') }}" class="btn btn-primary">New Article</a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.articles.index') }}" class="row g-3">
                <div class="col-md-7">
                    <label for="q" class="form-label">Search</label>
                    <input
                        type="text"
                        name="q"
                        id="q"
                        class="form-control"
                        value="{{ $q }}"
                        placeholder="Title or slug..."
                    >
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">All</option>
                        <option value="published" @selected($status === 'published')>Published</option>
                        <option value="draft" @selected($status === 'draft')>Draft</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
                    <a href="{{ route('admin.articles.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Slug</th>
                            <th>Schema</th>
                            <th>Status</th>
                            <th>Published</th>
                            <th>Updated</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($articles as $article)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $article->title_ka }}</div>
                                    @if($article->title_en)
                                        <div class="text-muted small">{{ $article->title_en }}</div>
                                    @endif
                                </td>
                                <td><code>{{ $article->slug }}</code></td>
                                <td>{{ $article->schema_type }}</td>
                                <td>
                                    @if($article->is_published)
                                        <span class="badge bg-success">Published</span>
                                    @else
                                        <span class="badge bg-secondary">Draft</span>
                                    @endif
                                </td>
                                <td>{{ $article->published_at?->format('Y-m-d H:i') ?: '-' }}</td>
                                <td>{{ $article->updated_at?->format('Y-m-d H:i') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('blog.show', $article) }}" class="btn btn-outline-info btn-sm" target="_blank" rel="noopener">View</a>
                                    <a href="{{ route('admin.articles.edit', $article) }}" class="btn btn-outline-primary btn-sm">Edit</a>
                                    <form method="POST" action="{{ route('admin.articles.toggle-publish', $article) }}" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-outline-warning btn-sm">
                                            {{ $article->is_published ? 'Unpublish' : 'Publish' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.articles.destroy', $article) }}" class="d-inline" onsubmit="return confirm('Delete this article?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">No articles found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($articles->hasPages())
                <div class="mt-3">
                    {{ $articles->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
