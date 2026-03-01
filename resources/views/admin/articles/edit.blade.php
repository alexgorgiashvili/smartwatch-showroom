@extends('admin.layout')

@section('title', 'Edit Article')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-0">Edit Article</h3>
            <p class="text-muted mb-0">Update blog content and SEO fields.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('blog.show', $article) }}" class="btn btn-outline-info" target="_blank" rel="noopener">View Live</a>
            <a href="{{ route('admin.articles.index') }}" class="btn btn-outline-secondary">Back to Articles</a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.articles.update', $article) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('admin.articles._form', ['article' => $article])

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Update Article</button>
                    <a href="{{ route('admin.articles.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
