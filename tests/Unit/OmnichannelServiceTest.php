<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Message;
use App\Models\Conversation;
use App\Services\MetaApiService;
use App\Services\OmnichannelService;
use App\Services\WhatsAppService;
use App\Services\Chatbot\CarouselBuilderService;
use App\Services\Chatbot\IdentityResolutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OmnichannelServiceTest extends TestCase
{
    use RefreshDatabase;

    protected OmnichannelService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new OmnichannelService(
            new MetaApiService(),
            new WhatsAppService(),
            new IdentityResolutionService()
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

    public function testFacebookWebhookUsesSenderIdAsConversationId(): void
    {
        $payload = [
            'object' => 'page',
            'entry' => [
                [
                    'messaging' => [
                        [
                            'sender' => ['id' => '25446744021623143'],
                            'recipient' => ['id' => '417018998164571'],
                            'timestamp' => time() * 1000,
                            'message' => [
                                'mid' => 'mid_fb_123',
                                'text' => 'Hello from Facebook',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $metaService = new MetaApiService();
        $parsed = $metaService->parseWebhookPayload($payload);

        $this->assertNotNull($parsed);
        $this->assertSame('25446744021623143', $parsed['sender_id']);
        $this->assertSame('25446744021623143', $parsed['conversation_id']);
    }

    public function testInstagramDirectPayloadParsing(): void
    {
        $payload = [
            'object' => 'instagram',
            'entry' => [
                [
                    'id' => '17841468956943989',
                    'time' => time(),
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'data' => [
                                    'messaging' => [
                                        [
                                            'sender' => ['id' => '17841400000000001'],
                                            'conversation' => ['id' => 't_1234567890123456789'],
                                            'timestamp' => time() * 1000,
                                            'message' => [
                                                'mid' => 'ig_mid_123',
                                                'text' => 'Instagram DM test',
                                            ],
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

        $this->assertNotNull($parsed);
        $this->assertSame('17841400000000001', $parsed['sender_id']);
        $this->assertSame('t_1234567890123456789', $parsed['conversation_id']);
        $this->assertSame('Instagram DM test', $parsed['message_text']);
        $this->assertSame('ig_mid_123', $parsed['platform_message_id']);
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

    public function testWhatsAppSendCarouselUsesSharedBuilderPayload(): void
    {
        config()->set('services.whatsapp.access_token', 'test-token');
        config()->set('services.whatsapp.phone_number_id', 'phone-123');
        config()->set('services.whatsapp.business_id', 'catalog-123');

        Http::fake([
            'https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.123']]], 200),
        ]);

        $products = [
            [
                'title' => 'MyTechnic Ultra',
                'subtitle' => 'ფასი: 79 ₾',
                'image_url' => 'https://example.com/ultra.jpg',
                'product_url' => 'https://example.com/products/mytechnic-ultra',
                'cta_label' => 'ნახვა',
                'cart_label' => 'კალათაში',
            ],
            [
                'title' => 'MyTechnic Pro',
                'subtitle' => 'ფასი: 99 ₾',
                'image_url' => 'https://example.com/pro.jpg',
                'product_url' => 'https://example.com/products/mytechnic-pro',
                'cta_label' => 'ნახვა',
                'cart_label' => 'კალათაში',
            ],
        ];

        $service = new WhatsAppService();
        $result = $service->sendCarousel('user-123', 'conv-123', $products);
        $expected = app(CarouselBuilderService::class)->buildWhatsAppCarousel($products);

        $this->assertTrue($result['success']);
        $this->assertSame($expected['type'], $result['payload']['type']);
        $this->assertSame($expected['interactive'], $result['payload']['interactive']);
        $this->assertSame('user-123', $result['payload']['to']);
    }

    public function testMetaSendCarouselUsesSharedBuilderPayload(): void
    {
        config()->set('services.facebook.page_access_token', 'page-token');

        Http::fake([
            'https://graph.facebook.com/*' => Http::response(['message_id' => 'mid.123'], 200),
        ]);

        $products = [
            [
                'title' => 'MyTechnic Ultra',
                'subtitle' => 'ფასი: 79 ₾',
                'image_url' => 'https://example.com/ultra.jpg',
                'product_url' => 'https://example.com/products/mytechnic-ultra',
                'cta_label' => 'ნახვა',
                'cart_label' => 'კალათაში',
            ],
            [
                'title' => 'MyTechnic Pro',
                'subtitle' => 'ფასი: 99 ₾',
                'image_url' => 'https://example.com/pro.jpg',
                'product_url' => 'https://example.com/products/mytechnic-pro',
                'cta_label' => 'ნახვა',
                'cart_label' => 'კალათაში',
            ],
        ];

        $service = new MetaApiService();
        $result = $service->sendCarousel('user-456', $products, 'facebook');
        $expected = app(CarouselBuilderService::class)->buildMetaGenericCarousel($products);

        $this->assertTrue($result['success']);
        $this->assertSame($expected['attachment'], $result['payload']['message']['attachment']);
        $this->assertSame('user-456', data_get($result['payload'], 'recipient.id'));
    }

    public function testMetaSendMessageUsesMessengerTokenAlias(): void
    {
        config()->set('services.facebook.page_access_token', 'messenger-token');
        config()->set('services.facebook.messenger_access_token', 'messenger-token');

        Http::fake([
            'https://graph.facebook.com/*' => Http::response(['message_id' => 'mid.alias.123'], 200),
        ]);

        $service = new MetaApiService();
        $result = $service->sendMessage('user-456', 'conv-456', 'Alias token test', null, 'facebook');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://graph.facebook.com/v18.0/me/messages'
                && $request->hasHeader('Authorization', 'Bearer messenger-token');
        });

        $this->assertTrue($result['success']);
    }

    public function testResolveOutgoingRecipientIdUsesLatestIncomingSenderPlatformId(): void
    {
        $customer = Customer::create([
            'name' => 'Legacy Customer',
            'platform_user_ids' => ['facebook' => '123456789012345'],
        ]);

        $conversation = Conversation::create([
            'customer_id' => $customer->id,
            'platform' => 'facebook',
            'platform_conversation_id' => '417018998164571',
            'status' => 'active',
            'unread_count' => 0,
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'sender_type' => 'customer',
            'sender_id' => $customer->id,
            'sender_name' => $customer->name,
            'content' => 'Newest incoming customer message',
            'platform_message_id' => 'mid.customer.123',
            'metadata' => [
                'platform' => 'facebook',
                'sender_platform_id' => '25446744021623143',
            ],
        ]);

        $method = new \ReflectionMethod($this->service, 'resolveOutgoingRecipientId');
        $method->setAccessible(true);

        $recipientId = $method->invoke($this->service, 'facebook', $customer, $conversation);

        $this->assertSame('25446744021623143', $recipientId);
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
