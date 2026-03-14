<x-filament-panels::page>
    <div class="space-y-6">
        {{-- SEO Health Statistics --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            @php
                $stats = $this->getSeoHealthStats();
                $metaPercentage = $stats['total_products'] > 0
                    ? round(($stats['products_with_meta'] / $stats['total_products']) * 100)
                    : 0;
                $imagesPercentage = $stats['total_products'] > 0
                    ? round(($stats['products_with_images'] / $stats['total_products']) * 100)
                    : 0;
            @endphp

            <x-filament::card>
                <div class="text-center">
                    <div class="text-3xl font-bold text-primary-600">{{ number_format($stats['total_products']) }}</div>
                    <div class="text-sm text-gray-600 mt-1">სულ პროდუქტები</div>
                </div>
            </x-filament::card>

            <x-filament::card>
                <div class="text-center">
                    <div class="text-3xl font-bold {{ $metaPercentage >= 80 ? 'text-success-600' : 'text-warning-600' }}">
                        {{ $metaPercentage }}%
                    </div>
                    <div class="text-sm text-gray-600 mt-1">Meta თეგები</div>
                    <div class="text-xs text-gray-500 mt-1">{{ $stats['products_with_meta'] }}/{{ $stats['total_products'] }}</div>
                </div>
            </x-filament::card>

            <x-filament::card>
                <div class="text-center">
                    <div class="text-3xl font-bold {{ $imagesPercentage >= 90 ? 'text-success-600' : 'text-warning-600' }}">
                        {{ $imagesPercentage }}%
                    </div>
                    <div class="text-sm text-gray-600 mt-1">სურათები</div>
                    <div class="text-xs text-gray-500 mt-1">{{ $stats['products_with_images'] }}/{{ $stats['total_products'] }}</div>
                </div>
            </x-filament::card>

            <x-filament::card>
                <div class="text-center">
                    <div class="text-3xl font-bold text-info-600">{{ number_format($stats['total_articles']) }}</div>
                    <div class="text-sm text-gray-600 mt-1">ბლოგ სტატიები</div>
                    <div class="text-xs text-gray-500 mt-1">{{ $stats['articles_with_meta'] }} meta-ით</div>
                </div>
            </x-filament::card>
        </div>

        {{-- SEO Recommendations --}}
        @php
            $recommendations = $this->getRecommendations();
        @endphp

        @if(count($recommendations) > 0)
        <x-filament::card>
            <h3 class="text-lg font-semibold mb-4">🎯 რეკომენდაციები</h3>
            <div class="space-y-3">
                @foreach($recommendations as $rec)
                    <div class="flex items-start gap-3 p-3 rounded-lg {{ $rec['priority'] === 'high' ? 'bg-red-50' : 'bg-yellow-50' }}">
                        <div class="flex-shrink-0">
                            @if($rec['priority'] === 'high')
                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-800">
                                    მაღალი
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                    საშუალო
                                </span>
                            @endif
                        </div>
                        <div class="flex-1">
                            <h4 class="font-medium text-sm">{{ $rec['title'] }}</h4>
                            <p class="text-xs text-gray-600 mt-1">{{ $rec['description'] }}</p>
                            <button class="text-xs text-primary-600 hover:text-primary-700 mt-2">
                                → {{ $rec['action'] }}
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::card>
        @endif

        {{-- Schema Markup Coverage --}}
        <x-filament::card>
            <h3 class="text-lg font-semibold mb-4">📋 Schema Markup</h3>
            @php
                $schemaStats = $this->getSchemaStats();
            @endphp
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="text-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ $schemaStats['products_with_schema'] }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-300 mt-1">პროდუქტები Schema-ით</div>
                </div>
                <div class="text-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="text-2xl font-bold text-success-600 dark:text-success-400">{{ $schemaStats['products_with_reviews'] }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-300 mt-1">მიმოხილვები</div>
                </div>
                <div class="text-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="text-2xl font-bold text-info-600 dark:text-info-400">{{ $schemaStats['articles_with_schema'] }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-300 mt-1">სტატიები Schema-ით</div>
                </div>
            </div>
        </x-filament::card>

        {{-- Sitemap Status --}}
        <x-filament::card>
            <h3 class="text-lg font-semibold mb-4">🗺️ Sitemap სტატუსი</h3>
            @php
                $sitemapStats = $this->getSitemapStats();
            @endphp
            <div class="space-y-2">
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium">სტატუსი:</span>
                    @if($sitemapStats['exists'])
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-success-100 text-success-800">
                            ✓ არსებობს
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                            ✗ არ არსებობს
                        </span>
                    @endif
                </div>
                @if($sitemapStats['exists'])
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600">ბოლო განახლება:</span>
                        <span class="font-mono text-xs">{{ $sitemapStats['last_modified'] }}</span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600">ზომა:</span>
                        <span class="font-mono text-xs">{{ number_format($sitemapStats['size'] / 1024, 2) }} KB</span>
                    </div>
                    <div class="mt-3">
                        <a href="{{ url('/sitemap.xml') }}" target="_blank" class="text-xs text-primary-600 hover:text-primary-700">
                            → ნახეთ Sitemap
                        </a>
                    </div>
                @endif
            </div>
        </x-filament::card>

        {{-- Products Missing Meta --}}
        @php
            $missingMeta = $this->getProductsMissingMeta();
        @endphp

        @if(count($missingMeta) > 0)
        <x-filament::card>
            <h3 class="text-lg font-semibold mb-4">⚠️ პროდუქტები Meta თეგების გარეშე</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2">პროდუქტი</th>
                            <th class="text-center py-2">Meta Title</th>
                            <th class="text-center py-2">Meta Description</th>
                            <th class="text-right py-2">მოქმედება</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($missingMeta as $product)
                            <tr class="border-b">
                                <td class="py-2">{{ $product['name'] }}</td>
                                <td class="py-2 text-center">
                                    @if(empty($product['meta_title_ka']))
                                        <span class="text-red-600">✗</span>
                                    @else
                                        <span class="text-success-600">✓</span>
                                    @endif
                                </td>
                                <td class="py-2 text-center">
                                    @if(empty($product['meta_description_ka']))
                                        <span class="text-red-600">✗</span>
                                    @else
                                        <span class="text-success-600">✓</span>
                                    @endif
                                </td>
                                <td class="py-2 text-right">
                                    <a href="{{ route('filament.admin.resources.products.edit', $product['id']) }}"
                                       class="text-xs text-primary-600 hover:text-primary-700">
                                        რედაქტირება →
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if(count($missingMeta) >= 10)
                <div class="mt-3 text-xs text-gray-500">
                    ნაჩვენებია პირველი 10 პროდუქტი
                </div>
            @endif
        </x-filament::card>
        @endif

        {{-- Top Products --}}
        <x-filament::card>
            <h3 class="text-lg font-semibold mb-4">🏆 ტოპ პროდუქტები</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2">პროდუქტი</th>
                            <th class="text-right py-2">ფასი</th>
                            <th class="text-center py-2">მარაგი</th>
                            <th class="text-center py-2">SEO</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->getTopProducts() as $product)
                            <tr class="border-b">
                                <td class="py-2">{{ Str::limit($product['name'], 40) }}</td>
                                <td class="py-2 text-right font-medium">{{ number_format($product['price'], 2) }}₾</td>
                                <td class="py-2 text-center">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $product['stock'] > 0 ? 'bg-success-100 text-success-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $product['stock'] ?? 0 }}
                                    </span>
                                </td>
                                <td class="py-2 text-center">
                                    @if($product['has_meta'])
                                        <span class="text-success-600">✓</span>
                                    @else
                                        <span class="text-red-600">✗</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::card>

        {{-- Recent Articles --}}
        <x-filament::card>
            <h3 class="text-lg font-semibold mb-4">📝 ბოლო სტატიები</h3>
            <div class="space-y-2">
                @foreach($this->getRecentArticles() as $article)
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="flex-1">
                            <h4 class="font-medium text-sm dark:text-gray-100">{{ $article['title'] }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $article['published_at'] }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            @if($article['has_meta'])
                                <span class="text-success-600 dark:text-success-400 text-sm">✓ SEO</span>
                            @else
                                <span class="text-red-600 dark:text-red-400 text-sm">✗ SEO</span>
                            @endif
                            <a href="{{ route('filament.admin.resources.articles.edit', $article['id']) }}"
                               class="text-xs text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300">
                                რედაქტირება →
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::card>
    </div>
</x-filament-panels::page>
