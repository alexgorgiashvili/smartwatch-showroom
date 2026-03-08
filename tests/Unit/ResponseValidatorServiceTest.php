<?php

namespace Tests\Unit;

use App\Services\Chatbot\IntentResult;
use App\Services\Chatbot\ResponseValidatorService;
use Tests\TestCase;

class ResponseValidatorServiceTest extends TestCase
{
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
}
