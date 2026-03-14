<?php

namespace App\Filament\Resources\FacebookPostResource\Pages;

use App\Filament\Resources\FacebookPostResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFacebookPosts extends ListRecords
{
    protected static string $resource = FacebookPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
