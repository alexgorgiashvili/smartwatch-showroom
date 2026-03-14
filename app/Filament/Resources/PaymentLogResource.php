<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentLogResource\Pages;
use App\Models\PaymentLog;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PaymentLogResource extends Resource
{
    protected static ?string $model = PaymentLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Payments';

    protected static ?string $slug = 'payments';

    protected static ?string $navigationGroup = 'Commerce';

    protected static ?int $navigationSort = 20;

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Payment')
                    ->schema([
                        TextEntry::make('order.order_number')
                            ->label('Order #')
                            ->placeholder('-'),
                        TextEntry::make('bog_order_id')
                            ->label('BOG Order ID')
                            ->placeholder('-'),
                        TextEntry::make('external_order_id')
                            ->label('External ID')
                            ->placeholder('-'),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('chveni_statusi')
                            ->label('Internal status')
                            ->badge(),
                        TextEntry::make('created_at')
                            ->dateTime('M d, Y H:i'),
                    ])
                    ->columns(2),
                Section::make('Order')
                    ->schema([
                        TextEntry::make('order.customer_name')
                            ->label('Customer')
                            ->placeholder('-'),
                        TextEntry::make('order.total_amount')
                            ->money(fn (PaymentLog $record): string => $record->order?->currency ?? 'GEL')
                            ->placeholder('-'),
                        TextEntry::make('order.payment_status')
                            ->badge()
                            ->placeholder('-'),
                    ])
                    ->columns(3),
                Section::make('Payment detail')
                    ->schema([
                        TextEntry::make('payment_detail')
                            ->formatStateUsing(fn (?array $state): string => empty($state)
                                ? '-'
                                : json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                            ->fontFamily('mono')
                            ->copyable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order.order_number')
                    ->label('Order #')
                    ->searchable(),
                TextColumn::make('bog_order_id')
                    ->label('BOG Order ID')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('external_order_id')
                    ->label('External ID')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('chveni_statusi')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('order.total_amount')
                    ->label('Amount')
                    ->money(fn (PaymentLog $record): string => $record->order?->currency ?? 'GEL')
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('chveni_statusi')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'rejected' => 'Rejected',
                    ]),
                Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('date_from'),
                        \Filament\Forms\Components\DatePicker::make('date_to'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['date_from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date))
                            ->when($data['date_to'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->paginated([25, 50, 100]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('order');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentLogs::route('/'),
            'view' => Pages\ViewPaymentLog::route('/{record}'),
        ];
    }
}
