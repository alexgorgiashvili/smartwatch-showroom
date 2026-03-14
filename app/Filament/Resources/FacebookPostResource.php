<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FacebookPostResource\Pages;
use App\Models\FacebookPost;
use App\Models\Product;
use App\Services\FacebookPageService;
use App\Services\InstagramPageService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class FacebookPostResource extends Resource
{
    protected static ?string $model = FacebookPost::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Social';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Textarea::make('message')
                    ->required()
                    ->rows(10)
                    ->maxLength(5000)
                    ->columnSpanFull(),
                Select::make('product_id')
                    ->label('Related product')
                    ->options(fn (): array => Cache::remember('facebook-posts:product-options:v1', now()->addMinutes(15), static function (): array {
                        return Product::active()
                            ->orderBy('name_ka')
                            ->get()
                            ->mapWithKeys(static fn (Product $product): array => [
                                $product->id => $product->name_ka ?: $product->name_en,
                            ])
                            ->all();
                    }))
                    ->searchable()
                    ->preload(),
                TextInput::make('image_url')
                    ->url()
                    ->maxLength(2000),
                Toggle::make('post_to_facebook')
                    ->default(true),
                Toggle::make('post_to_instagram')
                    ->default(false)
                    ->helperText('Instagram publication requires an image URL.'),
                Textarea::make('ai_prompt')
                    ->rows(3)
                    ->maxLength(5000)
                    ->columnSpanFull(),
                Placeholder::make('platform_notice')
                    ->label('Publishing')
                    ->content('Save as draft first, then use the Publish action from the table or edit page.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('message')
                    ->limit(70)
                    ->searchable(),
                TextColumn::make('product.name')
                    ->label('Product')
                    ->placeholder('-')
                    ->toggleable(),
                IconColumn::make('post_to_facebook')
                    ->label('FB')
                    ->boolean(),
                IconColumn::make('post_to_instagram')
                    ->label('IG')
                    ->boolean(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('published_at')
                    ->dateTime('M d, Y H:i')
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'failed' => 'Failed',
                    ]),
            ])
            ->actions([
                Action::make('publish')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->disabled(fn (FacebookPost $record): bool => $record->status === 'published')
                    ->action(function (FacebookPost $record): void {
                        static::publishPost($record);
                    }),
                EditAction::make(),
                DeleteAction::make(),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFacebookPosts::route('/'),
            'create' => Pages\CreateFacebookPost::route('/create'),
            'edit' => Pages\EditFacebookPost::route('/{record}/edit'),
        ];
    }

    public static function publishPost(FacebookPost $post): void
    {
        $errors = [];
        $successes = [];
        $facebookService = app(FacebookPageService::class);
        $instagramService = app(InstagramPageService::class);
        $fbPostId = $post->facebook_post_id;
        $igPostId = $post->instagram_post_id;

        if (! $post->post_to_facebook && ! $post->post_to_instagram) {
            Notification::make()
                ->title('Select at least one platform.')
                ->danger()
                ->send();

            return;
        }

        if ($post->post_to_facebook) {
            if (! $facebookService->isConfigured()) {
                $errors[] = 'Facebook API is not configured';
            } else {
                $fbResult = $facebookService->publishPost($post->message, $post->image_url);

                if ($fbResult['success']) {
                    $fbPostId = $fbResult['post_id'];
                    $successes[] = 'Facebook';
                } else {
                    $errors[] = 'Facebook: ' . $fbResult['error'];
                }
            }
        }

        if ($post->post_to_instagram) {
            if (! $instagramService->isConfigured()) {
                $errors[] = 'Instagram API is not configured';
            } elseif (blank($post->image_url)) {
                $errors[] = 'Instagram requires an image URL';
            } else {
                $igResult = $instagramService->publishPost($post->message, $post->image_url);

                if ($igResult['success']) {
                    $igPostId = $igResult['post_id'];
                    $successes[] = 'Instagram';
                } else {
                    $errors[] = 'Instagram: ' . $igResult['error'];
                }
            }
        }

        if (! empty($successes)) {
            $post->update([
                'status' => 'published',
                'facebook_post_id' => $fbPostId,
                'instagram_post_id' => $igPostId,
                'published_at' => now(),
                'error_message' => ! empty($errors) ? implode('; ', $errors) : null,
            ]);

            Notification::make()
                ->title(implode(' & ', $successes) . ' published successfully.')
                ->success()
                ->body(! empty($errors) ? implode('; ', $errors) : null)
                ->send();

            return;
        }

        $post->update([
            'status' => 'failed',
            'error_message' => implode('; ', $errors),
        ]);

        Notification::make()
            ->title('Publishing failed.')
            ->danger()
            ->body(implode('; ', $errors))
            ->send();
    }

    public static function normalizeFormData(array $data): array
    {
        if (($data['post_to_instagram'] ?? false) && blank($data['image_url'] ?? null)) {
            throw ValidationException::withMessages([
                'image_url' => 'Instagram publication requires an image URL.',
            ]);
        }

        return $data;
    }
}
