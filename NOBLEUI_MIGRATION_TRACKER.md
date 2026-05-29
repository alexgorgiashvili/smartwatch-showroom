# NobleUI Migration Tracker

## Phase 0: Foundation

### Phase 0-A: Core JS/CSS Infrastructure
- [x] **0-A-1** Create `resources/css/admin.css`
- [x] **0-A-2** Create `resources/js/admin-helpers.js`
- [x] **0-A-3** Create `resources/js/admin-router.js`
- [x] **0-A-4** Create `resources/js/admin.js`
- [x] **0-A-5** Update `vite.config.js`

### Phase 0-B: Layout, Auth & Views
- [x] **0-B-1** Update `resources/views/admin/layout.blade.php`
- [x] **0-B-2** Create `resources/views/admin/login.blade.php`
- [x] **0-B-3** Create `app/Http/Controllers/Admin/DashboardController.php`
- [x] **0-B-4** Create `resources/views/admin/dashboard.blade.php`

### Phase 0-C: Sidebar, Routes & Wiring
- [x] **0-C-1** Rewrite `resources/views/admin/partials/sidebar.blade.php`
- [x] **0-C-2** Reorganize `routes/web.php` + create `_placeholder.blade.php`
- [x] **0-C-3** `npm run build` — verified (admin.js 7KB, admin.css 6.5KB)
- [ ] **0-C-4** Manual browser test

---

## Phase 1: Dashboard
- [x] DashboardController with real widget data (overview, commerce, inventory, chatbot, inbox)
- [x] Dashboard view with stat cards, orders chart, quick actions, recent tables
- [x] admin-dashboard.js (ApexCharts mixed column/area chart)

## Phase 2: Products (Full CRUD)
- [x] Product index view — PJAX-aware + DataTable
- [x] Product create/edit forms (tabbed: Basic, Descriptions, Specs, SEO)
- [x] Variant inline CRUD (SweetAlert2 modals)
- [x] Image management (upload, set primary, delete)
- [x] admin-products.js (DataTable, variant/image/stock handlers)
- [x] ProductController — PJAX fragment support

## Phase 3: Orders (CRUD + Status Actions)
- [x] Order index view — PJAX-aware + payment status filters
- [x] Order create form (dynamic items, price calc)
- [x] Order detail view (customer info, items, payment logs)
- [x] Status/Payment update actions (inline forms)
- [x] admin-orders.js (item management, price calculation)
- [x] OrderController — PJAX fragment support

## Phase 4: Payments + Inquiries (Read-only)
- [x] Payment index — placeholder (inline route)
- [x] Inquiry index — placeholder (inline route)

## Phase 5: Articles (Full CRUD)
- [x] Article index view — PJAX-aware + status filters + search
- [x] Article create/edit views + shared _form partial (Content + SEO tabs)
- [x] Toggle publish action
- [x] ArticleController — PJAX fragment support

## Phase 6: Inbox (Full Rebuild)
- [x] InboxController with JSON API (conversations, messages, send, status, priority, AI toggle, counts)
- [x] 3-column PJAX-aware layout (sidebar list + chat + details panel)
- [x] admin-inbox.js (conversation list, chat, send, polling, actions)
- [x] Routes registered (GET/POST/PATCH for all inbox endpoints)

## Phase 7: Facebook Posts + Social Comments
- [x] Facebook post index/create/edit views + shared _form partial
- [x] FacebookPostController — PJAX fragment support
- [x] Social Comments — SocialCommentController (list, status, bulk, AI reply, hide, fetch)
- [x] Social Comments view — DataTable + filters + AI Reply modal + bulk actions
- [x] admin-social-comments.js — full SPA module

## Phase 8: Social Dashboard + AI Lab (5 pages)
- [x] Social Dashboard — SocialDashboardController + stats view (posts/comments KPIs + recent posts table)
- [x] Chatbot Content index view + ChatbotContentController PJAX
- [x] Chatbot Lab (index/cases/runs) — @fragment + ChatbotLabController PJAX
- [x] Alibaba Import view + AlibabaImportController PJAX
- [x] AI Analytics, Chatbot Testing, Chatbot Traces — working placeholders

## Phase 9: Competition + SEO + Admin
- [x] Competitor Monitor view + CompetitorMonitorController PJAX
- [x] Users index view + UserController PJAX
- [x] FB Competitors, SEO Monitoring, SMS Activation — working placeholders

## Phase 10: Filament Removal & Cleanup ✅
- [x] Removed filament/filament from composer.json + composer update (26 packages removed incl livewire)
- [x] Deleted app/Filament/ (46 files), app/Livewire/ (16 files), app/Providers/Filament/
- [x] Deleted resources/views/filament/, resources/views/livewire/
- [x] Deleted public/css/filament/, public/js/filament/
- [x] Removed SetFilamentLocale middleware
- [x] Cleaned User.php (removed FilamentUser interface + canAccessPanel)
- [x] Cleaned config/app.php (removed AdminPanelProvider)
- [x] Fixed broken Filament route refs in chatbot-lab view, PushSubscriptionController, tests
- [x] Deleted stale debug/test files (FilamentInboxSmokeTest, test_dashboard, test_modal, check_error, debug_inbox_render, public/test.php)
- [x] composer dump-autoload --optimize, optimize:clear — app boots with 161 routes
