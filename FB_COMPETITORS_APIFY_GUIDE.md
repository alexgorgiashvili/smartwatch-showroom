# Facebook Competitors - Apify Integration Guide

## 📋 Overview

სრულყოფილი Facebook კონკურენტების მონიტორინგის სისტემა Apify-ის გამოყენებით, AI ანალიზით და NobleUI ადმინ პანელში ინტეგრირებული.

## 🏗️ Architecture

### Backend Components

1. **Models**
   - `FacebookCompetitorPage` - კონკურენტების გვერდები
   - `FacebookCompetitorPost` - გაპარსილი პოსტები
   - `FacebookCompetitorAnalysis` - კვირეული AI ანალიზები
   - `FacebookCompetitorInsight` - გენერირებული insights

2. **Services**
   - `FacebookApifyScraperService` - Apify REST API integration
   - `FacebookCompetitorAiService` - OpenAI-based analysis

3. **Controller**
   - `App\Http\Controllers\Admin\FacebookCompetitorController` - NobleUI admin routes

4. **Command**
   - `php artisan competitors:scrape-facebook` - CLI scraping

### Database Schema

```sql
facebook_competitor_pages:
  - id, name, facebook_url, page_id, category
  - is_active, scraping_frequency (daily/weekly/manual)
  - last_scraped_at, total_posts_count, relevant_posts_count
  - avg_engagement_rate, follower_count

facebook_competitor_posts:
  - id, competitor_page_id, facebook_post_id, post_url
  - posted_at, scraped_at, text, images_json, video_url
  - likes_count, comments_count, shares_count, reactions_json
  - is_relevant, relevance_score, relevance_reason, product_mentions_json

facebook_competitor_analyses:
  - id, analysis_date, analysis_type, competitor_page_ids_json
  - posts_analyzed_count, competitive_intelligence_json
  - content_strategy_json, sentiment_analysis_json
  - trend_analysis_json, recommendations_json
  - ai_model_used, tokens_used

facebook_competitor_insights:
  - id, insight_type, priority, status, title, description
  - data_json, competitor_page_id, related_post_ids_json
  - acknowledged_at, actioned_at
```

## 🚀 Setup & Configuration

### 1. Environment Variables

```env
# Apify Configuration
APIFY_API_TOKEN=your_token_here
APIFY_BASE_URL=https://api.apify.com/v2
APIFY_FACEBOOK_POSTS_ACTOR=apify/facebook-posts-scraper
APIFY_FACEBOOK_PAGES_ACTOR=apify/facebook-pages-scraper
APIFY_FACEBOOK_MAX_POSTS=50
APIFY_FACEBOOK_SCRAPE_TIMEOUT=300
APIFY_USE_PROXY=true

# OpenAI for AI Analysis
OPENAI_API_KEY=your_openai_key
OPENAI_MODEL=gpt-4.1-mini
OPENAI_INTENT_MODEL=gpt-4.1-nano
```

### 2. Run Migration

```bash
php artisan migrate
```

Migration file: `database/migrations/2026_03_18_000200_create_facebook_competitor_tables.php`

### 3. Schedule Tasks (Optional)

Add to `app/Console/Kernel.php`:

```php
$schedule->command('competitors:scrape-facebook --analyze')
    ->dailyAt('03:00')
    ->withoutOverlapping();

$schedule->command('competitors:scrape-facebook --weekly-analysis')
    ->weeklyOn(0, '04:00')
    ->withoutOverlapping();
```

## 📱 Admin Panel Usage

### Access
Navigate to: **Admin → Competition → FB კონკურენტები**

### Features

#### 1. **Dashboard Overview**
   - რეალურ დროში სტატისტიკა
   - აქტიური კონკურენტების სია
   - რელევანტური პოსტები
   - Insights და რეკომენდაციები

#### 2. **კონკურენტის დამატება**
   ```
   სახელი: MyTech Kids
   Facebook URL: https://facebook.com/mytechkids
   კატეგორია: ელექტრონიკა
   გაპარსვის სიხშირე: ყოველდღიური
   ✓ აქტიური
   ```

