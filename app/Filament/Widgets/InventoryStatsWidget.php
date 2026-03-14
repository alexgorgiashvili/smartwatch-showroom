<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProductResource;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InventoryStatsWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 2,
    ];

    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $lowStockCount = ProductVariant::query()
            ->where('quantity', '>', 0)
            ->whereColumn('quantity', '<=', 'low_stock_threshold')
            ->count();

        $outOfStockCount = ProductVariant::query()
            ->where('quantity', '<=', 0)
            ->count();

        $totalInventory = (int) ProductVariant::sum('quantity');
        $recentAdjustments = StockAdjustment::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return [
            Stat::make('Low Stock Variants', number_format($lowStockCount))
                ->description('Threshold reached but still sellable')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning')
                ->url(ProductResource::getUrl()),
            Stat::make('Out of Stock', number_format($outOfStockCount))
                ->description('Variants with zero inventory')
                ->descriptionIcon('heroicon-m-no-symbol')
                ->color('danger')
                ->url(ProductResource::getUrl()),
            Stat::make('Inventory Units', number_format($totalInventory))
                ->description('Total units across all variants')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('success')
                ->url(ProductResource::getUrl()),
            Stat::make('7d Stock Changes', number_format($recentAdjustments))
                ->description('Recent manual stock adjustments')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('info'),
        ];
    }
}
