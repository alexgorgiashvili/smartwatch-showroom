@extends('admin.layout')

@section('title', 'Facebook Posts — Admin')

@section('content')
@fragment('content')
<div data-page-title="Facebook Posts">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div><h4 class="mb-3 mb-md-0">Facebook Posts</h4></div>
        <div class="d-flex gap-2">
            <div class="btn-group btn-group-sm" role="group">
                <a href="{{ route('admin.facebook-posts.index') }}" class="btn {{ !request('status') ? 'btn-primary' : 'btn-outline-primary' }}" data-pjax>All</a>
                <a href="{{ route('admin.facebook-posts.index', ['status' => 'draft']) }}" class="btn {{ request('status') === 'draft' ? 'btn-warning' : 'btn-outline-warning' }}" data-pjax>Drafts</a>
                <a href="{{ route('admin.facebook-posts.index', ['status' => 'published']) }}" class="btn {{ request('status') === 'published' ? 'btn-success' : 'btn-outline-success' }}" data-pjax>Published</a>
                <a href="{{ route('admin.facebook-posts.index', ['status' => 'failed']) }}" class="btn {{ request('status') === 'failed' ? 'btn-danger' : 'btn-outline-danger' }}" data-pjax>Failed</a>
            </div>
            <a href="{{ route('admin.facebook-posts.create') }}" class="btn btn-primary btn-sm d-flex align-items-center gap-1" data-pjax>
                <i data-feather="plus" style="width:16px;height:16px;"></i> New Post
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Message</th>
                            <th>Product</th>
                            <th>Platforms</th>
                            <th>Status</th>
                            <th>Author</th>
                            <th>Date</th>
                            <th style="width:100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($posts as $post)
                        <tr>
                            <td>
                                <a href="{{ route('admin.facebook-posts.edit', $post) }}" class="text-decoration-none" data-pjax>{{ Str::limit($post->message, 60) }}</a>
                            </td>
                            <td class="text-muted small">{{ $post->product?->name_en ?? '—' }}</td>
                            <td>
                                @if($post->post_to_facebook)<span class="badge bg-primary me-1">FB</span>@endif
                                @if($post->post_to_instagram)<span class="badge bg-danger">IG</span>@endif
                            </td>
                            <td>
                                @php $sColors = ['draft' => 'warning', 'published' => 'success', 'failed' => 'danger']; @endphp
                                <span class="badge bg-{{ $sColors[$post->status] ?? 'secondary' }}">{{ ucfirst($post->status) }}</span>
                            </td>
                            <td class="text-muted small">{{ $post->user?->name ?? '—' }}</td>
                            <td class="text-muted small">{{ $post->created_at->format('M d, Y') }}</td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="{{ route('admin.facebook-posts.edit', $post) }}" class="btn btn-outline-primary btn-sm p-1" data-pjax title="Edit">
                                        <i data-feather="edit-2" style="width:14px;height:14px;"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.facebook-posts.destroy', $post) }}" class="d-inline" onsubmit="return confirm('Delete this post?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm p-1" title="Delete">
                                            <i data-feather="trash-2" style="width:14px;height:14px;"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No posts found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($posts->hasPages())
            <div class="mt-3">{{ $posts->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endfragment
@endsection
