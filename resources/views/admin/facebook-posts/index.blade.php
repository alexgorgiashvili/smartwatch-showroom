@extends('admin.layout')

@section('title', 'Facebook Posts')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-0">Social Media Posts</h3>
            <p class="text-muted mb-0">AI-ით პოსტების გენერაცია და Facebook / Instagram გვერდზე გამოქვეყნება</p>
        </div>
        <a href="{{ route('admin.facebook-posts.create') }}" class="btn btn-primary">
            <i data-feather="plus"></i> ახალი პოსტი
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.facebook-posts.index') }}" class="row g-3">
                <div class="col-md-9">
                    <label for="status" class="form-label">სტატუსი</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">ყველა</option>
                        <option value="draft" @selected(request('status') === 'draft')>დრაფტი</option>
                        <option value="published" @selected(request('status') === 'published')>გამოქვეყნებული</option>
                        <option value="failed" @selected(request('status') === 'failed')>წარუმატებელი</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-outline-primary w-100">ფილტრი</button>
                    <a href="{{ route('admin.facebook-posts.index') }}" class="btn btn-outline-secondary">Reset</a>
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
                            <th>პოსტი</th>
                            <th>პლატფორმა</th>
                            <th>პროდუქტი</th>
                            <th>სტატუსი</th>
                            <th>ავტორი</th>
                            <th>თარიღი</th>
                            <th class="text-end">მოქმედება</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($posts as $post)
                            <tr>
                                <td style="max-width: 300px;">
                                    <div class="text-truncate" title="{{ $post->message }}">
                                        {{ Str::limit($post->message, 80) }}
                                    </div>
                                    @if($post->image_url)
                                        <span class="badge bg-info text-dark">
                                            <i data-feather="image" style="width:12px;height:12px"></i> სურათით
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if($post->post_to_facebook)
                                        <span class="badge bg-primary">FB</span>
                                    @endif
                                    @if($post->post_to_instagram)
                                        <span class="badge" style="background: linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888);">IG</span>
                                    @endif
                                </td>
                                <td>
                                    @if($post->product)
                                        {{ $post->product->name_ka ?? $post->product->name_en }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @switch($post->status)
                                        @case('draft')
                                            <span class="badge bg-secondary">დრაფტი</span>
                                            @break
                                        @case('published')
                                            <span class="badge bg-success">გამოქვეყნებული</span>
                                            @break
                                        @case('failed')
                                            <span class="badge bg-danger" title="{{ $post->error_message }}">წარუმატებელი</span>
                                            @break
                                    @endswitch
                                </td>
                                <td>{{ $post->user->name ?? '-' }}</td>
                                <td>
                                    @if($post->published_at)
                                        {{ $post->published_at->format('d/m/Y H:i') }}
                                    @else
                                        {{ $post->created_at->format('d/m/Y H:i') }}
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($post->status === 'published' && $post->facebook_post_id)
                                        <a href="https://facebook.com/{{ $post->facebook_post_id }}"
                                           class="btn btn-outline-info btn-sm" target="_blank" rel="noopener">
                                            FB
                                        </a>
                                    @endif
                                    @if($post->status === 'published' && $post->instagram_post_id)
                                        <a href="https://www.instagram.com/p/{{ $post->instagram_post_id }}/"
                                           class="btn btn-outline-info btn-sm" target="_blank" rel="noopener">
                                            IG
                                        </a>
                                    @endif
                                    @if($post->status !== 'published')
                                        <a href="{{ route('admin.facebook-posts.edit', $post) }}"
                                           class="btn btn-outline-primary btn-sm">რედაქტირება</a>
                                        <form method="POST" action="{{ route('admin.facebook-posts.publish', $post) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-success btn-sm"
                                                    onclick="return confirm('გამოქვეყნება Facebook-ზე?')">
                                                გამოქვეყნება
                                            </button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.facebook-posts.destroy', $post) }}"
                                          class="d-inline" onsubmit="return confirm('წაიშალოს პოსტი?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm">წაშლა</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    პოსტები არ მოიძებნა.
                                    <a href="{{ route('admin.facebook-posts.create') }}">შექმენით პირველი პოსტი</a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($posts->hasPages())
                <div class="mt-3">
                    {{ $posts->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
