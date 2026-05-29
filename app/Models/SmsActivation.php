<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class SmsActivation extends Model
{
    protected $fillable = [
        'activation_id',
        'phone_number',
        'service',
        'service_name',
        'country',
        'country_name',
        'cost',
        'currency',
        'status',
        'sms_code',
        'sms_text',
        'sms_received_at',
        'notes',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'currency' => 'integer',
        'sms_received_at' => 'datetime',
    ];

    // --- Scopes ---

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'ready', 'code_received']);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereIn('status', ['completed', 'cancelled']);
    }

    // --- Helpers ---

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'warning',
            'ready' => 'info',
            'code_received' => 'success',
            'completed' => 'gray',
            'cancelled' => 'danger',
            'expired' => 'gray',
            default => 'gray',
        };
    }
}
