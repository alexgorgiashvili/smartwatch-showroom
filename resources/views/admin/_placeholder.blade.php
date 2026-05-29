@extends('admin.layout')

@section('title', $title . ' — Admin')

@section('content')
@fragment('content')
<div data-page-title="{{ $title }}">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div>
            <h4 class="mb-3 mb-md-0">{{ $title }}</h4>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i data-feather="tool" class="icon-xxl text-muted mb-3" style="width:48px;height:48px;"></i>
                    <h5 class="text-muted mt-3">{{ $title }} — Coming Soon</h5>
                    <p class="text-muted mb-0">This page will be implemented in a future phase.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endfragment
@endsection
