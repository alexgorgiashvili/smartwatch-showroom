<?php

namespace App\Http\Controllers\Site;

use App\Events\OrderCreated;
use App\Events\PaymentCompleted;
use App\Http\Controllers\Controller;
use App\Jobs\PushBridgeOrderJob;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\OrderItem;
use App\Models\PaymentLog;
use App\Services\BogPayService;
use App\Services\BogPaymentStatusSynchronizer;
use App\Services\Cart\CartSnapshotService;
use App\Services\SmsOfficeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GeoPaymentController extends Controller
{
    public function __construct(
        private readonly BogPayService $bogPayService,
        private readonly BogPaymentStatusSynchronizer $bogPaymentStatusSynchronizer,
        private readonly SmsOfficeService $smsService,
        private readonly CartSnapshotService $cartSnapshotService
    ) {
    }

    private function shouldReturnJson(Request $request): bool
    {
        return $request->expectsJson()
            || $request->wantsJson()
            || $request->ajax()
            || $request->isXmlHttpRequest()
            || str_contains((string) $request->header('Accept'), 'application/json')
            || strtolower((string) $request->header('X-Requested-With')) === 'xmlhttprequest';
    }

    public function validatePaymentOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:160'],
            'customer_phone' => ['required', 'string', 'max:50', 'regex:/^(995[0-9]{9}|5[0-9]{8})$/'],
            'personal_number' => ['required', 'regex:/^\d{11}$/'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'exact_address' => ['required', 'string'],
            'payment_type' => ['required', 'in:1,2'],
        ]);

        DB::beginTransaction();

        try {
            $cartSnapshot = $this->cartSnapshotService->build($request, [
                'lock_for_update' => true,
                'enforce_stock' => true,
            ]);

            $summary = $cartSnapshot['summary'];
            $lineItems = $cartSnapshot['all_items']->values()->all();

            if ((int) $summary['count'] <= 0 || $lineItems === []) {
                throw new RuntimeException('Cart is empty.');
            }

            $city = City::query()->whereKey((int) $data['city_id'])->first();
            if (! $city) {
                throw new RuntimeException('Selected city is invalid.');
            }

            if ((int) $data['payment_type'] === 2 && ! $this->isTbilisi($city->name)) {
                throw new RuntimeException('კურიერთან გადახდა ხელმისაწვდომია მხოლოდ თბილისის შეკვეთებისთვის.');
            }

            // Format phone number
            $phone = $data['customer_phone'];
            if (strlen($phone) === 9 && str_starts_with($phone, '5')) {
                $data['customer_phone'] = '995' . $phone;
            }

            $orderFulfillmentMode = $this->determineOrderFulfillmentMode($lineItems);
            $hasBridgeItems = in_array($orderFulfillmentMode, ['dropship_bridge', 'mixed'], true);
            $giftGroups = $cartSnapshot['gift_groups'];

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
                'fulfillment_mode' => $orderFulfillmentMode,
                'bridge_sync_status' => $hasBridgeItems
                    ? ((int) $data['payment_type'] === 1 ? 'pending_payment' : 'pending_push')
                    : 'not_required',
                'fulfillment_status' => 'unfulfilled',
                'total_amount' => (float) $summary['total'],
                'currency' => config('bog.currency', 'GEL'),
                'notes' => null,
                'is_gift_order' => $giftGroups->isNotEmpty(),
                'gift_groups' => $this->giftGroupMetadata($giftGroups),
                'gift_packaging_amount' => (float) $summary['packaging_total'],
                'gift_discount_amount' => (float) $summary['discount_total'],
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
                    'bridge_product_id' => $lineItem['variant']->product->bridge_product_id,
                    'bridge_variation_id' => $lineItem['variant']->bridge_variation_id,
                    'fulfillment_mode' => $lineItem['fulfillment_mode'],
                    'gift_group_id' => $lineItem['gift_group_id'],
                    'gift_role' => $lineItem['gift_role'],
                    'gift_sort_order' => $lineItem['gift_sort_order'],
                ]);

                if ($lineItem['fulfillment_mode'] === 'local_stock') {
                    $lineItem['variant']->decrement('quantity', $lineItem['quantity']);
                }
            }

            $this->createGiftAdjustments($order, $giftGroups);

            $redirectData = null;
            if ((int) $data['payment_type'] === 1) {
                $redirectData = $this->createBogOrder($order->fresh(['items', 'adjustments']));
            } else {
                $redirectData = [
                    'redirect_url' => route('payment.success', ['order' => $order->order_number, 'method' => 'cod']),
                ];
            }

            DB::commit();
            if ((int) $data['payment_type'] === 2) {
                event(new OrderCreated($order->fresh(['items.variant.product']), true));
            }
            $request->session()->forget(['cart', 'gift_cart_groups']);

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

    private function isTbilisi(string $cityName): bool
    {
        $normalizedName = mb_strtolower(trim($cityName));

        return $normalizedName === 'თბილისი'
            || str_starts_with($normalizedName, 'თბილისი >')
            || $normalizedName === 'tbilisi'
            || str_starts_with($normalizedName, 'tbilisi >');
    }

    public function bogPayRedirect(Request $request): RedirectResponse|JsonResponse
    {
        $orderId = $request->integer('order_id');
        $returnJson = $this->shouldReturnJson($request);

        $order = Order::query()
            ->with(['items', 'adjustments'])
            ->whereKey($orderId)
            ->firstOrFail();

        if ($order->payment_type !== 1) {
            if ($returnJson) {
                return response()->json([
                    'message' => 'Unsupported payment type for this route.',
                ], 422);
            }

            return redirect()->route('payment.fail', ['order' => $order->order_number])
                ->with('retry_error', 'ამ შეკვეთაზე ბარათით გადახდის თავიდან დაწყება შეუძლებელია.');
        }

        try {
            $redirectData = $this->createBogOrder($order);
        } catch (RuntimeException $exception) {
            if ($returnJson) {
                return response()->json([
                    'message' => $exception->getMessage(),
                ], 422);
            }

            return redirect()->route('payment.fail', ['order' => $order->order_number])
                ->with('retry_error', $exception->getMessage());
        } catch (Throwable $exception) {
            Log::error('BOG bogPayRedirect failed', [
                'exception' => get_class($exception),
                'error' => $exception->getMessage(),
                'order_id' => $order->id,
            ]);

            if ($returnJson) {
                return response()->json([
                    'message' => 'Payment initialization failed.',
                ], 500);
            }

            return redirect()->route('payment.fail', ['order' => $order->order_number])
                ->with('retry_error', 'ბარათით გადახდის თავიდან დაწყება ვერ შესრულდა.');
        }

        if (! $returnJson) {
            return redirect()->away($redirectData['redirect_url']);
        }

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

        try {
            $verifiedPayment = $this->bogPayService->getPaymentDetails((string) $bogOrderId);
        } catch (Throwable $exception) {
            Log::warning('BOG callback verification failed.', [
                'bog_order_id' => $bogOrderId,
                'external_order_id' => $externalOrderId,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to verify callback.',
            ], 502);
        }

        $verifiedBogOrderId = (string) ($verifiedPayment['order_id'] ?? $verifiedPayment['id'] ?? '');
        $verifiedExternalOrderId = (string) ($verifiedPayment['external_order_id'] ?? '');
        $verifiedStatusKey = $this->bogPaymentStatusSynchronizer->normalizeBogStatus($verifiedPayment);

        if ($verifiedBogOrderId === '' || $verifiedStatusKey === '') {
            Log::warning('BOG callback verification returned incomplete payload.', [
                'bog_order_id' => $bogOrderId,
                'external_order_id' => $externalOrderId,
                'verified_payload' => $verifiedPayment,
            ]);

            return response()->json([
                'message' => 'Unable to verify callback.',
            ], 502);
        }

        $order = Order::query()
            ->where(function ($query) use ($verifiedBogOrderId, $verifiedExternalOrderId) {
                $query->where('bog_order_id', $verifiedBogOrderId);

                if ($verifiedExternalOrderId !== '') {
                    $query->orWhere('bog_external_order_id', $verifiedExternalOrderId);
                }
            })
            ->first();

        if (! $order) {
            return response()->json([
                'message' => 'Order not found.',
            ], 404);
        }

        $result = $this->bogPaymentStatusSynchronizer->syncOrder($order, $verifiedPayment, $paymentDetail);

        return match ($result) {
            'invalid' => response()->json(['message' => 'Callback verification mismatch.'], 422),
            'ignored' => response()->json(['message' => 'Ignored.']),
            default => response()->json(['message' => 'OK']),
        };
    }

    private function createBogOrder(Order $order): array
    {
        if ($order->bog_order_id) {
            if (! $this->canRetryBogOrder($order)) {
                throw new RuntimeException('BOG order already initialized.');
            }

            $order->update([
                'bog_order_id' => null,
                'bog_external_order_id' => null,
                'payment_status' => 'pending',
            ]);
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

    private function canRetryBogOrder(Order $order): bool
    {
        return (int) $order->payment_type === 1
            && $order->status === 'pending'
            && $order->payment_status !== 'completed';
    }

    private function determineOrderFulfillmentMode(array $lineItems): string
    {
        $modes = collect($lineItems)
            ->pluck('fulfillment_mode')
            ->filter()
            ->unique()
            ->values();

        if ($modes->count() > 1) {
            return 'mixed';
        }

        return $modes->first() ?: 'local_stock';
    }

    private function giftGroupMetadata($giftGroups): array
    {
        return $giftGroups
            ->map(fn (array $group): array => [
                'id' => $group['id'],
                'recipient_type' => $group['recipient_type'],
                'occasion' => $group['occasion'],
                'budget_band' => $group['budget_band'],
                'packaging_slug' => $group['packaging_slug'],
                'packaging_label' => $group['packaging_label'],
                'packaging_amount' => (float) $group['packaging_amount'],
                'discount_amount' => (float) $group['discount_amount'],
                'message' => $group['message'],
                'items_count' => (int) $group['items_count'],
                'items_subtotal' => (float) $group['items_subtotal'],
                'total' => (float) $group['total'],
            ])
            ->values()
            ->all();
    }

    private function createGiftAdjustments(Order $order, $giftGroups): void
    {
        foreach ($giftGroups as $group) {
            if ((float) $group['packaging_amount'] > 0) {
                OrderAdjustment::create([
                    'order_id' => $order->id,
                    'gift_group_id' => $group['id'],
                    'type' => 'gift_packaging',
                    'title' => $group['packaging_label'] ?: 'Gift packaging',
                    'amount' => (float) $group['packaging_amount'],
                    'metadata' => [
                        'packaging_slug' => $group['packaging_slug'],
                        'items_count' => (int) $group['items_count'],
                    ],
                ]);
            }

            if ((float) $group['discount_amount'] > 0) {
                OrderAdjustment::create([
                    'order_id' => $order->id,
                    'gift_group_id' => $group['id'],
                    'type' => 'gift_discount',
                    'title' => 'Gift box discount',
                    'amount' => -1 * (float) $group['discount_amount'],
                    'metadata' => [
                        'budget_band' => $group['budget_band'],
                    ],
                ]);
            }
        }
    }
}