#### 3. **გაპარსვა (Scraping)**
   - **ცალკე გვერდის გაპარსვა**: "გაპარსვა" ღილაკი კონკურენტის გვერდზე
   - **ყველას გაპარსვა**: "ყველას გაპარსვა" მთავარ დეშბორდზე
   - **CLI**: `php artisan competitors:scrape-facebook --page=1 --max-posts=100`

#### 4. **AI ანალიზი**
   - **რელევანტურობის ფილტრი**: გაფილტრავს პოსტებს kids smartwatch-ების შესახებ
   - **კვირეული ანალიზი**: სრული competitive intelligence 4 განზომილებით:
     - Competitive Intelligence (ფასები, ფუნქციები, პოზიციონირება)
     - Content Strategy (საუკეთესო ტიპები, დრო, ჩართულობა)
     - Sentiment Analysis (პოზიტიური/ნეგატიური შეფასებები)
     - Trend Analysis (ახალი ტრენდები, კლებადი ინტერესი)

#### 5. **Insights Management**
   - ავტომატურად გენერირებული შეტყობინებები
   - პრიორიტეტები: HIGH, MEDIUM, LOW
   - სტატუსები: new, acknowledged, actioned, dismissed
   - ტიპები: price_alert, content_opportunity, sentiment_shift, trend_emerging

## 🔧 CLI Commands

### Basic Scraping
```bash
# Scrape all active competitors
php artisan competitors:scrape-facebook

# Scrape specific page
php artisan competitors:scrape-facebook --page=1

# Custom max posts
php artisan competitors:scrape-facebook --max-posts=200

# Dry run (show what will be scraped)
php artisan competitors:scrape-facebook --dry-run
```

### With AI Analysis
```bash
# Scrape + relevance filtering
php artisan competitors:scrape-facebook --analyze

# Run weekly comprehensive analysis
php artisan competitors:scrape-facebook --weekly-analysis
```

## 📊 Apify Integration Details

### Current Implementation (REST API)

```php
// FacebookApifyScraperService uses REST API v2
POST https://api.apify.com/v2/acts/{actorId}/run-sync-get-dataset-items

// Authorization
Bearer {APIFY_API_TOKEN}

// Input for posts scraper
{
  "startUrls": [{"url": "https://facebook.com/page"}],
  "maxPosts": 50,
  "commentsMode": "RANKED_UNFILTERED",
  "scrapeReactions": true,
  "proxyConfiguration": {
    "useApifyProxy": true
  }
}
```

### Apify Actors Used

1. **`apify/facebook-posts-scraper`**
   - გამოყენება: პოსტების სკრეიპინგი
   - ღირებულება: ~$0.007 per post
   - Timeout: 300 seconds

2. **`apify/facebook-pages-scraper`**
   - გამოყენება: გვერდის metadata (followers, rating)
   - ღირებულება: ~$0.05 per page

### Cost Estimation

```php
// Service method
$cost = $scraperService->estimateCost($totalPosts);

// Example: 5 competitors × 50 posts = 250 posts
// Cost: 250 × $0.007 = $1.75 per day
// Monthly: ~$50-60 (scraping + OpenAI analysis)
```

## 🆕 New Apify Features & Recommendations

### 1. **mcpc - Universal MCP CLI Client**

**რა არის**: MCP (Model Context Protocol) კლიენტი Apify სერვერებთან სამუშაოდ.

**მთავარი უპირატესობები**:
- OAuth 2.1 + PKCE authentication out of the box
- Persistent sessions (არ სჭირდება ყოველჯერ re-authentication)
- `--json` output mode for scripting
- MCP proxy mode (stdio → HTTP)

**როგორ გამოვიყენოთ**:

```bash
# Installation
npm install -g @modelcontextprotocol/cli

# Connect to Apify MCP server
mcpc connect https://mcp.apify.com

# List available tools
mcpc tools list --json

# Execute Apify actor via MCP
mcpc tool call run_actor --json \
  --param actorId=apify/facebook-posts-scraper \
  --param input='{"startUrls":[{"url":"..."}]}'

# Save session for reuse
mcpc session save apify-session
mcpc session load apify-session
```

**ჩვენთვის რატომ სასარგებლოა**:
- უფრო მარტივი OAuth authentication
- Session persistence (ნაკლები API calls)
- JSON output ideal for Laravel integration
- Sandbox execution (ინსტრუმენტების გამოყოფა კოდიდან)

