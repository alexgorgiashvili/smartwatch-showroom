<?php

namespace Tests\Unit;

use App\Services\Chatbot\ConversationMemoryService;
use Tests\TestCase;

class ConversationMemoryServiceTest extends TestCase
{
    public function testStandaloneMessageDoesNotReuseStoredPreferences(): void
    {
        $service = new ConversationMemoryService();

        $scoped = $service->scopePreferencesForMessage(
            [
                'budget_max_gel' => 20,
                'color' => 'black',
                'size' => 'პატარა',
                'features' => ['gps', 'sos'],
                'excluded_features' => ['camera', 'calls'],
            ],
            'მხოლოდ ლოკაცია და გადაადგილების ისტორია მინდა, ზარი და კამერა საერთოდ არ არის მნიშვნელოვანი'
        );

        $this->assertArrayNotHasKey('budget_max_gel', $scoped);
        $this->assertArrayNotHasKey('color', $scoped);
        $this->assertArrayNotHasKey('size', $scoped);
        $this->assertSame(['camera', 'calls'], $scoped['excluded_features'] ?? []);
    }

    public function testContextualFollowUpCanReuseStoredPreferences(): void
    {
        $service = new ConversationMemoryService();

        $scoped = $service->scopePreferencesForMessage(
            [
                'budget_max_gel' => 20,
                'color' => 'blue',
                'features' => ['gps'],
            ],
            'იგივე მოდელი შავში თუ არ არის, შემდეგი ყველაზე მარტივი რა გაქვთ?'
        );

        $this->assertSame(20, $scoped['budget_max_gel'] ?? null);
        $this->assertSame('black', $scoped['color'] ?? null);
        $this->assertContains('gps', $scoped['features'] ?? []);
    }
}
