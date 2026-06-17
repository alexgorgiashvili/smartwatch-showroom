@extends('admin.layout')

@section('title', 'Edit Post — Admin')

@section('content')
@fragment('content')
<div data-page-title="პოსტის რედაქტირება">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div><h4 class="mb-3 mb-md-0">პოსტის რედაქტირება</h4></div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.facebook-posts.index') }}" class="btn btn-outline-secondary btn-sm" data-pjax>
                <i data-feather="arrow-left" style="width:14px;height:14px;"></i> უკან
            </a>
            <form method="POST" action="{{ route('admin.facebook-posts.destroy', $post) }}" class="d-inline" onsubmit="return confirm('წაიშალოს ეს პოსტი?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm"><i data-feather="trash-2" style="width:14px;height:14px;"></i> წაშლა</button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <form method="POST" action="{{ route('admin.facebook-posts.update', $post) }}">
        @csrf @method('PUT')
        @include('admin.facebook-posts._form', ['post' => $post])
    </form>
</div>
@endfragment
@endsection
