<?php

namespace App\Livewire\Inbox;

use App\Events\MessageReceived;
use App\Events\AgentTyping;
use App\Models\Conversation;
use App\Services\AiSuggestionService;
use App\Services\CollisionDetectionService;
use App\Services\OmnichannelService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class ReplyForm extends Component
{
    #[Reactive]
    public ?int $conversationId = null;

    public string $message = '';

    public array $suggestions = [];

    public bool $isSending = false;

    public bool $isSuggesting = false;

    public bool $isTyping = false;

    public ?string $typingWarning = null;

    public function sendMessage(OmnichannelService $omnichannelService, CollisionDetectionService $collisionService): void
    {
        if (! $this->conversationId || $this->isSending) {
            return;
        }

        $validated = $this->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $conversation = Conversation::query()
            ->with('customer')
            ->find($this->conversationId);

        if (! $conversation) {
            Notification::make()
                ->title('Conversation not found.')
                ->danger()
                ->send();

            return;
        }

        // Check for collision
        $agentIdentifier = $collisionService::generateAgentIdentifier(Auth::id());
        $check = $collisionService->checkBeforeSend($conversation, $agentIdentifier);

        if (!$check['can_send']) {
            Notification::make()
                ->title($check['warning'])
                ->warning()
                ->send();

            if ($check['conflict_with']) {
                $this->typingWarning = "{$check['conflict_with']['name']} is also replying to this conversation";
            }

            return;
        }

        $this->isSending = true;

        $message = $omnichannelService->sendReply(
            $conversation->id,
            (int) Auth::id(),
            trim($validated['message'])
        );

        $this->isSending = false;

        if (! $message) {
            Notification::make()
                ->title('Failed to send message.')
                ->danger()
                ->send();

            return;
        }

        // Clear collision detection
        $collisionService->clearReplyingStatus($conversation, $agentIdentifier);

        event(new MessageReceived(
            $message->fresh(),
            $conversation->fresh(),
            $conversation->customer,
            $conversation->platform
        ));

        $this->reset('message', 'suggestions', 'typingWarning');

        $this->dispatch('message-sent', conversationId: $conversation->id);
        $this->dispatch('conversation-updated', conversationId: $conversation->id);
    }

    public function suggestAi(AiSuggestionService $aiSuggestionService): void
    {
        if (! $this->conversationId || $this->isSuggesting) {
            return;
        }

        $conversation = Conversation::query()
            ->with('customer')
            ->find($this->conversationId);

        if (! $conversation) {
            return;
        }

        $latestCustomerMessage = $conversation->messages()
            ->where('sender_type', 'customer')
            ->latest('created_at')
            ->first();

        if (! $latestCustomerMessage) {
            Notification::make()
                ->title('No customer message found for AI suggestions.')
                ->warning()
                ->send();

            return;
        }

        $this->isSuggesting = true;

        $suggestions = $aiSuggestionService->generateSuggestions(
            $conversation,
            $latestCustomerMessage,
            3
        );

        $this->isSuggesting = false;

        if (! $suggestions) {
            Notification::make()
                ->title('AI suggestions could not be generated.')
                ->warning()
                ->send();

            return;
        }

        $this->suggestions = array_values($suggestions);
    }

    public function useSuggestion(string $suggestion): void
    {
        $this->message = $suggestion;
        $this->suggestions = [];
    }

    public function updatedMessage(): void
    {
        if (empty(trim($this->message))) {
            $this->stopTyping();
            return;
        }

        if (!$this->isTyping && $this->conversationId) {
            $this->startTyping();
        }
    }

    public function startTyping(): void
    {
        if (!$this->conversationId) {
            return;
        }

        $this->isTyping = true;

        // Mark as replying in collision detection
        $agentIdentifier = CollisionDetectionService::generateAgentIdentifier(Auth::id());
        $conversation = Conversation::find($this->conversationId);

        if ($conversation) {
            app(CollisionDetectionService::class)->markAsReplying($conversation, $agentIdentifier);
        }

        // Broadcast typing event
        broadcast(new AgentTyping(
            $this->conversationId,
            Auth::id(),
            Auth::user()->name,
            true
        ));
    }

    public function stopTyping(): void
    {
        if (!$this->isTyping) {
            return;
        }

        $this->isTyping = false;

        // Clear replying status
        if ($this->conversationId) {
            $agentIdentifier = CollisionDetectionService::generateAgentIdentifier(Auth::id());
            $conversation = Conversation::find($this->conversationId);

            if ($conversation) {
                app(CollisionDetectionService::class)->clearReplyingStatus($conversation, $agentIdentifier);
            }

            // Broadcast stop typing event
            broadcast(new AgentTyping(
                $this->conversationId,
                Auth::id(),
                Auth::user()->name,
                false
            ));
        }
    }

    public function render()
    {
        return view('livewire.inbox.reply-form');
    }
}
