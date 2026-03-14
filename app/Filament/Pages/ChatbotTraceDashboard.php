<?php

namespace App\Filament\Pages;

use App\Services\Chatbot\WidgetTraceReadService;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class ChatbotTraceDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'AI Lab';

    protected static ?int $navigationSort = 23;

    protected static ?string $title = 'ჩატბოტის ტრეის მონიტორინგი';

    protected static ?string $navigationLabel = 'ტრეის მონიტორინგი';

    protected static ?string $slug = 'chatbot-trace-dashboard';

    protected static string $view = 'filament.pages.chatbot-trace-dashboard';

    #[Url(as: 'hours')]
    public int $hours = 24;

    #[Url(as: 'step')]
    public string $stepSearch = '';

    #[Url(as: 'fallback')]
    public bool $fallbackOnly = false;

    #[Url(as: 'multi')]
    public bool $multiAgentOnly = false;

    #[Url(as: 'limit')]
    public int $limit = 300;

    public function resetFilters(): void
    {
        $this->hours = 24;
        $this->stepSearch = '';
        $this->fallbackOnly = false;
        $this->multiAgentOnly = false;
        $this->limit = 300;
    }

    protected function getViewData(): array
    {
        $traceReader = app(WidgetTraceReadService::class);
        $snapshot = $traceReader->pipelineSnapshot(
            $this->hours,
            $this->stepSearch,
            $this->fallbackOnly,
            $this->multiAgentOnly,
            $this->limit
        );

        return [
            'entries' => $snapshot['entries'],
            'summary' => $snapshot['summary'],
            'meta' => $snapshot['meta'],
            'hourOptions' => [
                6 => 'ბოლო 6 საათი',
                12 => 'ბოლო 12 საათი',
                24 => 'ბოლო 24 საათი',
                48 => 'ბოლო 48 საათი',
                72 => 'ბოლო 72 საათი',
                168 => 'ბოლო 7 დღე',
            ],
            'limitOptions' => [
                100 => '100 ჩანაწერი',
                300 => '300 ჩანაწერი',
                500 => '500 ჩანაწერი',
                1000 => '1000 ჩანაწერი',
            ],
        ];
    }
}