**Laravel Integration Example**:

```php
// Create new service: App\Services\ApifyMcpService.php
class ApifyMcpService
{
    public function runActorViaMcp(string $actorId, array $input): array
    {
        $command = sprintf(
            'mcpc tool call run_actor --json --param actorId=%s --param input=%s',
            escapeshellarg($actorId),
            escapeshellarg(json_encode($input))
        );
        
        $output = shell_exec($command);
        return json_decode($output, true);
    }
}
```

### 2. **Apify Agent Skills**

**რა არის**: Reusable instruction sets for AI coding assistants (Claude, Cursor).

**Available Skills**:

#### a) `apify-actor-development`
- Teaches AI how to build Apify Actors
- Best practices for project structure
- Error handling patterns
- Platform conventions

#### b) `apify-ultimate-scraper`
- Teaches AI how to select right Actor for scraping
- Input configuration guidelines
- Common scraping challenges & solutions

**როგორ დავაყენოთ**:

```bash
# Install skills in your project
npx skills add apify/agent-skills

# Skills will be stored in .skills/ directory
# AI assistant automatically picks them up
```

**რატომ სასარგებლოა ჩვენთვის**:
1. AI-assisted actor selection (რომელი scraper რა ვითარებაში)
2. Automatic input optimization suggestions
3. Better error handling patterns
4. Faster development when creating custom actors

**Use Case Example**:

```
User to AI: "I need to scrape Facebook pages with better rate limiting"

AI (with skills): 
"Based on apify-ultimate-scraper skill, I recommend:
1. Use apify/facebook-posts-scraper
2. Set proxyConfiguration.useApifyProxy = true
3. Add maxRequestRetries: 3
4. Enable respectRobotsTxt: false for Facebook
5. Consider residential proxies for better success rate

Here's the optimized config..."
```

### 3. **SSE Transport → Streamable HTTP Migration**

**მნიშვნელოვანი ცვლილება**: Apify MCP server მიგრირებულია SSE-დან Streamable HTTP-ზე.

**Deadline**: April 1, 2026

**რა უნდა გავაკეთოთ**:

```php
// OLD (deprecated)
$mcpEndpoint = 'https://mcp.apify.com/sse';

// NEW (required after April 1)
$mcpEndpoint = 'https://mcp.apify.com';
```

**ჩვენი კოდი**: 
- ✅ მხოლოდ REST API-ს ვიყენებთ ამჟამად
- ✅ არ გვჭირდება ცვლილება
- ⚠️ თუ მომავალში MCP-ს დავამატებთ, გამოვიყენოთ ახალი endpoint

## 🎯 Best Practices & Use Cases

### Use Case 1: Daily Competitor Monitoring

**Scenario**: ყოველდღე დილით 3 საათზე გავაპარსოთ კონკურენტები

```php
// Kernel.php
$schedule->command('competitors:scrape-facebook --analyze')
    ->dailyAt('03:00')
    ->emailOutputOnFailure('admin@mytechnic.ge');
```

**Outcome**: 
- ახალი პოსტები დილის 3 საათისთვის
- AI ფილტრი ავტომატურად გამოარჩევს რელევანტურებს
- Admin დილით უკვე ხედავს insights-ებს

### Use Case 2: Weekly Strategic Analysis

**Scenario**: კვირის ბოლოს სრული ანალიზი

```php
$schedule->command('competitors:scrape-facebook --weekly-analysis')
    ->weeklyOn(0, '04:00'); // Sunday 4 AM
```

**Outcome**:
- 4-dimensional analysis (pricing, content, sentiment, trends)
- Actionable recommendations with priorities
- Content strategy insights

### Use Case 3: Price Alert System

**Custom Implementation**:

```php
// Create observer: App\Observers\FacebookCompetitorPostObserver.php
class FacebookCompetitorPostObserver
{
    public function created(FacebookCompetitorPost $post)
    {
        // Check if post mentions pricing
        if ($post->product_mentions_json) {
            foreach ($post->product_mentions_json as $product) {
                if (isset($product['price'])) {
                    FacebookCompetitorInsight::create([
                        'insight_type' => 'price_alert',
                        'priority' => 'high',
                        'title' => "ახალი ფასი: {$product['model']}",
                        'description' => "კონკურენტმა განაახლა ფასი",
                        'data_json' => $product,
                        'competitor_page_id' => $post->competitor_page_id,
                    ]);
                }
            }
        }
    }
}
```

