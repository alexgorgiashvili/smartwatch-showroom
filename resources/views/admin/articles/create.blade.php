@extends('admin.layout')

@section('title', 'New Article — Admin')

@section('content')
@fragment('content')
<div data-page-title="ახალი სტატია">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div><h4 class="mb-3 mb-md-0">ახალი სტატია</h4></div>
        <div>
            <a href="{{ route('admin.articles.index') }}" class="btn btn-outline-secondary btn-sm" data-pjax>
                <i data-feather="arrow-left" style="width:14px;height:14px;"></i> უკან
            </a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.articles.store') }}" enctype="multipart/form-data">
        @csrf
        @include('admin.articles._form', ['article' => $article])
        <div class="d-flex justify-content-end gap-2 mt-3">
            <button type="submit" class="btn btn-primary"><i data-feather="save" style="width:16px;height:16px;"></i> სტატიის შექმნა</button>
        </div>
    </form>
</div>
@endfragment
@endsection
