# Phase 6: Advanced SEO Features - Setup Guide

## 📋 Overview

Phase 6 დამატებს advanced SEO features-ს:
- Google Search Console Integration
- Image Sitemap
- Sitemap Index
- Video Schema Support

---

## 🚀 შექმნილი ფაილები

### Services
1. `app/Services/GoogleSearchConsoleService.php` - GSC API integration
2. `app/Services/VideoSchemaService.php` - Video schema helpers

### Controllers
3. `app/Http/Controllers/ImageSitemapController.php` - Image sitemap XML
4. `app/Http/Controllers/SitemapIndexController.php` - Sitemap index

### Configuration
5. `config/services.php` - Google services config
6. `public/robots.txt` - Updated with all sitemaps

---

## 📦 Required Packages

### Google API Client (Optional)

თუ გსურთ Google Search Console integration:

```bash
composer require google/apiclient:"^2.0"
```

**Note:** Google API Client არ არის აუცილებელი Phase 6-ის სხვა ფუნქციებისთვის (Image Sitemap, Video Schema).

---

## ⚙️ Configuration

### 1. Environment Variables

დაამატეთ `.env` ფაილში:

```env
# Google Search Console (Optional)
GOOGLE_SEARCH_CONSOLE_CREDENTIALS=/path/to/credentials.json
GOOGLE_SEARCH_CONSOLE_SITE_URL=https://mytechnic.ge
```

### 2. Google Search Console Setup (Optional)

თუ გსურთ GSC integration:

1. **Google Cloud Console:**
   - გადადით: https://console.cloud.google.com
   - შექმენით ახალი project ან აირჩიეთ არსებული
   - Enable "Google Search Console API"

2. **Service Account:**
   - IAM & Admin → Service Accounts
   - Create Service Account
   - Grant "Search Console API User" role
   - Create JSON key და ჩამოტვირთეთ

3. **Search Console:**
   - გადადით: https://search.google.com/search-console
   - Add property: `https://mytechnic.ge`
   - Settings → Users and permissions
   - Add service account email as Owner

4. **Credentials:**
   - ჩამოტვირთული JSON ფაილი გადაიტანეთ: `storage/app/google/`
   - `.env`: `GOOGLE_SEARCH_CONSOLE_CREDENTIALS=storage/app/google/credentials.json`

---

## 🗺️ Sitemaps

### Available Sitemaps

1. **Main Sitemap:** `/sitemap.xml`
   - Static pages
   - Products
   - Blog posts
   - Landing pages
   - City pages

2. **Image Sitemap:** `/sitemap-images.xml`
   - Product images
   - Article featured images

3. **Sitemap Index:** `/sitemap-index.xml`
   - References all sitemaps

### Testing

```bash
# Test sitemaps locally
curl http://127.0.0.1:8000/sitemap.xml
curl http://127.0.0.1:8000/sitemap-images.xml
curl http://127.0.0.1:8000/sitemap-index.xml
```

### Submit to Google

1. Google Search Console → Sitemaps
2. Add: `https://mytechnic.ge/sitemap-index.xml`

---

## 📹 Video Schema

### Usage Example

```php
use App\Services\VideoSchemaService;

$videoService = new VideoSchemaService();

// YouTube video
$videoId = $videoService->parseYouTubeUrl('https://www.youtube.com/watch?v=ABC123');
$embedUrl = $videoService->getYouTubeEmbedUrl($videoId);
$thumbnail = $videoService->getYouTubeThumbnail($videoId);

// Generate schema
$schema = $videoService->generateProductVideoSchema([
    'name' => 'Smart Watch Setup Tutorial',
    'description' => 'How to set up your kids smartwatch',
    'thumbnail_url' => $thumbnail,
    'upload_date' => '2024-01-15T00:00:00Z',
    'duration' => $videoService->formatDuration(180), // 3 minutes
    'content_url' => 'https://www.youtube.com/watch?v=ABC123',
    'embed_url' => $embedUrl,
]);
```

### In Blade Template

```blade
@push('json_ld')
<script type="application/ld+json">
{!! json_encode($videoSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
@endpush
```

---

## 🔍 Google Search Console Service

### Usage Example

```php
use App\Services\GoogleSearchConsoleService;

$gsc = new GoogleSearchConsoleService();

if ($gsc->isAvailable()) {
    // Get top queries
    $topQueries = $gsc->getTopQueries('https://mytechnic.ge', 10);
    
    // Get search analytics
    $analytics = $gsc->getSearchAnalytics('https://mytechnic.ge', 30);
    
    // Submit URL for indexing
    $gsc->submitUrl('https://mytechnic.ge/products/new-product');
    
    // Get indexing status
    $status = $gsc->getIndexingStatus('https://mytechnic.ge');
}
```

### Artisan Command Example

შექმენით command GSC მონაცემების სინქრონიზაციისთვის:

```php
php artisan make:command SyncSearchConsoleData
```

---

## 📊 Monitoring

### SEO Health Check

```bash
php artisan seo:health-check
```

ახლა შეამოწმებს:
- ✅ robots.txt
- ✅ Sitemap accessibility
- ✅ Products meta
- ✅ Images optimization
- ✅ Schema markup
- ✅ Performance

---

## 🎯 Next Steps

### Immediate

1. ✅ Test all sitemaps locally
2. ✅ Submit sitemap-index.xml to Google Search Console
3. ⏳ (Optional) Setup Google API credentials
4. ⏳ (Optional) Install google/apiclient package

### Production Deployment

```bash
# Clear caches
php artisan optimize:clear

# Test routes
php artisan route:list | grep sitemap

# Verify sitemaps
curl https://mytechnic.ge/sitemap-index.xml
```

### Google Search Console

1. Add property: `https://mytechnic.ge`
2. Submit sitemap: `/sitemap-index.xml`
3. Monitor:
   - Coverage
   - Performance
   - Enhancements
   - Mobile usability

---

## 📈 Expected Results

### Sitemaps
- ✅ Better crawling efficiency
- ✅ Faster indexing of new content
- ✅ Image search visibility

### Video Schema
- ✅ Rich video results in Google
- ✅ Video thumbnails in search
- ✅ Better engagement

### GSC Integration (Optional)
- ✅ Real-time search data
- ✅ Performance tracking
- ✅ Automated URL submission

---

## 🐛 Troubleshooting

### Sitemap 404 Error

```bash
php artisan route:clear
php artisan optimize:clear
```

### Google API Errors

Check:
1. Credentials file path in `.env`
2. Service account permissions in GSC
3. API enabled in Google Cloud Console

### Image Sitemap Empty

Verify:
- Products have images
- `storage/app/public` is linked: `php artisan storage:link`

---

## 📚 Resources

- [Google Search Console API](https://developers.google.com/webmaster-tools)
- [Sitemap Protocol](https://www.sitemaps.org/protocol.html)
- [Image Sitemap](https://developers.google.com/search/docs/crawling-indexing/sitemaps/image-sitemaps)
- [Video Schema](https://developers.google.com/search/docs/appearance/structured-data/video)

---

## ✅ Phase 6 Complete!

ყველა advanced SEO feature იმპლემენტირებულია და მზადაა გამოსაყენებლად.
