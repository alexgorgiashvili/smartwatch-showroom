<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProductResource;
use App\Models\StockAdjustment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentStockAdjustmentsWidget extends TableWidget
{
    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected static ?int $sort = 7;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                StockAdjustment::query()
                    ->with('variant.product')
                    ->latest()
            )
            ->columns([
                TextColumn::make('variant.product.name_en')
                    ->label('Product')
                    ->wrap(),
                TextColumn::make('variant.name')
                    ->label('Variant')
                    ->wrap(),
                TextColumn::make('quantity_change')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? '+' . $state : (string) $state)
                    ->color(fn (int $state): string => $state >= 0 ? 'success' : 'danger'),
                TextColumn::make('reason')
                    ->limit(24)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->since(),
            ])
            ->recordUrl(function (StockAdjustment $record): ?string {
                $product = $record->variant?->product;

                return $product ? ProductResource::getUrl('edit', ['record' => $product]) : null;
            })
            ->paginated([5])
            ->defaultPaginationPageOption(5)
            ->defaultSort('created_at', 'desc');
    }
}
