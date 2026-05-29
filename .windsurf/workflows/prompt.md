---
description: Transform a short idea into a detailed, optimized prompt for Cascade
---

Take the text that follows `/prompt` and transform it into a well-structured, detailed prompt optimized for Cascade/AI agents.

Use the full project context below when expanding the request. Output ONLY the final prompt wrapped inside a single fenced code block (``` at start and end, no language tag) — no explanations, no preamble, no meta-commentary outside the block. The code block ensures the copy button is available.

### Project context
- **Framework**: Laravel 10, PHP 8.1
- **Admin panel**: Custom NobleUI (Bootstrap 5, PJAX navigation via admin-router.js, no Filament, no Livewire)
- **Frontend**: Blade templates, TailwindCSS, Vite, Splide slider, Font Awesome
- **Real-time**: Pusher + Laravel Echo, Web Push notifications (minishlink/web-push)
- **AI / Chatbot**: OpenAI GPT models, Pinecone vector search, multi-agent pipeline (SupervisorAgent, ComparisonAgent, GeneralAgent, InventoryAgent, VectorSqlReconciliationAgent), hybrid search, circuit breaker, bifurcated memory, chatbot lab, adaptive learning
- **Integrations**: Meta Graph API (Facebook/Instagram), Apify scrapers (Alibaba, Facebook competitors), Grizzly SMS activation, BOG payment gateway, WooCommerce
- **Services**: AlibabaScraperService, FacebookApifyScraperService, FacebookCompetitorAiService, AiPostGeneratorService, AiSuggestionService, BogPayService, SocialCommentService, GrizzlySmsService, EmbeddingService, PineconeService, ModelCompletionService
- **Data**: MySQL, Redis (predis), Spatie sitemap
- **Inbox**: Custom real-time inbox (Pusher), no Livewire
- **Language**: Georgian UI (ka locale), Georgian prompts

### Coding conventions
- Controllers return views or JSON — no Livewire, no Filament resources
- Admin controllers in `app/Http/Controllers/Admin/`, middleware `['auth', 'admin']`
- PJAX-aware: check `$request->header('X-PJAX')` → return `->fragment('content')`
- Blade: `resources/views/admin/` (admin), `resources/views/` (public); extend `admin.layout` or `layouts.app`
- Services injected via constructor DI; chatbot services in `app/Services/Chatbot/`
- OpenAI model selection: `gpt-4.1-nano` (cheap), `gpt-4.1-mini` (reasoning), `gpt-4.1` (critical)
- Redis for caching (Cache facade + predis); queue driver = Redis
- Georgian strings directly in Blade — no translation files for admin UI

### Key DB tables
`users`, `products`, `product_variants`, `product_images`, `orders`, `order_items`, `conversations`, `messages`, `customers`, `facebook_posts`, `social_comments`, `facebook_competitor_pages`, `facebook_competitor_posts`, `chatbot_training_cases`, `chatbot_test_runs`, `chatbot_test_results`, `articles`, `push_subscriptions`, `payment_logs`

### Admin panel patterns
- Sidebar: `resources/views/admin/partials/sidebar.blade.php` — `route()` + `data-pjax`
- Content swapped into `<div id="page-content">` via PJAX
- JS initializers: `AdminRouter.registerPage(pattern, fn)` in `resources/js/admin.js`
- Toasts: `showToast(message, type)` | Confirm: `confirmDelete(url, csrfToken)` from `admin-helpers.js`
- DataTables initialized per-page inside `registerPage` callbacks
