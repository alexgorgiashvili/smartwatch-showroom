@extends('admin.layout')

@section('title', 'Edit Order — Admin')

@section('content')
@fragment('content')
<div data-page-title="შეკვეთის რედაქტირება">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div>
            <h4 class="mb-1">შეკვეთის რედაქტირება</h4>
            <div class="text-muted small">{{ $order->order_number }}</div>
        </div>
        <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-outline-secondary btn-sm" data-pjax>
            <i data-feather="arrow-left" style="width:14px;height:14px;"></i> უკან
        </a>
    </div>

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="alert alert-info small">
        მოდელის ან რაოდენობის შეცვლისას ძველი local-stock ვარიანტი ბრუნდება მარაგში, ახალი კი ავტომატურად რეზერვდება.
    </div>

    <form method="POST" action="{{ route('admin.orders.update', $order) }}">
        @csrf
        @method('PATCH')

        <div class="row">
            <div class="col-xl-6 mb-3">
                <div class="card h-100"><div class="card-body">
                    <h6 class="card-title mb-3">მომხმარებელი</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="customer_name">სახელი *</label>
                            <input id="customer_name" name="customer_name" class="form-control @error('customer_name') is-invalid @enderror" value="{{ old('customer_name', $order->customer_name) }}" required>
                            @error('customer_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="customer_phone">ტელეფონი *</label>
                            <input id="customer_phone" name="customer_phone" class="form-control @error('customer_phone') is-invalid @enderror" value="{{ old('customer_phone', $order->customer_phone) }}" required>
                            @error('customer_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="personal_number">პირადი ნომერი</label>
                            <input id="personal_number" name="personal_number" class="form-control @error('personal_number') is-invalid @enderror" value="{{ old('personal_number', $order->personal_number) }}">
                            @error('personal_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="order_source">წყარო</label>
                            <select id="order_source" name="order_source" class="form-select">
                                @foreach(['Facebook', 'Instagram', 'Direct', 'Other'] as $source)
                                    <option value="{{ $source }}" {{ old('order_source', $order->order_source) === $source ? 'selected' : '' }}>{{ $source }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div></div>
            </div>
            <div class="col-xl-6 mb-3">
                <div class="card h-100"><div class="card-body">
                    <h6 class="card-title mb-3">მიწოდება</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="city_id">ქალაქი</label>
                            <select id="city_id" name="city_id" class="form-select">
                                <option value="">დასაზუსტებელია</option>
                                @foreach($cities as $city)
                                    <option value="{{ $city->id }}" {{ old('city_id', $order->city_id) == $city->id ? 'selected' : '' }}>{{ $city->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">გადახდა</label>
                            <input class="form-control" value="{{ $order->payment_type == 1 ? 'ონლაინ' : 'კურიერთან' }}" disabled>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="exact_address">ზუსტი მისამართი</label>
                            <textarea id="exact_address" name="exact_address" rows="2" class="form-control">{{ old('exact_address', $order->exact_address) }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="notes">შენიშვნა</label>
                            <textarea id="notes" name="notes" rows="2" class="form-control">{{ old('notes', $order->notes) }}</textarea>
                        </div>
                    </div>
                </div></div>
            </div>
        </div>

        <div class="card mb-3"><div class="card-body">
            <h6 class="card-title mb-3">ნივთები</h6>
            @foreach($order->items as $index => $item)
                <div class="row g-2 align-items-end {{ $loop->last ? '' : 'mb-3 pb-3 border-bottom' }}">
                    <div class="col-md-8">
                        <label class="form-label small">მოდელი და ვარიანტი *</label>
                        <select name="items[{{ $index }}][variant_id]" class="form-select @error("items.$index.variant_id") is-invalid @enderror" required>
                            @foreach($products as $product)
                                @php
                                    $modelName = trim((string) $product->model);
                                    $productName = $product->name_ka ?: $product->name_en;
                                    $productLabel = $modelName !== '' ? $modelName . ' — ' . $productName : $productName;
                                @endphp
                                <optgroup label="{{ $productLabel }}">
                                    @foreach($product->variants as $variant)
                                        <option value="{{ $variant->id }}" {{ (int) old("items.$index.variant_id", $item->product_variant_id) === $variant->id ? 'selected' : '' }}>
                                            {{ $modelName !== '' ? $modelName . ' — ' : '' }}{{ $variant->name }} (მარაგი: {{ $variant->available_quantity }})
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">რაოდენობა *</label>
                        <input type="number" name="items[{{ $index }}][quantity]" min="1" class="form-control @error("items.$index.quantity") is-invalid @enderror" value="{{ old("items.$index.quantity", $item->quantity) }}" required>
                    </div>
                    <div class="col-md-2 text-muted small">
                        მიმდინარე: GEL {{ number_format($item->subtotal, 2) }}
                    </div>
                </div>
            @endforeach
        </div></div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-light" data-pjax>გაუქმება</a>
            <button class="btn btn-primary" type="submit"><i data-feather="save" style="width:16px;height:16px;"></i> ცვლილებების შენახვა</button>
        </div>
    </form>
</div>
@endfragment
@endsection
