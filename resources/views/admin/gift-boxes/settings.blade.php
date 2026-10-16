@extends('admin.layout')

@section('title', 'Gift Builder პარამეტრები — Admin')

@section('content')
@fragment('content')
@php
    $packagingRows = old('packaging');
    if (!is_array($packagingRows)) {
        $packagingRows = collect($settings['packaging_options'] ?? [])->map(function ($item, $slug) {
            return array_merge(['slug' => $slug], (array) $item);
        })->values()->all();
    }
@endphp
<div data-page-title="Gift Builder — პარამეტრები">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div><h4 class="mb-3 mb-md-0">Gift Builder — მონაცემები და ხარჯები</h4></div>
        <a href="{{ route('admin.gift-boxes.index') }}" class="btn btn-outline-secondary btn-sm" data-pjax>ყუთების სია</a>
    </div>

    @if(session('status')) <div class="alert alert-success">{{ session('status') }}</div> @endif
    @if(!$settingsTableReady)
        <div class="alert alert-warning">პარამეტრების ცხრილის მიგრაცია ჯერ არ გაშვებულა. გვერდი აჩვენებს საწყის მნიშვნელობებს; შენახვა ხელმისაწვდომი გახდება მიგრაციის შემდეგ.</div>
    @endif
    @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

    <form method="POST" action="{{ route('admin.gift-boxes.settings.update') }}" data-pjax-form>
        @csrf
        @method('PUT')
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div><h6 class="mb-0">შეფუთვები</h6><small class="text-muted">გასაყიდი ფასი და რეალური ხარჯი ცალკეა. ხარჯის ცარიელი მნიშვნელობა უცნობია; 0 ხელით მითითებული ნულია.</small></div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="add-packaging-row">შეფუთვის დამატება</button>
            </div>
            <div class="card-body" id="packaging-rows">
                @foreach($packagingRows as $index => $packaging)
                    <div class="border rounded p-3 mb-3 packaging-row">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-2"><label class="form-label">Slug</label><input class="form-control" name="packaging[{{ $index }}][slug]" value="{{ $packaging['slug'] ?? '' }}" required maxlength="40"></div>
                            <div class="col-md-2"><label class="form-label">სახელი (KA)</label><input class="form-control" name="packaging[{{ $index }}][name_ka]" value="{{ $packaging['name_ka'] ?? '' }}" required maxlength="80"></div>
                            <div class="col-md-2"><label class="form-label">Name (EN)</label><input class="form-control" name="packaging[{{ $index }}][name_en]" value="{{ $packaging['name_en'] ?? '' }}" required maxlength="80"></div>
                            <div class="col-md-1"><label class="form-label">ფასი ₾</label><input type="number" step="0.01" min="0" class="form-control" name="packaging[{{ $index }}][price]" value="{{ $packaging['price'] ?? '' }}" required></div>
                            <div class="col-md-2"><label class="form-label">რეალური ხარჯი ₾</label><input type="number" step="0.0001" min="0" class="form-control" name="packaging[{{ $index }}][cost]" value="{{ $packaging['cost'] ?? '' }}"></div>
                            <div class="col-md-1"><div class="form-check"><input type="hidden" name="packaging[{{ $index }}][active]" value="0"><input type="checkbox" class="form-check-input" name="packaging[{{ $index }}][active]" value="1" @checked($packaging['active'] ?? false)><label class="form-check-label">აქტიური</label></div></div>
                            <div class="col-md-1"><button type="button" class="btn btn-outline-danger btn-sm remove-packaging-row">წაშლა</button></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card mb-3"><div class="card-header"><h6 class="mb-0">მიწოდება და შიდა შეფასების დაშვებები</h6></div><div class="card-body"><div class="row g-3">
            <div class="col-md-3"><label class="form-label">მიწოდების ფასი (₾)</label><input type="number" step="0.01" min="0" class="form-control" name="delivery_fee" value="{{ old('delivery_fee', $settings['delivery_fee']) }}" required></div>
            <div class="col-md-3"><label class="form-label">უფასო მიწოდების ზღვარი (₾)</label><input type="number" step="0.01" min="0" class="form-control" name="free_delivery_threshold" value="{{ old('free_delivery_threshold', $settings['free_delivery_threshold']) }}" required></div>
            <div class="col-md-3"><label class="form-label">რეკლამის ხარჯი შეკვეთაზე (₾)</label><input type="number" step="0.01" min="0" class="form-control" name="ad_cost_per_order" value="{{ old('ad_cost_per_order', $settings['ad_cost_per_order']) }}"><div class="form-text">ცარიელი = უცნობი, 0 = ნული.</div></div>
            <div class="col-md-3"><label class="form-label">კურიერის რეალური ხარჯი შეკვეთაზე (₾)</label><input type="number" step="0.01" min="0" class="form-control" name="courier_cost_per_order" value="{{ old('courier_cost_per_order', $settings['courier_cost_per_order']) }}"><div class="form-text">ცარიელი = უცნობი, 0 = ნული.</div></div>
        </div></div></div>

        <button class="btn btn-primary" @disabled(!$settingsTableReady)>შენახვა</button>
    </form>
</div>
<script>
(() => {
    const container = document.getElementById('packaging-rows');
    const addButton = document.getElementById('add-packaging-row');
    if (!container || !addButton) return;
    let nextIndex = container.querySelectorAll('.packaging-row').length;
    addButton.addEventListener('click', () => {
        const index = nextIndex++;
        const row = document.createElement('div');
        row.className = 'border rounded p-3 mb-3 packaging-row';
        row.innerHTML = `<div class="row g-3 align-items-end">
            <div class="col-md-2"><label class="form-label">Slug</label><input class="form-control" name="packaging[${index}][slug]" required maxlength="40"></div>
            <div class="col-md-2"><label class="form-label">სახელი (KA)</label><input class="form-control" name="packaging[${index}][name_ka]" required maxlength="80"></div>
            <div class="col-md-2"><label class="form-label">Name (EN)</label><input class="form-control" name="packaging[${index}][name_en]" required maxlength="80"></div>
            <div class="col-md-1"><label class="form-label">ფასი ₾</label><input type="number" step="0.01" min="0" class="form-control" name="packaging[${index}][price]" required></div>
            <div class="col-md-2"><label class="form-label">რეალური ხარჯი ₾</label><input type="number" step="0.0001" min="0" class="form-control" name="packaging[${index}][cost]"></div>
            <div class="col-md-1"><div class="form-check"><input type="hidden" name="packaging[${index}][active]" value="0"><input type="checkbox" class="form-check-input" name="packaging[${index}][active]" value="1" checked><label class="form-check-label">აქტიური</label></div></div>
            <div class="col-md-1"><button type="button" class="btn btn-outline-danger btn-sm remove-packaging-row">წაშლა</button></div>
        </div>`;
        container.appendChild(row);
    });
    container.addEventListener('click', event => {
        if (event.target.closest('.remove-packaging-row')) {
            const rows = container.querySelectorAll('.packaging-row');
            if (rows.length > 1) event.target.closest('.packaging-row').remove();
        }
    });
})();
</script>
@endfragment
@endsection
