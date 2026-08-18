@extends('admin.layout')

@section('title', 'ახალი სასაჩუქრე ყუთი — Admin')

@section('content')
@fragment('content')
<div data-page-title="ახალი სასაჩუქრე ყუთი">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <h4 class="mb-3 mb-md-0">ახალი სასაჩუქრე ყუთი</h4>
        <a href="{{ route('admin.gift-boxes.index') }}" class="btn btn-outline-secondary btn-sm" data-pjax>უკან</a>
    </div>
    <form method="POST" action="{{ route('admin.gift-boxes.store') }}" enctype="multipart/form-data">
        @csrf
        @include('admin.gift-boxes._form')
        <div class="d-flex justify-content-end mt-3"><button class="btn btn-primary">ყუთის შექმნა</button></div>
    </form>
</div>
@endfragment
@endsection
