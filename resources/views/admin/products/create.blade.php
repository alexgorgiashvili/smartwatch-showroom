@extends('admin.layout')

@section('title', 'Add Product — Admin')

@section('content')
@fragment('content')
<div data-page-title="Add Product">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div>
            <h4 class="mb-3 mb-md-0">Add Product</h4>
        </div>
        <div>
            <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary btn-sm" data-pjax>
                <i data-feather="arrow-left" style="width:14px;height:14px;"></i> Back
            </a>
        </div>
    </div>

    <form id="productForm" method="POST" action="{{ route('admin.products.store') }}" enctype="multipart/form-data">
        @csrf

        @include('admin.products._form', ['product' => $product])

        <div class="d-flex justify-content-end gap-2 mt-3">
            <button type="submit" class="btn btn-primary">
                <i data-feather="save" style="width:16px;height:16px;"></i> Create Product
            </button>
        </div>
    </form>
</div>
@endfragment
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    window.AdminProducts && window.AdminProducts.initForm();
});
</script>
@endpush
