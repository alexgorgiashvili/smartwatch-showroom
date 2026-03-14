<?php

namespace App\Filament\Pages;

use App\Models\ContactSetting;
use App\Models\Faq;
use App\Services\Chatbot\ChatbotContentSyncService;
use Filament\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ChatbotContent extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    protected static ?string $navigationGroup = 'AI Lab';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Chatbot Content';

    protected static ?string $navigationLabel = 'Chatbot Content';

    protected static string $view = 'filament.pages.chatbot-content';

    protected static ?string $slug = 'chatbot-content';

    public ?array $contactData = [];

    public function mount(): void
    {
        $this->contactForm->fill(ContactSetting::allKeyed());
    }

    protected function getForms(): array
    {
        return [
            'contactForm',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->createFaqAction(),
        ];
    }

    public function contactForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Contact Settings')
                    ->description('These values are synced to the chatbot knowledge base after every save.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('phone_display')
                                    ->label('Phone Display')
                                    ->required()
                                    ->maxLength(80),
                                TextInput::make('phone_link')
                                    ->label('Phone Link Digits')
                                    ->required()
                                    ->maxLength(30),
                                TextInput::make('whatsapp_url')
                                    ->label('WhatsApp URL')
                                    ->url()
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('location')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('hours')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('instagram_url')
                                    ->label('Instagram URL')
                                    ->url()
                                    ->maxLength(255),
                                TextInput::make('facebook_url')
                                    ->label('Facebook URL')
                                    ->url()
                                    ->maxLength(255),
                                TextInput::make('messenger_url')
                                    ->label('Messenger URL')
                                    ->url()
                                    ->maxLength(255),
                                TextInput::make('telegram_url')
                                    ->label('Telegram URL')
                                    ->url()
                                    ->maxLength(255),
                            ]),
                    ]),
            ])
            ->statePath('contactData');
    }

    public function saveContacts(ChatbotContentSyncService $syncService): void
    {
        $data = $this->contactForm->getState();

        foreach ($data as $key => $value) {
            ContactSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        $synced = $syncService->syncContacts(ContactSetting::allKeyed());

        Notification::make()
            ->title('Contact settings saved.')
            ->success()
            ->send();

        if (! $synced) {
            Notification::make()
                ->title('Contact data was saved, but chatbot sync failed.')
                ->warning()
                ->send();
        }
    }

    public function createFaqAction(): Action
    {
        return Action::make('createFaq')
            ->label('Add FAQ')
            ->icon('heroicon-o-plus')
            ->modalHeading('Add FAQ entry')
            ->form($this->getFaqFormSchema())
            ->action(function (array $data): void {
                $faq = Faq::query()->create($this->normalizeFaqData($data));
                $synced = app(ChatbotContentSyncService::class)->syncFaq($faq);

                Notification::make()
                    ->title('FAQ created.')
                    ->success()
                    ->send();

                if (! $synced) {
                    Notification::make()
                        ->title('FAQ saved, but chatbot sync failed.')
                        ->warning()
                        ->send();
                }
            });
    }

    public function editFaqAction(): Action
    {
        return Action::make('editFaq')
            ->label('Edit')
            ->icon('heroicon-o-pencil-square')
            ->modalHeading('Edit FAQ entry')
            ->form($this->getFaqFormSchema())
            ->fillForm(function (array $arguments): array {
                $faq = Faq::query()->findOrFail($arguments['faq']);

                return [
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                    'category' => $faq->category,
                    'sort_order' => $faq->sort_order,
                    'is_active' => $faq->is_active,
                ];
            })
            ->action(function (array $data, array $arguments): void {
                $faq = Faq::query()->findOrFail($arguments['faq']);
                $faq->update($this->normalizeFaqData($data));

                $synced = app(ChatbotContentSyncService::class)->syncFaq($faq);

                Notification::make()
                    ->title('FAQ updated.')
                    ->success()
                    ->send();

                if (! $synced) {
                    Notification::make()
                        ->title('FAQ saved, but chatbot sync failed.')
                        ->warning()
                        ->send();
                }
            });
    }

    public function deleteFaqAction(): Action
    {
        return Action::make('deleteFaq')
            ->label('Delete')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete FAQ entry?')
            ->action(function (array $arguments): void {
                $faq = Faq::query()->findOrFail($arguments['faq']);

                app(ChatbotContentSyncService::class)->deactivateFaq($faq);
                $faq->delete();

                Notification::make()
                    ->title('FAQ deleted.')
                    ->success()
                    ->send();
            });
    }

    protected function getViewData(): array
    {
        return [
            'faqs' => Faq::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        ];
    }

    private function getFaqFormSchema(): array
    {
        return [
            TextInput::make('question')
                ->required()
                ->maxLength(255),
            Textarea::make('answer')
                ->required()
                ->rows(5)
                ->columnSpanFull(),
            Grid::make(3)
                ->schema([
                    TextInput::make('category')
                        ->required()
                        ->maxLength(120)
                        ->default('Other'),
                    TextInput::make('sort_order')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->required(),
                    Toggle::make('is_active')
                        ->default(true),
                ]),
        ];
    }

    private function normalizeFaqData(array $data): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
