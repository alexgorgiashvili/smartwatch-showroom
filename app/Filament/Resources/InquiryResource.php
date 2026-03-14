<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InquiryResource\Pages;
use App\Models\Inquiry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class InquiryResource extends Resource
{
    protected static ?string $model = Inquiry::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Commerce';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'Inquiry';

    protected static ?string $pluralModelLabel = 'Inquiries';

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Inquiry')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('phone'),
                        TextEntry::make('email')
                            ->placeholder('-'),
                        TextEntry::make('preferred_contact')
                            ->label('Preferred contact')
                            ->placeholder('-'),
                        TextEntry::make('locale')
                            ->badge(),
                        TextEntry::make('selected_color')
                            ->label('Selected color')
                            ->placeholder('-'),
                        TextEntry::make('product.name')
                            ->label('Product')
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->dateTime('M d, Y H:i'),
                    ])
                    ->columns(2),
                Section::make('Message')
                    ->schema([
                        TextEntry::make('message')
                            ->placeholder('No message')
                            ->prose(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable(),
                TextColumn::make('preferred_contact')
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('message')
                    ->limit(50)
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('locale')
                    ->options([
                        'ka' => 'KA',
                        'en' => 'EN',
                    ]),
                SelectFilter::make('preferred_contact')
                    ->options([
                        'phone' => 'Phone',
                        'email' => 'Email',
                        'whatsapp' => 'WhatsApp',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->paginated([25, 50, 100]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'product:id,name_ka,name_en',
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInquiries::route('/'),
            'view' => Pages\ViewInquiry::route('/{record}'),
        ];
    }
}
