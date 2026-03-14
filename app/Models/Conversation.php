<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'assigned_to',
        'assigned_agent_id',
        'is_ai_enabled',
        'ai_mode',
        'priority',
        'tags',
        'metadata',
        'internal_notes',
        'last_agent_reply_at',
        'agent_replying_to',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'last_agent_reply_at' => 'datetime',
        'is_ai_enabled' => 'boolean',
        'tags' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Get the customer for this conversation
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the assigned agent for this conversation
     */
    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'assigned_agent_id');
    }

    /**
     * Get all assignment history for this conversation
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ConversationAssignment::class);
    }

    /**
     * Get the current active assignment
     */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(ConversationAssignment::class)
            ->whereNull('unassigned_at')
            ->latestOfMany('assigned_at');
    }

    /**
     * Get all messages in this conversation
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    /**
     * Get the latest message for sidebar previews.
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
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
            'messenger' => 'Messenger',
            'home' => 'Home Widget',
            default => $this->platform,
        };
    }

    /**
     * Assign conversation to an agent
     */
    public function assignToAgent(Agent $agent): void
    {
        $this->update([
            'assigned_to' => $agent->id,
            'last_agent_reply_at' => now(),
        ]);
    }

    /**
     * Unassign conversation from agent
     */
    public function unassign(): void
    {
        $this->update(['assigned_to' => null]);
    }

    /**
     * Check if conversation is assigned
     */
    public function isAssigned(): bool
    {
        return !is_null($this->assigned_to);
    }

    /**
     * Enable AI for this conversation
     */
    public function enableAI(): void
    {
        $this->update(['is_ai_enabled' => true]);
    }

    /**
     * Disable AI for this conversation
     */
    public function disableAI(): void
    {
        $this->update(['is_ai_enabled' => false]);
    }

    /**
     * Mark an agent as currently replying
     */
    public function markAgentAsReplying(string $agentIdentifier): void
    {
        $this->update(['agent_replying_to' => $agentIdentifier]);
    }

    /**
     * Clear the replying agent marker
     */
    public function clearReplyingAgent(): void
    {
        $this->update(['agent_replying_to' => null]);
    }

    /**
     * Check if another agent is replying
     */
    public function isBeingRepliedByOtherAgent(string $currentAgentIdentifier): bool
    {
        return $this->agent_replying_to &&
               $this->agent_replying_to !== $currentAgentIdentifier;
    }

    /**
     * Set conversation priority
     */
    public function setPriority(string $priority): void
    {
        if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
            return;
        }

        $this->update(['priority' => $priority]);
    }

    /**
     * Add tag to conversation
     */
    public function addTag(string $tag): void
    {
        $tags = $this->tags ?? [];

        if (!in_array($tag, $tags, true)) {
            $tags[] = $tag;
            $this->update(['tags' => $tags]);
        }
    }

    /**
     * Remove tag from conversation
     */
    public function removeTag(string $tag): void
    {
        $tags = $this->tags ?? [];
        $tags = array_values(array_filter($tags, fn($t) => $t !== $tag));
        $this->update(['tags' => $tags]);
    }

    /**
     * Set AI mode for conversation
     */
    public function setAiMode(string $mode): void
    {
        if (!in_array($mode, ['off', 'auto', 'manual_override'], true)) {
            return;
        }

        $this->update(['ai_mode' => $mode]);
    }

    /**
     * Check if AI is enabled (considering both old and new fields)
     */
    public function isAiActive(): bool
    {
        return $this->ai_mode === 'auto' ||
               $this->ai_mode === 'manual_override' ||
               $this->is_ai_enabled === true;
    }

    /**
     * Scope: Filter by priority
     */
    public function scopePriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope: Filter by AI mode
     */
    public function scopeAiMode($query, string $mode)
    {
        return $query->where('ai_mode', $mode);
    }

    /**
     * Scope: Unassigned conversations
     */
    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_agent_id');
    }

    /**
     * Scope: Assigned to specific agent
     */
    public function scopeAssignedTo($query, int $agentId)
    {
        return $query->where('assigned_agent_id', $agentId);
    }
}
