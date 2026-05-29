<?php

namespace Tests\Feature;

use App\Models\FacebookCompetitorPage;
use App\Models\FacebookCompetitorPost;
use App\Models\FacebookCompetitorAnalysis;
use App\Models\FacebookCompetitorInsight;
use App\Services\FacebookApifyScraperService;
use App\Services\FacebookCompetitorAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FacebookCompetitorScrapingTest extends TestCase
{
    use RefreshDatabase;

    private function createCompetitorPage(array $overrides = []): FacebookCompetitorPage
    {
        return FacebookCompetitorPage::create(array_merge([
            'name' => 'Test Competitor',
            'facebook_url' => 'https://www.facebook.com/testpage',
            'is_active' => true,
            'scraping_frequency' => 'daily',
        ], $overrides));
    }

    public function test_competitor_page_creation(): void
    {
        $page = $this->createCompetitorPage();

        $this->assertDatabaseHas('facebook_competitor_pages', [
            'name' => 'Test Competitor',
            'facebook_url' => 'https://www.facebook.com/testpage',
            'is_active' => true,
        ]);
    }

    public function test_competitor_page_scopes(): void
    {
        $this->createCompetitorPage(['name' => 'Active', 'is_active' => true]);
        $this->createCompetitorPage(['name' => 'Inactive', 'is_active' => false]);

        $this->assertCount(1, FacebookCompetitorPage::active()->get());
    }

    public function test_due_for_scraping_scope(): void
    {
        $this->createCompetitorPage(['name' => 'Never scraped', 'last_scraped_at' => null]);
        $this->createCompetitorPage(['name' => 'Old scrape', 'last_scraped_at' => now()->subDays(2)]);
        $this->createCompetitorPage(['name' => 'Recent scrape', 'last_scraped_at' => now()->subHours(2)]);

        $due = FacebookCompetitorPage::dueForScraping()->get();
        $this->assertCount(2, $due);
        $this->assertTrue($due->contains('name', 'Never scraped'));
        $this->assertTrue($due->contains('name', 'Old scrape'));
    }

    public function test_post_creation_and_relationships(): void
    {
        $page = $this->createCompetitorPage();

        $post = FacebookCompetitorPost::create([
            'competitor_page_id' => $page->id,
            'facebook_post_id' => '12345',
            'text' => 'ახალი საბავშვო სმარტ საათი GPS-ით და SIM ბარათით',
            'likes_count' => 100,
            'comments_count' => 25,
            'shares_count' => 10,
            'is_relevant' => true,
            'relevance_score' => 95,
            'scraped_at' => now(),
            'posted_at' => now()->subDay(),
        ]);

        $this->assertEquals($page->id, $post->competitorPage->id);
        $this->assertCount(1, $page->posts);
        $this->assertCount(1, $page->relevantPosts);
        $this->assertEquals(135, $post->engagement_total);
    }

    public function test_post_relevant_scope(): void
    {
        $page = $this->createCompetitorPage();

        FacebookCompetitorPost::create([
            'competitor_page_id' => $page->id,
            'facebook_post_id' => '111',
            'text' => 'Relevant post',
            'is_relevant' => true,
            'scraped_at' => now(),
        ]);

        FacebookCompetitorPost::create([
            'competitor_page_id' => $page->id,
            'facebook_post_id' => '222',
            'text' => 'Irrelevant post',
            'is_relevant' => false,
            'scraped_at' => now(),
        ]);

        FacebookCompetitorPost::create([
            'competitor_page_id' => $page->id,
            'facebook_post_id' => '333',
            'text' => 'Unfiltered post',
            'is_relevant' => null,
            'scraped_at' => now(),
        ]);

        $this->assertCount(1, FacebookCompetitorPost::relevant()->get());
        $this->assertCount(1, FacebookCompetitorPost::unfiltered()->get());
    }

    public function test_insight_lifecycle(): void
    {
        $page = $this->createCompetitorPage();

        $insight = FacebookCompetitorInsight::create([
            'insight_type' => 'price_alert',
            'priority' => 'high',
            'status' => 'new',
            'title' => 'Price drop detected',
            'description' => 'Competitor dropped price by 15%',
            'competitor_page_id' => $page->id,
        ]);

        $this->assertEquals('new', $insight->status);
        $this->assertNull($insight->acknowledged_at);

        $insight->acknowledge();
        $insight->refresh();

        $this->assertEquals('acknowledged', $insight->status);
        $this->assertNotNull($insight->acknowledged_at);

        $insight->markActioned();
        $insight->refresh();

        $this->assertEquals('actioned', $insight->status);
        $this->assertNotNull($insight->actioned_at);
    }

    public function test_insight_scopes(): void
    {
        $page = $this->createCompetitorPage();

        FacebookCompetitorInsight::create([
            'insight_type' => 'price_alert',
            'priority' => 'high',
            'status' => 'new',
            'title' => 'High priority new',
            'competitor_page_id' => $page->id,
        ]);

        FacebookCompetitorInsight::create([
            'insight_type' => 'content_opportunity',
            'priority' => 'medium',
            'status' => 'acknowledged',
            'title' => 'Medium acknowledged',
            'competitor_page_id' => $page->id,
        ]);

        FacebookCompetitorInsight::create([
            'insight_type' => 'trend_emerging',
            'priority' => 'low',
            'status' => 'actioned',
            'title' => 'Low actioned',
            'competitor_page_id' => $page->id,
        ]);

        $this->assertCount(1, FacebookCompetitorInsight::new()->get());
        $this->assertCount(1, FacebookCompetitorInsight::highPriority()->get());
        $this->assertCount(2, FacebookCompetitorInsight::active()->get());
    }

    public function test_apify_scraper_processes_posts(): void
    {
        Http::fake([
            'api.apify.com/*' => Http::response([
                [
                    'postId' => 'fb_001',
                    'postUrl' => 'https://facebook.com/page/posts/fb_001',
                    'text' => 'საბავშვო GPS სმარტ საათი SIM ბარათით',
                    'time' => '2026-03-17T10:00:00Z',
                    'likes' => 234,
                    'comments' => 45,
                    'shares' => 67,
                    'images' => ['https://example.com/image1.jpg'],
                    'reactions' => ['like' => 200, 'love' => 34],
                ],
                [
                    'postId' => 'fb_002',
                    'postUrl' => 'https://facebook.com/page/posts/fb_002',
                    'text' => 'ზაფხულის ფასდაკლება ყველა პროდუქტზე',
                    'time' => '2026-03-16T14:00:00Z',
                    'likes' => 120,
                    'comments' => 15,
                    'shares' => 30,
                ],
            ], 200),
        ]);

        $page = $this->createCompetitorPage();
        $scraper = app(FacebookApifyScraperService::class);

        $result = $scraper->scrapeCompetitorPage($page, 10);

        $this->assertEquals(2, $result['new_posts']);
        $this->assertEquals(0, $result['updated_posts']);
        $this->assertEquals(2, $result['total_scraped']);

        $this->assertDatabaseHas('facebook_competitor_posts', [
            'facebook_post_id' => 'fb_001',
            'likes_count' => 234,
            'comments_count' => 45,
            'shares_count' => 67,
        ]);

        $page->refresh();
        $this->assertNotNull($page->last_scraped_at);
    }

    public function test_apify_scraper_upserts_existing_posts(): void
    {
        Http::fake([
            'api.apify.com/*' => Http::response([
                [
                    'postId' => 'fb_existing',
                    'text' => 'Updated text',
                    'likes' => 500,
                    'comments' => 100,
                    'shares' => 50,
                ],
            ], 200),
        ]);

        $page = $this->createCompetitorPage();

        FacebookCompetitorPost::create([
            'competitor_page_id' => $page->id,
            'facebook_post_id' => 'fb_existing',
            'text' => 'Original text',
            'likes_count' => 10,
            'comments_count' => 2,
            'shares_count' => 1,
            'scraped_at' => now()->subDay(),
        ]);

        $scraper = app(FacebookApifyScraperService::class);
        $result = $scraper->scrapeCompetitorPage($page, 10);

        $this->assertEquals(0, $result['new_posts']);
        $this->assertEquals(1, $result['updated_posts']);

        $post = FacebookCompetitorPost::where('facebook_post_id', 'fb_existing')->first();
        $this->assertEquals('Updated text', $post->text);
        $this->assertEquals(500, $post->likes_count);
    }

    public function test_apify_scraper_throws_on_missing_token(): void
    {
        config(['services.apify.token' => '']);

        $page = $this->createCompetitorPage();
        $scraper = new FacebookApifyScraperService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Apify token is missing');

        $scraper->scrapeCompetitorPage($page);
    }

    public function test_cost_estimation(): void
    {
        $scraper = app(FacebookApifyScraperService::class);

        $this->assertEquals(0.35, $scraper->estimateCost(50));
        $this->assertEquals(3.50, $scraper->estimateCost(500));
    }

    public function test_artisan_command_dry_run(): void
    {
        $this->createCompetitorPage(['name' => 'Test Page']);

        $this->artisan('competitors:scrape-facebook', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Test Page');
    }

    public function test_artisan_command_no_pages(): void
    {
        $this->artisan('competitors:scrape-facebook')
            ->assertSuccessful()
            ->expectsOutputToContain('No active competitor pages');
    }

    public function test_analysis_model(): void
    {
        $analysis = FacebookCompetitorAnalysis::create([
            'analysis_date' => now()->toDateString(),
            'analysis_type' => 'weekly',
            'posts_analyzed_count' => 42,
            'competitive_intelligence_json' => ['pricing_insights' => []],
            'content_strategy_json' => ['best_performing_types' => []],
            'sentiment_analysis_json' => ['overall' => 'positive'],
            'trend_analysis_json' => ['emerging_topics' => ['GPS watches']],
            'recommendations_json' => [['priority' => 'high', 'title' => 'Test']],
            'ai_model_used' => 'gpt-4.1-mini',
        ]);

        $this->assertDatabaseHas('facebook_competitor_analyses', [
            'analysis_type' => 'weekly',
            'posts_analyzed_count' => 42,
        ]);

        $this->assertEquals('positive', $analysis->sentiment_analysis_json['overall']);
        $this->assertCount(1, $analysis->recommendations_json);
    }
}
