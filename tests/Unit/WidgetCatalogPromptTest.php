<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Chatbot\IntentResult;
use App\Services\Chatbot\PromptBuilderService;
use App\Services\Chatbot\SearchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WidgetCatalogPromptTest extends TestCase
{
    use RefreshDatabase;

    public function testWidgetComparisonIncludesPublishedFunctionsAndAvailableColors(): void
    {
        $product = Product::create([
            'name_en' => 'Q21', 'name_ka' => 'Q21', 'slug' => 'q21',
            'price' => 79, 'sale_price' => 59, 'is_active' => true,
            'water_resistant' => 'IP67', 'sim_support' => true,
            'functions' => ['SOS', 'ვიდეოზარი'],
        ]);
        ProductVariant::create([
            'product_id' => $product->id, 'name' => 'შავი',
            'color_name' => 'შავი', 'quantity' => 2,
        ]);
        ProductVariant::create([
            'product_id' => $product->id, 'name' => 'ლურჯი',
            'color_name' => 'ლურჯი', 'quantity' => 0,
        ]);
        $product->load('variants');
        $product->setAttribute('total_stock', 2);
        $intent = IntentResult::fromArray([
            'standalone_query' => 'Q21 რა ფუნქციები აქვს?',
            'intent' => 'features', 'entities' => [], 'needs_product_data' => true,
        ], 0);
        $search = new SearchContext('', collect([$product]), $product, null);
        $builder = app(PromptBuilderService::class);

        $widget = $builder->buildUserContext('Q21 რა ფუნქციები აქვს?', $intent, $search, [], collect([$product]), '', true);
        $social = $builder->buildUserContext('Q21 რა ფუნქციები აქვს?', $intent, $search, [], collect([$product]), '');
        $water = $builder->buildUserContext('რომელი მოდელია წყალგამძლე?', $intent, $search, [], collect([$product]), '', true);
        $sim = $builder->buildUserContext('სიმ ბარათიანი საათი გაქვთ?', $intent, $search, [], collect([$product]), '', true);
        $color = $builder->buildUserContext('შავი ფერის მოდელი გაქვთ?', $intent, $search, [], collect([$product]), '', true);
        $budget = $builder->buildUserContext('რას მირჩევდი 50 ლარად?', $intent, $search, [], collect([$product]), '', true);
        $missingBudget = $builder->buildUserContext('და ლარად მომივა რამე?', $intent, $search, [], collect(), '', true);

        $priceIntent = IntentResult::fromArray([
            'standalone_query' => 'საათების ფასები მაინტერესებს',
            'intent' => 'price_query', 'entities' => [], 'needs_product_data' => true,
        ], 0);
        $genericPrice = $builder->buildUserContext('საათების ფასები მაინტერესებს', $priceIntent, $search, [], collect([$product]), '', true);

        $this->assertStringContainsString('SOS, ვიდეოზარი', $widget);
        $this->assertStringContainsString('წყალგამძლეობის კატალოგის ჩანაწერი: IP67', $water);
        $this->assertStringContainsString('SIM მხარდაჭერა: მითითებულია', $sim);
        $this->assertStringContainsString('მარაგში არსებული ფერები: შავი', $color);
        $this->assertStringNotContainsString('მარაგში არსებული ფერები: ლურჯი', $color);
        $this->assertStringNotContainsString('კატალოგში მითითებული ფუნქციები:', $social);
        $this->assertStringNotContainsString('წყალგამძლეობის კატალოგის ჩანაწერი:', $social);
        $this->assertStringNotContainsString('კატალოგში მითითებული ფუნქციები:', $budget);
        $this->assertStringContainsString('ბიუჯეტის თანხა ამ შეტყობინებაში არ ჩანს', $missingBudget);
        $this->assertStringContainsString('ეს სია სრული კატალოგი არ არის', $genericPrice);
    }
}
