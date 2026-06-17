@extends('admin.layout')

@section('title', 'Edit: ' . ($article->title_en ?: $article->title_ka) . ' — Admin')

@section('content')
@fragment('content')
<div data-page-title="სტატიის რედაქტირება">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div><h4 class="mb-3 mb-md-0">რედაქტირება: {{ $article->title_en ?: $article->title_ka }}</h4></div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.articles.index') }}" class="btn btn-outline-secondary btn-sm" data-pjax>
                <i data-feather="arrow-left" style="width:14px;height:14px;"></i> უკან
            </a>
            <form method="POST" action="{{ route('admin.articles.destroy', $article) }}" class="d-inline" onsubmit="return confirm('წაიშალოს ეს სტატია?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i data-feather="trash-2" style="width:14px;height:14px;"></i> წაშლა
                </button>
            </form>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.articles.update', $article) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.articles._form', ['article' => $article])
        <div class="d-flex justify-content-end gap-2 mt-3">
            <button type="submit" class="btn btn-primary"><i data-feather="save" style="width:16px;height:16px;"></i> სტატიის განახლება</button>
        </div>
    </form>
</div>
@endfragment
@endsection
