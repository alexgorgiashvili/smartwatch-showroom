<div class="space-y-4">
    @forelse($messages as $message)
        <livewire:inbox.message-bubble 
            :message="$message" 
            wire:key="message-bubble-{{ $message->id }}"
        />
    @empty
        <div class="text-center py-8">
            <p class="text-gray-500 text-sm">No messages yet. Start the conversation!</p>
        </div>
    @endforelse
</div>
