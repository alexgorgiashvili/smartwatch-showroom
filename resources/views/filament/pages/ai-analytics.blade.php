<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Statistics Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            @php
                $stats = $this->getStats();
            @endphp

            <x-filament::card>
                <div class="text-center">
                    <div class="text-3xl font-bold text-primary-600 dark:text-primary-400">{{ number_format($stats['total_visits']) }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-300 mt-1">სულ ვიზიტები</div>
                </div>
            </x-filament::card>

            <x-filament::card>
                <div class="text-center">
                    <div class="text-3xl font-bold text-success-600 dark:text-success-400">{{ number_format($stats['today_visits']) }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-300 mt-1">დღეს</div>
                </div>
            </x-filament::card>

            <x-filament::card>
                <div class="text-center">
                    <div class="text-3xl font-bold text-warning-600 dark:text-warning-400">{{ number_format($stats['week_visits']) }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-300 mt-1">ბოლო კვირა</div>
                </div>
            </x-filament::card>

            <x-filament::card>
                <div class="text-center">
                    <div class="text-3xl font-bold text-info-600 dark:text-info-400">{{ number_format($stats['month_visits']) }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-300 mt-1">ბოლო თვე</div>
                </div>
            </x-filament::card>
        </div>

        {{-- AI Families Chart --}}
        <x-filament::card>
            <h3 class="text-lg font-semibold mb-4">AI ოჯახების მიხედვით</h3>
            <div class="space-y-2">
                @php
                    $families = $this->getVisitsByFamily();
                    $maxCount = max($families) ?: 1;
                @endphp

                @foreach($families as $family => $count)
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="font-medium dark:text-gray-100">{{ $family }}</span>
                            <span class="text-gray-600 dark:text-gray-300">{{ number_format($count) }}</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div class="bg-primary-600 dark:bg-primary-500 h-2 rounded-full" style="width: {{ ($count / $maxCount) * 100 }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::card>

        {{-- Top AI Bots --}}
        <x-filament::card>
            <h3 class="text-lg font-semibold mb-4">ტოპ AI ბოტები</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2">Bot</th>
                            <th class="text-left py-2">ოჯახი</th>
                            <th class="text-right py-2">ვიზიტები</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->getTopBots() as $bot)
                            <tr class="border-b dark:border-gray-700">
                                <td class="py-2 font-medium dark:text-gray-100">{{ $bot->ai_bot }}</td>
                                <td class="py-2 text-gray-600 dark:text-gray-400">{{ $bot->ai_family }}</td>
                                <td class="py-2 text-right dark:text-gray-200">{{ number_format($bot->count) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::card>

        {{-- Top Visited Paths --}}
        <x-filament::card>
            <h3 class="text-lg font-semibold mb-4">ყველაზე ნახული გვერდები</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2">Path</th>
                            <th class="text-right py-2">ვიზიტები</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->getTopPaths() as $path)
                            <tr class="border-b dark:border-gray-700">
                                <td class="py-2 font-mono text-xs dark:text-gray-300">{{ $path->path }}</td>
                                <td class="py-2 text-right dark:text-gray-200">{{ number_format($path->count) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::card>

        {{-- API Endpoint Usage --}}
        <x-filament::card>
            <h3 class="text-lg font-semibold mb-4">API Endpoint გამოყენება</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2">Endpoint</th>
                            <th class="text-right py-2">Calls</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->getApiEndpointUsage() as $endpoint)
                            <tr class="border-b dark:border-gray-700">
                                <td class="py-2 font-mono text-xs dark:text-gray-300">{{ $endpoint->path }}</td>
                                <td class="py-2 text-right dark:text-gray-200">{{ number_format($endpoint->count) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::card>

        {{-- Recent Visits --}}
        <x-filament::card>
            <h3 class="text-lg font-semibold mb-4">ბოლო ვიზიტები</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2">დრო</th>
                            <th class="text-left py-2">Bot</th>
                            <th class="text-left py-2">Path</th>
                            <th class="text-left py-2">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->getRecentVisits() as $visit)
                            <tr class="border-b dark:border-gray-700">
                                <td class="py-2 text-xs dark:text-gray-300">{{ \Carbon\Carbon::parse($visit->created_at)->format('Y-m-d H:i') }}</td>
                                <td class="py-2">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-primary-100 dark:bg-primary-900 text-primary-800 dark:text-primary-200">
                                        {{ $visit->ai_bot }}
                                    </span>
                                </td>
                                <td class="py-2 font-mono text-xs dark:text-gray-300">{{ Str::limit($visit->path, 40) }}</td>
                                <td class="py-2 text-xs text-gray-600 dark:text-gray-400">{{ $visit->ip }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::card>
    </div>
</x-filament-panels::page>
