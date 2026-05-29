<?php

namespace Tests\Feature;

use App\Services\Chatbot\CircuitBreakerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CircuitBreakerWiringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.openai.key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1');
        config()->set('services.openai.intent_enabled', false);
        config()->set('services.pinecone.api_key', null);
        config()->set('services.pinecone.host', null);
        config()->set('services.cohere.api_key', null);
        config()->set('chatbot.circuit_breaker.enabled', true);
        config()->set('chatbot.circuit_breaker.threshold', 1);
        config()->set('chatbot.caching.enabled', false);
    }

    public function testCircuitBreakerTripsAfterAgentException(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Simulated network failure');
        });

        $circuit = app(CircuitBreakerService::class);
        $this->assertSame('closed', $circuit->getState()['state']);

        $this->postJson('/chatbot', ['message' => 'GPS საათი მაჩვენე']);

        $state = $circuit->getState();
        $this->assertSame('open', $state['state'], 'Circuit breaker must open after threshold failures');
        $this->assertGreaterThanOrEqual(1, $state['failures']);
    }

    public function testCircuitBreakerRecordsSuccessAndRemainsClosedOnGoodResponse(): void
    {
        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if (str_contains($request->url(), 'chat/completions')) {
                return Http::response([
                    'choices' => [['message' => ['content' => 'GPS საათი ხელმისაწვდომია.']]],
                ], 200);
            }

            return Http::response([], 200);
        });

        $circuit = app(CircuitBreakerService::class);

        $this->postJson('/chatbot', ['message' => 'GPS საათი მაჩვენე']);

        $state = $circuit->getState();
        $this->assertSame('closed', $state['state'], 'Circuit must remain closed after successful response');
    }

    public function testCircuitBreakerOpenReturnsFallbackWithoutCallingOpenAi(): void
    {
        $circuit = app(CircuitBreakerService::class);

        $circuit->recordFailure('forced open for test');
        $circuit->recordFailure('forced open for test');
        $circuit->recordFailure('forced open for test');
        $circuit->recordFailure('forced open for test');
        $circuit->recordFailure('forced open for test');

        config()->set('chatbot.circuit_breaker.threshold', 5);

        Http::fake();

        $response = $this->postJson('/chatbot', ['message' => 'GPS საათი მაჩვენე']);

        $response->assertStatus(200);

        Http::assertNotSent(fn ($req) => str_contains($req->url(), 'chat/completions'));
    }
}
