<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Services\Chatbot\ChatbotContentSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockAdjustmentController extends Controller
{
    public function store(
        Request $request,
        ProductVariant $variant,
        ChatbotContentSyncService $contentSync
    ): JsonResponse
    {
        $data = $request->validate([
            'quantity_change' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $data['product_variant_id'] = $variant->id;

        // Update variant quantity
        $variant->increment('quantity', $data['quantity_change']);
        $variant->refresh();

        // Log the adjustment
        StockAdjustment::create($data);

        $product = $variant->product()->with('variants')->first();
        if ($product) {
            $contentSync->syncProduct($product);
        }

        return response()->json([
            'success' => true,
            'message' => 'Stock adjusted successfully.',
            'new_quantity' => $variant->quantity,
        ]);
    }
}
