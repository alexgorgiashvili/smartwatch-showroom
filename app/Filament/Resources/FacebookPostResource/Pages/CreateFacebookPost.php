<?php

namespace App\Filament\Resources\FacebookPostResource\Pages;

use App\Filament\Resources\FacebookPostResource;
use App\Models\Product;
use App\Services\AiPostGeneratorService;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateFacebookPost extends CreateRecord
{
    protected static string $resource = FacebookPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generateAi')
                ->label('Generate with AI')
                ->icon('heroicon-o-sparkles')
                ->color('success')
                ->form([
                    Select::make('product_id')
                        ->label('Product')
                        ->options($this->getProductOptions())
                        ->searchable()
                        ->preload(),
                    Textarea::make('description')
                        ->rows(3)
                        ->helperText('Use this only when no product is selected.'),
                    Select::make('language')
                        ->options([
                            'ka' => 'Georgian',
                            'en' => 'English',
                        ])
                        ->default('ka')
                        ->required(),
                    Select::make('tone')
                        ->options([
                            'professional' => 'Professional',
                            'casual' => 'Casual',
                            'exciting' => 'Exciting',
                            'urgent' => 'Urgent',
                        ])
                        ->default('professional')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->applyGeneratedContent($data);
                }),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = FacebookPostResource::normalizeFormData($data);
        $data['user_id'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return FacebookPostResource::getUrl('index');
    }

    protected function applyGeneratedContent(array $data): void
    {
        $service = app(AiPostGeneratorService::class);

        if (! empty($data['product_id'])) {
            $product = Product::with('primaryImage')->findOrFail($data['product_id']);
            $result = $service->generateProductPost($product, $data['language'], $data['tone']);
            $imageUrl = $product->primaryImage ? asset('storage/' . $product->primaryImage->path) : null;
        } else {
            $result = $service->generateCustomPost($data['description'] ?: 'სმარტ საათების მაღაზია MyTechnic.ge', $data['language'], $data['tone']);
            $imageUrl = null;
        }

        if (! $result['success']) {
            Notification::make()
                ->title('AI generation failed.')
                ->danger()
                ->body($result['error'] ?? 'Unknown error')
                ->send();

            return;
        }

        $state = $this->form->getState();
        $this->form->fill(array_merge($state, [
            'message' => $result['content'],
            'ai_prompt' => $result['prompt'] ?? null,
            'product_id' => $data['product_id'] ?? ($state['product_id'] ?? null),
            'image_url' => $imageUrl ?: ($state['image_url'] ?? null),
        ]));

        Notification::make()
            ->title('AI content generated.')
            ->success()
            ->send();
    }

    protected function getProductOptions(): array
    {
        return Product::active()
            ->orderBy('name_ka')
            ->get()
            ->mapWithKeys(fn (Product $product) => [$product->id => $product->name_ka ?: $product->name_en])
            ->all();
    }
}
