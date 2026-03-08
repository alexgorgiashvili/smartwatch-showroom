<?php

namespace Tests\Feature;

use App\Services\Chatbot\ChatPipelineService;
use App\Services\Chatbot\IntentResult;
use App\Services\Chatbot\PipelineResult;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ChatbotWidgetProductSuppressionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testWidgetDoesNotAttachProductsWhenPipelineHasExplicitFallbackReason(): void
    {
        $this->bindWidgetMocks($this->makePipelineResult(
            response: 'ბოდიში, სერვისი დროებით მიუწვდომელია.',
            intent: $this->makeIntent('price_query', 0.96),
            fallbackReason: 'provider_unavailable'
        ));

        $response = $this->postJson('/chatbot', [
            'message' => 'MyTechnic Ultra რა ღირს?',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('debug.fallback_reason', 'provider_unavailable')
            ->assertJsonPath('debug.carousel_suppressed', true)
            ->assertJsonMissingPath('products');
    }

    public function testWidgetDoesNotAttachGenericRecommendationCardsWithoutMentionedProduct(): void
    {
        $this->bindWidgetMocks($this->makePipelineResult(
            response: 'გირჩევთ რამდენიმე ვარიანტის შედარებას თქვენი საჭიროებების მიხედვით.',
            intent: $this->makeIntent('recommendation', 0.88)
        ));

        $response = $this->postJson('/chatbot', [
            'message' => 'რაიმე საათი მირჩიეთ',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('debug.fallback_reason', null)
            ->assertJsonPath('debug.products_found', 2)
            ->assertJsonPath('debug.products_attached', 0)
            ->assertJsonPath('debug.carousel_suppressed', true)
            ->assertJsonMissingPath('products');
    }

    public function testWidgetSuppressesPriceCardsWhenIntentMatchIsAmbiguous(): void
    {
        $this->bindWidgetMocks($this->makePipelineResult(
            response: 'ფასი დამოკიდებულია არჩეულ კონფიგურაციაზე და დაგიზუსტებთ შეკვეთისას.',
            intent: $this->makeIntent(
                intent: 'price_query',
                confidence: 0.95,
                model: 'Ultra',
                slugHint: null,
                searchKeywords: ['MyTechnic', 'Ultra']
            ),
            products: [
                [
                    'name' => 'MyTechnic Ultra Pro',
                    'slug' => 'mytechnic-ultra-pro',
                    'price' => 349,
                    'sale_price' => null,
                    'is_in_stock' => true,
                    'url' => url('/products/mytechnic-ultra-pro'),
                    'image' => '/storage/products/mytechnic-ultra-pro.jpg',
                ],
                [
                    'name' => 'MyTechnic Ultra Max',
                    'slug' => 'mytechnic-ultra-max',
                    'price' => 379,
                    'sale_price' => null,
                    'is_in_stock' => true,
                    'url' => url('/products/mytechnic-ultra-max'),
                    'image' => '/storage/products/mytechnic-ultra-max.jpg',
                ],
            ]
        ));

        $response = $this->postJson('/chatbot', [
            'message' => 'MyTechnic Ultra რა ღირს?',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('debug.products_found', 2)
            ->assertJsonPath('debug.products_attached', 0)
            ->assertJsonPath('debug.carousel_suppressed', true)
            ->assertJsonMissingPath('products');
    }

    public function testWidgetAttachesSingleCardForUniqueExactIntentMatch(): void
    {
        $this->bindWidgetMocks($this->makePipelineResult(
            response: 'ეს მოდელი ახლა ხელმისაწვდომია 299 ლარად.',
            intent: $this->makeIntent('price_query', 0.97),
            products: [
                [
                    'name' => 'MyTechnic Ultra',
                    'slug' => 'mytechnic-ultra',
                    'price' => 299,
                    'sale_price' => null,
                    'is_in_stock' => true,
                    'url' => url('/products/mytechnic-ultra'),
                    'image' => '/storage/products/mytechnic-ultra.jpg',
                ],
                [
                    'name' => 'MyTechnic Ultra Pro',
                    'slug' => 'mytechnic-ultra-pro',
                    'price' => 349,
                    'sale_price' => null,
                    'is_in_stock' => true,
                    'url' => url('/products/mytechnic-ultra-pro'),
                    'image' => '/storage/products/mytechnic-ultra-pro.jpg',
                ],
            ]
        ));

        $response = $this->postJson('/chatbot', [
            'message' => 'MyTechnic Ultra რა ღირს?',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('debug.products_found', 2)
            ->assertJsonPath('debug.products_attached', 1)
            ->assertJsonPath('debug.carousel_suppressed', false)
            ->assertJsonPath('products.0.name', 'MyTechnic Ultra');
    }

    public function testWidgetAttachesOnlyDirectlyMentionedProductsAndDropsLowPriceNoise(): void
    {
        $this->bindWidgetMocks($this->makePipelineResult(
            response: 'თქვენი ბიუჯეტის ფარგლებში გირჩევთ: **Wonlex CT23** 16.50 ლარად და **Q12** 4.18 ლარად.',
            intent: $this->makeIntent('recommendation', 0.94, brand: null, model: null, slugHint: null, searchKeywords: ['20 ლარის ფარგლებში']),
            products: [
                [
                    'name' => 'Wonlex CT23',
                    'slug' => 'wonlex-ct23',
                    'price' => 16.50,
                    'sale_price' => null,
                    'is_in_stock' => true,
                    'url' => url('/products/wonlex-ct23'),
                    'image' => '/storage/products/wonlex-ct23.jpg',
                ],
                [
                    'name' => 'Q12',
                    'slug' => 'q12-watch',
                    'price' => 4.18,
                    'sale_price' => null,
                    'is_in_stock' => true,
                    'url' => url('/products/q12-watch'),
                    'image' => '/storage/products/q12-watch.jpg',
                ],
                [
                    'name' => 'Q19',
                    'slug' => '2g-network-kids-smart-watch-anti-lost-sos-gps-video-call',
                    'price' => 0.01,
                    'sale_price' => null,
                    'is_in_stock' => true,
                    'url' => url('/products/q19-watch'),
                    'image' => '/storage/products/q19-watch.jpg',
                ],
            ]
        ));

        $response = $this->postJson('/chatbot', [
            'message' => 'რამე 20 ლარის ფარგლებში გაქვთ?',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('debug.products_found', 3)
            ->assertJsonPath('debug.products_attached', 2)
            ->assertJsonPath('products.0.name', 'Wonlex CT23')
            ->assertJsonPath('products.1.name', 'Q12')
            ->assertJsonMissingPath('products.2');
    }

    public function testWidgetLinkMentionDoesNotAttachOtherFourGRecommendationCards(): void
    {
        $this->bindWidgetMocks($this->makePipelineResult(
            response: 'თუ გსურთ კომპაქტური ვარიანტი, [Wonlex CT27](' . url('/products/ct27-ultra-small-4g-rtos-gps-watch') . ') კარგი არჩევანია.',
            intent: $this->makeIntent('recommendation', 0.91, brand: null, model: null, slugHint: null, searchKeywords: ['GPS', 'SOS']),
            products: [
                [
                    'name' => 'Wonlex CT27',
                    'slug' => 'ct27-ultra-small-4g-rtos-gps-watch',
                    'price' => 20.50,
                    'sale_price' => null,
                    'is_in_stock' => true,
                    'url' => url('/products/ct27-ultra-small-4g-rtos-gps-watch'),
                    'image' => '/storage/products/ct27.jpg',
                ],
                [
                    'name' => 'Wonlex KT34',
                    'slug' => '2024-newest-style-4g-smart-watch-sos-call-wifi-lbs-gps-tracker-android-smart-watch-kid-sos-camera-alarm-clock-kt34',
                    'price' => 33.90,
                    'sale_price' => null,
                    'is_in_stock' => true,
                    'url' => url('/products/2024-newest-style-4g-smart-watch-sos-call-wifi-lbs-gps-tracker-android-smart-watch-kid-sos-camera-alarm-clock-kt34'),
                    'image' => '/storage/products/kt34.jpg',
                ],
                [
                    'name' => 'Wonlex KT20',
                    'slug' => 'wonlex-kt20-4g-waterproof-smart-watch',
                    'price' => 33.00,
                    'sale_price' => null,
                    'is_in_stock' => true,
                    'url' => url('/products/wonlex-kt20-4g-waterproof-smart-watch'),
                    'image' => '/storage/products/kt20.jpg',
                ],
            ]
        ));

        $response = $this->postJson('/chatbot', [
            'message' => 'კომპაქტური მოდელი მინდა GPS-ით',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('debug.products_found', 3)
            ->assertJsonPath('debug.products_attached', 1)
            ->assertJsonPath('products.0.name', 'Wonlex CT27')
            ->assertJsonMissingPath('products.1');
    }

    public function testWidgetDoesNotAttachCardsWhenResponseSaysCatalogHasNoAdultSmartwatch(): void
    {
        $this->bindWidgetMocks($this->makePipelineResult(
            response: 'ჩვენი კატალოგი ამ ეტაპზე ძირითადად საბავშვო სმარტსაათებზეა ფოკუსირებული და ზრდასრულის smartwatch-ები არ გვაქვს.',
            intent: $this->makeIntent('stock_query', 0.9, brand: null, model: null, slugHint: null, searchKeywords: ['smartwatch', 'ზრდასრულის']),
            products: [
                [
                    'name' => 'Q12',
                    'slug' => 'q12-watch',
                    'price' => 4.18,
                    'sale_price' => null,
                    'is_in_stock' => true,
                    'url' => url('/products/q12-watch'),
                    'image' => '/storage/products/q12-watch.jpg',
                ],
                [
                    'name' => 'T53',
                    'slug' => 't53-watch',
                    'price' => 16.8,
                    'sale_price' => null,
                    'is_in_stock' => true,
                    'url' => url('/products/t53-watch'),
                    'image' => '/storage/products/t53-watch.jpg',
                ],
            ]
        ));

        $response = $this->postJson('/chatbot', [
            'message' => 'არ მინდა საბავშვო, ზრდასრულის smartwatch გაქვთ?',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('debug.products_found', 2)
            ->assertJsonPath('debug.products_attached', 0)
            ->assertJsonPath('debug.carousel_suppressed', true)
            ->assertJsonMissingPath('products');
    }

    private function bindWidgetMocks(PipelineResult $result): void
    {
        $pipelineMock = Mockery::mock(ChatPipelineService::class);
        $pipelineMock->shouldReceive('process')
            ->once()
            ->andReturn($result);

        $pushMock = Mockery::mock(PushNotificationService::class);
        $pushMock->shouldReceive('sendToAdmins')
            ->once()
            ->andReturn(false);

        $this->app->instance(ChatPipelineService::class, $pipelineMock);
        $this->app->instance(PushNotificationService::class, $pushMock);
    }

    private function makeIntent(
        string $intent,
        float $confidence,
        ?string $brand = 'MyTechnic',
        ?string $model = 'Ultra',
        ?string $slugHint = 'mytechnic-ultra',
        array $searchKeywords = ['MyTechnic', 'Ultra']
    ): IntentResult
    {
        return new IntentResult(
            'MyTechnic Ultra რა ღირს?',
            $intent,
            $brand,
            $model,
            $slugHint,
            null,
            null,
            true,
            $searchKeywords,
            false,
            $confidence,
            25,
            false
        );
    }

    private function makePipelineResult(
        string $response,
        IntentResult $intent,
        ?string $fallbackReason = null,
        bool $regenerationAttempted = false,
        bool $regenerationSucceeded = false,
        ?array $products = null
    ): PipelineResult {
        return new PipelineResult(
            $response,
            1,
            '',
            $intent,
            [
                'products' => $products ?? [
                    [
                        'name' => 'MyTechnic Ultra',
                        'slug' => 'mytechnic-ultra',
                        'price' => 299,
                        'sale_price' => null,
                        'is_in_stock' => true,
                        'url' => url('/products/mytechnic-ultra'),
                        'image' => '/storage/products/mytechnic-ultra.jpg',
                    ],
                    [
                        'name' => 'MyTechnic Neo',
                        'slug' => 'mytechnic-neo',
                        'price' => 199,
                        'sale_price' => null,
                        'is_in_stock' => true,
                        'url' => url('/products/mytechnic-neo'),
                        'image' => '/storage/products/mytechnic-neo.jpg',
                    ],
                ],
                'allowed_urls' => collect($products ?? [
                    [
                        'url' => url('/products/mytechnic-ultra'),
                    ],
                    [
                        'url' => url('/products/mytechnic-neo'),
                    ],
                ])->pluck('url')->filter()->values()->all(),
            ],
            true,
            null,
            true,
            [],
            true,
            20,
            $fallbackReason,
            $regenerationAttempted,
            $regenerationSucceeded
        );
    }
}
