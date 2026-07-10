<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportMymarketBatchCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exports_a_mymarket_batch_from_configured_preset(): void
    {
        config()->set('mymarket.presets.test-batch', [
            'batch_id' => 'test-batch',
            'models' => [
                [
                    'sequence' => 1,
                    'model_code' => 'QA1',
                    'slug' => 'qa-one',
                    'positioning_angle_ka' => 'პირველი ტესტური მოდელი',
                    'discount_expected' => true,
                    'discount_target_price_gel' => 89,
                    'camera_if_mandatory' => '2MP',
                    'must_emphasize_ka' => ['SOS', 'GPS'],
                    'must_avoid_ka' => ['ჰალუცინაცია'],
                ],
                [
                    'sequence' => 2,
                    'model_code' => 'QB2',
                    'slug' => 'qb-two',
                    'positioning_angle_ka' => 'მეორე ტესტური მოდელი',
                    'discount_expected' => false,
                    'camera_if_mandatory' => '3.2MP',
                ],
            ],
        ]);

        $productOne = Product::create([
            'name_en' => 'QA One',
            'name_ka' => 'QA One',
            'slug' => 'qa-one',
            'short_description_ka' => 'QA1 აღწერა',
            'price' => 99,
            'sale_price' => 89,
            'currency' => 'GEL',
            'warranty_months' => 3,
            'operating_system' => 'Android',
            'screen_size' => '1.44',
            'display_type' => 'OLED',
            'screen_resolution' => '128*128',
            'battery_capacity_mah' => 400,
            'battery_life_range' => '1-3',
            'water_resistant' => 'IP67',
            'camera' => '< 3MP',
            'functions' => ['SOS', 'GPS'],
            'is_active' => true,
        ]);

        $productTwo = Product::create([
            'name_en' => 'QB Two',
            'name_ka' => 'QB Two',
            'slug' => 'qb-two',
            'short_description_ka' => 'QB2 აღწერა',
            'price' => 149,
            'sale_price' => null,
            'currency' => 'GEL',
            'warranty_months' => 1,
            'operating_system' => 'RTOS',
            'screen_size' => '1.83',
            'display_type' => 'IPS',
            'screen_resolution' => '240x280',
            'battery_capacity_mah' => 650,
            'battery_life_hours' => 96,
            'water_resistant' => 'IP67',
            'camera' => '3 - 7MP',
            'functions' => ['ვიდეო ზარი'],
            'is_active' => true,
        ]);

        ProductVariant::create([
            'product_id' => $productOne->id,
            'name' => 'მწვანე',
            'color_name' => 'მწვანე',
            'color_hex' => '#00FF00',
            'quantity' => 4,
            'low_stock_threshold' => 1,
        ]);

        ProductVariant::create([
            'product_id' => $productOne->id,
            'name' => 'თეთრი',
            'color_name' => 'თეთრი',
            'color_hex' => '#FFFFFF',
            'quantity' => 0,
            'low_stock_threshold' => 1,
        ]);

        ProductVariant::create([
            'product_id' => $productTwo->id,
            'name' => 'შავი',
            'color_name' => 'შავი',
            'color_hex' => '#000000',
            'quantity' => 2,
            'low_stock_threshold' => 1,
        ]);

        ProductImage::create([
            'product_id' => $productOne->id,
            'path' => 'images/products/qa-one/01.jpg',
            'thumbnail_path' => 'images/products/qa-one/01_thumb.jpg',
            'alt_ka' => 'QA1',
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        ProductImage::create([
            'product_id' => $productTwo->id,
            'path' => 'images/products/qb-two/01.jpg',
            'thumbnail_path' => 'images/products/qb-two/01_thumb.jpg',
            'alt_ka' => 'QB2',
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        $jsonPath = storage_path('framework/testing/mymarket-test-batch.json');
        $csvPath = storage_path('framework/testing/mymarket-test-batch.csv');

        @unlink($jsonPath);
        @unlink($csvPath);

        $this->artisan('mymarket:export-batch', [
            '--preset' => 'test-batch',
            '--path' => $jsonPath,
            '--csv-path' => $csvPath,
        ])->assertSuccessful();

        $this->assertFileExists($jsonPath);
        $this->assertFileExists($csvPath);

        $payload = json_decode((string) file_get_contents($jsonPath), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('test-batch', $payload['batch_id']);
        $this->assertCount(2, $payload['models']);
        $this->assertSame('qa-one', $payload['models'][0]['slug']);
        $this->assertEquals(89.0, $payload['models'][0]['sale_price_gel']);
        $this->assertSame('2MP', $payload['models'][0]['camera_if_mandatory']);
        $this->assertSame('მწვანე', $payload['models'][0]['variants'][0]['color_ka']);
        $this->assertTrue($payload['models'][0]['variants'][0]['include']);
        $this->assertFalse($payload['models'][0]['variants'][1]['include']);
        $this->assertSame('qb-two', $payload['models'][1]['slug']);
        $this->assertSame('არა', $payload['models'][1]['memory_card_if_mandatory']);

        $csv = (string) file_get_contents($csvPath);

        $this->assertStringContainsString('qa-one', $csv);
        $this->assertStringContainsString('qb-two', $csv);
        $this->assertStringContainsString('მწვანე', $csv);
        $this->assertStringContainsString('თეთრი', $csv);
    }
}
