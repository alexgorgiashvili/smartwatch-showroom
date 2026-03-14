<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'customer_id',
        'reply_to_id',
        'sender_type',
        'sender_id',
        'sender_name',
        'content',
        'media_url',
        'media_type',
        'platform_message_id',
        'metadata',
        'read_at',
        'delivery_status',
        'encrypted',
        'confidential',
    ];

    protected $casts = [
        'metadata' => 'array',
        'read_at' => 'datetime',
        'encrypted' => 'boolean',
        'confidential' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'delivery_status' => 'sent',
    ];

    /**
     * Get the conversation this message belongs to
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the customer this message is from
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the sender (for admin messages, resolve to User model)
     */
    public function sender()
    {
        if ($this->sender_type === 'admin') {
            return $this->belongsTo(User::class, 'sender_id');
        }
        return $this->belongsTo(Customer::class, 'sender_id');
    }

    /**
     * Get the message this is replying to
     */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    /**
     * Get all replies to this message
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Message::class, 'reply_to_id');
    }

    /**
     * Check if message has been read
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Mark message as read
     */
    public function markAsRead()
    {
        $this->update(['read_at' => now()]);
    }

    /**
     * Check if message has media attached
     */
    public function hasMedia(): bool
    {
        return !is_null($this->media_url);
    }

    /**
     * Get media icon based on type
     */
    public function getMediaIcon(): string
    {
        return match ($this->media_type) {
            'image' => 'image',
            'video' => 'play-circle',
            'audio' => 'volume-2',
            'file' => 'file',
            default => 'attachment',
        };
    }

    /**
     * Check if message is from customer
     */
    public function isFromCustomer(): bool
    {
        return $this->sender_type === 'customer';
    }

    /**
     * Check if message is from admin
     */
    public function isFromAdmin(): bool
    {
        return $this->sender_type === 'admin';
    }

    /**
     * Check if message is from bot
     */
    public function isFromBot(): bool
    {
        return $this->sender_type === 'bot';
    }

    /**
     * Check if message is system message
     */
    public function isSystemMessage(): bool
    {
        return $this->sender_type === 'system';
    }

    /**
     * Update delivery status
     */
    public function updateDeliveryStatus(string $status): void
    {
        if (!in_array($status, ['pending', 'sent', 'delivered', 'failed'], true)) {
            return;
        }

        $this->update(['delivery_status' => $status]);
    }

    /**
     * Mark as delivered
     */
    public function markAsDelivered(): void
    {
        $this->updateDeliveryStatus('delivered');
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(): void
    {
        $this->updateDeliveryStatus('failed');
    }

    /**
     * Check if message has been delivered
     */
    public function isDelivered(): bool
    {
        return $this->delivery_status === 'delivered';
    }

    /**
     * Check if message failed to send
     */
    public function hasFailed(): bool
    {
        return $this->delivery_status === 'failed';
    }

    /**
     * Create a message from a webhook
     */
    public static function createFromWebhook(
        Conversation $conversation,
        Customer $customer,
        array $data
    ) {
        return static::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'sender_type' => 'customer',
            'sender_id' => $customer->id,
            'sender_name' => $data['sender_name'] ?? $customer->name,
            'content' => $data['content'] ?? '',
            'media_url' => $data['media_url'] ?? null,
            'media_type' => $data['media_type'] ?? null,
            'platform_message_id' => $data['platform_message_id'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    /**
     * Create a reply message from admin
     */
    public static function createReply(
        Conversation $conversation,
        Customer $customer,
        int $adminUserId,
        string $content,
        ?string $mediaUrl = null,
        ?string $mediaType = null
    ) {
        $admin = User::find($adminUserId);

        return static::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'sender_type' => 'admin',
            'sender_id' => $adminUserId,
            'sender_name' => $admin->name ?? 'Admin',
            'content' => $content,
            'media_url' => $mediaUrl,
            'media_type' => $mediaType,
            'read_at' => now(),
        ]);
    }
}
