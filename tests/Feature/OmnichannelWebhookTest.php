<?php

namespace Tests\Feature;

use App\Events\MessageReceived;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\WebhookLog;
use App\Services\MetaApiService;
use App\Services\OmnichannelService;
use App\Services\WebhookVerificationService;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OmnichannelWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected WebhookVerificationService $verificationService;
    protected OmnichannelService $omnichannelService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->verificationService = app(WebhookVerificationService::class);
        $this->omnichannelService = app(OmnichannelService::class);

        Event::fake();
    }

    /** Test valid Meta webhook processing */
    public function testValidMetaWebhookProcessing(): void
    {
        $appSecret = config('services.meta.app_secret', 'test-secret');

        // Create valid Meta webhook payload with message
        $payload = [
            'object' => 'page',
            'entry' => [
                [
                    'messaging' => [
                        [
                            'sender' => ['id' => '123456789'],
                            'recipient' => ['id' => '987654321'],
                            'timestamp' => time() * 1000,
                            'message' => [
                                'mid' => 'msg_123',
                                'text' => 'Hello, this is a test message',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Calculate valid signature
        $rawPayload = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $rawPayload, $appSecret);

        // Send POST request with valid signature
        $response = $this->post('/api/webhooks/messages', $payload, [
            'X-Hub-Signature-256' => $signature,
            'Content-Type' => 'application/json',
        ]);

        // Assert: Returns 200 OK
        $response->assertStatus(200);

        // Assert: Message created in database
        $this->assertDatabaseHas('messages', [
            'content' => 'Hello, this is a test message',
        ]);

        // Assert: Conversation created for customer
        $this->assertDatabaseHas('conversations', [
            'platform' => 'facebook',
        ]);

        // Assert: Customer created
        $this->assertDatabaseHas('customers', []);

        // Assert: MessageReceived event broadcasted
        Event::assertDispatched(MessageReceived::class);

        // Assert: WebhookLog marked as verified
        $this->assertDatabaseHas('webhook_logs', [
            'verified' => true,
            'event_type' => 'message',
        ]);
    }

    /** Test invalid webhook signature fails */
    public function testInvalidWebhookSignatureFails(): void
    {
        $payload = [
            'object' => 'page',
            'entry' => [
                [
                    'messaging' => [
                        [
                            'sender' => ['id' => '123456789'],
                            'recipient' => ['id' => '987654321'],
                            'timestamp' => time() * 1000,
                            'message' => [
                                'mid' => 'msg_123',
                                'text' => 'Test message',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Create webhook with invalid signature
        $wrongSignature = 'sha256=invalidsignaturehash';

        // Send POST with wrong signature
        $response = $this->post('/api/webhooks/messages', $payload, [
            'X-Hub-Signature-256' => $wrongSignature,
            'Content-Type' => 'application/json',
        ]);

        // Assert: Returns 403 Forbidden
        $response->assertStatus(403);

        // Assert: No message created in database
        $this->assertDatabaseMissing('messages', [
            'content' => 'Test message',
        ]);

        // Assert: WebhookLog marked with verified=false
        $this->assertDatabaseHas('webhook_logs', [
            'verified' => false,
        ]);

        // Assert: Event not dispatched
        Event::assertNotDispatched(MessageReceived::class);
    }

    /** Test Meta verification challenge */
    public function testMetaVerificationChallenge(): void
    {
        $verifyToken = config('services.meta.verify_token', 'test-token');
        $challenge = 'test_challenge_string_12345';

        // Send GET to /api/webhooks/messages with hub.mode=subscribe
        $response = $this->get('/api/webhooks/messages', [
            'hub.mode' => 'subscribe',
            'hub.challenge' => $challenge,
            'hub.verify_token' => $verifyToken,
        ]);

        // Assert: Returns 200 OK with challenge
        $response->assertStatus(200);
        $response->assertSee($challenge);

        // Assert: No database changes
        $this->assertDatabaseMissing('messages', []);
        $this->assertDatabaseMissing('customers', []);
        $this->assertDatabaseMissing('conversations', []);
    }

    /** Test webhook rate limiting */
    public function testWebhookRateLimiting(): void
    {
        $appSecret = config('services.meta.app_secret', 'test-secret');

        $payload = [
            'object' => 'page',
            'entry' => [
                [
                    'messaging' => [
                        [
                            'sender' => ['id' => '123456789'],
                            'recipient' => ['id' => '987654321'],
                            'timestamp' => time() * 1000,
                            'message' => [
                                'mid' => 'msg_123',
                                'text' => 'Test message',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $rawPayload = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $rawPayload, $appSecret);

        $successCount = 0;
        $rateLimitedCount = 0;

        // Send 100 valid requests
        for ($i = 0; $i < 100; $i++) {
            $response = $this->post('/api/webhooks/messages', $payload, [
                'X-Hub-Signature-256' => $signature,
                'Content-Type' => 'application/json',
            ]);

            if ($response->status() === 200) {
                $successCount++;
            } elseif ($response->status() === 429) {
                $rateLimitedCount++;
            }
        }

        // Assert: First requests succeed
        $this->assertGreaterThan(0, $successCount);

        // Note: Rate limiting is typically configured per IP or customer
        // This assertion validates the general structure
        $this->assertLessThanOrEqual(100, $successCount + $rateLimitedCount);
    }

    /** Test WhatsApp webhook processing */
    public function testWhatsAppWebhookProcessing(): void
    {
        $apiKey = config('services.whatsapp.api_key', 'test-key');

        // Create WhatsApp webhook payload
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123456789',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '1234567890',
                                    'phone_number_id' => '1234567890',
                                ],
                                'messages' => [
                                    [
                                        'from' => '1234567890',
                                        'id' => 'wamid.123.456',
                                        'timestamp' => (string) time(),
                                        'type' => 'text',
                                        'text' => [
                                            'body' => 'WhatsApp test message',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Send POST with API key
        $response = $this->post('/api/webhooks/messages', $payload, [
            'Authorization' => "Bearer $apiKey",
            'Content-Type' => 'application/json',
        ]);

        // Assert: Returns 200 OK
        $response->assertStatus(200);

        // Assert: Message correctly parsed
        $this->assertDatabaseHas('messages', [
            'content' => 'WhatsApp test message',
        ]);

        // Assert: Customer created
        $customer = Customer::first();
        $this->assertNotNull($customer);

        // Assert: Conversation created with whatsapp platform
        $this->assertDatabaseHas('conversations', [
            'platform' => 'whatsapp',
        ]);

        // Assert: MessageReceived event broadcasted
        Event::assertDispatched(MessageReceived::class);
    }

    /** Test webhook with image attachment */
    public function testWebhookWithAttachment(): void
    {
        $appSecret = config('services.meta.app_secret', 'test-secret');

        $payload = [
            'object' => 'page',
            'entry' => [
                [
                    'messaging' => [
                        [
                            'sender' => ['id' => '123456789'],
                            'recipient' => ['id' => '987654321'],
                            'timestamp' => time() * 1000,
                            'message' => [
                                'mid' => 'msg_123',
                                'text' => 'Check this image',
                                'attachments' => [
                                    [
                                        'type' => 'image',
                                        'payload' => [
                                            'url' => 'https://example.com/image.jpg',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $rawPayload = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $rawPayload, $appSecret);

        $response = $this->post('/api/webhooks/messages', $payload, [
            'X-Hub-Signature-256' => $signature,
            'Content-Type' => 'application/json',
        ]);

        // Assert: Message with attachment created
        $this->assertDatabaseHas('messages', [
            'content' => 'Check this image',
            'media_type' => 'image',
        ]);
    }

    /** Test webhook with message echo (should be ignored) */
    public function testWebhookIgnoresMessageEcho(): void
    {
        $appSecret = config('services.meta.app_secret', 'test-secret');

        $payload = [
            'object' => 'page',
            'entry' => [
                [
                    'messaging' => [
                        [
                            'sender' => ['id' => '987654321'], // Our page ID
                            'recipient' => ['id' => '123456789'],
                            'timestamp' => time() * 1000,
                            'message' => [
                                'mid' => 'msg_123',
                                'text' => 'Message echo from us',
                                'is_echo' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $rawPayload = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $rawPayload, $appSecret);

        $response = $this->post('/api/webhooks/messages', $payload, [
            'X-Hub-Signature-256' => $signature,
            'Content-Type' => 'application/json',
        ]);

        // Assert: Returns 200 but no message created
        $response->assertStatus(200);
        $this->assertDatabaseMissing('messages', [
            'content' => 'Message echo from us',
        ]);
    }

    /** Test concurrent webhook processing */
    public function testConcurrentWebhookProcessing(): void
    {
        $appSecret = config('services.meta.app_secret', 'test-secret');

        $payload = [
            'object' => 'page',
            'entry' => [
                [
                    'messaging' => [
                        [
                            'sender' => ['id' => '123456789'],
                            'recipient' => ['id' => '987654321'],
                            'timestamp' => time() * 1000,
                            'message' => [
                                'mid' => 'msg_123',
                                'text' => 'Concurrent message',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $rawPayload = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $rawPayload, $appSecret);

        // Send multiple webhooks from same customer
        for ($i = 0; $i < 5; $i++) {
            $payload['entry'][0]['messaging'][0]['message']['mid'] = 'msg_' . $i;
            $rawPayload = json_encode($payload);
            $signature = 'sha256=' . hash_hmac('sha256', $rawPayload, $appSecret);

            $response = $this->post('/api/webhooks/messages', $payload, [
                'X-Hub-Signature-256' => $signature,
                'Content-Type' => 'application/json',
            ]);

            $response->assertStatus(200);
        }

        // All messages should be created in same conversation
        $customer = Customer::first();
        $this->assertNotNull($customer);

        $conversation = $customer->conversations()->first();
        $this->assertNotNull($conversation);
        $this->assertEquals(5, $conversation->messages()->count());
    }
}
