<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Filament\Resources\ProductResource;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(160),
                TextInput::make('color_name')
                    ->maxLength(50),
                ColorPicker::make('color_hex')
                    ->formatStateUsing(fn (?string $state): ?string => $state ? strtoupper($state) : null)
                    ->dehydrateStateUsing(fn (?string $state): ?string => $state ? strtoupper($state) : null),
                TextInput::make('quantity')
                    ->numeric()
                    ->required()
                    ->minValue(0),
                TextInput::make('low_stock_threshold')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->default(5),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->weight('medium'),
                TextColumn::make('color_name')
                    ->placeholder('—'),
                ColorColumn::make('color_hex')
                    ->label('Color')
                    ->placeholder('—'),
                TextColumn::make('quantity')
                    ->badge()
                    ->color(fn (ProductVariant $record): string => $record->isOutOfStock() ? 'danger' : ($record->isLowStock() ? 'warning' : 'success')),
                TextColumn::make('low_stock_threshold')
                    ->label('Low stock'),
                TextColumn::make('stock_status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (ProductVariant $record): string => $record->isOutOfStock() ? 'Out of Stock' : ($record->isLowStock() ? 'Low Stock' : 'In Stock'))
                    ->color(fn (string $state): string => match ($state) {
                        'Out of Stock' => 'danger',
                        'Low Stock' => 'warning',
                        default => 'success',
                    }),
            ])
            ->filters([
            ])
            ->headerActions([
                CreateAction::make()
                    ->after(fn () => ProductResource::syncProduct($this->getOwnerRecord())),
            ])
            ->actions([
                Action::make('adjustStock')
                    ->label('Adjust')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->form([
                        TextInput::make('quantity_change')
                            ->numeric()
                            ->required()
                            ->helperText('Use positive numbers to add stock, negative to reduce.'),
                        Select::make('reason')
                            ->options([
                                'Facebook Sale' => 'Facebook Sale',
                                'Instagram Sale' => 'Instagram Sale',
                                'Direct Sale' => 'Direct Sale',
                                'Return' => 'Return',
                                'Damage' => 'Damage',
                                'Manual Adjustment' => 'Manual Adjustment',
                            ])
                            ->required(),
                        Textarea::make('notes')
                            ->rows(2)
                            ->maxLength(500),
                    ])
                    ->action(function (ProductVariant $record, array $data): void {
                        $record->increment('quantity', (int) $data['quantity_change']);

                        StockAdjustment::create([
                            'product_variant_id' => $record->id,
                            'quantity_change' => (int) $data['quantity_change'],
                            'reason' => $data['reason'],
                            'notes' => $data['notes'] ?? null,
                        ]);

                        ProductResource::syncProduct($this->getOwnerRecord());

                        Notification::make()
                            ->title('Stock adjusted successfully.')
                            ->success()
                            ->send();
                    }),
                EditAction::make()
                    ->after(fn () => ProductResource::syncProduct($this->getOwnerRecord())),
                DeleteAction::make()
                    ->after(fn () => ProductResource::syncProduct($this->getOwnerRecord())),
            ]);
    }
}
