<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CommerceStatsWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 4;

    protected function getStats(): array
    {
        $totalOrders = Order::count();
        $pendingOrders = Order::query()->where('status', 'pending')->count();
        $totalRevenue = (float) Order::query()
            ->whereNotIn('status', ['cancelled'])
            ->sum('total_amount');
        $completedPayments = Order::query()->where('payment_status', 'completed')->count();
        $pendingPayments = Order::query()
            ->where('payment_status', 'pending')
            ->whereNotNull('payment_type')
            ->count();
        $rejectedPayments = Order::query()->where('payment_status', 'rejected')->count();

        return [
            Stat::make('Orders', number_format($totalOrders))
                ->description('All recorded orders')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary')
                ->url(OrderResource::getUrl()),
            Stat::make('Pending Orders', number_format($pendingOrders))
                ->description('Orders awaiting next action')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->url(OrderResource::getUrl()),
            Stat::make('Revenue', 'GEL ' . number_format($totalRevenue, 2))
                ->description('Excluding cancelled orders')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->url(OrderResource::getUrl()),
            Stat::make('Completed Payments', number_format($completedPayments))
                ->description('Successful payment confirmations')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success')
                ->url(OrderResource::getUrl()),
            Stat::make('Pending Payments', number_format($pendingPayments))
                ->description('Payment initiated but unresolved')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('info')
                ->url(OrderResource::getUrl()),
            Stat::make('Rejected Payments', number_format($rejectedPayments))
                ->description('Declined or failed payments')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger')
                ->url(OrderResource::getUrl()),
        ];
    }
}
