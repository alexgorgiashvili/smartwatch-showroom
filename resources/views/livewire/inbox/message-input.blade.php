<div class="border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-3 sm:p-4">
    {{-- Attachment Preview --}}
    @if($attachment)
    <div class="mb-3 flex items-center p-3 text-sm text-blue-800 border border-blue-300 rounded-xl bg-gradient-to-r from-blue-50 to-blue-100 dark:bg-gray-800 dark:text-blue-400 dark:border-blue-800 shadow-sm animate-fade-in">
        <svg class="flex-shrink-0 inline w-4 h-4 mr-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
            <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z"/>
        </svg>
        <span class="sr-only">Info</span>
        <div class="flex-1">
            <span class="font-medium">Attachment ready:</span> {{ $attachment->getClientOriginalName() }}
        </div>
        <button type="button" wire:click="$set('attachment', null)" class="ml-auto -mx-1.5 -my-1.5 bg-blue-50 text-blue-500 rounded-lg focus:ring-2 focus:ring-blue-400 p-1.5 hover:bg-blue-200 inline-flex items-center justify-center h-8 w-8 dark:bg-gray-800 dark:text-blue-400 dark:hover:bg-gray-700 transition-colors">
            <span class="sr-only">Close</span>
            <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
            </svg>
        </button>
    </div>
    @endif

    <form wire:submit.prevent="sendMessage">
        <label for="chat" class="sr-only">Your message</label>
        <div class="flex items-center px-3 py-2 rounded-2xl bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 focus-within:border-violet-400 focus-within:ring-2 focus-within:ring-violet-100 transition-all">
            {{-- Attachment Button --}}
            <label class="inline-flex justify-center p-2 text-gray-500 rounded-lg cursor-pointer hover:text-violet-600 hover:bg-violet-50 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-600 transition-colors">
                <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 18">
                    <path fill="currentColor" d="M13 5.5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0ZM7.565 7.423 4.5 14h11.518l-2.516-3.71L11 13 7.565 7.423Z"/>
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 1H2a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1Z"/>
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5.5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0ZM7.565 7.423 4.5 14h11.518l-2.516-3.71L11 13 7.565 7.423Z"/>
                </svg>
                <span class="sr-only">Upload image</span>
                <input type="file" wire:model="attachment" class="hidden" accept="image/*,video/*,.pdf,.doc,.docx">
            </label>

            {{-- AI Assistant Button --}}
            <button type="button" wire:click="$dispatch('open-ai-suggestions')" class="p-2 text-gray-500 rounded-lg cursor-pointer hover:text-violet-600 hover:bg-violet-50 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-600 transition-colors" title="AI Assistant">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                <span class="sr-only">AI Assistant</span>
            </button>

            {{-- Text Input --}}
            <textarea id="chat"
                      rows="1"
                      wire:model="content"
                      wire:keydown="handleTyping"
                      @keydown.enter.prevent="if(!$event.shiftKey) { $wire.sendMessage(); $event.target.value = ''; }"
                      class="block mx-3 sm:mx-4 p-2.5 w-full text-sm text-gray-900 bg-transparent border-0 focus:ring-0 focus:outline-none dark:placeholder-gray-400 dark:text-white resize-none"
                      placeholder="Type a message..."></textarea>

            {{-- Send Button --}}
            <button type="submit"
                    wire:click="sendMessage"
                    wire:loading.attr="disabled"
                    :disabled="!$wire.content?.trim()"
                    class="inline-flex justify-center p-2 text-white bg-violet-600 rounded-full cursor-pointer hover:bg-violet-700 dark:bg-violet-500 dark:hover:bg-violet-600 disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-gray-400 transition-all shadow-sm hover:shadow-md">
                <svg wire:loading.remove wire:target="sendMessage" class="w-5 h-5 rotate-90 rtl:-rotate-90" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 18 20">
                    <path d="m17.914 18.594-8-18a1 1 0 0 0-1.828 0l-8 18a1 1 0 0 0 1.157 1.376L8 18.281V9a1 1 0 0 1 2 0v9.281l6.758 1.689a1 1 0 0 0 1.156-1.376Z"/>
                </svg>
                <svg wire:loading wire:target="sendMessage" class="w-5 h-5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="sr-only">Send message</span>
            </button>
        </div>
    </form>
</div>
