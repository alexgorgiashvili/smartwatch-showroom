<div class="p-4 border-b border-gray-200 bg-white">
    @if($conversation)
    <div class="flex items-center justify-between">
        {{-- Customer Info --}}
        <div class="flex items-center gap-3">
            {{-- Back Button (Mobile) --}}
            <button 
                wire:click="closeChat"
                class="lg:hidden p-2 hover:bg-gray-100 rounded-lg transition"
            >
                <x-filament::icon icon="heroicon-o-arrow-left" class="w-5 h-5 text-gray-600" />
            </button>

            {{-- Avatar --}}
            @if($conversation->customer->avatar_url)
                <img 
                    src="{{ $conversation->customer->avatar_url }}" 
                    alt="{{ $conversation->customer->name }}"
                    class="w-10 h-10 rounded-full object-cover"
                />
            @else
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-violet-400 to-purple-600 flex items-center justify-center text-white font-semibold">
                    {{ substr($conversation->customer->name, 0, 1) }}
                </div>
            @endif

            <div>
                <h3 class="font-semibold text-gray-900">{{ $conversation->customer->name }}</h3>
                <p class="text-xs text-gray-500">{{ $conversation->getPlatformLabel() }}</p>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-2">
            {{-- AI Toggle --}}
            <button 
                wire:click="$parent.toggleAi"
                class="p-2 rounded-lg transition {{ $conversation->ai_mode === 'auto' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}"
                title="{{ $conversation->ai_mode === 'auto' ? 'AI Enabled' : 'AI Disabled' }}"
            >
                <x-filament::icon icon="heroicon-o-cpu-chip" class="w-5 h-5" />
            </button>

            {{-- Priority Dropdown --}}
            <div x-data="{ open: false }" class="relative">
                <button 
                    @click="open = !open"
                    class="p-2 rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 transition"
                >
                    <x-filament::icon icon="heroicon-o-flag" class="w-5 h-5" />
                </button>

                <div 
                    x-show="open" 
                    @click.away="open = false"
                    x-transition
                    class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-10"
                >
                    <button wire:click="$parent.setPriority('low')" class="w-full px-4 py-2 text-left text-sm hover:bg-gray-50">Low Priority</button>
                    <button wire:click="$parent.setPriority('normal')" class="w-full px-4 py-2 text-left text-sm hover:bg-gray-50">Normal</button>
                    <button wire:click="$parent.setPriority('high')" class="w-full px-4 py-2 text-left text-sm hover:bg-gray-50">High Priority</button>
                    <button wire:click="$parent.setPriority('urgent')" class="w-full px-4 py-2 text-left text-sm hover:bg-gray-50 text-red-600">Urgent</button>
                </div>
            </div>

            {{-- Status Dropdown --}}
            <div x-data="{ open: false }" class="relative">
                <button 
                    @click="open = !open"
                    class="p-2 rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 transition"
                >
                    <x-filament::icon icon="heroicon-o-ellipsis-vertical" class="w-5 h-5" />
                </button>

                <div 
                    x-show="open" 
                    @click.away="open = false"
                    x-transition
                    class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-10"
                >
                    @if(!$conversation->assignedAgent)
                    <button wire:click="assignToMe" class="w-full px-4 py-2 text-left text-sm hover:bg-gray-50">Assign to Me</button>
                    @else
                    <button wire:click="unassign" class="w-full px-4 py-2 text-left text-sm hover:bg-gray-50">Unassign</button>
                    @endif
                    <button wire:click="$parent.updateStatus('archived')" class="w-full px-4 py-2 text-left text-sm hover:bg-gray-50">Archive</button>
                    <button wire:click="$parent.updateStatus('closed')" class="w-full px-4 py-2 text-left text-sm hover:bg-gray-50">Close</button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
