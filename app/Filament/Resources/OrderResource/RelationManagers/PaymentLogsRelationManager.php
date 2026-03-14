<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Tables\Columns\TextColumn;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class PaymentLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'paymentLogs';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('status')
            ->columns([
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('chveni_statusi')
                    ->label('Internal status')
                    ->badge(),
                TextColumn::make('bog_order_id')
                    ->label('BOG Order ID')
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->dateTime('M d, Y H:i'),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
