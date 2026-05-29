@extends('admin.layout')

@section('title', 'Articles — Admin')

@section('content')
@fragment('content')
<div data-page-title="Articles">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div>
            <h4 class="mb-3 mb-md-0">Articles</h4>
        </div>
        <div class="d-flex gap-2">
            <div class="btn-group btn-group-sm" role="group">
                <a href="{{ route('admin.articles.index') }}" class="btn {{ !$status ? 'btn-primary' : 'btn-outline-primary' }}" data-pjax>All</a>
                <a href="{{ route('admin.articles.index', ['status' => 'published']) }}" class="btn {{ $status === 'published' ? 'btn-success' : 'btn-outline-success' }}" data-pjax>Published</a>
                <a href="{{ route('admin.articles.index', ['status' => 'draft']) }}" class="btn {{ $status === 'draft' ? 'btn-warning' : 'btn-outline-warning' }}" data-pjax>Drafts</a>
            </div>
            <a href="{{ route('admin.articles.create') }}" class="btn btn-primary btn-sm d-flex align-items-center gap-1" data-pjax>
                <i data-feather="plus" style="width:16px;height:16px;"></i> New Article
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.articles.index') }}" class="mb-3">
                <div class="input-group input-group-sm" style="max-width:300px;">
                    <input type="text" class="form-control" name="q" value="{{ $q }}" placeholder="Search articles...">
                    <button class="btn btn-outline-primary" type="submit"><i data-feather="search" style="width:14px;height:14px;"></i></button>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Title (KA)</th>
                            <th>Title (EN)</th>
                            <th>Slug</th>
                            <th>Schema</th>
                            <th>Status</th>
                            <th>Published</th>
                            <th style="width:120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($articles as $article)
                        <tr>
                            <td>
                                <a href="{{ route('admin.articles.edit', $article) }}" class="fw-bold text-decoration-none" data-pjax>{{ Str::limit($article->title_ka, 40) }}</a>
                            </td>
                            <td class="text-muted">{{ Str::limit($article->title_en, 40) ?: '—' }}</td>
                            <td class="text-muted small">{{ $article->slug }}</td>
                            <td><span class="badge bg-secondary">{{ $article->schema_type }}</span></td>
                            <td>
                                @if($article->is_published)
                                    <span class="badge bg-success">Published</span>
                                @else
                                    <span class="badge bg-warning text-dark">Draft</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $article->published_at ? $article->published_at->format('M d, Y') : '—' }}</td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="{{ route('admin.articles.edit', $article) }}" class="btn btn-outline-primary btn-sm p-1" data-pjax title="Edit">
                                        <i data-feather="edit-2" style="width:14px;height:14px;"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.articles.toggle-publish', $article) }}" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-outline-{{ $article->is_published ? 'warning' : 'success' }} btn-sm p-1" title="{{ $article->is_published ? 'Unpublish' : 'Publish' }}">
                                            <i data-feather="{{ $article->is_published ? 'eye-off' : 'eye' }}" style="width:14px;height:14px;"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.articles.destroy', $article) }}" class="d-inline" onsubmit="return confirm('Delete this article?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm p-1" title="Delete">
                                            <i data-feather="trash-2" style="width:14px;height:14px;"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No articles found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($articles->hasPages())
            <div class="mt-3">{{ $articles->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endfragment
@endsection
