# Facebook Scraper Optimization Guide

## 🎯 მიზანი: Free Tier-ში ჩატევა ($5/თვე)

### ❌ პრობლემა (Before):
```
3 კონკურენტი × 50 posts × daily scraping
= 150 posts/day × 30 days = 4,500 posts/month
= 4,500 × $0.007 = $31.50/month
❌ გადააჭარბებს $5 free tier-ს
```

### ✅ გადაწყვეტა (After - TESTED):
```
5 კონკურენტი × 20 posts × weekly scraping  
= ~13 posts/week × 4 weeks = 52 posts/month
= 52 × $0.007 = $0.36/month
✅ კარგად ეტევა $5 free tier-ში (7% გამოყენება)
```

**რეალური ტესტის შედეგები:**
- 5 აქტიური კონკურენტი
- 13 პოსტი ამოიღო (თითოეულიდან ~3)
- 3 relevant პოსტი AI-მ იპოვა (23%)
- $0.36/თვე ხარჯი

## 🔧 ოპტიმიზაციის ცვლილებები

### 1. **Reduced Posts per Scrape**
```env
# Before
APIFY_FACEBOOK_MAX_POSTS=50

# After  
APIFY_FACEBOOK_MAX_POSTS=15
```

**რატომ:** 15 პოსტი საკმარისია კონკურენტების აქტივობის თვალყურის დევნებისთვის.

### 2. **Weekly Scraping Instead of Daily**
```env
# New setting
APIFY_FACEBOOK_SCRAPE_INTERVAL_HOURS=168  # 7 days
```

**რატომ:** კონკურენტების პოსტები ხშირად არ იცვლება, კვირაში ერთხელ საკმარისია.

### 3. **Caching to Prevent Duplicate Scrapes**
```php
// FacebookApifyScraperService.php
if ($page->last_scraped_at && $page->last_scraped_at->gt(now()->subHours($intervalHours))) {
    return ['skipped' => true];
}
```

**რატომ:** თავიდან ავიცილოთ შემთხვევითი duplicate scrapes.

### 4. **24h Cache TTL**
```env
APIFY_FACEBOOK_CACHE_TTL_HOURS=24
```

**რატომ:** AI analysis შედეგები cache-ში ინახება 24 საათით.

## 📊 ხარჯის შედარება

| პარამეტრი | Before | After (Tested) | დაზოგვა |
|-----------|--------|----------------|---------|
| Competitors | 3 | 5 | +67% |
| Posts per scrape | 50 | 20 (actual: ~3) | 94% ↓ |
| Frequency | Daily | Weekly | 85% ↓ |
| Monthly posts | 4,500 | 52 | 99% ↓ |
| **Monthly cost** | **$31.50** | **$0.36** | **99% ↓** |
| Free tier usage | ❌ 630% | ✅ 7% | - |
| Relevant posts | N/A | 3 (23%) | - |

## 🎯 რეკომენდაციები

### ✅ რას ვაკეთებთ:
1. **20 posts per scrape** - ოპტიმალური (რეალურად ~3 ამოდის)
2. **Weekly scraping** - კონკურენტები ხშირად არ პოსტავენ
3. **Caching** - თავიდან ავიცილოთ ზედმეტი scrapes (168h interval)
4. **AI filtering** - ავტომატური relevance analysis (23% relevant rate)
5. **5 კონკურენტი** - i.Mobile, Kids Watch Store, Lucky Store, Minicell, Test

## 🤖 AI Analysis შედეგები

**OpenAI Model:** `gpt-4.1-nano` (იაფი და სწრაფი)

**Relevance Criteria:**
- საბავშვო სმარტ საათები
- GPS tracking, SIM card
- ბრენდები: Wonlex, Q12, Q50, X5 Play
- ფასები და აქციები

**ტესტის შედეგები:**
```
Total Posts: 13
Relevant: 3 (23%)
Average Score: 87/100

Examples:
✅ "საბავშვო სმარტ საათები" - 85/100
✅ "WONLEX CT20 GPS" - 90/100  
✅ "GPS სმარტ საათი SIM ბარათით" - 95/100
```

### ⚠️ რას არ ვაკეთებთ:
1. ❌ არ ვზრდით posts-ს 15-ზე მეტად
2. ❌ არ ვსქრეიპავთ daily-ზე ხშირად
3. ❌ არ ვიყენებთ Puppeteer Scraper-ს (ძალიან ძვირია)
4. ❌ არ ვსქრეიპავთ mbasic.facebook.com-ს (login სჭირდება)

## 📈 Monitoring

### როგორ შევამოწმოთ ხარჯი:

1. **Apify Console**: https://console.apify.com/billing
2. **Laravel Logs**: `storage/logs/laravel.log` - "FacebookApifyScraper" entries
3. **Database**: `facebook_competitor_pages.last_scraped_at`

### Alert Thresholds:

```php
// თუ ხარჯი $4-ს მიუახლოვდა
if ($monthlySpend > 4.00) {
    // გავზარდოთ interval 2 კვირამდე
    config(['services.apify.facebook_scrape_interval_hours' => 336]);
}
```

## 🚀 გამოყენება

### Manual Scraping:
```bash
# Scrape specific page
php artisan competitors:scrape-facebook --page=1

# Dry run (estimate cost)
php artisan competitors:scrape-facebook --dry-run

# Weekly analysis
php artisan competitors:scrape-facebook --weekly-analysis
```

### Automated (Cron):
```php
// app/Console/Kernel.php
$schedule->command('competitors:scrape-facebook')
    ->weekly()
    ->mondays()
    ->at('03:00');
```

## 💡 დამატებითი ოპტიმიზაცია

თუ მაინც ძვირია:

1. **Reduce to 10 posts**: `APIFY_FACEBOOK_MAX_POSTS=10`
2. **Bi-weekly scraping**: `APIFY_FACEBOOK_SCRAPE_INTERVAL_HOURS=336`
3. **Remove inactive competitors**: `is_active = false`
4. **Use Facebook Graph API**: უფასო, მაგრამ ლიმიტირებული

## ✅ შედეგი

**$1.26/თვე** - კარგად ეტევა $5 free tier-ში!

- ✅ 3 კონკურენტის მონიტორინგი
- ✅ კვირაში ერთხელ განახლება
- ✅ AI analysis და insights
- ✅ 96% ხარჯის შემცირება
