<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\ProductImage;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('path')
                    ->disk('public')
                    ->directory('images/products')
                    ->image()
                    ->required()
                    ->afterStateHydrated(function ($component, $state) {
                        if (is_string($state) && str_starts_with($state, 'storage/')) {
                            $component->state(str_replace('storage/', '', $state));
                        }
                    })
                    ->dehydrateStateUsing(fn (?string $state): ?string => $state ? ('storage/' . ltrim($state, '/')) : null),
                TextInput::make('alt_en')
                    ->maxLength(160),
                TextInput::make('alt_ka')
                    ->maxLength(160),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('path')
            ->columns([
                ImageColumn::make('thumbnail_url')
                    ->label('Image')
                    ->square(),
                TextColumn::make('path')
                    ->limit(30),
                TextColumn::make('alt_en')
                    ->label('Alt')
                    ->placeholder('No alt text'),
                IconColumn::make('is_primary')
                    ->label('Primary')
                    ->boolean(),
            ])
            ->filters([
            ])
            ->headerActions([
                CreateAction::make()
                    ->after(function (ProductImage $record): void {
                        if (! $this->getOwnerRecord()->images()->where('is_primary', true)->exists()) {
                            $record->update(['is_primary' => true]);
                        }
                    }),
            ])
            ->actions([
                Action::make('makePrimary')
                    ->label('Primary')
                    ->icon('heroicon-o-star')
                    ->color('success')
                    ->hidden(fn (ProductImage $record): bool => $record->is_primary)
                    ->action(function (ProductImage $record): void {
                        $this->getOwnerRecord()->images()->update(['is_primary' => false]);
                        $record->update(['is_primary' => true]);
                    }),
                DeleteAction::make()
                    ->before(function (ProductImage $record): void {
                        if (! empty($record->path) && str_starts_with($record->path, 'storage/')) {
                            Storage::disk('public')->delete(str_replace('storage/', '', $record->path));
                        }

                        if (! empty($record->thumbnail_path) && str_starts_with($record->thumbnail_path, 'storage/')) {
                            Storage::disk('public')->delete(str_replace('storage/', '', $record->thumbnail_path));
                        }
                    })
                    ->after(function (ProductImage $record): void {
                        $nextImage = $this->getOwnerRecord()->images()->where('id', '!=', $record->id)->oldest('sort_order')->first();

                        if ($record->is_primary && $nextImage) {
                            $nextImage->update(['is_primary' => true]);
                        }
                    }),
            ]);
    }
}
