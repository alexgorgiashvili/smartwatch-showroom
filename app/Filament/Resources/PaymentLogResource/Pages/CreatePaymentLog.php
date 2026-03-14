<?php

namespace App\Filament\Resources\PaymentLogResource\Pages;

use App\Filament\Resources\PaymentLogResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentLog extends CreateRecord
{
    protected static string $resource = PaymentLogResource::class;
}
