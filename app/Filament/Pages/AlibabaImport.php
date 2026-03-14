<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use App\Services\AlibabaDataProcessorService;
use App\Services\AlibabaScraperService;
use App\Services\Chatbot\ChatbotContentSyncService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AlibabaImport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cloud-arrow-down';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 30;

    protected static ?string $title = 'Alibaba Import';

    protected static ?string $navigationLabel = 'Alibaba Import';

    protected static string $view = 'filament.pages.alibaba-import';

    protected static ?string $slug = 'alibaba-import';

    public ?array $parseData = [];

    public ?array $productData = [];

    public array $parsedImages = [];

    public bool $hasParsedProduct = false;

    public function mount(): void
    {
        $this->parseForm->fill();
        $this->productForm->fill($this->defaultProductData());
    }

    protected function getForms(): array
    {
        return [
            'parseForm',
            'productForm',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewProducts')
                ->label('Back to Products')
                ->icon('heroicon-o-arrow-left')
                ->url(ProductResource::getUrl()),
        ];
    }

    public function parseForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Source Input')
                    ->description('Use the product URL first. If Alibaba blocks scraping, paste the full browser page source below.')
                    ->schema([
                        TextInput::make('url')
                            ->label('Alibaba Product URL')
                            ->url()
                            ->placeholder('https://www.alibaba.com/product-detail/...'),
                        Textarea::make('raw_html')
                            ->label('Fallback Full Page Source')
                            ->rows(8)
                            ->placeholder('Paste the full HTML source here if the direct request is blocked by captcha or unusual traffic checks.')
                            ->minLength(1000),
                    ]),
            ])
            ->statePath('parseData');
    }

    public function productForm(Form $form): Form
    {
        return $form
            ->schema([
                Hidden::make('source_url'),
                Hidden::make('source_product_id'),
                Section::make('Images')
                    ->description('Choose which images to download and attach to the new product.')
                    ->schema([
                        CheckboxList::make('selected_images')
                            ->label('Images to Import')
                            ->options(fn (): array => collect($this->parsedImages)
                                ->mapWithKeys(fn (string $url, int $index): array => [$url => 'Image ' . ($index + 1)])
                                ->all())
                            ->columns(2)
                            ->bulkToggleable(),
                    ]),
                Section::make('Core Product Data')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('name_en')
                                    ->label('Name (EN)')
                                    ->required()
                                    ->maxLength(160),
                                TextInput::make('name_ka')
                                    ->label('Name (KA)')
                                    ->required()
                                    ->maxLength(160),
                                TextInput::make('slug')
                                    ->maxLength(200),
                                TextInput::make('price')
                                    ->numeric()
                                    ->minValue(0),
                                TextInput::make('sale_price')
                                    ->numeric()
                                    ->minValue(0),
                                TextInput::make('currency')
                                    ->maxLength(3)
                                    ->default('GEL'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('short_description_en')
                                    ->label('Short Description (EN)')
                                    ->maxLength(255),
                                TextInput::make('short_description_ka')
                                    ->label('Short Description (KA)')
                                    ->maxLength(255),
                            ]),
                        Grid::make(2)
                            ->schema([
                                Textarea::make('description_en')
                                    ->label('Description (EN)')
                                    ->rows(6),
                                Textarea::make('description_ka')
                                    ->label('Description (KA)')
                                    ->rows(6),
                            ]),
                    ]),
                Section::make('Specifications')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextInput::make('water_resistant')
                                    ->label('Water Resistance')
                                    ->maxLength(50),
                                TextInput::make('battery_life_hours')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(1000),
                                TextInput::make('warranty_months')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(120),
                                TextInput::make('brand')
                                    ->maxLength(100),
                                TextInput::make('model')
                                    ->maxLength(100),
                                TextInput::make('memory_size')
                                    ->maxLength(100),
                                TextInput::make('operating_system')
                                    ->maxLength(100),
                                TextInput::make('screen_size')
                                    ->maxLength(100),
                                TextInput::make('display_type')
                                    ->maxLength(100),
                                TextInput::make('screen_resolution')
                                    ->maxLength(100),
                                TextInput::make('battery_capacity_mah')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(100000),
                                TextInput::make('charging_time_hours')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(999.9),
                                TextInput::make('case_material')
                                    ->maxLength(100),
                                TextInput::make('band_material')
                                    ->maxLength(100),
                                TextInput::make('camera')
                                    ->maxLength(100),
                            ]),
                        Textarea::make('functions')
                            ->label('Functions')
                            ->rows(3)
                            ->placeholder('Comma or line separated feature list'),
                        Grid::make(4)
                            ->schema([
                                Toggle::make('sim_support')
                                    ->label('SIM Support'),
                                Toggle::make('gps_features')
                                    ->label('GPS Features'),
                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
                                Toggle::make('featured')
                                    ->label('Featured'),
                            ]),
                    ]),
                Section::make('Variants')
                    ->schema([
                        Repeater::make('variants')
                            ->label('Variants')
                            ->defaultItems(1)
                            ->minItems(1)
                            ->schema([
                                Grid::make(5)
                                    ->schema([
                                        TextInput::make('name')
                                            ->required()
                                            ->maxLength(160),
                                        TextInput::make('color_name')
                                            ->maxLength(50),
                                        TextInput::make('color_hex')
                                            ->label('Color Hex')
                                            ->maxLength(7)
                                            ->rule('regex:/^#[0-9A-Fa-f]{6}$/'),
                                        TextInput::make('quantity')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(0),
                                        TextInput::make('low_stock_threshold')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(5),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('productData')
            ->model(Product::class);
    }

    public function parseAlibaba(): void
    {
        $data = $this->parseForm->getState();

        if (blank($data['url'] ?? null) && blank($data['raw_html'] ?? null)) {
            Notification::make()
                ->title('Alibaba URL or full page source is required.')
                ->danger()
                ->send();

            return;
        }

        try {
            $raw = app(AlibabaScraperService::class)->scrape($data['url'] ?? null, $data['raw_html'] ?? null);
            $processed = app(AlibabaDataProcessorService::class)->process($raw);
        } catch (\RuntimeException $exception) {
            Notification::make()
                ->title('Parsing failed.')
                ->body($exception->getMessage())
                ->warning()
                ->send();

            return;
        } catch (\InvalidArgumentException $exception) {
            Notification::make()
                ->title('Invalid input.')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Failed to parse this page.')
                ->body('Try a direct product URL or paste the full browser page source.')
                ->danger()
                ->send();

            return;
        }

        $product = (array) ($processed['product'] ?? []);
        $this->parsedImages = array_values((array) ($processed['images'] ?? []));
        $this->hasParsedProduct = true;

        $this->productForm->fill(array_merge($this->defaultProductData(), $product, [
            'source_url' => $processed['source_url'] ?? ($data['url'] ?? null),
            'source_product_id' => $processed['source_product_id'] ?? null,
            'functions' => is_array($product['functions'] ?? null)
                ? implode(', ', $product['functions'])
                : ($product['functions'] ?? ''),
            'selected_images' => $this->parsedImages,
            'variants' => $processed['variants'] ?? $this->defaultProductData()['variants'],
        ]));

        Notification::make()
            ->title('Product parsed successfully.')
            ->success()
            ->send();
    }

    public function createProduct(): void
    {
        $payload = $this->productForm->getState();
        $duplicate = $this->findDuplicateBySource(
            $payload['source_url'] ?? null,
            $payload['source_product_id'] ?? null,
        );

        if ($duplicate) {
            Notification::make()
                ->title('This Alibaba product has already been imported.')
                ->warning()
                ->send();

            $this->redirect(ProductResource::getUrl('edit', ['record' => $duplicate]));

            return;
        }

        $productData = [
            'name_en' => $payload['name_en'],
            'name_ka' => $payload['name_ka'],
            'slug' => $this->ensureSlug($payload['slug'] ?? null, $payload['name_en']),
            'external_source' => 'alibaba',
            'external_source_url' => $payload['source_url'] ?? null,
            'external_product_id' => $payload['source_product_id'] ?? null,
            'short_description_en' => $payload['short_description_en'] ?? null,
            'short_description_ka' => $payload['short_description_ka'] ?? null,
            'description_en' => $payload['description_en'] ?? null,
            'description_ka' => $payload['description_ka'] ?? null,
            'price' => $payload['price'] ?? null,
            'sale_price' => $payload['sale_price'] ?? null,
            'currency' => 'GEL',
            'sim_support' => (bool) ($payload['sim_support'] ?? false),
            'gps_features' => (bool) ($payload['gps_features'] ?? false),
            'water_resistant' => $payload['water_resistant'] ?? null,
            'battery_life_hours' => $payload['battery_life_hours'] ?? null,
            'warranty_months' => $payload['warranty_months'] ?? null,
            'brand' => $payload['brand'] ?? null,
            'model' => $payload['model'] ?? null,
            'memory_size' => $payload['memory_size'] ?? null,
            'operating_system' => $payload['operating_system'] ?? null,
            'screen_size' => $payload['screen_size'] ?? null,
            'display_type' => $payload['display_type'] ?? null,
            'screen_resolution' => $payload['screen_resolution'] ?? null,
            'battery_capacity_mah' => $payload['battery_capacity_mah'] ?? null,
            'charging_time_hours' => $payload['charging_time_hours'] ?? null,
            'case_material' => $payload['case_material'] ?? null,
            'band_material' => $payload['band_material'] ?? null,
            'camera' => $payload['camera'] ?? null,
            'functions' => $this->normalizeFunctions($payload['functions'] ?? null),
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'featured' => (bool) ($payload['featured'] ?? false),
        ];

        $product = DB::transaction(function () use ($productData, $payload) {
            $product = Product::query()->create($productData);

            $selectedImages = array_slice((array) ($payload['selected_images'] ?? []), 0, 12);
            if ($selectedImages !== []) {
                $assets = app(AlibabaScraperService::class)->downloadImages($selectedImages, $product->slug);

                foreach ($assets as $index => $asset) {
                    $mainPath = (string) ($asset['path'] ?? '');
                    if ($mainPath === '') {
                        continue;
                    }

                    $product->images()->create([
                        'path' => 'storage/' . $mainPath,
                        'thumbnail_path' => isset($asset['thumbnail_path']) && $asset['thumbnail_path']
                            ? 'storage/' . $asset['thumbnail_path']
                            : null,
                        'alt_en' => $product->name_en,
                        'alt_ka' => $product->name_ka,
                        'sort_order' => $index,
                        'is_primary' => $index === 0,
                    ]);
                }
            }

            foreach (array_slice((array) ($payload['variants'] ?? []), 0, 30) as $variant) {
                $name = trim((string) ($variant['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $product->variants()->create([
                    'name' => $name,
                    'color_name' => $this->nullableString($variant['color_name'] ?? null),
                    'color_hex' => $this->nullableColorHex($variant['color_hex'] ?? null),
                    'quantity' => max(0, (int) ($variant['quantity'] ?? 0)),
                    'low_stock_threshold' => max(0, (int) ($variant['low_stock_threshold'] ?? 5)),
                ]);
            }

            return $product;
        });

        app(ChatbotContentSyncService::class)->syncProduct($product->fresh('variants'));

        Notification::make()
            ->title('Product created from Alibaba import.')
            ->success()
            ->send();

        $this->redirect(ProductResource::getUrl('edit', ['record' => $product]));
    }

    private function defaultProductData(): array
    {
        return [
            'currency' => 'GEL',
            'functions' => '',
            'is_active' => true,
            'featured' => false,
            'selected_images' => [],
            'variants' => [
                [
                    'name' => 'Default',
                    'color_name' => null,
                    'color_hex' => null,
                    'quantity' => 0,
                    'low_stock_threshold' => 5,
                ],
            ],
        ];
    }

    private function findDuplicateBySource(?string $sourceUrl, ?string $sourceProductId): ?Product
    {
        $sourceUrl = trim((string) ($sourceUrl ?? ''));
        $sourceProductId = trim((string) ($sourceProductId ?? ''));

        if ($sourceUrl === '' && $sourceProductId === '') {
            return null;
        }

        return Product::query()
            ->where('external_source', 'alibaba')
            ->where(function ($query) use ($sourceUrl, $sourceProductId) {
                $hasCondition = false;

                if ($sourceProductId !== '') {
                    $query->where('external_product_id', $sourceProductId);
                    $hasCondition = true;
                }

                if ($sourceUrl !== '') {
                    if ($hasCondition) {
                        $query->orWhere('external_source_url', $sourceUrl);
                    } else {
                        $query->where('external_source_url', $sourceUrl);
                    }
                }
            })
            ->first();
    }

    private function ensureSlug(?string $slug, string $name): string
    {
        $baseSlug = $slug ? Str::slug($slug) : Str::slug($name);
        $candidate = $baseSlug;
        $counter = 1;

        while (Product::query()->where('slug', $candidate)->exists()) {
            $candidate = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $candidate;
    }

    private function normalizeFunctions(mixed $value): ?array
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $text = trim((string) ($value ?? ''));
            if ($text === '') {
                return null;
            }

            $items = preg_split('/[,\n]+/', $text) ?: [];
        }

        $normalized = [];
        foreach ($items as $item) {
            $clean = trim((string) $item);
            if ($clean !== '') {
                $normalized[] = Str::limit($clean, 100, '');
            }
        }

        $normalized = array_values(array_unique($normalized));

        return $normalized === [] ? null : $normalized;
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : Str::limit($text, 255, '');
    }

    private function nullableColorHex(mixed $value): ?string
    {
        $hex = strtoupper(trim((string) ($value ?? '')));

        if ($hex === '') {
            return null;
        }

        return preg_match('/^#[0-9A-F]{6}$/', $hex) === 1 ? $hex : null;
    }
}
