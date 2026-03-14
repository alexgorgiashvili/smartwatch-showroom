<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ChatbotQualityWidget;
use App\Filament\Widgets\CommerceStatsWidget;
use App\Filament\Widgets\InventoryStatsWidget;
use App\Filament\Widgets\OverviewStatsWidget;
use App\Filament\Widgets\QuickActionsWidget;
use App\Filament\Widgets\RecentInquiriesWidget;
use App\Filament\Widgets\RecentOrdersWidget;
use App\Filament\Widgets\RecentStockAdjustmentsWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\Widget;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    /**
     * @return array<class-string<Widget>>
     */
    public function getWidgets(): array
    {
        return [
            OverviewStatsWidget::class,
            InventoryStatsWidget::class,
            QuickActionsWidget::class,
            CommerceStatsWidget::class,
            ChatbotQualityWidget::class,
            RecentInquiriesWidget::class,
            RecentStockAdjustmentsWidget::class,
            RecentOrdersWidget::class,
        ];
    }

    public function getColumns(): int | string | array
    {
        return [
            'md' => 2,
            'xl' => 3,
        ];
    }
}
