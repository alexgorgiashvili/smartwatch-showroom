<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(): View
    {
        $orders = Order::query()
            ->with('items')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.orders.index', [
            'orders' => $orders,
        ]);
    }

    public function create(): View
    {
        $products = Product::with('variants')->where('is_active', true)->get();

        return view('admin.orders.create', [
            'order' => new Order(),
            'products' => $products,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:160'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email', 'max:160'],
            'delivery_address' => ['required', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'order_source' => ['required', 'in:Facebook,Instagram,Direct,Other'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        DB::beginTransaction();

        try {
            // Generate order number
            $data['order_number'] = Order::generateOrderNumber();
            $data['status'] = 'pending';
            $data['currency'] = 'GEL';
            $data['total_amount'] = 0;

            // Create order
            $order = Order::create($data);

            $totalAmount = 0;

            // Create order items and adjust stock
            foreach ($request->items as $item) {
                $variant = ProductVariant::with('product')->findOrFail($item['variant_id']);

                // Check stock availability
                if ($variant->quantity < $item['quantity']) {
                    throw new \Exception("Insufficient stock for {$variant->name}. Available: {$variant->quantity}");
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
                ]);

                // Decrease stock
                $variant->decrement('quantity', $item['quantity']);

                // Log stock adjustment
                StockAdjustment::create([
                    'product_variant_id' => $variant->id,
                    'quantity_change' => -$item['quantity'],
                    'reason' => "Order {$order->order_number}",
                    'notes' => "Order created for {$order->customer_name}",
                ]);
            }

            // Update order total
            $order->update(['total_amount' => $totalAmount]);

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

    public function show(Order $order): View
    {
        $order->load(['items.variant.product']);

        return view('admin.orders.show', [
            'order' => $order,
        ]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,shipped,delivered,cancelled'],
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
                    $variant->increment('quantity', $item->quantity);

                    // Log stock adjustment
                    StockAdjustment::create([
                        'product_variant_id' => $variant->id,
                        'quantity_change' => $item->quantity,
                        'reason' => "Order {$order->order_number} Cancelled",
                        'notes' => $request->notes ?? 'Order cancelled',
                    ]);
                }

                $order->update(['status' => $data['status']]);

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

        return redirect()->route('admin.orders.show', $order)
            ->with('status', 'Order status updated.');
    }

    public function destroy(Order $order): RedirectResponse
    {
        if (!$order->canBeCancelled()) {
            return redirect()->back()
                ->with('error', 'Cannot delete this order.');
        }

        // Restore stock if not cancelled
        if (!$order->isCancelled()) {
            foreach ($order->items as $item) {
                $item->variant->increment('quantity', $item->quantity);

                StockAdjustment::create([
                    'product_variant_id' => $item->variant->id,
                    'quantity_change' => $item->quantity,
                    'reason' => "Order {$order->order_number} Deleted",
                    'notes' => 'Order deleted, stock restored',
                ]);
            }
        }

        $order->delete();

        return redirect()->route('admin.orders.index')
            ->with('status', 'Order deleted.');
    }
}
