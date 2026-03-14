<?php

namespace App\Filament\Pages;

use App\Repositories\ConversationRepository;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Cache;

class Inbox extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $navigationGroup = 'Messaging';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Inbox';

    protected static ?string $navigationLabel = 'Inbox';

    protected static ?string $slug = 'inbox';

    protected static string $view = 'filament.pages.inbox';

    public function mount(): void
    {
        $this->markUserOnInboxPage();
    }

    public function dehydrate(): void
    {
        $this->markUserOnInboxPage();
    }

    protected function markUserOnInboxPage(): void
    {
        if (auth()->check()) {
            $sessionKey = 'user_' . auth()->id() . '_on_inbox_page';
            Cache::put($sessionKey, true, now()->addMinutes(2));
        }
    }

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = app(ConversationRepository::class)->getUnreadCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
