<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Admin';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'User';

    protected static ?string $pluralModelLabel = 'Users';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(160),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->minLength(6)
                    ->maxLength(255),
                TextInput::make('password_confirmation')
                    ->password()
                    ->revealable()
                    ->required()
                    ->same('password')
                    ->dehydrated(false),
                Toggle::make('is_admin')
                    ->label('Administrator')
                    ->default(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_admin')
                    ->label('Admin')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_admin')
                    ->label('Admin only'),
            ])
            ->actions([
                Action::make('toggleAdmin')
                    ->label(fn (User $record): string => $record->is_admin ? 'Remove admin' : 'Make admin')
                    ->icon('heroicon-o-shield-check')
                    ->color(fn (User $record): string => $record->is_admin ? 'gray' : 'success')
                    ->requiresConfirmation()
                    ->disabled(fn (User $record): bool => auth()->id() === $record->id)
                    ->action(function (User $record): void {
                        if (auth()->id() === $record->id) {
                            Notification::make()
                                ->title('You cannot change your own admin status.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $record->update([
                            'is_admin' => ! $record->is_admin,
                        ]);

                        Notification::make()
                            ->title('User role updated.')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->paginated([25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [
        ];
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
        ];
    }
}
