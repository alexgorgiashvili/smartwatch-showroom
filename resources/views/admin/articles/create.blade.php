@extends('admin.layout')

@section('title', 'New Article')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-0">Create Article</h3>
            <p class="text-muted mb-0">Add a new blog post for the public site.</p>
        </div>
        <a href="{{ route('admin.articles.index') }}" class="btn btn-outline-secondary">Back to Articles</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.articles.store') }}" enctype="multipart/form-data">
                @csrf
                @include('admin.articles._form', ['article' => $article])

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save Article</button>
                    <a href="{{ route('admin.articles.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
