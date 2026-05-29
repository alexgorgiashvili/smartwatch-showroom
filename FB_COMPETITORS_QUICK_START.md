# Facebook Competitors - Quick Start Guide

## ⚡ 5-Minute Setup

### 1. დააკონფიგურირე .env

```env
APIFY_API_TOKEN=apify_api_xxxxx
OPENAI_API_KEY=sk-xxxxx
```

### 2. გაუშვი Migration

```bash
php artisan migrate
```

### 3. გახსენი Admin Panel

```
Admin → Competition → FB კონკურენტები
```

### 4. დაამატე პირველი კონკურენტი

```
სახელი: SmartWatch GE
URL: https://facebook.com/smartwatchge
კატეგორია: ელექტრონიკა
სიხშირე: ყოველდღიური
✓ აქტიური
```

### 5. გაუშვი გაპარსვა

```bash
php artisan competitors:scrape-facebook --analyze
```

ან დააჭირე "ყველას გაპარსვა" ღილაკს დეშბორდზე.

## 📊 რას ვიღებ?

✅ **Automated Daily Scraping** - ყოველდღიური მონიტორინგი  
✅ **AI Relevance Filter** - მხოლოდ kids smartwatch-ების პოსტები  
✅ **Weekly Analysis** - competitive intelligence 4 განზომილებით  
✅ **Smart Insights** - ავტომატური რეკომენდაციები პრიორიტეტებით  
✅ **Georgian UI** - სრულად ქართულ ენაზე  

## 🎯 სწრაფი მოქმედებები

### ცალკე კონკურენტის გაპარსვა
```bash
php artisan competitors:scrape-facebook --page=1 --max-posts=100
```

### AI რელევანტურობის ანალიზი
```bash
php artisan competitors:scrape-facebook --analyze
```

### კვირეული სრული ანალიზი
```bash
php artisan competitors:scrape-facebook --weekly-analysis
```

### ღირებულების შეფასება
```bash
php artisan competitors:scrape-facebook --dry-run
```

## 📱 Admin Panel ფუნქციები

### Dashboard
- **სტატისტიკა**: კონკურენტები, პოსტები, რელევანტურობა, insights
- **4 Tabs**: კონკურენტები | პოსტები | Insights | ანალიზები

### ქმედებები თითოეულ კონკურენტზე
- 👁️ **ნახვა** - დეტალური გვერდი პოსტებით
- 🔄 **გაპარსვა** - ახალი პოსტების მოძიება
- ⚡ **AI ფილტრი** - რელევანტურობის განსაზღვრა
- ✏️ **რედაქტირება** - სახელი, URL, სიხშირე
- 🗑️ **წაშლა** - კონკურენტის ამოშლა

### Insights Management
- **პრიორიტეტები**: HIGH (წითელი), MEDIUM (ყვითელი), LOW (ლურჯი)
- **ტიპები**: price_alert, content_opportunity, sentiment_shift, trend_emerging
- **სტატუსები**: new → acknowledged → actioned / dismissed

## 🆕 Apify სიახლეები

### mcpc CLI (რეკომენდირებული)
```bash
# Installation
npm install -g @modelcontextprotocol/cli

# Connect
mcpc connect https://mcp.apify.com

# Run actor via MCP
mcpc tool call run_actor --json \
  --param actorId=apify/facebook-posts-scraper \
  --param input='{"startUrls":[{"url":"https://facebook.com/page"}]}'
```

**უპირატესობები**:
- OAuth authentication ავტომატური
- Persistent sessions
- JSON output for Laravel
- Sandbox execution

### Agent Skills (AI-Assisted Development)
```bash
npx skills add apify/agent-skills
```

**რას გაძლევს**:
- AI რჩევები actor-ის არჩევაზე
- Input optimization suggestions
- Best practices auto-apply
- Error handling patterns

### SSE → Streamable HTTP Migration
**Important**: Apify MCP endpoint changed

```php
// ❌ OLD (deprecated after April 1, 2026)
'mcp_endpoint' => 'https://mcp.apify.com/sse'

// ✅ NEW (use this)
'mcp_endpoint' => 'https://mcp.apify.com'
```

## 💰 ღირებულების გაანგარიშება

```php
// Admin panel-ში:
FB კონკურენტები → "Cost Estimate" button

// CLI:
$cost = $scraperService->estimateCost($totalPosts);

// Example:
5 competitors × 50 posts/day = 250 posts
Cost: 250 × $0.007 = $1.75/day ≈ $50/month
+ OpenAI analysis ≈ $10/month
Total: ~$60/month
```

## ⚙️ Automation (Optional)

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Daily scraping at 3 AM
    $schedule->command('competitors:scrape-facebook --analyze')
        ->dailyAt('03:00')
        ->withoutOverlapping();
    
    // Weekly analysis on Sunday 4 AM
    $schedule->command('competitors:scrape-facebook --weekly-analysis')
        ->weeklyOn(0, '04:00')
        ->withoutOverlapping();
}
```

## 🔥 Best Use Cases

### 1. Price Monitoring
ავტომატურად აღმოაჩენს ფასების ცვლილებებს და ქმნის high-priority insights.

### 2. Content Ideas
ყოველკვირეული ანალიზი გიჩვენებს რა ტიპის კონტენტი მუშაობს საუკეთესოდ.

### 3. Market Positioning
Sentiment analysis და competitive intelligence გიჩვენებს როგორ პოზიციონირებენ კონკურენტები.

### 4. Trend Detection
AI ამოიცნობს emerging topics და declining interests.

## 🐛 Common Issues

**"Apify token is missing"**
```bash
php artisan config:clear
# Check APIFY_API_TOKEN in .env
```

**"No relevant posts found"**
- AI filter ძალიან მკაცრია
- დაამატე `--max-posts=200` მეტი პოსტისთვის

**"Facebook blocking"**
```php
// Use residential proxies
'proxyConfiguration' => [
    'useApifyProxy' => true,
    'apifyProxyGroups' => ['RESIDENTIAL'],
]
```

## 📚 Full Documentation

სრული დოკუმენტაცია: `FB_COMPETITORS_APIFY_GUIDE.md`

## 🎉 Ready!

გილოცავ! Facebook Competitors მონიტორინგი მზადაა.

ახლა:
1. დაამატე 3-5 კონკურენტი
2. გაუშვი scraping
3. დაელოდე AI analysis-ს
4. ნახე insights დეშბორდზე

**Questions?** Check full guide or contact dev team.
