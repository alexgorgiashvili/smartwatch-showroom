<?php

namespace Tests\Unit;

use App\Services\Chatbot\IntentResult;
use App\Services\Chatbot\ResponseValidatorService;
use Tests\TestCase;

class ResponseValidatorServiceTest extends TestCase
{
    public function testWidgetV2RejectsPriceWithoutLiveCatalogEvidence(): void
    {
        $service = new ResponseValidatorService();
        $context = ['products' => [], 'require_live_catalog_evidence' => true];

        $result = $service->validatePriceIntegrity('ეს მოდელი ღირს 199 ₾.', $context);
        $this->assertFalse($result->isValid());
        $this->assertSame('price_without_live_catalog', $result->violations()[0]['type']);

        $budget = $service->validatePriceIntegrity('თქვენი ბიუჯეტი 200 ლარამდეა.', $context);
        $this->assertTrue($budget->isValid());
    }

    public function testWidgetV2RejectsUnsupportedStockAndInexactCatalogPrice(): void
    {
        $service = new ResponseValidatorService();
        $empty = [
            'products' => [],
            'require_live_catalog_evidence' => true,
            'catalog_intent' => 'stock_query',
        ];

        $stock = $service->validateStockClaims('ეს საათი მარაგშია.', $empty);
        $this->assertFalse($stock->isValid());
        $this->assertSame('stock_without_live_catalog', $stock->violations()[0]['type']);

        $priced = $service->validatePriceIntegrity('საათი ღირს 150 ₾.', [
            'products' => [['price' => 100], ['price' => 200]],
            'require_live_catalog_evidence' => true,
        ]);
        $this->assertFalse($priced->isValid());
        $this->assertSame('price_mismatch', $priced->violations()[0]['type']);
    }

    public function testBudgetPhraseDoesNotTriggerPriceMismatch(): void
    {
        $service = new ResponseValidatorService();

        $result = $service->validatePriceIntegrity(
            'თქვენი ბიუჯეტის ფარგლებში, 200 ლარამდე გირჩევთ რამდენიმე მოდელს.',
            [
                'products' => [
                    ['price' => 33.9, 'sale_price' => null],
                    ['price' => 16.5, 'sale_price' => null],
                    ['price' => 79.0, 'sale_price' => 69.0],
                ],
            ]
        );

        $this->assertTrue($result->isValid());
    }

    public function testActualUnknownPriceStillTriggersMismatch(): void
    {
        $service = new ResponseValidatorService();

        $result = $service->validatePriceIntegrity(
            'ეს მოდელი ღირს 200 ₾.',
            [
                'products' => [
                    ['price' => 33.9, 'sale_price' => null],
                    ['price' => 16.5, 'sale_price' => null],
                    ['price' => 79.0, 'sale_price' => 69.0],
                ],
            ]
        );

        $this->assertFalse($result->isValid());
        $this->assertSame('price_mismatch', $result->violations()[0]['type'] ?? null);
        $this->assertSame(200.0, $result->violations()[0]['price'] ?? null);
    }

    public function testRecommendationIntentDoesNotFailWholeValidationOnPricePhrase(): void
    {
        $service = new ResponseValidatorService();

        $intent = new IntentResult(
            '200 ლარამდე რას შემომთავაზებ',
            'recommendation',
            null,
            null,
            null,
            null,
            null,
            true,
            ['საბავშვო საათი'],
            false,
            0.92,
            120,
            false
        );

        $result = $service->validateAll(
            'თქვენი ბიუჯეტის ფარგლებში, 200 ლარამდე რამდენიმე ვარიანტი გვაქვს.',
            [
                'products' => [
                    ['price' => 33.9, 'sale_price' => null, 'is_in_stock' => true, 'url' => 'http://127.0.0.1:8000/products/kt34'],
                    ['price' => 79.0, 'sale_price' => 69.0, 'is_in_stock' => true, 'url' => 'http://127.0.0.1:8000/products/q12'],
                ],
                'allowed_urls' => ['http://127.0.0.1:8000/products/kt34', 'http://127.0.0.1:8000/products/q12'],
            ],
            $intent
        );

        $this->assertTrue($result->isValid());
    }

    public function testPriceQueryIntentStillEnforcesStrictPriceValidation(): void
    {
        $service = new ResponseValidatorService();

        $intent = new IntentResult(
            'MyTechnic Ultra რა ღირს?',
            'price_query',
            'MyTechnic',
            'Ultra',
            'mytechnic-ultra',
            null,
            null,
            true,
            ['MyTechnic', 'Ultra'],
            false,
            0.97,
            90,
            false
        );

        $result = $service->validateAll(
            'ეს მოდელი ღირს 200 ₾.',
            [
                'products' => [
                    ['price' => 79.0, 'sale_price' => 69.0, 'is_in_stock' => true, 'url' => 'http://127.0.0.1:8000/products/mytechnic-ultra'],
                ],
                'allowed_urls' => ['http://127.0.0.1:8000/products/mytechnic-ultra'],
            ],
            $intent
        );

        $this->assertFalse($result->isValid());
        $this->assertSame('price_mismatch', $result->violations()[0]['type'] ?? null);
    }

    public function testProductCodeLabelCannotPointToCatalogRoot(): void
    {
        $service = new ResponseValidatorService();

        $result = $service->validateUrls(
            'იხილეთ [CT23](http://smartwatch-showroom.test/products)',
            [
                'allowed_urls' => [
                    'http://smartwatch-showroom.test/products',
                    'http://smartwatch-showroom.test/products/wonlex-cheaper-kids-gps-smart-watch-4g-ct23',
                ],
            ]
        );

        $this->assertFalse($result->isValid());
        $this->assertSame('misleading_product_link', $result->violations()[0]['type'] ?? null);
    }

    public function testCatalogFacetReplyCannotDenyAvailabilityWhenMatchingProductsExist(): void
    {
        $service = new ResponseValidatorService();

        $intent = new IntentResult(
            'რომელი 2G მოდელები გაქვთ?',
            'recommendation',
            null,
            null,
            null,
            null,
            '2g_catalog',
            true,
            ['2G'],
            false,
            0.96,
            0,
            false
        );

        $result = $service->validateAll(
            'ამჟამად ჩვენს კატალოგში 2G სმარტსაათების არჩევანი არ გვაქვს.',
            [
                'products' => [
                    ['name' => 'Q19 2G', 'slug' => 'q19-2g', 'price' => 79.0, 'sale_price' => 59.0, 'is_in_stock' => true],
                    ['name' => 'Q21 2G', 'slug' => 'q21-2g', 'price' => 79.0, 'sale_price' => null, 'is_in_stock' => true],
                ],
                'allowed_urls' => [],
            ],
            $intent
        );

        $this->assertFalse($result->isValid());
        $this->assertSame('catalog_availability_mismatch', $result->violations()[0]['type'] ?? null);
    }
}
