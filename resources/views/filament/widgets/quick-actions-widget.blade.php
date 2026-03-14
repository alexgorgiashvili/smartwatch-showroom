<x-filament-widgets::widget class="fi-quick-actions-widget">
    <x-filament::section>
        <x-slot name="heading">
            Quick Actions
        </x-slot>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
            @foreach ($actions as $action)
                <x-filament::button
                    :color="$action['color']"
                    :href="$action['url']"
                    :icon="$action['icon']"
                    tag="a"
                    class="justify-start"
                >
                    {{ $action['label'] }}
                </x-filament::button>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
