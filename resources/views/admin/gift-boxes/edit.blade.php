@extends('admin.layout')

@section('title', 'სასაჩუქრე ყუთის რედაქტირება — Admin')

@section('content')
@fragment('content')
<div data-page-title="სასაჩუქრე ყუთის რედაქტირება">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <h4 class="mb-3 mb-md-0">{{ $box->title_ka }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.gift-boxes.index') }}" class="btn btn-outline-secondary btn-sm" data-pjax>უკან</a>
            <a href="{{ route('admin.gift-boxes.preview-box', $box) }}" class="btn btn-outline-primary btn-sm"><i data-feather="eye" style="width:14px;height:14px;"></i> Preview</a>
            <form method="POST" action="{{ route('admin.gift-boxes.toggle-status', $box) }}">
                @csrf @method('PATCH')
                <button class="btn btn-outline-{{ $box->is_active ? 'warning' : 'success' }} btn-sm">{{ $box->is_active ? 'Draft-ში გადატანა' : 'გააქტიურება' }}</button>
            </form>
            <form method="POST" action="{{ route('admin.gift-boxes.destroy', $box) }}" onsubmit="return confirm('წაიშალოს ეს ყუთი?')">
                @csrf @method('DELETE')
                <button class="btn btn-outline-danger btn-sm">წაშლა</button>
            </form>
        </div>
    </div>

    @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    @if($report && !$report['available'])
        <div class="alert alert-warning">
            <strong>ყუთი საჯაროდ ვერ გამოჩნდება:</strong>
            <ul class="mb-0 mt-2">@foreach($report['reasons'] as $reason)<li>{{ $reason['message'] }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.gift-boxes.update', $box) }}" enctype="multipart/form-data">
        @csrf @method('PUT')
        @include('admin.gift-boxes._form')
        <div class="d-flex justify-content-end mt-3"><button class="btn btn-primary">ცვლილებების შენახვა</button></div>
    </form>
</div>
@endfragment
@endsection
