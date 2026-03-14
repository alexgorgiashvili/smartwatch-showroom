<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ProductResource::mutateProductData($data, $this->record->id);
    }

    protected function afterSave(): void
    {
        ProductResource::syncProduct($this->record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(fn () => ProductResource::deactivateProduct($this->record)),
        ];
    }
}
