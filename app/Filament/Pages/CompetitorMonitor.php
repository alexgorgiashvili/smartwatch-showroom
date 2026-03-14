<?php

namespace App\Filament\Pages;

use App\Models\CompetitorMapping;
use App\Models\CompetitorProduct;
use App\Models\CompetitorSource;
use App\Models\Product;
use App\Services\CompetitorMonitorService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

class CompetitorMonitor extends Page
{
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Competition';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Competitor Monitor';

    protected static ?string $navigationLabel = 'Competitor Monitor';

    protected static string $view = 'filament.pages.competitor-monitor';

    protected static ?string $slug = 'competitor-monitor';

    #[Url(as: 'source_id')]
    public ?int $selectedSourceId = null;

    public function mount(): void
    {
        $this->ensureDefaultSourceExists();

        if ($this->selectedSourceId === null) {
            $this->selectedSourceId = $this->getSources()->first()?->id;
        }
    }

    public function updatedSelectedSourceId(): void
    {
        $this->resetPage();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->createSourceAction(),
            $this->refreshSourceAction(),
        ];
    }

    public function createSourceAction(): Action
    {
        return Action::make('createSource')
            ->label('Add Source')
            ->icon('heroicon-o-plus')
            ->modalHeading('Add competitor source')
            ->form([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('category_url')
                    ->label('Category URL')
                    ->url()
                    ->required()
                    ->maxLength(2048)
                    ->unique(CompetitorSource::class, 'category_url'),
            ])
            ->action(function (array $data): void {
                $categoryUrl = trim((string) $data['category_url']);

                $source = CompetitorSource::query()->create([
                    'name' => trim((string) $data['name']),
                    'domain' => $this->extractDomain($categoryUrl),
                    'category_url' => $categoryUrl,
                    'is_active' => true,
                ]);

                $this->selectedSourceId = $source->id;
                $this->resetPage();

                Notification::make()
                    ->title('Competitor source created.')
                    ->success()
                    ->send();
            });
    }

    public function refreshSourceAction(): Action
    {
        return Action::make('refreshSource')
            ->label('Refresh Selected Source')
            ->icon('heroicon-o-arrow-path')
            ->color('primary')
            ->requiresConfirmation()
            ->visible(fn (): bool => $this->getSelectedSource() !== null)
            ->action(function (): void {
                $source = $this->getSelectedSource();

                if (! $source) {
                    return;
                }

                try {
                    $result = app(CompetitorMonitorService::class)->refreshSource($source);

                    Notification::make()
                        ->title('Refresh completed.')
                        ->body('Scraped: ' . $result['total_scraped'] . ', created: ' . $result['created'] . ', updated: ' . $result['updated'] . '.')
                        ->success()
                        ->send();
                } catch (\Throwable $exception) {
                    report($exception);

                    Notification::make()
                        ->title('Refresh failed.')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public function mapProductAction(): Action
    {
        return Action::make('mapProduct')
            ->label('Map')
            ->icon('heroicon-o-link')
            ->modalHeading('Map competitor product')
            ->form([
                Select::make('product_id')
                    ->label('Local product')
                    ->options(fn (): array => $this->getLocalProductOptions())
                    ->searchable()
                    ->preload()
                    ->placeholder('Not mapped'),
            ])
            ->fillForm(function (array $arguments): array {
                $product = CompetitorProduct::query()
                    ->with('mapping')
                    ->findOrFail($arguments['product']);

                return [
                    'product_id' => $product->mapping?->product_id,
                ];
            })
            ->action(function (array $data, array $arguments): void {
                $competitorProduct = CompetitorProduct::query()->findOrFail($arguments['product']);
                $productId = $data['product_id'] ?? null;

                if (blank($productId)) {
                    CompetitorMapping::query()
                        ->where('competitor_product_id', $competitorProduct->id)
                        ->delete();

                    Notification::make()
                        ->title('Mapping removed.')
                        ->success()
                        ->send();

                    return;
                }

                CompetitorMapping::query()->updateOrCreate(
                    ['competitor_product_id' => $competitorProduct->id],
                    ['product_id' => (int) $productId]
                );

                Notification::make()
                    ->title('Mapping saved.')
                    ->success()
                    ->send();
            });
    }

    protected function getViewData(): array
    {
        $sources = $this->getSources();
        $source = $sources->firstWhere('id', (int) $this->selectedSourceId) ?? $sources->first();

        if ($source && $this->selectedSourceId !== $source->id) {
            $this->selectedSourceId = $source->id;
        }

        return [
            'sources' => $sources,
            'source' => $source,
            'products' => $this->getProducts($source),
        ];
    }

    private function getSources(): Collection
    {
        $sources = CompetitorSource::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($sources->isNotEmpty()) {
            return $sources;
        }

        return CompetitorSource::query()
            ->orderBy('name')
            ->get();
    }

    private function getSelectedSource(): ?CompetitorSource
    {
        return $this->getSources()->firstWhere('id', (int) $this->selectedSourceId)
            ?? $this->getSources()->first();
    }

    private function getProducts(?CompetitorSource $source): LengthAwarePaginator
    {
        return CompetitorProduct::query()
            ->where('competitor_source_id', $source?->id)
            ->with(['mapping.product'])
            ->withCount('snapshots')
            ->orderByDesc('last_seen_at')
            ->paginate(40);
    }

    private function getLocalProductOptions(): array
    {
        return Product::query()
            ->select(['id', 'name_ka', 'name_en'])
            ->orderBy('name_ka')
            ->limit(1000)
            ->get()
            ->mapWithKeys(fn (Product $product): array => [
                $product->id => '#' . $product->id . ' - ' . ($product->name_ka ?: $product->name_en),
            ])
            ->all();
    }

    private function ensureDefaultSourceExists(): void
    {
        CompetitorSource::query()->firstOrCreate(
            ['category_url' => 'https://i-mobile.ge/categories/27'],
            [
                'name' => 'i-mobile - Kids Watches',
                'domain' => 'i-mobile.ge',
                'is_active' => true,
            ]
        );
    }

    private function extractDomain(string $categoryUrl): string
    {
        $host = parse_url($categoryUrl, PHP_URL_HOST);

        if (! is_string($host) || trim($host) === '') {
            throw new \InvalidArgumentException('Invalid category URL.');
        }

        $domain = mb_strtolower(trim($host));

        return str_starts_with($domain, 'www.') ? substr($domain, 4) : $domain;
    }
}
