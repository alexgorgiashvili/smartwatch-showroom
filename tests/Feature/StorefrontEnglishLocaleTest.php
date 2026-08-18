<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ChatbotDocument;
use App\Models\City;
use App\Models\Faq;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StorefrontEnglishLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function testEnglishContentSyncFillsLegacyCitiesAndFaqDocuments(): void
    {
        $city = City::query()->create([
            'name' => 'თბილისი > კოჯორი',
            'region' => 'თბილისი',
        ]);
        $faq = Faq::query()->create([
            'question' => 'ტესტ კითხვა',
            'question_en' => 'Test Question',
            'answer' => 'ტესტ პასუხი',
            'answer_en' => 'Test Answer',
            'category' => 'ტესტი',
            'category_en' => 'Testing',
            'is_active' => true,
        ]);
        $document = ChatbotDocument::query()->create([
            'key' => 'faq-legacy-test',
            'type' => 'faq',
            'title' => $faq->question,
            'content_ka' => $faq->answer,
            'is_active' => true,
        ]);

        $this->artisan('storefront:sync-english-content')->assertExitCode(0);

        $this->assertSame('Tbilisi > Kojori', $city->fresh()->name_en);
        $this->assertSame('Test Question', $document->fresh()->title_en);
        $this->assertSame("Question: Test Question\n\nAnswer: Test Answer", $document->fresh()->content_en);
    }

    private Product $product;
    private ProductVariant $variant;
    private Article $article;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('gift_builder.enabled', true);
        config()->set('gift_builder.public_enabled', true);

        $this->product = Product::query()->create([
            'name_en' => 'Orbit 4G Kids Watch',
            'name_ka' => 'ორბიტა 4G საბავშვო საათი',
            'slug' => 'orbit-4g-kids-watch',
            'short_description_en' => 'A safe smartwatch with calls, GPS, and SOS.',
            'short_description_ka' => 'უსაფრთხო საბავშვო საათი ზარით, GPS-ით და SOS-ით.',
            'description_en' => 'Stay connected with verified location, calls, and video calling.',
            'description_ka' => 'იყავით კავშირზე ზუსტი ლოკაციით, ზარებითა და ვიდეოზარით.',
            'meta_title_en' => 'Orbit 4G Kids Watch',
            'meta_title_ka' => 'ორბიტა 4G საბავშვო საათი',
            'meta_description_en' => 'English product metadata for Orbit 4G.',
            'meta_description_ka' => 'ქართული პროდუქტის მეტა აღწერა.',
            'price' => 199,
            'currency' => 'GEL',
            'sim_support' => true,
            'gps_features' => true,
            'water_resistant' => 'IP67',
            'battery_life_hours' => 48,
            'warranty_months' => 3,
            'functions' => ['Video calls', 'SOS', 'GPS'],
            'is_active' => true,
            'featured' => true,
            'fulfillment_mode' => 'local_stock',
            'gift_builder_enabled' => true,
            'gift_builder_role' => 'main',
            'gift_budget_band' => 'all',
            'gift_capacity_units' => 1,
        ]);

        $this->variant = ProductVariant::query()->create([
            'product_id' => $this->product->id,
            'name' => 'ვარდისფერი',
            'name_en' => 'Pink',
            'color_name' => 'ვარდისფერი',
            'color_name_en' => 'Pink',
            'color_hex' => '#F9A8D4',
            'quantity' => 5,
            'low_stock_threshold' => 1,
        ]);

        Faq::query()->create([
            'question' => 'როგორ მუშაობს GPS?',
            'question_en' => 'How does GPS work?',
            'answer' => 'მშობელი აპში ხედავს საათის მდებარეობას.',
            'answer_en' => 'A parent can view the watch location in the companion app.',
            'category' => 'უსაფრთხოება',
            'category_en' => 'Safety',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->article = Article::query()->create([
            'slug' => 'choose-a-kids-smartwatch',
            'title_ka' => 'როგორ ავირჩიოთ საბავშვო საათი',
            'title_en' => 'How to Choose a Kids Smartwatch',
            'excerpt_ka' => 'ქართული სტატიის მოკლე აღწერა.',
            'excerpt_en' => 'A practical English buying guide.',
            'body_ka' => '<p>ქართული სტატიის ტექსტი.</p>',
            'body_en' => '<p>Compare connectivity, safety, battery life, and comfort.</p>',
            'meta_title_ka' => 'ქართული სტატია',
            'meta_title_en' => 'Kids Smartwatch Buying Guide',
            'meta_description_ka' => 'ქართული სტატიის მეტა აღწერა.',
            'meta_description_en' => 'Learn how to choose a kids smartwatch.',
            'is_published' => true,
            'published_at' => now(),
        ]);

        City::query()->create([
            'name' => 'თბილისი',
            'name_en' => 'Tbilisi',
            'region_en' => 'Tbilisi',
        ]);
    }

    public function test_locale_switcher_persists_locale_and_preserves_the_current_url(): void
    {
        $response = $this->from('/products?sort=newest')->get(route('locale', 'en'));

        $response->assertRedirect('/products?sort=newest');
        $response->assertSessionHas('locale', 'en');

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee(route('locale', 'ka'), false)
            ->assertSee('Switch to Georgian')
            ->assertSee('Orbit 4G Kids Watch')
            ->assertDontSee('ორბიტა 4G საბავშვო საათი');
    }

    public function test_english_product_details_translate_legacy_georgian_specifications(): void
    {
        $this->product->update([
            'operating_system' => '0.150 კგ',
            'screen_size' => '1.85 ინჩი',
            'case_material' => 'პლასტმასი',
            'band_material' => 'სილიკონის გელი',
            'camera' => '0.3 მეგაპიქსელი',
            'functions' => ['GPS ტრეკერი', 'SOS საგანგებო ღილაკი'],
        ]);

        $response = $this->withSession(['locale' => 'en'])
            ->get(route('products.show', $this->product));

        $response
            ->assertOk()
            ->assertSee('1.85 inches')
            ->assertSee('Plastic')
            ->assertSee('Silicone gel')
            ->assertSee('0.3 megapixels')
            ->assertSee('GPS tracker, SOS emergency button')
            ->assertDontSee('პლასტმასი')
            ->assertDontSee('სილიკონის გელი')
            ->assertDontSee('0.150 kg')
            ->assertDontSee('0.150 კგ');
    }

    public function test_all_public_english_pages_render_without_georgian_unicode(): void
    {
        $routes = [
            route('home'),
            route('products.index'),
            route('products.show', $this->product),
            route('products.quick-review', $this->product),
            route('contact'),
            route('faq'),
            route('about'),
            route('privacy'),
            route('terms'),
            route('blog.index'),
            route('blog.show', $this->article),
            route('landing.age', '7-10'),
            route('landing.sim-guide'),
            route('landing.gift-guide'),
            route('landing.city', 'tbilisi'),
            route('gift-builder.show'),
            route('gift-builder.products'),
            route('cart.index'),
            route('payment.success', ['order' => 'TEST-EN']),
            route('payment.fail', ['order' => 'TEST-EN']),
        ];

        foreach ($routes as $url) {
            $response = $this->withSession(['locale' => 'en'])->get($url);

            $response->assertOk();
            $this->assertEnglishOnlyResponse($response, $url);
        }
    }

    public function test_checkout_and_cart_messages_are_english(): void
    {
        $session = [
            'locale' => 'en',
            'cart' => [
                $this->variant->id => [
                    'variant_id' => $this->variant->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $checkout = $this->withSession($session)->get(route('checkout.index'));
        $checkout->assertOk()->assertSee('Checkout')->assertSee('Tbilisi');
        $this->assertEnglishOnlyResponse($checkout, route('checkout.index'));

        $added = $this->withSession(['locale' => 'en'])->postJson(route('cart.add'), [
            'variant_id' => $this->variant->id,
            'quantity' => 1,
        ]);
        $added->assertOk()->assertJsonPath('success', true);
        $this->assertEnglishOnlyResponse($added, route('cart.add'));

        $invalid = $this->withSession($session)->postJson(route('payment.validate'), []);
        $invalid->assertUnprocessable();
        $this->assertEnglishOnlyResponse($invalid, route('payment.validate'));

        $inquiryValidation = $this->withSession(['locale' => 'en'])->post(route('inquiries.store'), []);
        $inquiryValidation->assertSessionHasErrors(['name', 'phone']);
        foreach (session('errors')->all() as $message) {
            $this->assertDoesNotMatchRegularExpression('/\p{Georgian}/u', $message);
        }

        $giftValidation = $this->withSession(['locale' => 'en'])->postJson(route('gift-builder.price'), [
            'recipient_type' => array_key_first(config('gift_builder.recipients')),
            'occasion' => array_key_first(config('gift_builder.occasions')),
            'budget_band' => 'all',
            'packaging_slug' => 'standard',
            'items' => [[
                'variant_id' => $this->variant->id,
                'quantity' => 1,
                'role' => 'addon',
            ]],
        ]);
        $giftValidation->assertUnprocessable();
        $this->assertEnglishOnlyResponse($giftValidation, route('gift-builder.price'));
    }

    public function test_customer_facing_ai_endpoints_honor_english_locale(): void
    {
        $urls = [
            route('api.ai.products', ['lang' => 'en']),
            route('api.ai.products.show', ['product' => $this->product, 'lang' => 'en']),
            route('api.ai.recommendations', ['lang' => 'en', 'features' => ['gps']]),
            route('api.ai.knowledge', ['lang' => 'en']),
            route('api.ai.products.markdown', ['product' => $this->product, 'lang' => 'en']),
        ];

        foreach ($urls as $url) {
            $response = $this->withSession(['locale' => 'ka'])->get($url);

            $response->assertOk();
            $this->assertEnglishOnlyResponse($response, $url);
        }

        $this->getJson(route('api.ai.products', ['lang' => 'en']))
            ->assertOk()
            ->assertJsonPath('language', 'en')
            ->assertJsonPath('products.0.name', 'Orbit 4G Kids Watch');

        $this->getJson(route('api.ai.knowledge', ['lang' => 'en', 'topic' => 'faq']))
            ->assertOk()
            ->assertJsonPath('language', 'en')
            ->assertJsonPath('knowledge.faq.0.question', 'How does GPS work?');
    }

    public function test_invalid_api_language_falls_back_to_the_active_english_session(): void
    {
        $response = $this->withSession(['locale' => 'en'])
            ->getJson(route('api.ai.products', ['lang' => 'unsupported']));

        $response->assertOk()
            ->assertJsonPath('language', 'en')
            ->assertJsonPath('products.0.name', 'Orbit 4G Kids Watch');
        $this->assertEnglishOnlyResponse($response, 'invalid API locale fallback');
    }

    private function assertEnglishOnlyResponse(TestResponse $response, string $context): void
    {
        $content = html_entity_decode($response->getContent(), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $this->assertDoesNotMatchRegularExpression(
            '/\p{Georgian}/u',
            $content,
            "Georgian customer-facing text leaked in English response: {$context}"
        );
    }
}
