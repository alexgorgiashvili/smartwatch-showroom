<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentOrdersWidget extends TableWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 8;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()
                    ->with('items')
                    ->latest()
            )
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable(),
                TextColumn::make('customer_name')
                    ->description(fn (Order $record): string => $record->customer_phone),
                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items'),
                TextColumn::make('total_amount')
                    ->money(fn (Order $record): string => $record->currency),
                TextColumn::make('order_source')
                    ->badge(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->date('M d, Y'),
            ])
            ->recordUrl(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]))
            ->paginated([5])
            ->defaultPaginationPageOption(5)
            ->defaultSort('created_at', 'desc');
    }
}
