@extends('admin.layout')

@section('title', 'სასაჩუქრე ყუთები — Admin')

@section('content')
@fragment('content')
<div data-page-title="სასაჩუქრე ყუთები">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div><h4 class="mb-3 mb-md-0">სასაჩუქრე ყუთები</h4></div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.gift-boxes.preview') }}" class="btn btn-outline-primary btn-sm"><i data-feather="eye" style="width:16px;height:16px;"></i> Private preview</a>
            <a href="{{ route('admin.gift-boxes.create') }}" class="btn btn-primary btn-sm" data-pjax><i data-feather="plus" style="width:16px;height:16px;"></i> ახალი ყუთი</a>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.gift-boxes.index') }}" class="row g-2 mb-3">
                <div class="col-sm-5 col-lg-3">
                    <input type="search" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="სათაური ან slug">
                </div>
                <div class="col-sm-4 col-lg-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">ყველა სტატუსი</option>
                        <option value="active" @selected($status === 'active')>აქტიური</option>
                        <option value="draft" @selected($status === 'draft')>Draft</option>
                    </select>
                </div>
                <div class="col-auto"><button class="btn btn-outline-primary btn-sm">გაფილტვრა</button></div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>ყუთი</th><th>პროდუქტები</th><th>ფასი</th><th>ფასდაკლება</th><th>მდგომარეობა</th><th>რიგი</th><th></th></tr></thead>
                    <tbody>
                    @forelse($boxes as $box)
                        @php($report = $reports->get($box->id))
                        @php($total = $totals->get($box->id))
                        <tr>
                            <td style="min-width:220px;">
                                <div class="d-flex gap-2 align-items-center">
                                    @if($box->hero_image_url)
                                        <img src="{{ $box->hero_image_url }}" alt="" width="52" height="52" class="rounded object-fit-cover">
                                    @endif
                                    <div>
                                        <a href="{{ route('admin.gift-boxes.edit', $box) }}" class="fw-semibold text-decoration-none" data-pjax>{{ $box->title_ka }}</a>
                                        <div class="small text-muted">{{ $box->slug }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $box->items->count() }} / {{ config('gift_builder.max_items', 4) }}</td>
                            <td><span class="text-muted text-decoration-line-through">{{ number_format((float)$total['original'], 2) }} ₾</span><br><strong>{{ number_format((float)$total['total'], 2) }} ₾</strong></td>
                            <td>
                                @if($box->discount_type === 'percent')
                                    {{ (float) $box->discount_value }}%
                                @elseif($box->discount_type === 'fixed')
                                    {{ number_format((float) $box->discount_value, 2) }} ₾
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $box->is_active ? 'success' : 'secondary' }}">{{ $box->is_active ? 'აქტიური' : 'Draft' }}</span>
                                @if(!($report['available'] ?? false))
                                    <span class="badge bg-danger" title="{{ collect($report['reasons'] ?? [])->pluck('message')->implode(' ') }}">პრობლემაა</span>
                                @endif
                            </td>
                            <td>{{ $box->sort_order }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.gift-boxes.edit', $box) }}" class="btn btn-outline-primary btn-sm" data-pjax>რედაქტირება</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">ყუთები ჯერ არ დამატებულა.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($boxes->hasPages())<div class="mt-3">{{ $boxes->links() }}</div>@endif
        </div>
    </div>
</div>
@endfragment
@endsection
