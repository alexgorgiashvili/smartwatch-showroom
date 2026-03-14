<?php

namespace App\Filament\Resources\PaymentLogResource\Pages;

use App\Filament\Resources\PaymentLogResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPaymentLog extends EditRecord
{
    protected static string $resource = PaymentLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
