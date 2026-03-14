<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use App\Services\Chatbot\ChatbotContentSyncService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Resources\Components\Tab;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Product')
                    ->tabs([
                        Tabs\Tab::make('General')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextInput::make('name_en')
                                            ->label('Name (EN)')
                                            ->required()
                                            ->maxLength(160)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                                if (blank($get('slug')) && filled($state)) {
                                                    $set('slug', Str::slug($state));
                                                }
                                            }),
                                        TextInput::make('name_ka')
                                            ->label('Name (KA)')
                                            ->required()
                                            ->maxLength(160),
                                        TextInput::make('slug')
                                            ->maxLength(200)
                                            ->unique(ignoreRecord: true),
                                        TextInput::make('price')
                                            ->numeric()
                                            ->minValue(0)
                                            ->step('0.01')
                                            ->prefix('GEL'),
                                        TextInput::make('sale_price')
                                            ->numeric()
                                            ->minValue(0)
                                            ->step('0.01')
                                            ->prefix('GEL'),
                                        TextInput::make('currency')
                                            ->default('GEL')
                                            ->disabled()
                                            ->dehydrated(false),
                                    ]),
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('short_description_en')
                                            ->label('Short Description (EN)')
                                            ->maxLength(255),
                                        TextInput::make('short_description_ka')
                                            ->label('Short Description (KA)')
                                            ->maxLength(255),
                                        Textarea::make('description_en')
                                            ->label('Description (EN)')
                                            ->rows(5),
                                        Textarea::make('description_ka')
                                            ->label('Description (KA)')
                                            ->rows(5),
                                    ]),
                                Grid::make(4)
                                    ->schema([
                                        TextInput::make('brand')->maxLength(100),
                                        TextInput::make('model')->maxLength(100),
                                        TextInput::make('memory_size')->maxLength(100),
                                        TextInput::make('water_resistant')->maxLength(50),
                                        TextInput::make('battery_life_hours')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(1000),
                                        TextInput::make('warranty_months')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(120),
                                        TextInput::make('operating_system')->maxLength(100),
                                        TextInput::make('screen_size')->maxLength(100),
                                        TextInput::make('display_type')->maxLength(100),
                                        TextInput::make('screen_resolution')->maxLength(100),
                                        TextInput::make('battery_capacity_mah')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(100000),
                                        TextInput::make('charging_time_hours')
                                            ->numeric()
                                            ->step('0.1')
                                            ->minValue(0)
                                            ->maxValue(999.9),
                                        TextInput::make('case_material')->maxLength(100),
                                        TextInput::make('band_material')->maxLength(100),
                                        TextInput::make('camera')->maxLength(100),
                                        Textarea::make('functions')
                                            ->rows(2)
                                            ->helperText('Comma or line separated values.')
                                            ->formatStateUsing(fn ($state): string => is_array($state) ? implode(', ', $state) : (string) $state)
                                            ->dehydrateStateUsing(fn (?string $state): ?array => static::normalizeFunctions($state))
                                            ->columnSpan(4),
                                    ]),
                                Grid::make(4)
                                    ->schema([
                                        Toggle::make('sim_support')->default(true),
                                        Toggle::make('gps_features')->default(true),
                                        Toggle::make('is_active')->default(true),
                                        Toggle::make('featured')->default(false),
                                    ]),
                            ]),
                        Tabs\Tab::make('SEO')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('meta_title_ka')->label('Meta Title (KA)')->maxLength(160),
                                        TextInput::make('meta_title_en')->label('Meta Title (EN)')->maxLength(160),
                                        Textarea::make('meta_description_ka')->label('Meta Description (KA)')->rows(3),
                                        Textarea::make('meta_description_en')->label('Meta Description (EN)')->rows(3),
                                    ]),
                            ]),
                        Tabs\Tab::make('External')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextInput::make('external_source')->maxLength(50),
                                        TextInput::make('external_product_id')->maxLength(120),
                                        TextInput::make('external_source_url')->url()->maxLength(1024),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('primaryImage.thumbnail_url')
                    ->label('Image')
                    ->state(function (Product $record): ?string {
                        return static::resolveTableImageUrl($record);
                    })
                    ->defaultImageUrl(asset('assets/images/others/placeholder.jpg'))
                    ->size(52)
                    ->square(),
                TextColumn::make('name_ka')
                    ->label('Name')
                    ->description(fn (Product $record): string => 'EN: ' . ($record->name_en ?: '-') . ' | ' . $record->slug)
                    ->searchable(['name_ka', 'name_en', 'slug'])
                    ->limit(34)
                    ->tooltip(fn (Product $record): string => $record->name_ka ?: '-'),
                TextColumn::make('brand')
                    ->label('Brand')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('model')
                    ->label('Model')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('display_price')
                    ->label('Price')
                    ->state(function (Product $record): string {
                        $price = $record->sale_price ?? $record->price;

                        return $price !== null
                            ? number_format((float) $price, 2) . ' ' . $record->currency
                            : '-';
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->state(function (Product $record): string {
                        if (! $record->is_active) {
                            return 'Inactive';
                        }

                        return $record->featured ? 'Featured' : 'Active';
                    })
                    ->color(function (string $state): string {
                        return match ($state) {
                            'Featured' => 'primary',
                            'Active' => 'success',
                            default => 'gray',
                        };
                    }),
                TextColumn::make('updated_at')
                    ->date('Y-m-d')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
                TernaryFilter::make('featured')->label('Featured'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(fn (Product $record) => static::deactivateProduct($record)),
            ])
            ->defaultSort('updated_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->paginated([25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\VariantsRelationManager::class,
            RelationManagers\ImagesRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'primaryImage:id,product_id,path,thumbnail_path',
            ]);
    }

    public static function mutateProductData(array $data, ?int $productId = null): array
    {
        $data['slug'] = static::ensureSlug($data['slug'] ?? null, $data['name_en'] ?? 'product', $productId);
        $data['currency'] = 'GEL';
        $data['sim_support'] = (bool) ($data['sim_support'] ?? false);
        $data['gps_features'] = (bool) ($data['gps_features'] ?? false);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['featured'] = (bool) ($data['featured'] ?? false);
        $data['functions'] = static::normalizeFunctions($data['functions'] ?? null);

        return $data;
    }

    public static function normalizeFunctions(mixed $value): ?array
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

    public static function ensureSlug(?string $slug, string $name, ?int $productId = null): string
    {
        $baseSlug = Str::slug($slug ?: $name);

        if ($baseSlug === '') {
            $baseSlug = 'product';
        }

        $candidate = $baseSlug;
        $counter = 1;

        while (static::slugExists($candidate, $productId)) {
            $candidate = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $candidate;
    }

    protected static function resolveTableImageUrl(Product $record): ?string
    {
        $fallback = asset('assets/images/others/placeholder.jpg');

        $rawPath = $record->primaryImage?->thumbnail_path
            ?: $record->primaryImage?->path;

        if (! filled($rawPath)) {
            return $fallback;
        }

        if (str_starts_with((string) $rawPath, 'http')) {
            return (string) $rawPath;
        }

        $normalizedPath = ltrim((string) $rawPath, '/');

        if (str_starts_with($normalizedPath, 'storage/')) {
            $normalizedPath = substr($normalizedPath, 8);
        }

        if (! Storage::disk('public')->exists($normalizedPath)) {
            return $fallback;
        }

        return Storage::url($normalizedPath);
    }

    protected static function slugExists(string $slug, ?int $productId = null): bool
    {
        $query = Product::query()->where('slug', $slug);

        if ($productId) {
            $query->where('id', '!=', $productId);
        }

        return $query->exists();
    }

    public static function syncProduct(Product $product): void
    {
        app(ChatbotContentSyncService::class)->syncProduct($product->fresh('variants'));
        Cache::forget('orders:variant-options:v1');
        Cache::forget('facebook-posts:product-options:v1');
    }

    public static function deactivateProduct(Product $product): void
    {
        app(ChatbotContentSyncService::class)->deactivateProduct($product);
        Cache::forget('orders:variant-options:v1');
        Cache::forget('facebook-posts:product-options:v1');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
