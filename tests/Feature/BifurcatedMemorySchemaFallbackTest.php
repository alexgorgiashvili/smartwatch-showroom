<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Services\Chatbot\BifurcatedMemoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BifurcatedMemorySchemaFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function testPreferencesFallbackToMetadataWhenPreferencesColumnIsUnavailable(): void
    {
        Schema::table('customers', function ($table) {
            $table->dropColumn('preferences');
        });

        $customer = Customer::query()->create([
            'name' => 'Fallback User',
            'metadata' => [
                'chatbot_preferences' => [
                    'budget_max_gel' => 120,
                    'color' => 'blue',
                ],
            ],
        ]);

        $memory = app(BifurcatedMemoryService::class);

        $this->assertSame(120, data_get($memory->getUserPreferences($customer->id), 'budget_max_gel'));

        $memory->updateUserPreferences($customer->id, [
            'features' => ['gps'],
        ]);

        $customer->refresh();

        $this->assertSame(120, data_get($customer->metadata, 'chatbot_preferences.budget_max_gel'));
        $this->assertSame('blue', data_get($customer->metadata, 'chatbot_preferences.color'));
        $this->assertSame(['gps'], data_get($customer->metadata, 'chatbot_preferences.features'));
    }
}
