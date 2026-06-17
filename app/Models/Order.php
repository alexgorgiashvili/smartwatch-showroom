<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'customer_name',
        'customer_phone',
        'personal_number',
        'customer_email',
        'delivery_address',
        'exact_address',
        'city',
        'city_id',
        'postal_code',
        'order_source',
        'payment_type',
        'status',
        'payment_status',
        'bog_order_id',
        'bog_external_order_id',
        'fulfillment_mode',
        'bridge_order_id',
        'bridge_order_number',
        'bridge_sync_status',
        'bridge_synced_at',
        'fulfillment_status',
        'tracking_number',
        'tracking_carrier',
        'fulfilled_at',
        'total_amount',
        'currency',
        'notes',
        'sms_sent_at',
        'sms_reference',
        'is_gift_order',
        'gift_groups',
        'gift_packaging_amount',
        'gift_discount_amount',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'payment_type' => 'integer',
        'sms_sent_at' => 'datetime',
        'bridge_synced_at' => 'datetime',
        'fulfilled_at' => 'datetime',
        'is_gift_order' => 'boolean',
        'gift_groups' => 'array',
        'gift_packaging_amount' => 'decimal:2',
        'gift_discount_amount' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function paymentLogs(): HasMany
    {
        return $this->hasMany(PaymentLog::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(OrderAdjustment::class);
    }

    public function cityRelation(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public static function generateOrderNumber(): string
    {
        $date = now()->format('Ymd');
        $lastOrder = static::whereDate('created_at', now()->toDateString())
            ->orderByDesc('id')
            ->first();

        $sequence = $lastOrder ? (int) substr($lastOrder->order_number, -4) + 1 : 1;

        return 'ORD-' . $date . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'shipped']);
    }

    public function isSmsSent(): bool
    {
        return !is_null($this->sms_sent_at);
    }

    public function markSmsSent(string $reference = null): void
    {
        $this->update([
            'sms_sent_at' => now(),
            'sms_reference' => $reference,
        ]);
    }

    public function dropshipItems(): Collection
    {
        return $this->items->filter(fn (OrderItem $item) => $item->fulfillment_mode === 'dropship_bridge')->values();
    }

    public function localStockItems(): Collection
    {
        return $this->items->filter(fn (OrderItem $item) => $item->fulfillment_mode === 'local_stock')->values();
    }

    public function requiresBridgePush(): bool
    {
        return in_array($this->fulfillment_mode, ['dropship_bridge', 'mixed'], true);
    }

    public function isBridgePushAllowed(): bool
    {
        if (! $this->requiresBridgePush()) {
            return false;
        }

        if ((int) $this->payment_type === 1) {
            return $this->payment_status === 'completed';
        }

        if ((int) $this->payment_type === 2) {
            return $this->status === 'confirmed';
        }

        return false;
    }
}
