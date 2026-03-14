<div>
    @forelse($conversations as $conversation)
        <div wire:click="selectConversation({{ $conversation->id }})"
             class="flex items-center gap-4 p-4 border-b border-gray-200 dark:border-gray-700 cursor-pointer transition-all hover:bg-gray-100 dark:hover:bg-gray-700 {{ $selectedConversationId === $conversation->id ? 'bg-blue-50 dark:bg-blue-900/20 border-l-4 border-l-blue-600' : '' }}">
            {{-- Avatar with Status --}}
            <div class="relative flex-shrink-0">
                @if($conversation->customer->avatar_url)
                    <img class="w-12 h-12 rounded-full" src="{{ $conversation->customer->avatar_url }}" alt="{{ $conversation->customer->name }}">
                @else
                    <div class="relative inline-flex items-center justify-center w-12 h-12 overflow-hidden bg-gradient-to-br from-blue-500 to-purple-600 rounded-full">
                        <span class="font-semibold text-white">{{ strtoupper(substr($conversation->customer->name, 0, 1)) }}</span>
                    </div>
                @endif
                <span class="absolute bottom-0 right-0 block h-3.5 w-3.5 rounded-full ring-2 ring-white {{ $conversation->customer->is_online ?? false ? 'bg-green-500' : 'bg-gray-400' }}"></span>
            </div>

            {{-- Content --}}
            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between mb-1">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate {{ $conversation->unread_count > 0 ? 'font-bold' : '' }}">
                        {{ $conversation->customer->name }}
                    </p>
                    <time class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $conversation->last_message_at?->format('g:i A') ?? 'Now' }}
                    </time>
                </div>
                <div class="flex items-center justify-between">
                    @if($conversation->latestMessage)
                        <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                            @if($conversation->latestMessage->sender_type === 'admin')
                                <span class="text-blue-600 dark:text-blue-400 font-medium">You: </span>
                            @endif
                            {{ Str::limit($conversation->latestMessage->content ?: '📎 Attachment', 35) }}
                        </p>
                    @else
                        <p class="text-sm text-gray-400 dark:text-gray-500 italic">No messages yet</p>
                    @endif

                    @if($conversation->unread_count > 0)
                        <span class="inline-flex items-center justify-center w-5 h-5 ml-2 text-xs font-semibold text-white bg-blue-600 rounded-full">
                            {{ $conversation->unread_count }}
                        </span>
                    @endif
                </div>

                {{-- Platform Badge --}}
                <div class="mt-1">
                    @if($conversation->platform === 'instagram')
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium text-pink-800 bg-pink-100 rounded dark:bg-pink-900 dark:text-pink-300">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                            Instagram
                        </span>
                    @elseif($conversation->platform === 'facebook' || $conversation->platform === 'messenger')
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium text-blue-800 bg-blue-100 rounded dark:bg-blue-900 dark:text-blue-300">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            Messenger
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium text-gray-800 bg-gray-100 rounded dark:bg-gray-700 dark:text-gray-300">
                            {{ ucfirst($conversation->platform) }}
                        </span>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="flex flex-col items-center justify-center py-12 px-4">
            <svg class="w-16 h-16 text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
            </svg>
            <h3 class="mb-2 text-sm font-medium text-gray-900 dark:text-white">No conversations found</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">Try adjusting your search or filters</p>
        </div>
    @endforelse

    {{-- Pagination --}}
    @if($conversations->hasPages())
    <div class="p-4 border-t border-gray-200 dark:border-gray-700">
        {{ $conversations->links() }}
    </div>
    @endif
</div>
