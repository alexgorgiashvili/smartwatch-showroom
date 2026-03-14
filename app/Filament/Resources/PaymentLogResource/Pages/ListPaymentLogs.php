<?php

namespace App\Filament\Resources\PaymentLogResource\Pages;

use App\Filament\Resources\PaymentLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPaymentLogs extends ListRecords
{
    protected static string $resource = PaymentLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
