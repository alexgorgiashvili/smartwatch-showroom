<div class="h-full bg-gray-900 dark:bg-gray-900">
    <div class="flex h-full min-h-[calc(100dvh-4rem)] max-h-none overflow-hidden rounded-none bg-gray-900 shadow-none dark:bg-gray-900 xl:h-[calc(100vh-200px)] xl:max-h-[800px] xl:min-h-0 xl:rounded-lg xl:shadow-lg">
        {{-- Left Sidebar: Conversations --}}
        <div class="w-full xl:w-2/3 border-r border-gray-200 dark:border-gray-700 {{ $showChatOnMobile ? 'hidden xl:flex' : 'flex' }} flex-col bg-gray-50 dark:bg-gray-800">
            {{-- Search Header --}}
            <div class="p-4 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                <div class="mb-3">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Inbox</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Omnichannel conversations</p>
                </div>
                <form class="flex items-center">
                    <label for="simple-search" class="sr-only">Search</label>
                    <div class="relative w-full">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z"/>
                            </svg>
                        </div>
                        <input type="text"
                               wire:model.live.debounce.500ms="searchQuery"
                               id="simple-search"
                               class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                               placeholder="Search conversations..."
                               required />
                    </div>
                </form>
            </div>

            {{-- Filter Tabs --}}
            <div class="border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                <ul class="flex flex-wrap -mb-px text-xs font-medium text-center text-gray-500 dark:text-gray-400">
                    <li class="flex-1">
                        <button wire:click="$set('statusFilter', 'all')"
                                class="inline-flex items-center justify-center p-3 w-full {{ $statusFilter === 'all' ? 'text-blue-600 border-b-2 border-blue-600 dark:text-blue-500 dark:border-blue-500' : 'border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300 dark:hover:text-gray-300' }}"
                                title="All Conversations">
                            <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 0a10 10 0 1 0 10 10A10.011 10.011 0 0 0 10 0Zm0 5a3 3 0 1 1 0 6 3 3 0 0 1 0-6Zm0 13a8.949 8.949 0 0 1-4.951-1.488A3.987 3.987 0 0 1 9 13h2a3.987 3.987 0 0 1 3.951 3.512A8.949 8.949 0 0 1 10 18Z"/>
                            </svg>
                            <span class="ml-1.5 text-xs">All</span>
                        </button>
                    </li>
                    <li class="flex-1">
                        <button wire:click="$set('statusFilter', 'active')"
                                class="inline-flex items-center justify-center p-3 w-full {{ $statusFilter === 'active' ? 'text-blue-600 border-b-2 border-blue-600 dark:text-blue-500 dark:border-blue-500' : 'border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300 dark:hover:text-gray-300' }}"
                                title="Active Conversations">
                            <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 18 18">
                                <path d="M6.143 0H1.857A1.857 1.857 0 0 0 0 1.857v4.286C0 7.169.831 8 1.857 8h4.286A1.857 1.857 0 0 0 8 6.143V1.857A1.857 1.857 0 0 0 6.143 0Zm10 0h-4.286A1.857 1.857 0 0 0 10 1.857v4.286C10 7.169 10.831 8 11.857 8h4.286A1.857 1.857 0 0 0 18 6.143V1.857A1.857 1.857 0 0 0 16.143 0Zm-10 10H1.857A1.857 1.857 0 0 0 0 11.857v4.286C0 17.169.831 18 1.857 18h4.286A1.857 1.857 0 0 0 8 16.143v-4.286A1.857 1.857 0 0 0 6.143 10Zm10 0h-4.286A1.857 1.857 0 0 0 10 11.857v4.286c0 1.026.831 1.857 1.857 1.857h4.286A1.857 1.857 0 0 0 18 16.143v-4.286A1.857 1.857 0 0 0 16.143 10Z"/>
                            </svg>
                            <span class="ml-1.5 text-xs">Active</span>
                        </button>
                    </li>
                    <li class="flex-1">
                        <button wire:click="$set('statusFilter', 'archived')"
                                class="inline-flex items-center justify-center p-3 w-full {{ $statusFilter === 'archived' ? 'text-blue-600 border-b-2 border-blue-600 dark:text-blue-500 dark:border-blue-500' : 'border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300 dark:hover:text-gray-300' }}"
                                title="Archived Conversations">
                            <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M5 20h10a1 1 0 0 0 1-1v-5H4v5a1 1 0 0 0 1 1Z"/>
                                <path d="M18 7H2a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2v-3a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2Z"/>
                                <path d="M10 3a1 1 0 0 0-1 1v2h2V4a1 1 0 0 0-1-1Z"/>
                            </svg>
                            <span class="ml-1.5 text-xs">Archived</span>
                        </button>
                    </li>
                </ul>
            </div>

            {{-- Platform Filters --}}
            <div class="p-2 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                <div class="flex gap-1">
                    <button wire:click="$set('platformFilter', 'all')"
                            class="flex-1 px-2 py-1.5 text-xs font-medium rounded {{ $platformFilter === 'all' || !$platformFilter ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300' }}">
                        All
                    </button>
                    <button wire:click="$set('platformFilter', 'instagram')"
                            class="flex-1 px-2 py-1.5 text-xs font-medium rounded {{ $platformFilter === 'instagram' ? 'bg-pink-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300' }}"
                            title="Instagram">
                        📷
                    </button>
                    <button wire:click="$set('platformFilter', 'facebook')"
                            class="flex-1 px-2 py-1.5 text-xs font-medium rounded {{ $platformFilter === 'facebook' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300' }}"
                            title="Messenger">
                        💬
                    </button>
                    <button wire:click="$set('platformFilter', 'whatsapp')"
                            class="flex-1 px-2 py-1.5 text-xs font-medium rounded {{ $platformFilter === 'whatsapp' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300' }}"
                            title="WhatsApp">
                        📱
                    </button>
                </div>
            </div>

            {{-- Conversations List --}}
            <div class="flex-1 overflow-y-auto chat-scroll">
                <livewire:inbox.conversation-feed
                    :selectedConversationId="$selectedConversationId"
                    :platformFilter="$platformFilter"
                    :statusFilter="$statusFilter"
                    :searchQuery="$searchQuery"
                    wire:key="conversation-feed-core"
                />
            </div>
        </div>

        {{-- Right: Chat Area --}}
        <div class="w-full xl:w-1/3 {{ $showChatOnMobile ? 'flex' : 'hidden xl:flex' }} flex-col">
            <livewire:inbox.chat-workspace
                :conversationId="$selectedConversationId"
                wire:key="chat-workspace-{{ $selectedConversationId }}"
            />
        </div>
    </div>
</div>
