@extends('admin.layout')

@section('title', 'New Post — Admin')

@section('content')
@fragment('content')
<div data-page-title="ახალი პოსტი">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div><h4 class="mb-3 mb-md-0">ახალი პოსტი</h4></div>
        <div>
            <a href="{{ route('admin.facebook-posts.index') }}" class="btn btn-outline-secondary btn-sm" data-pjax>
                <i data-feather="arrow-left" style="width:14px;height:14px;"></i> უკან
            </a>
        </div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <form method="POST" action="{{ route('admin.facebook-posts.store') }}">
        @csrf
        @include('admin.facebook-posts._form', ['post' => null])
    </form>
</div>
@endfragment
@endsection
