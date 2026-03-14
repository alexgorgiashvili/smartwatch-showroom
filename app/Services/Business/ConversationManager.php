<?php

namespace App\Services\Business;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\ConversationAssignment;
use App\Repositories\ConversationRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConversationManager
{
    public function __construct(
        protected ConversationRepository $conversationRepository
    ) {}

    public function findOrCreateConversation(
        Customer $customer,
        string $platform,
        string $platformId
    ): Conversation {
        Log::info('ConversationManager: findOrCreateConversation', [
            'platform' => $platform,
            'platform_id' => $platformId,
            'customer_id' => $customer->id,
        ]);

        $conversation = $this->conversationRepository->findByPlatformId($platform, $platformId);

        if ($conversation) {
            Log::info('ConversationManager: Found existing conversation', [
                'conversation_id' => $conversation->id,
                'platform' => $platform,
            ]);

            if ($conversation->status === 'closed' || $conversation->status === 'archived') {
                $this->conversationRepository->updateStatus($conversation->id, 'active');
            }

            return $conversation;
        }

        Log::info('ConversationManager: Creating new conversation', [
            'platform' => $platform,
            'platform_id' => $platformId,
        ]);

        return $this->conversationRepository->createConversation(
            $customer,
            $platform,
            $platformId
        );
    }

    public function getFilteredConversations(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->conversationRepository->getActiveConversations($filters, $perPage);
    }

    public function assignConversation(int $conversationId, int $agentId, ?int $assignedBy = null): bool
    {
        try {
            return DB::transaction(function () use ($conversationId, $agentId, $assignedBy) {
                $conversation = $this->conversationRepository->findById($conversationId);

                if (!$conversation) {
                    return false;
                }

                $currentAssignment = $conversation->currentAssignment;
                if ($currentAssignment && $currentAssignment->agent_id === $agentId) {
                    return true;
                }

                if ($currentAssignment) {
                    $currentAssignment->unassign('Reassigned to another agent');
                }

                ConversationAssignment::create([
                    'conversation_id' => $conversationId,
                    'agent_id' => $agentId,
                    'assigned_by' => $assignedBy,
                    'assigned_at' => now(),
                ]);

                $this->conversationRepository->assignToAgent($conversationId, $agentId);

                return true;
            });
        } catch (\Exception $e) {
            \Log::error('Failed to assign conversation', [
                'conversation_id' => $conversationId,
                'agent_id' => $agentId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function unassignConversation(int $conversationId, ?string $notes = null): bool
    {
        try {
            return DB::transaction(function () use ($conversationId, $notes) {
                $conversation = $this->conversationRepository->findById($conversationId);

                if (!$conversation) {
                    return false;
                }

                $currentAssignment = $conversation->currentAssignment;
                if ($currentAssignment) {
                    $currentAssignment->unassign($notes);
                }

                $this->conversationRepository->unassign($conversationId);

                return true;
            });
        } catch (\Exception $e) {
            \Log::error('Failed to unassign conversation', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function updateConversationStatus(int $conversationId, string $status): bool
    {
        if (!in_array($status, ['active', 'archived', 'closed'], true)) {
            return false;
        }

        try {
            $this->conversationRepository->updateStatus($conversationId, $status);
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to update conversation status', [
                'conversation_id' => $conversationId,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function toggleAiMode(int $conversationId): bool
    {
        try {
            $conversation = $this->conversationRepository->findById($conversationId);

            if (!$conversation) {
                return false;
            }

            $newMode = match ($conversation->ai_mode) {
                'off' => 'auto',
                'auto' => 'manual_override',
                'manual_override' => 'off',
                default => 'auto',
            };

            $this->conversationRepository->updateAiMode($conversationId, $newMode);

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to toggle AI mode', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function setAiMode(int $conversationId, string $mode): bool
    {
        if (!in_array($mode, ['off', 'auto', 'manual_override'], true)) {
            return false;
        }

        try {
            $this->conversationRepository->updateAiMode($conversationId, $mode);
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to set AI mode', [
                'conversation_id' => $conversationId,
                'mode' => $mode,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function setPriority(int $conversationId, string $priority): bool
    {
        if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
            return false;
        }

        try {
            $this->conversationRepository->updatePriority($conversationId, $priority);
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to set priority', [
                'conversation_id' => $conversationId,
                'priority' => $priority,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function addTag(int $conversationId, string $tag): bool
    {
        try {
            $this->conversationRepository->addTag($conversationId, $tag);
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to add tag', [
                'conversation_id' => $conversationId,
                'tag' => $tag,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function removeTag(int $conversationId, string $tag): bool
    {
        try {
            $this->conversationRepository->removeTag($conversationId, $tag);
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to remove tag', [
                'conversation_id' => $conversationId,
                'tag' => $tag,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getUnassignedCount(): int
    {
        return $this->conversationRepository->getUnassignedCount();
    }

    public function getUnreadCount(): int
    {
        return $this->conversationRepository->getUnreadCount();
    }

    public function getAgentConversations(int $agentId, int $perPage = 20): LengthAwarePaginator
    {
        return $this->conversationRepository->getByAgent($agentId, $perPage);
    }
}
