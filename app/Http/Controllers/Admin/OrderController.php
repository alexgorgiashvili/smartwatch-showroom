<?php

namespace App\Http\Controllers\Admin;

use App\Events\OrderCreated;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Services\Bridge\BridgeOrderSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::query()
            ->with('items.variant.product')
            ->when(
                $request->filled('payment_status'),
                fn ($query) => $query->where('payment_status', $request->string('payment_status')->value())
            )
            ->orderByDesc('created_at')
            ->paginate(20)
            ->appends($request->query());

        $view = view('admin.orders.index', [
            'orders' => $orders,
            'paymentStatus' => $request->string('payment_status')->value(),
        ]);

        return $this->renderPjaxView($request, $view);
    }

    public function create(Request $request)
    {
        $products = Product::with('variants')->where('is_active', true)->get();
        $cities = City::query()->orderBy('name')->get(['id', 'name']);

        $view = view('admin.orders.create', [
            'order' => new Order(),
            'products' => $products,
            'cities' => $cities,
            'oldItems' => old('items', [['variant_id' => null, 'quantity' => 1]]),
        ]);

        return $this->renderPjaxView($request, $view);
    }

    public function store(Request $request, BridgeOrderSyncService $bridgeOrderSync): RedirectResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:160'],
            'customer_phone' => ['required', 'string', 'max:50', 'regex:/^(995[0-9]{9}|5[0-9]{8})$/'],
            'personal_number' => ['nullable', 'regex:/^\d{11}$/'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'exact_address' => ['nullable', 'string'],
            'order_source' => ['nullable', 'in:Facebook,Instagram,Direct,Other'],
            'payment_type' => ['nullable', 'integer', 'in:1,2'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        DB::beginTransaction();

        try {
            // Generate order number
            $city = filled($data['city_id'] ?? null)
                ? City::query()->findOrFail((int) $data['city_id'])
                : null;

            // Format phone number
            $phone = $data['customer_phone'];
            if (strlen($phone) === 9 && str_starts_with($phone, '5')) {
                $data['customer_phone'] = '995' . $phone;
            }

            $data['order_number'] = Order::generateOrderNumber();
            $data['status'] = 'pending';
            $data['order_source'] = $data['order_source'] ?? 'Direct';
            $data['payment_type'] = (int) ($data['payment_type'] ?? 2);
            $data['payment_status'] = 'pending';
            $data['currency'] = 'GEL';
            $data['total_amount'] = 0;
            $data['city'] = $city?->name;
            $data['exact_address'] = filled($data['exact_address'] ?? null) ? $data['exact_address'] : null;
            $data['delivery_address'] = $data['exact_address'] ?? 'დასაზუსტებელია';
            $data['customer_email'] = null;
            $data['postal_code'] = null;

            // Create order
            $order = Order::create($data);

            $totalAmount = 0;

            // Create order items and adjust stock
            foreach ($request->items as $item) {
                $variant = ProductVariant::with('product')->findOrFail($item['variant_id']);

                // Check stock availability
                if (! $variant->canFulfillQuantity((int) $item['quantity'])) {
                    throw new \Exception("Insufficient stock for {$variant->name}. Available: {$variant->available_quantity}");
                }

                // Calculate price
                $unitPrice = $variant->product->sale_price ?? $variant->product->price;
                $subtotal = $unitPrice * $item['quantity'];
                $totalAmount += $subtotal;

                // Create order item
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_variant_id' => $variant->id,
                    'product_name' => $variant->product->name_en,
                    'variant_name' => $variant->name,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                    'bridge_product_id' => $variant->product->bridge_product_id,
                    'bridge_variation_id' => $variant->bridge_variation_id,
                    'fulfillment_mode' => $variant->product->fulfillment_mode,
                ]);

                if ($variant->product->fulfillment_mode === 'local_stock') {
                    $variant->decrement('quantity', $item['quantity']);

                    StockAdjustment::create([
                        'product_variant_id' => $variant->id,
                        'quantity_change' => -$item['quantity'],
                        'reason' => "Order {$order->order_number}",
                        'notes' => "Order created for {$order->customer_name}",
                    ]);
                }
            }

            $order->refresh()->load('items');

            $order->update([
                'total_amount' => $totalAmount,
                'fulfillment_mode' => $this->determineOrderFulfillmentMode($order),
                'bridge_sync_status' => $this->determineInitialBridgeStatus($order),
                'fulfillment_status' => 'unfulfilled',
            ]);

            // Trigger SMS for courier payments
            if ((int) $data['payment_type'] === 2) {
                event(new OrderCreated($order));
            }

            if ($order->requiresBridgePush() && $order->isBridgePushAllowed()) {
                $bridgeOrderSync->pushOrder($order->fresh('items.variant.product'));
            }

            DB::commit();

            return redirect()->route('admin.orders.show', $order)
                ->with('status', 'Order created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function show(Request $request, Order $order)
    {
        $order->load([
            'items.variant.product',
            'adjustments',
            'cityRelation',
            'paymentLogs' => fn ($query) => $query->latest(),
        ]);

        $view = view('admin.orders.show', [
            'order' => $order,
            'canEditItems' => $this->canEditItems($order),
        ]);

        return $this->renderPjaxView($request, $view);
    }

    public function edit(Request $request, Order $order): View|RedirectResponse
    {
        if (! $this->canEditItems($order)) {
            return redirect()->route('admin.orders.show', $order)
                ->with('error', 'This order can no longer be edited.');
        }

        $order->load('items.variant.product');

        return view('admin.orders.edit', [
            'order' => $order,
            'products' => Product::with('variants')->where('is_active', true)->get(),
            'cities' => City::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Order $order, BridgeOrderSyncService $bridgeOrderSync): RedirectResponse
    {
        if (! $this->canEditItems($order)) {
            return redirect()->route('admin.orders.show', $order)
                ->with('error', 'This order can no longer be edited.');
        }

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:160'],
            'customer_phone' => ['required', 'string', 'max:50', 'regex:/^(995[0-9]{9}|5[0-9]{8})$/'],
            'personal_number' => ['nullable', 'regex:/^\d{11}$/'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'exact_address' => ['nullable', 'string'],
            'order_source' => ['nullable', 'in:Facebook,Instagram,Direct,Other'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'distinct', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        try {
            DB::transaction(function () use ($order, $data) {
                $lockedOrder = Order::query()
                    ->with(['items.variant.product'])
                    ->lockForUpdate()
                    ->findOrFail($order->id);

                if (! $this->canEditItems($lockedOrder)) {
                    throw new \RuntimeException('This order can no longer be edited.');
                }

                foreach ($lockedOrder->items as $item) {
                    if ($item->fulfillment_mode === 'local_stock' && $order->payment_status !== 'rejected') {
                        $item->variant?->increment('quantity', (int) $item->quantity);
                        StockAdjustment::create([
                            'product_variant_id' => $item->product_variant_id,
                            'quantity_change' => (int) $item->quantity,
                            'reason' => "Order {$lockedOrder->order_number} item changed",
                            'notes' => 'Previous item returned before replacement',
                        ]);
                    }
                }

                $variants = ProductVariant::query()
                    ->with('product')
                    ->whereIn('id', collect($data['items'])->pluck('variant_id'))
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $lockedOrder->items()->delete();
                $totalAmount = 0;

                foreach ($data['items'] as $itemData) {
                    $variant = $variants->get((int) $itemData['variant_id']);
                    $quantity = (int) $itemData['quantity'];

                    if (! $variant || ! $variant->canFulfillQuantity($quantity)) {
                        $available = $variant?->available_quantity ?? 0;
                        throw new \RuntimeException("Insufficient stock. Available: {$available}");
                    }

                    $catalogPrice = $variant->product->sale_price ?? $variant->product->price;
                    $unitPrice = filled($itemData['unit_price'] ?? null)
                        ? round((float) $itemData['unit_price'], 2)
                        : $catalogPrice;
                    $subtotal = $unitPrice * $quantity;
                    $totalAmount += $subtotal;

                    OrderItem::create([
                        'order_id' => $lockedOrder->id,
                        'product_variant_id' => $variant->id,
                        'product_name' => $variant->product->name_en,
                        'variant_name' => $variant->name,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'subtotal' => $subtotal,
                        'bridge_product_id' => $variant->product->bridge_product_id,
                        'bridge_variation_id' => $variant->bridge_variation_id,
                        'fulfillment_mode' => $variant->product->fulfillment_mode,
                    ]);

                    if ($variant->product->fulfillment_mode === 'local_stock') {
                        $variant->decrement('quantity', $quantity);
                        StockAdjustment::create([
                            'product_variant_id' => $variant->id,
                            'quantity_change' => -$quantity,
                            'reason' => "Order {$lockedOrder->order_number} item changed",
                            'notes' => 'Replacement item reserved',
                        ]);
                    }
                }

                $city = filled($data['city_id'] ?? null)
                    ? City::query()->findOrFail((int) $data['city_id'])
                    : null;
                $phone = $data['customer_phone'];
                if (strlen($phone) === 9 && str_starts_with($phone, '5')) {
                    $phone = '995' . $phone;
                }

                $exactAddress = filled($data['exact_address'] ?? null) ? $data['exact_address'] : null;
                $lockedOrder->update([
                    'customer_name' => $data['customer_name'],
                    'customer_phone' => $phone,
                    'personal_number' => $data['personal_number'] ?? null,
                    'city_id' => $city?->id,
                    'city' => $city?->name,
                    'exact_address' => $exactAddress,
                    'delivery_address' => $exactAddress ?? 'დასაზუსტებელია',
                    'order_source' => $data['order_source'] ?? $lockedOrder->order_source,
                    'notes' => $data['notes'] ?? null,
                    'total_amount' => $totalAmount,
                ]);

                $lockedOrder->load('items');
                $lockedOrder->update([
                    'fulfillment_mode' => $this->determineOrderFulfillmentMode($lockedOrder),
                    'bridge_sync_status' => $this->determineInitialBridgeStatus($lockedOrder),
                ]);
            });

            $order->refresh()->load('items.variant.product');
            if ($order->requiresBridgePush() && $order->isBridgePushAllowed()) {
                $bridgeOrderSync->pushOrder($order);
            }

            return redirect()->route('admin.orders.show', $order)
                ->with('status', 'Order updated and stock adjusted.');
        } catch (\Throwable $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }
    }

    public function updateStatus(Request $request, Order $order, BridgeOrderSyncService $bridgeOrderSync): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,confirmed,shipped,delivered,cancelled'],
            'notes' => ['nullable', 'string'],
        ]);

        $oldStatus = $order->status;

        // If cancelling, restore stock
        if ($data['status'] === 'cancelled' && !$order->isCancelled()) {
            DB::beginTransaction();

            try {
                foreach ($order->items as $item) {
                    $variant = $item->variant;

                    // Restore stock
                    if ($item->fulfillment_mode === 'local_stock' && $order->payment_status !== 'rejected') {
                        $variant->increment('quantity', $item->quantity);
                    }

                    if ($item->fulfillment_mode === 'local_stock' && $order->payment_status !== 'rejected') {
                        StockAdjustment::create([
                            'product_variant_id' => $variant->id,
                            'quantity_change' => $item->quantity,
                            'reason' => "Order {$order->order_number} Cancelled",
                            'notes' => $request->notes ?? 'Order cancelled',
                        ]);
                    }
                }

                $order->update([
                    'status' => $data['status'],
                    'bridge_sync_status' => $order->requiresBridgePush() ? 'cancelled' : $order->bridge_sync_status,
                    'fulfillment_status' => 'cancelled',
                ]);

                DB::commit();

                return redirect()->route('admin.orders.show', $order)
                    ->with('status', 'Order cancelled and stock restored.');

            } catch (\Exception $e) {
                DB::rollBack();

                return redirect()->back()
                    ->with('error', $e->getMessage());
            }
        }

        // Regular status update
        $order->update(['status' => $data['status']]);

        if ($order->requiresBridgePush() && $order->isBridgePushAllowed()) {
            $bridgeOrderSync->pushOrder($order->fresh('items.variant.product'));
        }

        return redirect()->route('admin.orders.show', $order)
            ->with('status', 'Order status updated.');
    }

    public function updatePaymentStatus(Request $request, Order $order, BridgeOrderSyncService $bridgeOrderSync): RedirectResponse
    {
        $data = $request->validate([
            'payment_status' => ['required', 'in:pending,completed,rejected'],
        ]);

        $order->update([
            'payment_status' => $data['payment_status'],
        ]);

        if ($data['payment_status'] === 'completed' && $order->requiresBridgePush() && $order->isBridgePushAllowed()) {
            $bridgeOrderSync->pushOrder($order->fresh('items.variant.product'));
        }

        return redirect()->route('admin.orders.show', $order)
            ->with('status', 'Payment status updated.');
    }

    public function destroy(Order $order): RedirectResponse
    {
        if (! $order->isCancelled() && ! $order->canBeCancelled()) {
            return redirect()->back()
                ->with('error', 'Cannot delete this order.');
        }

        // Restore stock if not cancelled
        if (!$order->isCancelled() && $order->payment_status !== 'rejected') {
            foreach ($order->items as $item) {
                if ($item->fulfillment_mode === 'local_stock') {
                    $item->variant->increment('quantity', $item->quantity);

                    StockAdjustment::create([
                        'product_variant_id' => $item->variant->id,
                        'quantity_change' => $item->quantity,
                        'reason' => "Order {$order->order_number} Deleted",
                        'notes' => 'Order deleted, stock restored',
                    ]);
                }
            }
        }

        $order->delete();

        return redirect()->route('admin.orders.index')
            ->with('status', 'Order deleted.');
    }

    public function pushBridgeOrder(Order $order, BridgeOrderSyncService $bridgeOrderSync): RedirectResponse
    {
        try {
            $result = $bridgeOrderSync->pushOrder($order);
        } catch (\Throwable $e) {
            return redirect()->route('admin.orders.show', $order)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('admin.orders.show', $order)
            ->with('status', 'Bridge push status: ' . ($result['status'] ?? 'done'));
    }

    public function refreshBridgeOrder(Order $order, BridgeOrderSyncService $bridgeOrderSync): RedirectResponse
    {
        try {
            $result = $bridgeOrderSync->refreshOrderStatus($order);
        } catch (\Throwable $e) {
            return redirect()->route('admin.orders.show', $order)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('admin.orders.show', $order)
            ->with('status', 'Bridge refresh status: ' . ($result['status'] ?? 'done'));
    }

    private function determineOrderFulfillmentMode(Order $order): string
    {
        $modes = $order->items->pluck('fulfillment_mode')->filter()->unique()->values();

        return $modes->count() > 1 ? 'mixed' : ($modes->first() ?: 'local_stock');
    }

    private function canEditItems(Order $order): bool
    {
        return in_array($order->status, ['pending', 'confirmed', 'shipped'], true)
            && ! $order->bridge_order_id
            && ! $order->is_gift_order;
    }

    private function determineInitialBridgeStatus(Order $order): string
    {
        if (! $order->requiresBridgePush()) {
            return 'not_required';
        }

        if ((int) $order->payment_type === 1) {
            return $order->payment_status === 'completed' ? 'pending_push' : 'pending_payment';
        }

        return $order->status === 'confirmed' ? 'pending_push' : 'pending_push';
    }
}
