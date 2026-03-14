<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\InquiryResource;
use App\Models\Inquiry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentInquiriesWidget extends TableWidget
{
    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 2,
    ];

    protected static ?int $sort = 6;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Inquiry::query()
                    ->with('product')
                    ->latest()
            )
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('product.name')
                    ->label('Product')
                    ->placeholder('-'),
                TextColumn::make('preferred_contact')
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('message')
                    ->limit(50)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->since(),
            ])
            ->recordUrl(fn (Inquiry $record): string => InquiryResource::getUrl('view', ['record' => $record]))
            ->paginated([5])
            ->defaultPaginationPageOption(5)
            ->defaultSort('created_at', 'desc');
    }
}