### Use Case 4: Content Gap Analysis

**Custom Service**:

```php
// App\Services\ContentGapAnalyzer.php
class ContentGapAnalyzer
{
    public function findGaps(): array
    {
        // What competitors post about that we don't
        $competitorTopics = $this->getCompetitorTopics();
        $ourTopics = $this->getOurTopics();
        
        return array_diff($competitorTopics, $ourTopics);
    }
    
    private function getCompetitorTopics(): array
    {
        $posts = FacebookCompetitorPost::where('is_relevant', true)
            ->where('scraped_at', '>=', now()->subMonth())
            ->get();
            
        // Extract topics using AI
        return $this->extractTopics($posts);
    }
}
```

## 📈 Performance Optimization

### 1. Batch Processing

```php
// Instead of individual API calls
foreach ($pages as $page) {
    $scraper->scrapeCompetitorPage($page); // N API calls
}

// Use batch with delay
$pages->chunk(3)->each(function ($chunk) use ($scraper) {
    foreach ($chunk as $page) {
        $scraper->scrapeCompetitorPage($page);
    }
    sleep(60); // Respect rate limits
});
```

### 2. Incremental Scraping

```php
// Only scrape new posts since last scrape
$input = [
    'startUrls' => [['url' => $page->facebook_url]],
    'maxPosts' => 50,
    'since' => $page->last_scraped_at?->toIso8601String(),
];
```

### 3. Caching Analysis Results

```php
// Cache expensive AI analysis
$insights = Cache::remember(
    "competitor_insights_{$page->id}",
    now()->addHours(24),
    fn() => $aiService->generateInsights($page)
);
```

## 🔐 Security Considerations

1. **API Token Security**
   - ✅ Store in `.env` file
   - ✅ Never commit to git
   - ⚠️ Rotate tokens quarterly

2. **Rate Limiting**
   ```php
   RateLimiter::for('apify-scraping', function (Request $request) {
       return Limit::perMinute(10);
   });
   ```

3. **Data Privacy**
   - მხოლოდ public პოსტები
   - არ ვინახავთ personal data
   - GDPR compliance via data retention policy

## 📚 Additional Resources

- [Apify Documentation](https://docs.apify.com)
- [Facebook Posts Scraper](https://apify.com/apify/facebook-posts-scraper)
- [mcpc GitHub](https://github.com/modelcontextprotocol/cli)
- [Apify Agent Skills](https://github.com/apify/agent-skills)
- [MCP Specification](https://modelcontextprotocol.io)

## 🐛 Troubleshooting

### Problem: "Apify token is missing"
**Solution**: 
```bash
php artisan config:clear
# Check .env has APIFY_API_TOKEN
```

### Problem: "Actor run failed (HTTP 401)"
**Solution**: Token expired or invalid. Generate new token from Apify Console.

### Problem: "No relevant posts found"
**Solution**: 
- AI filter too strict
- Adjust prompts in `FacebookCompetitorAiService::buildRelevancePrompt()`
- Lower relevance_score threshold

### Problem: Facebook blocking scraper
**Solution**:
```php
'proxyConfiguration' => [
    'useApifyProxy' => true,
    'apifyProxyGroups' => ['RESIDENTIAL'], // Use residential proxies
]
```

## 💡 Future Enhancements

1. **Real-time Notifications**
   - WebSocket alerts for high-priority insights
   - Telegram/Slack integration

2. **Competitor Benchmarking**
   - Visual charts comparing engagement rates
   - Growth trends over time

3. **AI-Generated Response Suggestions**
   - Auto-generate competitive responses
   - Content calendar based on competitor activity

4. **Image Analysis**
   - Analyze competitor's product images
   - Detect pricing in images using OCR

5. **Multi-platform Support**
   - Instagram competitors
   - TikTok monitoring
   - LinkedIn company pages

---

**Created**: March 18, 2026  
**Last Updated**: March 18, 2026  
**Version**: 1.0
