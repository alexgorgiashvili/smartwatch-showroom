<div class="flex {{ $alignment }}">
    <div class="max-w-[75%]">
        {{-- Sender Label (for customer/bot/system messages) --}}
        @if($message->sender_type !== 'admin')
        <div class="text-xs text-gray-500 mb-1 px-1">
            {{ $senderLabel }}
        </div>
        @endif

        {{-- Message Bubble --}}
        <div class="rounded-2xl px-4 py-2 {{ $bubbleClasses }}">
            {{-- Message Content --}}
            @if($message->content)
            <p class="text-sm whitespace-pre-wrap break-words">{{ $message->content }}</p>
            @endif

            {{-- Media Attachment --}}
            @if($message->media_url)
            <div class="mt-2">
                @if($message->media_type === 'image')
                    <img 
                        src="{{ $message->media_url }}" 
                        alt="Image attachment"
                        class="rounded-lg max-w-full h-auto cursor-pointer hover:opacity-90 transition"
                        onclick="window.open('{{ $message->media_url }}', '_blank')"
                    />
                @elseif($message->media_type === 'video')
                    <video 
                        src="{{ $message->media_url }}" 
                        controls
                        class="rounded-lg max-w-full h-auto"
                    ></video>
                @elseif($message->media_type === 'audio')
                    <audio 
                        src="{{ $message->media_url }}" 
                        controls
                        class="w-full"
                    ></audio>
                @else
                    <a 
                        href="{{ $message->media_url }}" 
                        target="_blank"
                        class="flex items-center gap-2 text-sm hover:underline"
                    >
                        <x-filament::icon icon="heroicon-o-paper-clip" class="w-4 h-4" />
                        <span>View attachment</span>
                    </a>
                @endif
            </div>
            @endif

            {{-- Timestamp & Status --}}
            <div class="flex items-center gap-2 mt-1">
                <span class="text-xs {{ $message->sender_type === 'admin' ? 'text-violet-200' : 'text-gray-500' }}">
                    {{ $message->created_at->format('g:i A') }}
                </span>

                {{-- Delivery Status (for admin messages) --}}
                @if($message->sender_type === 'admin')
                    @if($message->delivery_status === 'sent')
                        <x-filament::icon icon="heroicon-o-check" class="w-3 h-3 text-violet-200" />
                    @elseif($message->delivery_status === 'delivered')
                        <x-filament::icon icon="heroicon-o-check-circle" class="w-3 h-3 text-violet-200" />
                    @elseif($message->delivery_status === 'failed')
                        <x-filament::icon icon="heroicon-o-exclamation-circle" class="w-3 h-3 text-red-300" />
                    @endif
                @endif
            </div>
        </div>

        {{-- Timestamp (for admin messages, shown below) --}}
        @if($message->sender_type === 'admin')
        <div class="text-xs text-gray-500 mt-1 px-1 text-right">
            You
        </div>
        @endif
    </div>
</div>
