<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [
        'customer_id',
        'platform',
        'platform_conversation_id',
        'subject',
        'status',
        'ai_enabled',
        'unread_count',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the customer for this conversation
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get all messages in this conversation
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    /**
     * Get the most recent message
     */
    public function getLastMessage()
    {
        return $this->messages()->latest('created_at')->first();
    }

    /**
     * Get paginated messages
     */
    public function getMessagesPage($page = 1, $perPage = 50)
    {
        return $this->messages()->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Mark conversation as read
     */
    public function markAsRead()
    {
        $this->update([
            'unread_count' => 0,
        ]);

        // Mark all messages as read
        $this->messages()->update(['read_at' => now()]);
    }

    /**
     * Mark conversation as archived
     */
    public function archive()
    {
        $this->update(['status' => 'archived']);
    }

    /**
     * Mark conversation as closed
     */
    public function close()
    {
        $this->update(['status' => 'closed']);
    }

    /**
     * Reopen a closed conversation
     */
    public function reopen()
    {
        $this->update(['status' => 'active']);
    }

    /**
     * Find or create conversation by platform identifier
     */
    public static function findOrCreateByPlatformId(
        Customer $customer,
        string $platform,
        string $platformConversationId,
        array $data = []
    ) {
        return static::firstOrCreate(
            [
                'platform' => $platform,
                'platform_conversation_id' => $platformConversationId,
            ],
            [
                'customer_id' => $customer->id,
                'subject' => $data['subject'] ?? null,
                'status' => 'active',
                'unread_count' => 0,
            ]
        );
    }

    /**
     * Increment unread count
     */
    public function incrementUnreadCount()
    {
        $this->increment('unread_count');
        $this->update(['last_message_at' => now()]);
    }

    /**
     * Get platform badge display name
     */
    public function getPlatformLabel(): string
    {
        return match ($this->platform) {
            'facebook' => 'Facebook Messenger',
            'instagram' => 'Instagram DM',
            'whatsapp' => 'WhatsApp',
            default => $this->platform,
        };
    }
}
