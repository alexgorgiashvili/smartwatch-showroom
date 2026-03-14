<?php

namespace App\Filament\Resources\PaymentLogResource\Pages;

use App\Filament\Resources\PaymentLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPaymentLog extends ViewRecord
{
    protected static string $resource = PaymentLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
