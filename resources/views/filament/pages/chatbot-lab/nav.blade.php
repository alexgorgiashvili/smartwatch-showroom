<div class="flex flex-wrap gap-2">
    <x-filament::button :href="\App\Filament\Pages\ChatbotLabManual::getUrl()" tag="a" :color="request()->routeIs('filament.admin.pages.chatbot-lab') ? 'primary' : 'gray'">
        Manual Lab
    </x-filament::button>
    <x-filament::button :href="\App\Filament\Pages\ChatbotLabCases::getUrl()" tag="a" :color="request()->routeIs('filament.admin.pages.chatbot-lab-cases') ? 'primary' : 'gray'">
        Training Cases
    </x-filament::button>
    <x-filament::button :href="\App\Filament\Pages\ChatbotLabRuns::getUrl()" tag="a" :color="request()->routeIs('filament.admin.pages.chatbot-lab-runs') || request()->routeIs('filament.admin.pages.chatbot-lab-runs.show') ? 'primary' : 'gray'">
        Evaluation Runs
    </x-filament::button>
    <x-filament::button :href="\App\Filament\Pages\ChatbotTraceDashboard::getUrl()" tag="a" :color="request()->routeIs('filament.admin.pages.chatbot-trace-dashboard') ? 'primary' : 'gray'">
        ტრეის მონიტორინგი
    </x-filament::button>
</div>
