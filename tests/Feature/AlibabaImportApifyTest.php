<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\Chatbot\ChatbotContentSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class AlibabaImportApifyTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.openai.api_key', null);

        $this->admin = User::factory()->create([
            'is_admin' => true,
        ]);
    }

    public function testParseAcceptsApifyJsonAndReturnsProcessedPayload(): void
    {
        $apifyItem = [
            'url' => 'https://www.alibaba.com/product-detail/Smart-watch_1600123456789.html',
            'productId' => '1600123456789',
            'title' => 'Kids Smart Watch X1',
            'description' => '4G kids smartwatch with GPS tracking',
            'price' => 39.99,
            'currency' => 'USD',
            'images' => [
                'https://example.com/image-1.jpg',
                'https://example.com/image-2.jpg',
            ],
            'variants' => [
                ['name' => 'Black'],
                ['name' => 'Pink'],
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.products.import-alibaba.parse'), [
                'import_source' => 'apify',
                'apify_json' => json_encode($apifyItem, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.source_product_id', '1600123456789')
            ->assertJsonPath('data.source_url', 'https://www.alibaba.com/product-detail/Smart-watch_1600123456789.html')
            ->assertJsonStructure([
                'data' => [
                    'product' => ['name_en', 'name_ka', 'slug'],
                    'variants',
                    'images',
                ],
            ]);
    }

    public function testConfirmBlocksDuplicateByExternalProductId(): void
    {
        Product::create([
            'name_en' => 'Existing Watch',
            'name_ka' => 'არსებული საათი',
            'slug' => 'existing-watch',
            'external_source' => 'alibaba',
            'external_product_id' => '1600123456789',
            'external_source_url' => 'https://www.alibaba.com/product-detail/Existing_1600123456789.html',
            'price' => 100,
            'currency' => 'USD',
            'is_active' => true,
            'featured' => false,
        ]);

        $mock = Mockery::mock(ChatbotContentSyncService::class);
        $mock->shouldReceive('syncProduct')->never();
        $this->instance(ChatbotContentSyncService::class, $mock);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.products.import-alibaba.confirm'), [
                'source_url' => 'https://www.alibaba.com/product-detail/New-watch_1600123456789.html',
                'source_product_id' => '1600123456789',
                'name_en' => 'New Watch',
                'name_ka' => 'ახალი საათი',
                'currency' => 'USD',
                'variants' => [
                    ['name' => 'Default', 'quantity' => 0, 'low_stock_threshold' => 5],
                ],
            ]);

        $response
            ->assertStatus(409)
            ->assertJsonPath('message', 'This Alibaba product has already been imported.');

        $this->assertEquals(1, Product::count());
    }

    public function testParseAcceptsApifyUrlAndRunsLiveApifyActor(): void
    {
        config()->set('services.apify.token', 'test-token');
        config()->set('services.apify.actor_id', 'apify/web-scraper');
        config()->set('services.apify.base_url', 'https://api.apify.com/v2');

        Http::fake([
            'https://api.apify.com/v2/acts/apify~web-scraper/run-sync-get-dataset-items*' => Http::response([
                [
                    'url' => 'https://www.alibaba.com/product-detail/Watch_1600999999999.html',
                    'productId' => '1600999999999',
                    'title' => 'Live Parsed Watch',
                    'description' => 'Live dataset item from Apify run.',
                    'images' => [
                        'https://example.com/live-1.jpg',
                    ],
                    'variants' => [
                        ['name' => 'Blue'],
                    ],
                    'price' => 49.99,
                    'currency' => 'USD',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.products.import-alibaba.parse'), [
                'import_source' => 'apify',
                'url' => 'https://www.alibaba.com/product-detail/Watch_1600999999999.html',
            ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.source_product_id', '1600999999999')
            ->assertJsonPath('data.source_url', 'https://www.alibaba.com/product-detail/Watch_1600999999999.html')
            ->assertJsonPath('data.product.name_en', 'Live Parsed Watch');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/acts/apify~web-scraper/run-sync-get-dataset-items')
                && $request->hasHeader('Authorization', 'Bearer test-token');
        });
    }

    public function testParseMapsTemplateStyleApifyFields(): void
    {
        $apifyTemplateItem = [
            'url' => 'https://www.alibaba.com/product-detail/Template-watch_1600111111111.html',
            'productId' => '1600111111111',
            'pageTitle' => 'Template Parsed Watch',
            'h1' => 'Template Parsed Watch H1',
            'random_text_from_the_page' => 'Template description text.',
            'images' => ['https://example.com/template-1.jpg'],
            'variants' => [['name' => 'Silver']],
            'currency' => 'USD',
            'price' => 42,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.products.import-alibaba.parse'), [
                'import_source' => 'apify',
                'apify_json' => json_encode($apifyTemplateItem, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.product.name_en', 'Template Parsed Watch')
            ->assertJsonPath('data.product.description_en', 'Template description text.')
            ->assertJsonPath('data.source_product_id', '1600111111111');
    }

    public function testParseReturnsValidationErrorWhenApifyIsBlockedByCaptcha(): void
    {
        config()->set('services.apify.token', 'test-token');
        config()->set('services.apify.actor_id', 'apify/web-scraper');
        config()->set('services.apify.base_url', 'https://api.apify.com/v2');
        config()->set('services.apify.retry_with_residential', false);

        Http::fake([
            'https://api.apify.com/v2/acts/apify~web-scraper/run-sync-get-dataset-items*' => Http::response([
                [
                    'titleTag' => 'Captcha Interception',
                    'bodySample' => 'Sorry, we have detected unusual traffic from your network.',
                    'title' => '',
                    'description' => '',
                    'images' => [],
                    'variants' => [],
                    'specs' => [],
                    '#debug' => [
                        'errorMessages' => [],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.products.import-alibaba.parse'), [
                'import_source' => 'apify',
                'url' => 'https://www.alibaba.com/product-detail/blocked_1601113065262.html',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'Alibaba blocked crawler access (captcha/interception). Try APIFY_PROXY_COUNTRY, enable residential proxy, or import using manual JSON/Page Source fallback.');
    }

    public function testParseFromRawHtmlFillsSmartwatchSpecFieldsFromWindowDetailData(): void
    {
        $detailData = [
            'globalData' => [
                'product' => [
                    'productId' => 1601113065262,
                    'subject' => 'KT34 Smart Watch',
                    'price' => [
                        'productLadderPrices' => [
                            ['price' => 37.29],
                            ['price' => 33.90],
                        ],
                    ],
                    'productBasicProperties' => [
                        ['attrName' => 'Operation System', 'attrValue' => 'RTOS'],
                        ['attrName' => 'screen size', 'attrValue' => '1.85 Inch'],
                        ['attrName' => 'display type', 'attrValue' => 'IPS'],
                        ['attrName' => 'screen resolution', 'attrValue' => '240*240'],
                        ['attrName' => 'Brand', 'attrValue' => 'Wonlex'],
                        ['attrName' => 'model number', 'attrValue' => 'KT34'],
                        ['attrName' => 'memory size', 'attrValue' => '1GB+8GB'],
                        ['attrName' => 'case material', 'attrValue' => 'Rubber'],
                        ['attrName' => 'band material', 'attrValue' => 'Silica Gel'],
                        ['attrName' => 'camera', 'attrValue' => '< 3MP'],
                        ['attrName' => 'function', 'attrValue' => 'Calendar, Alarm Clock, Sleep Tracker'],
                    ],
                    'sku' => [
                        'skuAttrs' => [
                            [
                                'name' => 'color',
                                'values' => [
                                    ['name' => 'Black', 'color' => '#000000'],
                                ],
                            ],
                        ],
                    ],
                    'mediaItems' => [
                        [
                            'group' => 'photos',
                            'type' => 'image',
                            'imageUrl' => [
                                'big' => 'https://sc04.alicdn.com/kf/H231af9a72ece4b34a2870add53b09a31r.jpg',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $rawHtml = '<!DOCTYPE html><html><head><title>KT34</title><meta property="og:title" content="KT34 Smart Watch"></head><body>'
            . str_repeat('X', 1200)
            . '<script>window.detailData = ' . json_encode($detailData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>'
            . '</body></html>';

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.products.import-alibaba.parse'), [
                'import_source' => 'apify',
                'url' => 'https://www.alibaba.com/product-detail/2024-Newest-Style-4g-Smart-Watch_1601113065262.html',
                'raw_html' => $rawHtml,
            ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.source_product_id', '1601113065262')
            ->assertJsonPath('data.product.operating_system', 'RTOS')
            ->assertJsonPath('data.product.screen_size', '1.85 Inch')
            ->assertJsonPath('data.product.display_type', 'IPS')
            ->assertJsonPath('data.product.screen_resolution', '240*240')
            ->assertJsonPath('data.product.brand', 'Wonlex')
            ->assertJsonPath('data.product.model', 'KT34')
            ->assertJsonPath('data.product.memory_size', '1GB+8GB')
            ->assertJsonPath('data.product.case_material', 'Rubber')
            ->assertJsonPath('data.product.band_material', 'Silica Gel')
            ->assertJsonPath('data.product.camera', '< 3MP')
            ->assertJsonPath('data.product.price', 33.9)
            ->assertJsonFragment(['კალენდარი'])
            ->assertJsonPath('data.variants.0.name', 'შავი');
    }

        public function testParseFromRawHtmlExtractsSpecsFromProductDescriptionTable(): void
        {
                $rawHtml = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <title>Alibaba Product Detail</title>
    <meta property="og:title" content="Alibaba Smart Watch Listing">
</head>
<body>
    <h2>Product Description</h2>
    <table>
        <tr><td>Product Name</td><td>KT34 4G AMOLED Android Smart Watch</td></tr>
        <tr><td>Brand Name</td><td>Wonlex</td></tr>
        <tr><td>Colors</td><td>Pink, Blue, Black</td></tr>
        <tr><td>Memory (RAM/ROM)</td><td>RAM 1GB + ROM 8GB</td></tr>
        <tr><td>Warranty</td><td>1 year</td></tr>
        <tr><td>Operating system (OS)</td><td>Android 8.1</td></tr>
        <tr><td>Screen size</td><td>1.78 inch Amoled screen, 368*448 resolution</td></tr>
        <tr><td>Camera</td><td>Support, 0.3MP</td></tr>
        <tr><td>Battery</td><td>lithium-ion battery 800mAh</td></tr>
        <tr><td>Featured functions</td><td>1. SOS button 2. GPS navigation 3. Voice call</td></tr>
    </table>
    <div>https://sc04.alicdn.com/kf/H231af9a72ece4b34a2870add53b09a31r.jpg</div>
    <div>https://sc04.alicdn.com/kf/H949faf8fe589447ca8ffeb3d70aa7b241.jpg</div>
    <div>https://sc04.alicdn.com/kf/H95b68e8e2e824d779a9dbb0891f913dbr.jpg</div>
    <div>https://sc04.alicdn.com/kf/H8000a3c768884fb9b180bd8258f388b4E.jpg</div>
    <div>https://sc04.alicdn.com/kf/H0dde5ba6b9204a1d89ff03fcda73ca28G.jpg</div>
    <div>https://sc04.alicdn.com/kf/He6a5c2475ffc4af09310d65fd28b318fT.jpg</div>
</body>
</html>
HTML;

                $response = $this->actingAs($this->admin)
                        ->postJson(route('admin.products.import-alibaba.parse'), [
                                'import_source' => 'apify',
                                'url' => 'https://www.alibaba.com/product-detail/KT34_1601113065262.html',
                                'raw_html' => $rawHtml,
                        ]);

                $response
                        ->assertStatus(200)
                    ->assertJsonPath('data.product.name_en', 'KT34 4G AMOLED Android Smart Watch')
                        ->assertJsonPath('data.product.brand', 'Wonlex')
                    ->assertJsonPath('data.product.model', null)
                        ->assertJsonPath('data.product.memory_size', 'RAM 1GB + ROM 8GB')
                    ->assertJsonPath('data.product.warranty_months', 12)
                        ->assertJsonPath('data.product.operating_system', 'Android 8.1')
                        ->assertJsonPath('data.product.screen_resolution', '368*448')
                        ->assertJsonPath('data.product.battery_capacity_mah', 800)
                        ->assertJsonPath('data.product.camera', 'Support, 0.3MP')
                        ->assertJsonFragment(['SOS ღილაკი'])
                        ->assertJsonFragment(['GPS ნავიგაცია'])
                        ->assertJsonFragment(['ხმოვანი ზარი']);
        }

    public function testParseFromRawHtmlDeduplicatesImageVariantsFromMediaItems(): void
    {
        $detailData = [
            'globalData' => [
                'product' => [
                    'productId' => '1601113065262',
                    'mediaItems' => [
                        [
                            'type' => 'image',
                            'imageUrl' => [
                                'origin' => 'https://sc04.alicdn.com/kf/HAAA11111111111111111111111111111.jpg',
                                'big' => 'https://sc04.alicdn.com/kf/HAAA11111111111111111111111111111_960x960.jpg',
                                'normal' => 'https://sc04.alicdn.com/kf/HAAA11111111111111111111111111111_300x300.jpg',
                            ],
                        ],
                        [
                            'type' => 'image',
                            'imageUrl' => [
                                'origin' => 'https://sc04.alicdn.com/kf/HBBB22222222222222222222222222222.jpg',
                                'big' => 'https://sc04.alicdn.com/kf/HBBB22222222222222222222222222222_960x960.jpg',
                                'normal' => 'https://sc04.alicdn.com/kf/HBBB22222222222222222222222222222_300x300.jpg',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $rawHtml = '<!DOCTYPE html><html><head><title>KT34</title><meta property="og:title" content="KT34 Smart Watch"></head><body>'
            . str_repeat('X', 1200)
            . '<script>window.detailData = ' . json_encode($detailData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>'
            . '</body></html>';

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.products.import-alibaba.parse'), [
                'import_source' => 'apify',
                'url' => 'https://www.alibaba.com/product-detail/2024-Newest-Style-4g-Smart-Watch_1601113065262.html',
                'raw_html' => $rawHtml,
            ]);

        $response
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.images')
            ->assertJsonFragment(['https://sc04.alicdn.com/kf/HAAA11111111111111111111111111111.jpg'])
            ->assertJsonFragment(['https://sc04.alicdn.com/kf/HBBB22222222222222222222222222222.jpg']);
    }
}
