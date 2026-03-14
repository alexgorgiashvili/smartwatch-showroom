<?php

namespace App\Livewire\Inbox;

use App\Events\AgentTypingStarted;
use App\Events\AgentTypingStopped;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Repositories\ConversationRepository;
use App\Services\Business\MessageDispatcher;
use App\Services\Platform\InstagramApiService;
use App\Services\Platform\MessengerApiService;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use Livewire\WithFileUploads;

class MessageInput extends Component
{
    use WithFileUploads;

    #[Reactive]
    public ?int $conversationId = null;

    public string $content = '';
    public $attachment = null;
    public bool $isTyping = false;
    public bool $isSending = false;

    protected $listeners = [
        'clear-input' => 'clearInput',
        'use-suggestion' => 'useSuggestion',
    ];

    public function handleTyping(): void
    {
        // This method is called by wire:keydown in the view
        // The actual typing logic is in updatedContent()
    }

    public function updatedContent(): void
    {
        if (!$this->conversationId) {
            return;
        }

        if (trim($this->content) !== '' && !$this->isTyping) {
            $this->isTyping = true;
            AgentTypingStarted::dispatch(
                $this->conversationId,
                auth()->id(),
                auth()->user()->name
            );
        } elseif (trim($this->content) === '' && $this->isTyping) {
            $this->isTyping = false;
            AgentTypingStopped::dispatch($this->conversationId, auth()->id());
        }
    }

    public function sendMessage(): void
    {
        if (!$this->conversationId || trim($this->content) === '') {
            return;
        }

        $this->isSending = true;

        try {
            $conversation = app(ConversationRepository::class)->findForChat($this->conversationId);

            if (!$conversation) {
                $this->isSending = false;
                return;
            }

            $mediaUrl = null;
            if ($this->attachment) {
                $mediaUrl = $this->attachment->store('message-attachments', 'public');
                $mediaUrl = asset('storage/' . $mediaUrl);
            }

            $message = app(MessageDispatcher::class)->sendOutgoingMessage(
                $conversation,
                trim($this->content),
                $mediaUrl,
                auth()->user(),
                'admin'
            );

            if ($message) {
                $this->sendToPlatform($conversation, $message);

                MessageSent::dispatch(
                    $message,
                    $conversation,
                    auth()->user(),
                    $conversation->platform
                );

                $this->dispatch('message-sent', conversationId: $this->conversationId);
                $this->dispatch('conversation-updated');
            }

            $this->clearInput();
        } catch (\Exception $e) {
            Log::error('Failed to send message', [
                'conversation_id' => $this->conversationId,
                'error' => $e->getMessage(),
            ]);
        } finally {
            $this->isSending = false;

            if ($this->isTyping) {
                $this->isTyping = false;
                AgentTypingStopped::dispatch($this->conversationId, auth()->id());
            }
        }
    }

    protected function sendToPlatform(Conversation $conversation, $message): void
    {
        $platformConversationId = (string) $conversation->platform_conversation_id;
        $recipientPlatformId = (string) ($conversation->customer?->platform_id ?? '');

        if ($recipientPlatformId === '') {
            $recipientPlatformId = preg_replace('/^(ig_|fb_)/', '', $platformConversationId) ?? $platformConversationId;
        }

        $messageContent = $message->content;
        $mediaUrl = $message->media_url;

        try {
            $result = match ($conversation->platform) {
                'instagram' => app(InstagramApiService::class)->sendMessage($recipientPlatformId, $messageContent, $mediaUrl),
                'facebook', 'messenger' => app(MessengerApiService::class)->sendMessage($recipientPlatformId, $messageContent, $mediaUrl),
                'whatsapp' => app(WhatsAppService::class)->sendMessage($platformConversationId, $conversation->platform_conversation_id, $messageContent, $mediaUrl),
                default => ['success' => false, 'error' => 'unsupported_platform'],
            };

            if ($result['success'] ?? false) {
                $message->updateDeliveryStatus('sent');
            } else {
                $message->updateDeliveryStatus('failed');
                Log::error('Platform send failed', [
                    'platform' => $conversation->platform,
                    'result' => $result,
                ]);
            }
        } catch (\Exception $e) {
            $message->updateDeliveryStatus('failed');
            Log::error('Platform send exception', [
                'platform' => $conversation->platform,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function useSuggestion(string $suggestion): void
    {
        $this->content = $suggestion;
        $this->dispatch('suggestion-used');
    }

    public function clearInput(): void
    {
        $this->content = '';
        $this->attachment = null;
    }

    public function render()
    {
        return view('livewire.inbox.message-input');
    }
}
