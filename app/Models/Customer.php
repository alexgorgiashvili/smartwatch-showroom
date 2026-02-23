<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'platform_user_ids',
        'email',
        'phone',
        'avatar_url',
        'metadata',
    ];

    protected $casts = [
        'platform_user_ids' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get all conversations for this customer
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Get all messages from this customer
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get the most recent conversation
     */
    public function getLastConversation()
    {
        return $this->conversations()->latest('last_message_at')->first();
    }

    /**
     * Get unread message count
     */
    public function getUnreadCount(): int
    {
        return $this->conversations()->sum('unread_count');
    }

    /**
     * Find or create customer by platform identifier
     */
    public static function findOrCreateByPlatformId(string $platform, string $platformId, array $data = [])
    {
        // Check if customer exists with this platform ID
        $customers = static::query()
            ->where(function ($query) use ($platform, $platformId) {
                $query->whereJsonContains('platform_user_ids->' . $platform, $platformId);
            })
            ->get();

        if ($customers->count() > 0) {
            return $customers->first();
        }

        // Create new customer
        $customer = static::create([
            'name' => $data['name'] ?? "Customer $platformId",
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'avatar_url' => $data['avatar_url'] ?? null,
            'platform_user_ids' => [$platform => $platformId],
            'metadata' => $data['metadata'] ?? null,
        ]);

        return $customer;
    }

    /**
     * Add platform identifier to existing customer
     */
    public function addPlatformId(string $platform, string $platformId)
    {
        $ids = $this->platform_user_ids ?? [];
        $ids[$platform] = $platformId;
        $this->update(['platform_user_ids' => $ids]);
    }
}
