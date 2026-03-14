<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\InquiryResource;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\UserResource;
use Filament\Widgets\Widget;

class QuickActionsWidget extends Widget
{
    protected static string $view = 'filament.widgets.quick-actions-widget';

    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected static ?int $sort = 3;

    protected function getViewData(): array
    {
        return [
            'actions' => [
                [
                    'label' => 'Add Product',
                    'url' => ProductResource::getUrl('create'),
                    'icon' => 'heroicon-m-plus',
                    'color' => 'primary',
                ],
                [
                    'label' => 'Create Order',
                    'url' => OrderResource::getUrl('create'),
                    'icon' => 'heroicon-m-shopping-cart',
                    'color' => 'success',
                ],
                [
                    'label' => 'Add User',
                    'url' => UserResource::getUrl('create'),
                    'icon' => 'heroicon-m-user-plus',
                    'color' => 'gray',
                ],
                [
                    'label' => 'View Orders',
                    'url' => OrderResource::getUrl(),
                    'icon' => 'heroicon-m-clipboard-document-list',
                    'color' => 'gray',
                ],
                [
                    'label' => 'View Inquiries',
                    'url' => InquiryResource::getUrl(),
                    'icon' => 'heroicon-m-chat-bubble-left-right',
                    'color' => 'gray',
                ],
                [
                    'label' => 'View Products',
                    'url' => ProductResource::getUrl(),
                    'icon' => 'heroicon-m-cube',
                    'color' => 'gray',
                ],
            ],
        ];
    }
}
