<div class="flex flex-col h-full max-h-full overflow-hidden">
    @if($conversation)
    {{-- Chat Header --}}
    <div class="flex items-center justify-between px-4 py-3 sm:px-6 sm:py-4 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm">
        <div class="flex items-center gap-3">
            {{-- Back button for mobile --}}
            <button wire:click="$dispatch('conversation-closed')"
                    class="xl:hidden inline-flex items-center p-2 text-sm text-gray-500 rounded-lg hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-200 dark:text-gray-400 dark:hover:bg-gray-700 dark:focus:ring-gray-600 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </button>

            {{-- Avatar --}}
            <div class="relative flex-shrink-0">
                @if($conversation->customer->avatar_url)
                    <img class="w-10 h-10 sm:w-12 sm:h-12 rounded-full object-cover" src="{{ $conversation->customer->avatar_url }}" alt="{{ $conversation->customer->name }}">
                @else
                    <div class="relative inline-flex items-center justify-center w-10 h-10 sm:w-12 sm:h-12 overflow-hidden bg-gradient-to-br from-blue-500 to-purple-600 rounded-full">
                        <span class="text-sm sm:text-base font-semibold text-white">{{ strtoupper(substr($conversation->customer->name, 0, 1)) }}</span>
                    </div>
                @endif
                <span class="absolute bottom-0 right-0 block h-3 w-3 rounded-full ring-2 ring-white bg-green-500 animate-pulse"></span>
            </div>

            {{-- Name & Status --}}
            <div class="min-w-0 flex-1">
                <h3 class="text-sm sm:text-base font-semibold text-gray-900 dark:text-white truncate">{{ $conversation->customer->name }}</h3>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 flex items-center gap-1">
                    <span class="inline-flex items-center">
                        <span class="w-2 h-2 bg-green-500 rounded-full mr-1.5"></span>
                        Active now
                    </span>
                    <span class="hidden sm:inline">• {{ ucfirst($conversation->platform) }}</span>
                </p>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-1 sm:gap-2">
            <button wire:click="toggleAi"
                    type="button"
                    title="{{ $conversation->ai_mode === 'auto' ? 'AI Enabled' : 'AI Disabled' }}"
                    class="inline-flex items-center p-2 text-sm font-medium text-center {{ $conversation->ai_mode === 'auto' ? 'text-green-600' : 'text-gray-500' }} rounded-lg hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-50 dark:hover:bg-gray-700 dark:focus:ring-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
            </button>

            <button id="dropdownMenuIconButton" data-dropdown-toggle="dropdownDots"
                    class="inline-flex items-center p-2 text-sm font-medium text-center text-gray-900 bg-white rounded-lg hover:bg-gray-100 focus:ring-4 focus:outline-none dark:text-white focus:ring-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700 dark:focus:ring-gray-600"
                    type="button">
                <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 4 15">
                    <path d="M3.5 1.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm0 6.041a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm0 5.959a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z"/>
                </svg>
            </button>
            <div id="dropdownDots" class="z-10 hidden bg-white divide-y divide-gray-100 rounded-lg shadow w-44 dark:bg-gray-700 dark:divide-gray-600">
                <ul class="py-2 text-sm text-gray-700 dark:text-gray-200">
                    <li>
                        <a href="javascript:;" wire:click="updateStatus('archived')" class="block px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 dark:hover:text-white">Archive</a>
                    </li>
                    <li>
                        <a href="javascript:;" wire:click="updateStatus('closed')" class="block px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 dark:hover:text-white">Close</a>
                    </li>
                    <li>
                        <a href="javascript:;" wire:click="setPriority('urgent')" class="block px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 dark:hover:text-white">Mark Urgent</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    {{-- Messages Area --}}
    <div class="flex-1 overflow-y-auto overflow-x-hidden p-3 sm:p-4 md:p-6 space-y-3 sm:space-y-4 bg-gradient-to-b from-gray-50 to-white dark:from-gray-800 dark:to-gray-900 chat-scroll"
         x-data="{
            threshold: 220,
            isNearBottom() {
                return this.$el.scrollTop + this.$el.clientHeight + this.threshold >= this.$el.scrollHeight;
            },
            scrollToBottom(force = false, smooth = false) {
                if (force || this.isNearBottom()) {
                    this.$refs.bottomAnchor?.scrollIntoView({
                        block: 'end',
                        behavior: smooth ? 'smooth' : 'auto',
                    });
                }
            }
         }"
         x-init="
            const jump = () => scrollToBottom(true, false);
            jump();
            $nextTick(jump);
            setTimeout(jump, 80);
            setTimeout(jump, 220);
            setTimeout(jump, 420);
            setTimeout(jump, 800);
         "
         @scroll-to-bottom.window="$nextTick(() => scrollToBottom(true, false))"
         @message-received.window="$nextTick(() => scrollToBottom(false, false))">
        @foreach($messages as $message)
            <div wire:key="chat-message-{{ $message->id }}">
                @if($message->sender_type === 'customer')
                    {{-- Customer Message --}}
                    <div class="flex min-w-0 items-start gap-2 sm:gap-2.5 animate-fade-in">
                        @if($conversation->customer->avatar_url)
                            <img class="w-7 h-7 sm:w-8 sm:h-8 rounded-full object-cover flex-shrink-0" src="{{ $conversation->customer->avatar_url }}" alt="{{ $conversation->customer->name }}">
                        @else
                            <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center flex-shrink-0">
                                <span class="text-xs font-semibold text-white">{{ strtoupper(substr($conversation->customer->name, 0, 1)) }}</span>
                            </div>
                        @endif
                        <div class="flex min-w-0 flex-col gap-1 max-w-[85%] sm:max-w-[75%] md:max-w-[65%]">
                            <div class="flex items-center space-x-2">
                                <span class="text-xs sm:text-sm font-semibold text-gray-900 dark:text-white">{{ $conversation->customer->name }}</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $message->created_at->format('g:i A') }}</span>
                            </div>
                            <div class="flex min-w-0 max-w-full flex-col leading-relaxed p-3 sm:p-4 bg-white rounded-2xl rounded-tl-sm shadow-sm border border-gray-100 dark:bg-gray-700 dark:border-gray-600 transition-shadow hover:shadow-md overflow-hidden">
                                @if($message->content)
                                    <p style="white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-all;" class="chat-message-content w-full min-w-0 text-sm sm:text-base font-normal text-gray-900 dark:text-white">{{ $message->content }}</p>
                                @endif
                                @if($message->media_url)
                                    <div class="mt-2">
                                        @if($message->media_type === 'image')
                                            <img src="{{ $message->media_url }}" loading="lazy" decoding="async" class="rounded-lg max-w-full h-auto" alt="Image">
                                        @else
                                            <a href="{{ $message->media_url }}" target="_blank" class="flex items-center gap-2 text-blue-600 hover:underline">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                                </svg>
                                                <span class="text-sm">View attachment</span>
                                            </a>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @else
                    {{-- Admin Message --}}
                    <div class="flex min-w-0 items-start gap-2 sm:gap-2.5 justify-end animate-fade-in">
                        <div class="flex min-w-0 flex-col gap-1 max-w-[85%] sm:max-w-[75%] md:max-w-[65%]">
                            <div class="flex items-center justify-end space-x-2">
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $message->created_at->format('g:i A') }}</span>
                                <span class="text-xs sm:text-sm font-semibold text-gray-900 dark:text-white">You</span>
                            </div>
                            <div class="flex min-w-0 max-w-full flex-col leading-relaxed p-3 sm:p-4 bg-gradient-to-br from-blue-600 to-blue-700 rounded-2xl rounded-tr-sm shadow-md overflow-hidden">
                                @if($message->content)
                                    <p style="white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-all;" class="chat-message-content w-full min-w-0 text-sm sm:text-base font-normal text-white">{{ $message->content }}</p>
                                @endif
                                @if($message->media_url)
                                    <div class="mt-2">
                                        @if($message->media_type === 'image')
                                            <img src="{{ $message->media_url }}" loading="lazy" decoding="async" class="rounded-lg max-w-full h-auto" alt="Image">
                                        @else
                                            <a href="{{ $message->media_url }}" target="_blank" class="flex items-center gap-2 text-white hover:underline">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                                </svg>
                                                <span class="text-sm">View attachment</span>
                                            </a>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                        @if(auth()->user()->avatar)
                            <img class="w-7 h-7 sm:w-8 sm:h-8 rounded-full object-cover flex-shrink-0" src="{{ auth()->user()->avatar }}" alt="You">
                        @else
                            <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center flex-shrink-0">
                                <span class="text-xs font-semibold text-white">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach

        {{-- Typing Indicator --}}
        @if($typingAgents && count($typingAgents) > 0)
        <div class="flex items-center gap-2 sm:gap-2.5 animate-fade-in">
            <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                </svg>
            </div>
            <div class="flex items-center gap-1">
                <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></span>
                <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
            </div>
            <span class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">{{ implode(', ', $typingAgents) }} {{ count($typingAgents) > 1 ? 'are' : 'is' }} typing...</span>
        </div>
        @endif
        <div x-ref="bottomAnchor" class="h-px w-full"></div>
    </div>

    {{-- Message Input --}}
    <div class="flex-shrink-0 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
        <livewire:inbox.message-input :conversationId="$conversationId" wire:key="message-input-{{ $conversationId }}" />
    </div>
    @else
    {{-- Empty State --}}
    <div class="flex items-center justify-center h-full bg-gradient-to-b from-gray-50 to-white dark:from-gray-800 dark:to-gray-900">
        <div class="text-center px-4">
            <svg class="w-20 h-20 sm:w-24 sm:h-24 text-gray-300 dark:text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
            </svg>
            <h4 class="text-lg sm:text-xl font-semibold text-gray-900 dark:text-white mb-2">Select a conversation</h4>
            <p class="text-sm sm:text-base text-gray-500 dark:text-gray-400">Choose a conversation to start messaging</p>
        </div>
    </div>
    @endif
</div>
