<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleResource\Pages;
use App\Models\Article;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(2)
                    ->schema([
                        TextInput::make('slug')
                            ->maxLength(200)
                            ->placeholder('auto-generated-if-empty')
                            ->unique(ignoreRecord: true)
                            ->columnSpan(1),
                        Select::make('schema_type')
                            ->options([
                                'Article' => 'Article',
                                'HowTo' => 'HowTo',
                                'ItemList' => 'ItemList',
                            ])
                            ->default('Article')
                            ->required(),
                        TextInput::make('title_ka')
                            ->label('Title (KA)')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                if (blank($get('slug')) && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),
                        TextInput::make('title_en')
                            ->label('Title (EN)')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                if (blank($get('slug')) && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),
                        Textarea::make('excerpt_ka')
                            ->label('Excerpt (KA)')
                            ->rows(3),
                        Textarea::make('excerpt_en')
                            ->label('Excerpt (EN)')
                            ->rows(3),
                        RichEditor::make('body_ka')
                            ->label('Body HTML (KA)')
                            ->required()
                            ->columnSpanFull(),
                        RichEditor::make('body_en')
                            ->label('Body HTML (EN)')
                            ->columnSpanFull(),
                        TextInput::make('meta_title_ka')
                            ->label('Meta Title (KA)')
                            ->maxLength(160),
                        TextInput::make('meta_title_en')
                            ->label('Meta Title (EN)')
                            ->maxLength(160),
                        Textarea::make('meta_description_ka')
                            ->label('Meta Description (KA)')
                            ->rows(3)
                            ->maxLength(160),
                        Textarea::make('meta_description_en')
                            ->label('Meta Description (EN)')
                            ->rows(3)
                            ->maxLength(160),
                        FileUpload::make('cover_image')
                            ->label('Cover Image')
                            ->disk('public')
                            ->directory('images/articles')
                            ->image()
                            ->imagePreviewHeight('200')
                            ->columnSpan(1),
                        DateTimePicker::make('published_at')
                            ->seconds(false)
                            ->native(false),
                        Toggle::make('is_published')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title_ka')
                    ->label('Title')
                    ->searchable(['title_ka', 'title_en', 'slug'])
                    ->wrap(),
                TextColumn::make('schema_type')
                    ->badge(),
                IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean(),
                TextColumn::make('published_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('updated_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'published' => 'Published',
                        'draft' => 'Draft',
                    ])
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;

                        return match ($value) {
                            'published' => $query->where('is_published', true),
                            'draft' => $query->where('is_published', false),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Action::make('togglePublish')
                    ->label(fn (Article $record): string => $record->is_published ? 'Move to draft' : 'Publish')
                    ->icon('heroicon-o-bolt')
                    ->color(fn (Article $record): string => $record->is_published ? 'gray' : 'success')
                    ->action(function (Article $record): void {
                        $nextState = ! $record->is_published;

                        $record->update([
                            'is_published' => $nextState,
                            'published_at' => $nextState ? ($record->published_at ?? now()) : null,
                        ]);

                        Notification::make()
                            ->title($nextState ? 'Article published.' : 'Article moved to draft.')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->paginated([25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit' => Pages\EditArticle::route('/{record}/edit'),
        ];
    }

    public static function normalizeFormData(array $data, ?int $articleId = null): array
    {
        $title = $data['title_en'] ?: ($data['title_ka'] ?: 'article');
        $data['slug'] = static::ensureSlug($data['slug'] ?? null, $title, $articleId);

        $published = (bool) ($data['is_published'] ?? false);
        $data['is_published'] = $published;
        $data['published_at'] = $published
            ? ($data['published_at'] ?? now())
            : null;

        return $data;
    }

    public static function ensureSlug(?string $slug, string $title, ?int $articleId = null): string
    {
        $baseSlug = Str::slug($slug ?: $title);

        if ($baseSlug === '') {
            $baseSlug = 'article';
        }

        $candidate = $baseSlug;
        $counter = 1;

        while (static::slugExists($candidate, $articleId)) {
            $candidate = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $candidate;
    }

    protected static function slugExists(string $slug, ?int $articleId = null): bool
    {
        $query = Article::query()->where('slug', $slug);

        if ($articleId) {
            $query->where('id', '!=', $articleId);
        }

        return $query->exists();
    }
}
