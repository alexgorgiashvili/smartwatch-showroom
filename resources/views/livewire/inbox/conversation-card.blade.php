<div 
    wire:click="selectConversation"
    class="p-4 border-b border-gray-200 hover:bg-gray-50 cursor-pointer transition {{ $isSelected ? 'bg-violet-50 border-l-4 border-l-violet-600' : '' }}"
>
    <div class="flex items-start gap-3">
        {{-- Avatar --}}
        <div class="relative flex-shrink-0">
            @if($conversation->customer->avatar_url)
                <img 
                    src="{{ $conversation->customer->avatar_url }}" 
                    alt="{{ $conversation->customer->name }}"
                    class="w-12 h-12 rounded-full object-cover"
                />
            @else
                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-violet-400 to-purple-600 flex items-center justify-center text-white font-semibold text-lg">
                    {{ substr($conversation->customer->name, 0, 1) }}
                </div>
            @endif
            
            {{-- Platform Badge --}}
            <div class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full bg-white flex items-center justify-center shadow-sm">
                <x-filament::icon 
                    :icon="'heroicon-o-' . $platformIcon" 
                    class="w-3 h-3 {{ $platformColor }}" 
                />
            </div>
        </div>

        {{-- Content --}}
        <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-2 mb-1">
                <h4 class="font-semibold text-gray-900 truncate {{ $conversation->unread_count > 0 ? 'font-bold' : '' }}">
                    {{ $conversation->customer->name }}
                </h4>
                <span class="text-xs text-gray-500 flex-shrink-0">
                    {{ $conversation->last_message_at?->diffForHumans() ?? 'Just now' }}
                </span>
            </div>

            @if($conversation->latestMessage)
            <p class="text-sm text-gray-600 truncate {{ $conversation->unread_count > 0 ? 'font-semibold' : '' }}">
                @if($conversation->latestMessage->sender_type === 'admin')
                    <span class="text-violet-600">You:</span>
                @elseif($conversation->latestMessage->sender_type === 'bot')
                    <span class="text-emerald-600">Bot:</span>
                @endif
                {{ $conversation->latestMessage->content ?: '📎 Attachment' }}
            </p>
            @endif

            {{-- Metadata --}}
            <div class="flex items-center gap-2 mt-2">
                @if($conversation->unread_count > 0)
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-violet-100 text-violet-800">
                    {{ $conversation->unread_count }} new
                </span>
                @endif

                @if($conversation->priority === 'urgent')
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                    Urgent
                </span>
                @elseif($conversation->priority === 'high')
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                    High
                </span>
                @endif

                @if($conversation->ai_mode === 'auto')
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                    🤖 AI
                </span>
                @endif

                @if($conversation->assignedAgent)
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    {{ $conversation->assignedAgent->user->name }}
                </span>
                @endif
            </div>
        </div>
    </div>
</div>
