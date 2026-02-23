<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentLog;
use App\Models\ProductVariant;
use App\Services\BogPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GeoPaymentController extends Controller
{
    public function __construct(private readonly BogPayService $bogPayService)
    {
    }

    public function validatePaymentOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:160'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'personal_number' => ['required', 'regex:/^\d{11}$/'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'exact_address' => ['required', 'string'],
            'payment_type' => ['required', 'in:1,2'],
        ]);

        $cart = collect($request->session()->get('cart', []));
        if ($cart->isEmpty()) {
            return response()->json([
                'message' => 'Cart is empty.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $variantIds = $cart->keys()->map(fn ($id) => (int) $id)->values();

            $variants = ProductVariant::query()
                ->with('product')
                ->whereIn('id', $variantIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lineItems = [];
            $totalAmount = 0.0;

            foreach ($cart as $variantId => $item) {
                $variant = $variants->get((int) $variantId);

                if (! $variant || ! $variant->product || ! $variant->product->is_active) {
                    throw new RuntimeException('One or more products are no longer available.');
                }

                $quantity = max(1, min((int) ($item['quantity'] ?? 1), 10));
                if ($variant->quantity < $quantity) {
                    throw new RuntimeException('Insufficient stock for: ' . $variant->name);
                }

                $unitPrice = (float) ($variant->product->sale_price ?? $variant->product->price ?? 0);
                if ($unitPrice <= 0) {
                    throw new RuntimeException('Invalid product price detected.');
                }

                $subtotal = $unitPrice * $quantity;
                $totalAmount += $subtotal;

                $lineItems[] = [
                    'variant' => $variant,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ];
            }

            if ($lineItems === []) {
                throw new RuntimeException('Cart is empty.');
            }

            $city = City::query()->whereKey((int) $data['city_id'])->first();
            if (! $city) {
                throw new RuntimeException('Selected city is invalid.');
            }

            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'personal_number' => $data['personal_number'],
                'customer_email' => null,
                'delivery_address' => $data['exact_address'],
                'exact_address' => $data['exact_address'],
                'city' => $city->name,
                'city_id' => $city->id,
                'postal_code' => null,
                'order_source' => 'Direct',
                'status' => 'pending',
                'payment_type' => (int) $data['payment_type'],
                'payment_status' => 'pending',
                'total_amount' => $totalAmount,
                'currency' => config('bog.currency', 'GEL'),
                'notes' => null,
            ]);

            foreach ($lineItems as $lineItem) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_variant_id' => $lineItem['variant']->id,
                    'product_name' => $lineItem['variant']->product->name,
                    'variant_name' => $lineItem['variant']->name,
                    'quantity' => $lineItem['quantity'],
                    'unit_price' => $lineItem['unit_price'],
                    'subtotal' => $lineItem['subtotal'],
                ]);

                $lineItem['variant']->decrement('quantity', $lineItem['quantity']);
            }

            $redirectData = null;
            if ((int) $data['payment_type'] === 1) {
                $redirectData = $this->createBogOrder($order->fresh('items'));
            } else {
                $redirectData = [
                    'redirect_url' => route('payment.success', ['order' => $order->order_number, 'method' => 'cod']),
                ];
            }

            DB::commit();
            $request->session()->forget('cart');

            return response()->json([
                'redirect_url' => $redirectData['redirect_url'],
                'order_number' => $order->order_number,
            ]);
        } catch (RuntimeException $exception) {
            DB::rollBack();

            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            DB::rollBack();

            Log::error('BOG validatePaymentOrder failed', [
                'exception' => get_class($exception),
                'error' => $exception->getMessage(),
                'payment_type' => $data['payment_type'] ?? null,
            ]);

            return response()->json([
                'message' => 'Payment initialization failed.',
            ], 500);
        }
    }

    public function bogPayRedirect(Request $request): JsonResponse
    {
        $orderId = $request->integer('order_id');

        $order = Order::query()
            ->with('items')
            ->whereKey($orderId)
            ->firstOrFail();

        if ($order->payment_type !== 1) {
            return response()->json([
                'message' => 'Unsupported payment type for this route.',
            ], 422);
        }

        $redirectData = $this->createBogOrder($order);

        return response()->json([
            'redirect_url' => $redirectData['redirect_url'],
            'order_number' => $order->order_number,
        ]);
    }

    public function bogPaymentCallback(Request $request): JsonResponse
    {
        $payload = $request->input('body', $request->all());

        $bogOrderId = $payload['order_id'] ?? null;
        $externalOrderId = $payload['external_order_id'] ?? null;
        $statusKey = strtolower((string) ($payload['order_status']['key'] ?? ''));
        $paymentDetail = $payload['payment_detail'] ?? null;

        if (! $bogOrderId || ! $externalOrderId || ! $statusKey) {
            return response()->json([
                'message' => 'Invalid callback payload.',
            ], 400);
        }

        $order = Order::query()
            ->where('bog_order_id', $bogOrderId)
            ->orWhere('bog_external_order_id', $externalOrderId)
            ->first();

        if (! $order) {
            return response()->json([
                'message' => 'Order not found.',
            ], 404);
        }

        if ($statusKey === 'completed') {
            $order->update([
                'payment_status' => 'completed',
            ]);

            PaymentLog::create([
                'order_id' => $order->id,
                'bog_order_id' => $bogOrderId,
                'external_order_id' => $externalOrderId,
                'status' => 'PERFORMED',
                'chveni_statusi' => 'warmatebuli gadaxda',
                'payment_detail' => $paymentDetail,
            ]);
        } elseif ($statusKey === 'rejected') {
            if ($order->payment_status !== 'rejected') {
                foreach ($order->items as $item) {
                    $item->variant?->increment('quantity', (int) $item->quantity);
                }
            }

            $order->update([
                'payment_status' => 'rejected',
            ]);

            PaymentLog::create([
                'order_id' => $order->id,
                'bog_order_id' => $bogOrderId,
                'external_order_id' => $externalOrderId,
                'status' => 'REJECTED',
                'chveni_statusi' => 'gadaxda ver moxerxda',
                'payment_detail' => $paymentDetail,
            ]);
        }

        return response()->json([
            'message' => 'OK',
        ]);
    }

    private function createBogOrder(Order $order): array
    {
        if ($order->bog_order_id) {
            throw new RuntimeException('BOG order already initialized.');
        }

        $externalOrderId = 'IPAY-' . strtoupper(substr((string) Str::uuid(), 0, 8));
        $response = $this->bogPayService->create($order, $externalOrderId);

        $order->update([
            'bog_order_id' => $response['id'],
            'bog_external_order_id' => $externalOrderId,
        ]);

        PaymentLog::create([
            'order_id' => $order->id,
            'bog_order_id' => $response['id'],
            'external_order_id' => $externalOrderId,
            'status' => 'CREATED',
            'chveni_statusi' => 'dawyeba',
            'payment_detail' => $response['raw'] ?? null,
        ]);

        return [
            'redirect_url' => $response['redirect_url'],
        ];
    }
}
