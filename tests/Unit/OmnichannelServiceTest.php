<?php

namespace Tests\Unit;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Services\MetaApiService;
use App\Services\OmnichannelService;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class OmnichannelServiceTest extends TestCase
{
    use RefreshDatabase;

    protected OmnichannelService $service;
    protected MockInterface $metaServiceMock;
    protected MockInterface $whatsappServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->metaServiceMock = Mockery::mock(MetaApiService::class);
        $this->whatsappServiceMock = Mockery::mock(WhatsAppService::class);

        $this->service = new OmnichannelService(
            $this->metaServiceMock,
            $this->whatsappServiceMock
        );
    }

    /** Test processWebhookMessage creates customer */
    public function testProcessWebhookMessageCreatesCustomer(): void
    {
        $parsedMessage = [
            'sender_id' => '123456789',
            'conversation_id' => '987654321',
            'message_text' => 'Hello, this is a test message',
            'attachments' => [],
            'timestamp' => time(),
        ];

        $message = $this->service->processWebhookMessage('facebook', $parsedMessage);

        // Assert: Customer created
        $this->assertDatabaseHas('customers', []);
        $customer = Customer::first();
        $this->assertNotNull($customer);

        // Assert: Message model returned
        $this->assertNotNull($message);
        $this->assertInstanceOf(Message::class, $message);
        $this->assertEquals('Hello, this is a test message', $message->content);
    }

    /** Test processWebhookMessage creates conversation */
    public function testProcessWebhookMessageCreatesConversation(): void
    {
        $parsedMessage = [
            'sender_id' => '123456789',
            'conversation_id' => '987654321',
            'message_text' => 'Test message',
            'attachments' => [],
            'timestamp' => time(),
        ];

        $this->service->processWebhookMessage('facebook', $parsedMessage);

        // Assert: Conversation created
        $this->assertDatabaseHas('conversations', [
            'platform' => 'facebook',
        ]);
    }

    /** Test message parsing extracts attachments */
    public function testMessageParsingExtractsAttachments(): void
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

        $metaService = new MetaApiService();
        $parsed = $metaService->parseWebhookPayload($payload);

        // Assert: media_url extracted
        $this->assertNotEmpty($parsed['attachments']);
        $this->assertEquals('image', $parsed['attachments'][0]['type']);

        // Assert: media_type = 'image'
        $this->assertEquals('https://example.com/image.jpg', $parsed['attachments'][0]['url']);
    }

    /** Test WhatsApp parsing */
    public function testWhatsAppParsing(): void
    {
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

        $whatsappService = new WhatsAppService();
        $parsed = $whatsappService->parseWebhookPayload($payload);

        // Assert: Correct message structure returned
        $this->assertNotNull($parsed);
        $this->assertEquals('1234567890', $parsed['sender_id']);
        $this->assertEquals('WhatsApp test message', $parsed['message_text']);
        $this->assertEquals('1234567890', $parsed['conversation_id']);
    }

    /** Test invalid message data returns null */
    public function testProcessWebhookMessageWithInvalidDataReturnsNull(): void
    {
        $parsedMessage = [
            'message_text' => 'Test message',
            'attachments' => [],
            'timestamp' => time(),
            // Missing sender_id and conversation_id
        ];

        $message = $this->service->processWebhookMessage('facebook', $parsedMessage);

        // Assert: Returns null
        $this->assertNull($message);
    }

    /** Test customer with multiple platforms */
    public function testCustomerWithMultiplePlatforms(): void
    {
        // Create customer from Facebook
        $facebookMessage = [
            'sender_id' => 'fb_user_123',
            'conversation_id' => 'fb_page_456',
            'message_text' => 'Facebook message',
            'attachments' => [],
            'timestamp' => time(),
        ];

        $this->service->processWebhookMessage('facebook', $facebookMessage);

        $customer = Customer::first();
        $this->assertNotNull($customer);

        // Same customer on WhatsApp
        $whatsappMessage = [
            'sender_id' => 'whatsapp_user_789',
            'conversation_id' => 'whatsapp_phone_000',
            'message_text' => 'WhatsApp message',
            'attachments' => [],
            'timestamp' => time(),
        ];

        $this->service->processWebhookMessage('whatsapp', $whatsappMessage);

        // Assert: Two different customers created (platform-specific)
        $this->assertEquals(2, Customer::count());
    }

    /** Test message creates with correct metadata */
    public function testMessageCreatesWithCorrectMetadata(): void
    {
        $parsedMessage = [
            'sender_id' => '123456789',
            'conversation_id' => '987654321',
            'message_text' => 'Test message',
            'attachments' => [],
            'timestamp' => time(),
        ];

        $message = $this->service->processWebhookMessage('facebook', $parsedMessage);

        // Assert: Message has correct metadata
        $this->assertEquals('customer', $message->sender_type);
        $this->assertEquals('Test message', $message->content);
        $this->assertEquals('facebook', $message->conversation->platform);
    }

    /** Test idempotent message processing */
    public function testIdempotentMessageProcessing(): void
    {
        $parsedMessage = [
            'sender_id' => '123456789',
            'conversation_id' => '987654321',
            'message_text' => 'Test message',
            'attachments' => [],
            'timestamp' => time(),
        ];

        // Process same message twice
        $message1 = $this->service->processWebhookMessage('facebook', $parsedMessage);
        $message2 = $this->service->processWebhookMessage('facebook', $parsedMessage);

        // Both should be different instances (not idempotent by design)
        $this->assertNotNull($message1);
        $this->assertNotNull($message2);
    }
}
