<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\InquiryResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\UserResource;
use App\Models\Inquiry;
use App\Models\Product;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OverviewStatsWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalProducts = Product::count();
        $totalInquiries = Inquiry::count();
        $totalUsers = User::count();
        $totalAdmins = User::where('is_admin', true)->count();

        return [
            Stat::make('Products', number_format($totalProducts))
                ->description('Catalog items in Filament')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary')
                ->url(ProductResource::getUrl()),
            Stat::make('Inquiries', number_format($totalInquiries))
                ->description('Customer leads and contact requests')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('info')
                ->url(InquiryResource::getUrl()),
            Stat::make('Users', number_format($totalUsers))
                ->description($totalAdmins . ' admin account(s)')
                ->descriptionIcon('heroicon-m-users')
                ->color('success')
                ->url(UserResource::getUrl()),
            Stat::make('Admins', number_format($totalAdmins))
                ->description('Users with panel access')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('warning')
                ->url(UserResource::getUrl()),
        ];
    }
}
