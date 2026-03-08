<?php

namespace Tests\Unit;

use App\Services\Chatbot\ChatbotProductSelectionService;
use App\Services\Chatbot\IntentResult;
use App\Services\Chatbot\PipelineResult;
use Tests\TestCase;

class ChatbotProductSelectionServiceTest extends TestCase
{
    public function testRecommendationCardsRequireExplicitProductMention(): void
    {
        $service = new ChatbotProductSelectionService();

        $selected = $service->selectWidgetProductsForResponse(
            [
                $this->product('Wonlex CT23 — 4G საბავშვო სმარტ საათი GPS-ით', 'wonlex-cheaper-kids-gps-smart-watch-4g-ct23', 16.50),
                $this->product('Wonlex CT27 — კომპაქტური 4G საბავშვო სმარტ საათი', 'ct27-ultra-small-4g-rtos-gps-watch', 20.50),
                $this->product('Wonlex KT34 — 4G საბავშვო სმარტ საათი GPS ტრეკერით', '2024-newest-style-4g-smart-watch-sos-call-wifi-lbs-gps-tracker-android-smart-watch-kid-sos-camera-alarm-clock-kt34', 33.90),
            ],
            $this->pipelineResult(
                'თქვენი ბიუჯეტის ფარგლებში ხელმისაწვდომია 2 მოდელი: **Wonlex CT23** და **Wonlex CT27**.',
                $this->intent('recommendation', null, null, null, ['20 ლარის ფარგლებში'])
            )
        );

        $this->assertCount(2, $selected);
        $this->assertSame('Wonlex CT23 — 4G საბავშვო სმარტ საათი GPS-ით', $selected[0]['name']);
        $this->assertSame('Wonlex CT27 — კომპაქტური 4G საბავშვო სმარტ საათი', $selected[1]['name']);
    }

    public function testFeatureCardsIgnoreUnmentionedNoiseProducts(): void
    {
        $service = new ChatbotProductSelectionService();

        $selected = $service->selectWidgetProductsForResponse(
            [
                $this->product('Wonlex KT34 — 4G საბავშვო სმარტ საათი GPS ტრეკერით', '2024-newest-style-4g-smart-watch-sos-call-wifi-lbs-gps-tracker-android-smart-watch-kid-sos-camera-alarm-clock-kt34', 33.90),
                $this->product('Q12 — 2G საბავშვო სმარტ საათი SOS ღილაკით', 'high-quality-kids-smartwatch-q12-waterproof-ip67-2g-child-anti-lost-sos-call-gsm-lbs-location', 4.18),
                $this->product('T53 — 4G საბავშვო სმარტ საათი ვიდეო ზარითა და GPS-ით', 'yqt-4g-sos-smart-watch-for-kids-video-call-1-83-camera-oem-factory-gps-smartwatch-for-children', 16.80),
            ],
            $this->pipelineResult(
                'გირჩევთ **Q12** მოდელს, ხოლო ალტერნატივად შეგიძლიათ **Wonlex KT34** დაათვალიეროთ.',
                $this->intent('recommendation', null, null, null, ['GPS', 'SOS'])
            )
        );

        $this->assertCount(2, $selected);
        $this->assertSame('Wonlex KT34 — 4G საბავშვო სმარტ საათი GPS ტრეკერით', $selected[0]['name']);
        $this->assertSame('Q12 — 2G საბავშვო სმარტ საათი SOS ღილაკით', $selected[1]['name']);
    }

    public function testRecommendationLinkMentionDoesNotMatchOtherFourGProducts(): void
    {
        $service = new ChatbotProductSelectionService();

        $selected = $service->selectWidgetProductsForResponse(
            [
                $this->product('Wonlex CT27 — კომპაქტური 4G საბავშვო სმარტ საათი', 'ct27-ultra-small-4g-rtos-gps-watch', 20.50),
                $this->product('Wonlex KT34 — 4G საბავშვო სმარტ საათი GPS ტრეკერით', '2024-newest-style-4g-smart-watch-sos-call-wifi-lbs-gps-tracker-android-smart-watch-kid-sos-camera-alarm-clock-kt34', 33.90),
                $this->product('Wonlex KT20 — 4G წყალგამძლე საბავშვო სმარტ საათი', 'wonlex-kt20-4g-waterproof-smart-watch', 33.00),
            ],
            $this->pipelineResult(
                'თუ გსურთ კომპაქტური ვარიანტი, [Wonlex CT27](' . url('/products/ct27-ultra-small-4g-rtos-gps-watch') . ') კარგი არჩევანია.',
                $this->intent('recommendation', null, null, null, ['GPS', 'SOS'])
            )
        );

        $this->assertCount(1, $selected);
        $this->assertSame('Wonlex CT27 — კომპაქტური 4G საბავშვო სმარტ საათი', $selected[0]['name']);
    }

    public function testAlternativeFollowUpDropsRequestedProductFromAttachedCards(): void
    {
        $service = new ChatbotProductSelectionService();

        $selected = $service->selectWidgetProductsForResponse(
            [
                $this->product('Q12 — 2G საბავშვო სმარტ საათი SOS ღილაკით', 'high-quality-kids-smartwatch-q12-waterproof-ip67-2g-child-anti-lost-sos-call-gsm-lbs-location', 4.18),
                $this->product('Wonlex CT23 — 4G საბავშვო სმარტ საათი GPS-ით', 'wonlex-cheaper-kids-gps-smart-watch-4g-ct23', 16.50),
            ],
            $this->pipelineResult(
                'Q12-ს მსგავსი კლასის და ფუნქციების მქონე შავი ფერის ვარიანტია **Wonlex CT23**. [Q12](' . url('/products/high-quality-kids-smartwatch-q12-waterproof-ip67-2g-child-anti-lost-sos-call-gsm-lbs-location') . ') | [Wonlex CT23](' . url('/products/wonlex-cheaper-kids-gps-smart-watch-4g-ct23') . ')',
                $this->intent('comparison', null, 'Q12', null, ['Q12', 'შავი ფერი', 'კლასი'])
            )
        );

        $this->assertCount(1, $selected);
        $this->assertSame('Wonlex CT23 — 4G საბავშვო სმარტ საათი GPS-ით', $selected[0]['name']);
    }

    private function product(string $name, string $slug, float $price): array
    {
        return [
            'name' => $name,
            'slug' => $slug,
            'price' => $price,
            'sale_price' => null,
            'is_in_stock' => true,
            'url' => url('/products/' . $slug),
            'image' => '/storage/products/' . $slug . '.jpg',
        ];
    }

    private function intent(string $intent, ?string $brand, ?string $model, ?string $slugHint, array $keywords): IntentResult
    {
        return new IntentResult(
            'query',
            $intent,
            $brand,
            $model,
            $slugHint,
            null,
            null,
            true,
            $keywords,
            false,
            0.95,
            12,
            false
        );
    }

    private function pipelineResult(string $response, IntentResult $intentResult): PipelineResult
    {
        return new PipelineResult(
            $response,
            1,
            '',
            $intentResult,
            ['products' => [], 'allowed_urls' => []],
            true,
            null,
            true,
            [],
            true,
            100,
            null,
            false,
            false
        );
    }
}
