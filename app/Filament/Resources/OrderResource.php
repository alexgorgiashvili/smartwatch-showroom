<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\City;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Commerce';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Customer Information')
                    ->schema([
                        TextInput::make('customer_name')->required()->maxLength(160),
                        TextInput::make('customer_phone')->required()->maxLength(50),
                        TextInput::make('personal_number')
                            ->required()
                            ->rule('regex:/^\d{11}$/')
                            ->maxLength(20),
                        Select::make('city_id')
                            ->label('City')
                            ->options(fn (): array => Cache::remember('orders:city-options:v1', now()->addMinutes(15), static function (): array {
                                return City::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all();
                            }))
                            ->searchable()
                            ->preload()
                            ->required(),
                        Textarea::make('exact_address')
                            ->required()
                            ->rows(2)
                            ->columnSpanFull(),
                        Select::make('order_source')
                            ->options([
                                'Facebook' => 'Facebook',
                                'Instagram' => 'Instagram',
                                'Direct' => 'Direct',
                                'Other' => 'Other',
                            ])
                            ->required(),
                        Textarea::make('notes')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
                Section::make('Order Items')
                    ->schema([
                        Repeater::make('items')
                            ->label('Items')
                            ->required()
                            ->defaultItems(1)
                            ->minItems(1)
                            ->schema([
                                Select::make('variant_id')
                                    ->label('Product Variant')
                                    ->options(fn (): array => static::getVariantOptions())
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set): void {
                                        $variant = $state ? ProductVariant::with('product')->find($state) : null;
                                        $price = $variant ? (float) ($variant->product->sale_price ?? $variant->product->price ?? 0) : 0;

                                        $set('unit_price', $price);
                                        $set('product_name', $variant?->product->name_en ?? null);
                                        $set('variant_name', $variant?->name ?? null);
                                        $set('subtotal', $price);
                                    }),
                                TextInput::make('quantity')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->default(1)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $get, callable $set): void {
                                        $set('subtotal', ((float) ($get('unit_price') ?? 0)) * ((int) $state ?: 0));
                                    }),
                                TextInput::make('unit_price')
                                    ->numeric()
                                    ->prefix('GEL')
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('subtotal')
                                    ->numeric()
                                    ->prefix('GEL')
                                    ->disabled()
                                    ->dehydrated(false),
                            ])
                            ->columns(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('Order')
                    ->schema([
                        TextEntry::make('order_number')->label('Order #'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('payment_status')->badge()->placeholder('-'),
                        TextEntry::make('total_amount')->money(fn (Order $record): string => $record->currency),
                        TextEntry::make('created_at')->dateTime('M d, Y H:i'),
                    ])
                    ->columns(3),
                InfolistSection::make('Customer Information')
                    ->schema([
                        TextEntry::make('customer_name'),
                        TextEntry::make('customer_phone'),
                        TextEntry::make('personal_number')->placeholder('-'),
                        TextEntry::make('exact_address')->label('Exact Address')->placeholder('-'),
                        TextEntry::make('cityRelation.name')->label('City')->placeholder(fn (Order $record) => $record->city ?: '-'),
                        TextEntry::make('order_source')->badge(),
                        TextEntry::make('notes')->placeholder('-')->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->description(fn (Order $record): string => $record->customer_phone)
                    ->searchable(),
                TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items'),
                TextColumn::make('total_amount')
                    ->money(fn (Order $record): string => $record->currency),
                TextColumn::make('order_source')
                    ->badge(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('payment_status')
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->date('M d, Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('payment_status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->actions([
                Action::make('updateStatus')
                    ->label('Status')
                    ->icon('heroicon-o-arrow-path')
                    ->form([
                        Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'shipped' => 'Shipped',
                                'delivered' => 'Delivered',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required(),
                        Textarea::make('notes')
                            ->rows(2),
                    ])
                    ->fillForm(fn (Order $record): array => ['status' => $record->status])
                    ->action(function (Order $record, array $data): void {
                        static::applyStatusUpdate($record, $data['status'], $data['notes'] ?? null);
                    }),
                Action::make('updatePaymentStatus')
                    ->label('Payment')
                    ->icon('heroicon-o-credit-card')
                    ->form([
                        Select::make('payment_status')
                            ->options([
                                'pending' => 'Pending',
                                'completed' => 'Completed',
                                'rejected' => 'Rejected',
                            ])
                            ->required(),
                    ])
                    ->fillForm(fn (Order $record): array => ['payment_status' => $record->payment_status])
                    ->action(function (Order $record, array $data): void {
                        $record->update(['payment_status' => $data['payment_status']]);

                        Notification::make()
                            ->title('Payment status updated.')
                            ->success()
                            ->send();
                    }),
                ViewAction::make(),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->action(function (Order $record): void {
                        static::deleteOrder($record);
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->paginated([25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PaymentLogsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['cityRelation:id,name']);
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function createOrderWithItems(array $data): Order
    {
        $items = $data['items'] ?? [];
        unset($data['items']);

        return DB::transaction(function () use ($data, $items) {
            $city = City::query()->findOrFail((int) $data['city_id']);

            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'personal_number' => $data['personal_number'],
                'city_id' => $city->id,
                'city' => $city->name,
                'exact_address' => $data['exact_address'],
                'delivery_address' => $data['exact_address'],
                'order_source' => $data['order_source'],
                'notes' => $data['notes'] ?? null,
                'status' => 'pending',
                'payment_status' => 'pending',
                'currency' => 'GEL',
                'customer_email' => null,
                'postal_code' => null,
                'total_amount' => 0,
            ]);

            $totalAmount = 0;

            foreach ($items as $item) {
                $variant = ProductVariant::with('product')->findOrFail($item['variant_id']);
                $quantity = (int) $item['quantity'];

                if ($variant->quantity < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Insufficient stock for {$variant->name}. Available: {$variant->quantity}",
                    ]);
                }

                $unitPrice = (float) ($variant->product->sale_price ?? $variant->product->price ?? 0);
                $subtotal = $unitPrice * $quantity;
                $totalAmount += $subtotal;

                $order->items()->create([
                    'product_variant_id' => $variant->id,
                    'product_name' => $variant->product->name_en,
                    'variant_name' => $variant->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);

                $variant->decrement('quantity', $quantity);

                StockAdjustment::create([
                    'product_variant_id' => $variant->id,
                    'quantity_change' => -$quantity,
                    'reason' => "Order {$order->order_number}",
                    'notes' => "Order created for {$order->customer_name}",
                ]);
            }

            $order->update(['total_amount' => $totalAmount]);

            return $order->fresh(['items', 'paymentLogs', 'cityRelation']);
        });
    }

    public static function applyStatusUpdate(Order $order, string $status, ?string $notes = null): void
    {
        if ($status === 'cancelled' && ! $order->isCancelled()) {
            DB::transaction(function () use ($order, $status, $notes) {
                foreach ($order->items()->with('variant')->get() as $item) {
                    $item->variant->increment('quantity', $item->quantity);

                    StockAdjustment::create([
                        'product_variant_id' => $item->variant->id,
                        'quantity_change' => $item->quantity,
                        'reason' => "Order {$order->order_number} Cancelled",
                        'notes' => $notes ?: 'Order cancelled',
                    ]);
                }

                $order->update(['status' => $status]);
            });

            Notification::make()
                ->title('Order cancelled and stock restored.')
                ->success()
                ->send();

            return;
        }

        $order->update(['status' => $status]);

        Notification::make()
            ->title('Order status updated.')
            ->success()
            ->send();
    }

    public static function deleteOrder(Order $order): void
    {
        if (! $order->canBeCancelled()) {
            Notification::make()
                ->title('Cannot delete this order.')
                ->danger()
                ->send();

            return;
        }

        DB::transaction(function () use ($order) {
            if (! $order->isCancelled()) {
                foreach ($order->items()->with('variant')->get() as $item) {
                    $item->variant->increment('quantity', $item->quantity);

                    StockAdjustment::create([
                        'product_variant_id' => $item->variant->id,
                        'quantity_change' => $item->quantity,
                        'reason' => "Order {$order->order_number} Deleted",
                        'notes' => 'Order deleted, stock restored',
                    ]);
                }
            }

            $order->delete();
        });

        Notification::make()
            ->title('Order deleted.')
            ->success()
            ->send();
    }

    protected static function getVariantOptions(): array
    {
        return Cache::remember('orders:variant-options:v1', now()->addMinutes(5), static function (): array {
            return ProductVariant::query()
                ->select([
                    'product_variants.id',
                    'product_variants.name',
                    'product_variants.quantity',
                    'products.name_en as product_name',
                ])
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->where('products.is_active', true)
                ->orderBy('products.name_en')
                ->orderBy('product_variants.name')
                ->get()
                ->mapWithKeys(function (ProductVariant $variant): array {
                    $productName = (string) ($variant->getAttribute('product_name') ?? '');

                    return [
                        $variant->id => sprintf(
                            '%s - %s (Stock: %d)',
                            $productName !== '' ? $productName : 'Product',
                            $variant->name,
                            $variant->quantity
                        ),
                    ];
                })
                ->all();
        });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
