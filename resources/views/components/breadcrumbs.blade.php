{{-- Breadcrumb Navigation with Schema.org markup --}}
<nav aria-label="Breadcrumb" class="mb-6">
    <ol class="flex items-center gap-2 text-sm text-gray-500">
        @foreach($items as $index => $item)
        <li class="flex items-center gap-2">
            @if(isset($item['url']) && !$loop->last)
            <a href="{{ $item['url'] }}" class="hover:text-primary-600 transition-colors">
                {{ $item['name'] }}
            </a>
            @else
            <span class="text-gray-700 font-medium">{{ $item['name'] }}</span>
            @endif

            @if(!$loop->last)
            <i class="fa-solid fa-chevron-right text-[10px] text-gray-400"></i>
            @endif
        </li>
        @endforeach
    </ol>
</nav>

{{-- JSON-LD Schema --}}
<script type="application/ld+json">{!! $schema() !!}</script>
