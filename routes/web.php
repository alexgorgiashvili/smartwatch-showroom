<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ChatbotMetricsController as AdminChatbotMetricsController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ProductQualityController as AdminProductQualityController;
use App\Http\Controllers\Admin\ReadyGiftBoxController as AdminReadyGiftBoxController;
use App\Http\Controllers\Admin\ArticleController as AdminArticleController;
use App\Http\Controllers\Admin\AlibabaImportController as AdminAlibabaImportController;
use App\Http\Controllers\Admin\CompetitorMonitorController as AdminCompetitorMonitorController;
use App\Http\Controllers\Admin\WebhookController as AdminWebhookController;
use App\Http\Controllers\Admin\ProductImageController as AdminProductImageController;
use App\Http\Controllers\Admin\MediaController as AdminMediaController;
use App\Http\Controllers\Admin\StockAdjustmentController as AdminStockAdjustmentController;
use App\Http\Controllers\Admin\ChatbotContentController as AdminChatbotContentController;
use App\Http\Controllers\Admin\ChatbotLabController as AdminChatbotLabController;
use App\Http\Controllers\Admin\ChatbotTrainingController as AdminChatbotTrainingController;
use App\Http\Controllers\Admin\LangfuseDashboardController as AdminLangfuseDashboardController;
use App\Http\Controllers\Admin\LangfuseController as AdminLangfuseController;
use App\Http\Controllers\Admin\BridgeController as AdminBridgeController;
use App\Http\Controllers\Admin\FacebookPostController as AdminFacebookPostController;
use App\Http\Controllers\Admin\InboxController as AdminInboxController;
use App\Http\Controllers\Admin\SocialCommentController as AdminSocialCommentController;
use App\Http\Controllers\Admin\SocialDashboardController as AdminSocialDashboardController;
use App\Http\Controllers\Admin\PushSubscriptionController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\GiftBuilderController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\Site\CartController;
use App\Http\Controllers\Site\CheckoutController;
use App\Http\Controllers\Site\GeoPaymentController;
use App\Http\Controllers\Site\PaymentStatusController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\LandingPageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/sitemap-index.xml', [\App\Http\Controllers\SitemapIndexController::class, 'index'])->name('sitemap.index');
Route::get('/sitemap-images.xml', [\App\Http\Controllers\ImageSitemapController::class, 'index'])->name('sitemap.images');

// AI API Routes (for LLM optimization)
Route::prefix('api/ai')->group(function () {
    Route::get('/products', [\App\Http\Controllers\Api\AiProductsController::class, 'index'])->name('api.ai.products');
    Route::get('/products/{product}', [\App\Http\Controllers\Api\AiProductsController::class, 'show'])->name('api.ai.products.show');
    Route::get('/products/{product}/markdown', [\App\Http\Controllers\Api\AiContentController::class, 'showMarkdown'])->name('api.ai.products.markdown');
    Route::get('/recommendations', [\App\Http\Controllers\Api\AiRecommendationsController::class, 'index'])->name('api.ai.recommendations');
    Route::get('/knowledge', [\App\Http\Controllers\Api\AiKnowledgeController::class, 'index'])->name('api.ai.knowledge');
});

// AI Sitemap
Route::get('/sitemap-ai.xml', [\App\Http\Controllers\AiSitemapController::class, 'index'])->name('sitemap.ai');

Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('products.show');
Route::get('/products/{product:slug}/quick-review', [ProductController::class, 'quickReview'])->name('products.quick-review');
Route::get('/contact', [HomeController::class, 'contact'])->name('contact');
Route::get('/faq', [FaqController::class, 'index'])->name('faq');
Route::get('/about', fn () => view('pages.about'))->name('about');
Route::get('/privacy', fn () => view('pages.privacy'))->name('privacy');
Route::get('/terms', fn () => view('pages.terms'))->name('terms');
Route::get('/lang/{locale}', [HomeController::class, 'locale'])->name('locale');
Route::post('/inquiries', [InquiryController::class, 'store'])->name('inquiries.store');

// Blog
Route::get('/blog', [ArticleController::class, 'index'])->name('blog.index');
Route::get('/blog/{article:slug}', [ArticleController::class, 'show'])->name('blog.show');

// Landing pages — niche SEO
Route::get('/smartwatches/bavshvis-saati-{range}', [LandingPageController::class, 'age'])
    ->name('landing.age')
    ->where('range', '4-6|7-10|11-14');
Route::get('/sim-card-guide', [LandingPageController::class, 'simGuide'])->name('landing.sim-guide');
Route::get('/gift-guide', [LandingPageController::class, 'giftGuide'])->name('landing.gift-guide');
Route::get('/gift-box-builder', [GiftBuilderController::class, 'show'])->name('gift-builder.show');
Route::get('/gift-boxes', [GiftBuilderController::class, 'boxes'])->name('gift-builder.boxes');
Route::get('/gift-boxes/{box}/options', [GiftBuilderController::class, 'readyBoxOptions'])->name('gift-boxes.options');
Route::post('/gift-boxes/{box}/add-to-cart', [GiftBuilderController::class, 'addReadyBoxToCart'])->name('gift-boxes.add-to-cart');
Route::get('/gift-box-builder/products', [GiftBuilderController::class, 'products'])->name('gift-builder.products');
Route::post('/gift-box-builder/price', [GiftBuilderController::class, 'price'])->name('gift-builder.price');
Route::post('/gift-box-builder/add-to-cart', [GiftBuilderController::class, 'addToCart'])->name('gift-builder.add-to-cart');

// City landing pages — local SEO
Route::get('/city/{city}', [\App\Http\Controllers\CityLandingController::class, 'show'])
    ->name('landing.city')
    ->where('city', 'tbilisi|batumi|kutaisi|rustavi|gori');
Route::post('/chatbot', [ChatController::class, 'respond'])
	->name('chatbot.respond')
	->middleware('throttle:30,1');
Route::get('/chatbot/history', [ChatController::class, 'history'])
	->name('chatbot.history')
	->middleware('throttle:10,1');

Route::get('/cart', [CartController::class, 'show'])->name('cart.index');
Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
Route::patch('/cart/update', [CartController::class, 'update'])->name('cart.update');
Route::patch('/cart/replace-variant', [CartController::class, 'replaceVariant'])->name('cart.replace-variant');
Route::delete('/cart/remove', [CartController::class, 'remove'])->name('cart.remove');
Route::delete('/cart/gift-groups/{group}', [GiftBuilderController::class, 'removeFromCart'])->name('cart.gift-groups.remove');
Route::delete('/cart/clear', [CartController::class, 'clear'])->name('cart.clear');

Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.index');
Route::post('/order/validate', [GeoPaymentController::class, 'validatePaymentOrder'])->name('payment.validate');
Route::get('/bog/payment/redirect', [GeoPaymentController::class, 'bogPayRedirect'])->name('payment.bog.redirect');
Route::post('/bog/payment/callback', [GeoPaymentController::class, 'bogPaymentCallback'])->name('payment.bog.callback');
Route::get('/payment/success', [PaymentStatusController::class, 'success'])->name('payment.success');
Route::get('/payment/fail', [PaymentStatusController::class, 'fail'])->name('payment.fail');

/*
|--------------------------------------------------------------------------
| Facebook Webhook Routes
|--------------------------------------------------------------------------
*/
Route::get('/webhook/facebook', [AdminWebhookController::class, 'verify']);
Route::post('/webhook/facebook', [AdminWebhookController::class, 'handle'])
	->middleware('webhook.verify');

/*
|--------------------------------------------------------------------------
| Admin Auth Routes (outside auth middleware)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {
	Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
	Route::post('/login', [AdminAuthController::class, 'login'])
		->middleware('throttle:admin-login')
		->name('login.submit');
	Route::post('/logout', [AdminAuthController::class, 'logout'])
		->middleware('auth')
		->name('logout');
});

/*
|--------------------------------------------------------------------------
| Admin Panel Routes (NobleUI — PJAX-aware view routes + API routes)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {

	// ── Dashboard ──
	Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

	// ── Commerce: Orders ──
	Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
	Route::get('/orders/create', [AdminOrderController::class, 'create'])->name('orders.create');
	Route::get('/orders/{order}/edit', [AdminOrderController::class, 'edit'])->name('orders.edit');
	Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');

	// ── Commerce: Payments ──
	Route::get('/payments', [\App\Http\Controllers\Admin\PaymentLogController::class, 'index'])->name('payments.index');
	Route::get('/payments/{paymentLog}', [\App\Http\Controllers\Admin\PaymentLogController::class, 'show'])->name('payments.show');

	// ── Commerce: Inquiries ──
	Route::get('/inquiries', [\App\Http\Controllers\Admin\InquiryController::class, 'index'])->name('inquiries.index');
	Route::get('/inquiries/{inquiry}', [\App\Http\Controllers\Admin\InquiryController::class, 'show'])->name('inquiries.show');

	// ── Catalog: Products ──
	Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
	Route::get('/products/create', [AdminProductController::class, 'create'])->name('products.create');
	Route::get('/products/{product}/edit', [AdminProductController::class, 'edit'])->name('products.edit');
	Route::get('/gift-boxes', [AdminReadyGiftBoxController::class, 'index'])->name('gift-boxes.index');
	Route::get('/gift-boxes/create', [AdminReadyGiftBoxController::class, 'create'])->name('gift-boxes.create');
	Route::get('/gift-boxes/preview', [AdminReadyGiftBoxController::class, 'preview'])->name('gift-boxes.preview');
	Route::get('/gift-boxes/{giftBox}/preview', [AdminReadyGiftBoxController::class, 'previewBox'])->name('gift-boxes.preview-box');
	Route::get('/gift-boxes/{giftBox}/edit', [AdminReadyGiftBoxController::class, 'edit'])->name('gift-boxes.edit');
	Route::get('/bridge', [AdminBridgeController::class, 'index'])->name('bridge.index');

	// ── Messaging: Inbox ──
	Route::get('/inbox', [AdminInboxController::class, 'index'])->name('inbox.index');
	Route::get('/inbox/conversations', [AdminInboxController::class, 'conversations'])->name('inbox.conversations');
	Route::get('/inbox/{conversationId}/messages', [AdminInboxController::class, 'messages'])->name('inbox.messages');
	Route::post('/inbox/{conversationId}/send', [AdminInboxController::class, 'sendMessage'])->name('inbox.send');
	Route::post('/inbox/{conversationId}/read', [AdminInboxController::class, 'markRead'])->name('inbox.read');
	Route::patch('/inbox/{conversationId}/status', [AdminInboxController::class, 'updateStatus'])->name('inbox.status');
	Route::patch('/inbox/{conversationId}/priority', [AdminInboxController::class, 'updatePriority'])->name('inbox.priority');
	Route::post('/inbox/{conversationId}/toggle-ai', [AdminInboxController::class, 'toggleAi'])->name('inbox.toggle-ai');
	Route::get('/inbox/counts', [AdminInboxController::class, 'counts'])->name('inbox.counts');

	// ── Content: Articles ──
	Route::get('/articles', [AdminArticleController::class, 'index'])->name('articles.index');
	Route::get('/articles/create', [AdminArticleController::class, 'create'])->name('articles.create');
	Route::get('/articles/{article}/edit', [AdminArticleController::class, 'edit'])->name('articles.edit');

	// ── Content: Facebook Posts ──
	Route::get('/facebook-posts', [AdminFacebookPostController::class, 'index'])->name('facebook-posts.index');
	Route::get('/facebook-posts/create', [AdminFacebookPostController::class, 'create'])->name('facebook-posts.create');
	Route::get('/facebook-posts/{facebookPost}/edit', [AdminFacebookPostController::class, 'edit'])->name('facebook-posts.edit');

	// ── AI Lab (placeholders) ──
	Route::get('/ai-analytics', [\App\Http\Controllers\Admin\AiAnalyticsController::class, 'index'])->name('ai-analytics');
	Route::get('/product-quality', [AdminProductQualityController::class, 'index'])->name('product-quality.index');
	Route::get('/product-quality/create', [AdminProductQualityController::class, 'create'])->name('product-quality.create');
	Route::get('/product-quality/{productQuality}', [AdminProductQualityController::class, 'show'])->name('product-quality.show');
	Route::get('/chatbot-content', [AdminChatbotContentController::class, 'index'])->name('chatbot-content.index');
	Route::get('/chatbot-lab', [AdminChatbotLabController::class, 'index'])->name('chatbot-lab.index');
	Route::get('/chatbot-lab/cases', [AdminChatbotLabController::class, 'cases'])->name('chatbot-lab.cases.index');
	Route::get('/chatbot-lab/runs', [AdminChatbotLabController::class, 'runs'])->name('chatbot-lab.runs.index');
	Route::get('/chatbot-lab/runs/{run}', function (\Illuminate\Http\Request $request, string $run) {
		$runModel = \App\Models\ChatbotTestRun::query()
			->with('results')
			->findOrFail((int) $run);
		return response()->view('admin.chatbot-lab.run-detail-compat', [
			'run' => $runModel,
			'results' => $runModel->results,
		]);
	})->name('chatbot-lab.runs.show');
	Route::get('/chatbot-testing', [\App\Http\Controllers\Admin\ChatbotTestingController::class, 'index'])->name('chatbot-testing');
	Route::post('/chatbot-testing/send', [\App\Http\Controllers\Admin\ChatbotTestingController::class, 'sendMessage'])->name('chatbot-testing.send');
	Route::post('/chatbot-testing/reset-circuit-breaker', [\App\Http\Controllers\Admin\ChatbotTestingController::class, 'resetCircuitBreaker'])->name('chatbot-testing.reset-circuit-breaker');
	Route::post('/chatbot-testing/flush-cache', [\App\Http\Controllers\Admin\ChatbotTestingController::class, 'flushCache'])->name('chatbot-testing.flush-cache');
	Route::post('/chatbot-training/generation-requests', [AdminChatbotTrainingController::class, 'requestGeneration'])
		->name('chatbot-training.request-generation');
	Route::post('/chatbot-training/generation-requests/{generationRequest}/import', [AdminChatbotTrainingController::class, 'importGeneratedBatch'])
		->name('chatbot-training.import-generated-batch');
	Route::post('/chatbot-training/generate-batch', [AdminChatbotTrainingController::class, 'generateBatch'])
		->name('chatbot-training.generate-batch');
	Route::post('/chatbot-training/manual-flow', [AdminChatbotTrainingController::class, 'runManualFlow'])
		->name('chatbot-training.manual-flow');
	Route::post('/chatbot-training/{batch}/run', [AdminChatbotTrainingController::class, 'runBatch'])
		->name('chatbot-training.run-batch');
	Route::post('/chatbot-training/runs/{run}/review', [AdminChatbotTrainingController::class, 'createReviewRequest'])
		->name('chatbot-training.create-review');
	Route::post('/chatbot-training/reviews/{review}/import-analysis', [AdminChatbotTrainingController::class, 'importReviewAnalysis'])
		->name('chatbot-training.reviews.import-analysis');
	Route::post('/chatbot-training/reviews/{review}/decision', [AdminChatbotTrainingController::class, 'updateReviewDecision'])
		->name('chatbot-training.reviews.decision');
	Route::get('/chatbot-training', [AdminChatbotTrainingController::class, 'index'])->name('chatbot-training');
	Route::get('/chatbot-traces', [\App\Http\Controllers\Admin\ChatbotTracesController::class, 'index'])->name('chatbot-traces');
	Route::get('/langfuse-dashboard', [AdminLangfuseDashboardController::class, 'index'])->name('langfuse-dashboard');
	Route::get('/langfuse-link', [AdminLangfuseController::class, 'index'])->name('langfuse-link');

	// ── Social ──
	Route::get('/social-dashboard', [AdminSocialDashboardController::class, 'index'])->name('social-dashboard');
	Route::get('/social-dashboard/stats', [AdminSocialDashboardController::class, 'stats'])->name('social-dashboard.stats');
	Route::get('/social-dashboard/posts', [AdminSocialDashboardController::class, 'posts'])->name('social-dashboard.posts');
	Route::get('/social-dashboard/scheduled', [AdminSocialDashboardController::class, 'scheduled'])->name('social-dashboard.scheduled');
	Route::get('/social-dashboard/compare-facebook', [AdminSocialDashboardController::class, 'compareFacebook'])->name('social-dashboard.compare-facebook');
	Route::get('/social-dashboard/platform-status', [AdminSocialDashboardController::class, 'platformStatus'])->name('social-dashboard.platform-status');
	Route::get('/social-comments', [AdminSocialCommentController::class, 'index'])->name('social-comments.index');
	Route::get('/social-comments/list', [AdminSocialCommentController::class, 'list'])->name('social-comments.list');
	Route::patch('/social-comments/{id}/status', [AdminSocialCommentController::class, 'updateStatus'])->name('social-comments.update-status');
	Route::post('/social-comments/bulk-status', [AdminSocialCommentController::class, 'bulkUpdateStatus'])->name('social-comments.bulk-status');
	Route::post('/social-comments/{id}/generate-reply', [AdminSocialCommentController::class, 'generateReply'])->name('social-comments.generate-reply');
	Route::post('/social-comments/{id}/reply', [AdminSocialCommentController::class, 'sendReply'])->name('social-comments.reply');
	Route::post('/social-comments/{id}/hide', [AdminSocialCommentController::class, 'hideComment'])->name('social-comments.hide');
	Route::post('/social-comments/fetch', [AdminSocialCommentController::class, 'fetchComments'])->name('social-comments.fetch');
	Route::post('/social-comments/bulk-delete', [AdminSocialCommentController::class, 'bulkDelete'])->name('social-comments.bulk-delete');
	Route::get('/social-comments/export', [AdminSocialCommentController::class, 'export'])->name('social-comments.export');
	Route::post('/social-comments/{id}/block-user', [AdminSocialCommentController::class, 'blockUser'])->name('social-comments.block-user');
	Route::post('/social-comments/bulk-block-users', [AdminSocialCommentController::class, 'bulkBlockUsers'])->name('social-comments.bulk-block-users');
	Route::get('/social-comments/{id}/replies', [AdminSocialCommentController::class, 'replies'])->name('social-comments.replies');

	Route::get('/social-comments/quick-replies', [AdminSocialCommentController::class, 'listQuickReplies'])->name('social-comments.quick-replies.list');
	Route::post('/social-comments/quick-replies', [AdminSocialCommentController::class, 'storeQuickReply'])->name('social-comments.quick-replies.store');
	Route::put('/social-comments/quick-replies/{id}', [AdminSocialCommentController::class, 'updateQuickReply'])->name('social-comments.quick-replies.update');
	Route::delete('/social-comments/quick-replies/{id}', [AdminSocialCommentController::class, 'deleteQuickReply'])->name('social-comments.quick-replies.delete');

	Route::get('/social-comments/auto-reply-rules/{facebookPostId}', [AdminSocialCommentController::class, 'listAutoReplyRules'])->name('social-comments.auto-reply-rules.list');
	Route::post('/social-comments/auto-reply-rules', [AdminSocialCommentController::class, 'storeAutoReplyRule'])->name('social-comments.auto-reply-rules.store');
	Route::put('/social-comments/auto-reply-rules/{id}', [AdminSocialCommentController::class, 'updateAutoReplyRule'])->name('social-comments.auto-reply-rules.update');
	Route::delete('/social-comments/auto-reply-rules/{id}', [AdminSocialCommentController::class, 'deleteAutoReplyRule'])->name('social-comments.auto-reply-rules.delete');

	// ── Competition ──
	Route::get('/competitors', [AdminCompetitorMonitorController::class, 'index'])->name('competitors.index');

	// FB Competitors
	Route::get('/fb-competitors', [\App\Http\Controllers\Admin\FacebookCompetitorController::class, 'index'])->name('fb-competitors');
	Route::get('/fb-competitors/charts', [\App\Http\Controllers\Admin\FacebookCompetitorController::class, 'charts'])->name('fb-competitors.charts');
	Route::get('/fb-competitors/analytics', [\App\Http\Controllers\Admin\FacebookCompetitorController::class, 'analytics'])->name('fb-competitors.analytics');
	Route::get('/fb-competitors/export', [\App\Http\Controllers\Admin\FacebookCompetitorController::class, 'export'])->name('fb-competitors.export');
	Route::get('/fb-competitors/{page}', [\App\Http\Controllers\Admin\FacebookCompetitorController::class, 'show'])->name('fb-competitors.show');
	Route::post('/fb-competitors', [\App\Http\Controllers\Admin\FacebookCompetitorController::class, 'store'])->name('fb-competitors.store');
	Route::put('/fb-competitors/{page}', [\App\Http\Controllers\Admin\FacebookCompetitorController::class, 'update'])->name('fb-competitors.update');
	Route::delete('/fb-competitors/{page}', [\App\Http\Controllers\Admin\FacebookCompetitorController::class, 'destroy'])->name('fb-competitors.destroy');
	Route::post('/fb-competitors/{page}/scrape', [\App\Http\Controllers\Admin\FacebookCompetitorController::class, 'scrape'])->name('fb-competitors.scrape');
	Route::post('/fb-competitors/analyze', [\App\Http\Controllers\Admin\FacebookCompetitorController::class, 'analyze'])->name('fb-competitors.analyze');
	Route::post('/fb-competitors/weekly-analysis', [\App\Http\Controllers\Admin\FacebookCompetitorController::class, 'weeklyAnalysis'])->name('fb-competitors.weekly-analysis');
	Route::get('/fb-competitors/analysis/{analysis}', [\App\Http\Controllers\Admin\FacebookCompetitorController::class, 'showAnalysis'])->name('fb-competitors.analysis');
	Route::put('/fb-competitors/insights/{insight}', [\App\Http\Controllers\Admin\FacebookCompetitorController::class, 'updateInsight'])->name('fb-competitors.insights.update');
	Route::get('/fb-competitors/estimate-cost', [\App\Http\Controllers\Admin\FacebookCompetitorController::class, 'estimateCost'])->name('fb-competitors.estimate-cost');

	// ── SEO ──
	Route::get('/seo', [\App\Http\Controllers\Admin\SeoMonitoringController::class, 'index'])->name('seo');

	// ── Admin ──
	Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
	Route::get('/users/create', fn (\Illuminate\Http\Request $request) => $request->header('X-PJAX') ? view('admin._placeholder', ['title' => 'Create User'])->fragment('content') : view('admin._placeholder', ['title' => 'Create User']))->name('users.create');
	Route::get('/sms', [\App\Http\Controllers\Admin\SmsActivationController::class, 'index'])->name('sms');
	Route::post('/sms/get-services', [\App\Http\Controllers\Admin\SmsActivationController::class, 'getServices'])->name('sms.get-services');
	Route::post('/sms/get-number', [\App\Http\Controllers\Admin\SmsActivationController::class, 'getNumber'])->name('sms.get-number');
	Route::post('/sms/{activation}/set-status', [\App\Http\Controllers\Admin\SmsActivationController::class, 'setStatus'])->name('sms.set-status');
	Route::get('/sms/{activation}/check-status', [\App\Http\Controllers\Admin\SmsActivationController::class, 'checkStatus'])->name('sms.check-status');
	Route::get('/alibaba-import', [AdminAlibabaImportController::class, 'index'])->name('alibaba-import');

	// ══════════════════════════════════════════════════════
	// API / Action Routes (POST, PATCH, DELETE — JSON responses)
	// ══════════════════════════════════════════════════════
	Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store']);
	Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy']);
	Route::post('/push-subscriptions/test', [PushSubscriptionController::class, 'test']);
	Route::get('/chatbot-metrics', [AdminChatbotMetricsController::class, 'summary'])
		->name('chatbot-metrics.summary');
	Route::post('/users', [AdminUserController::class, 'store'])
		->name('users.store');
	Route::patch('/users/{user}/admin', [AdminUserController::class, 'toggleAdmin'])
		->name('users.toggle-admin');
	Route::post('/orders', [AdminOrderController::class, 'store'])
		->name('orders.store');
	Route::patch('/orders/{order}', [AdminOrderController::class, 'update'])
		->name('orders.update');
	Route::patch('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])
		->name('orders.update-status');
	Route::patch('/orders/{order}/payment-status', [AdminOrderController::class, 'updatePaymentStatus'])
		->name('orders.update-payment-status');
	Route::post('/orders/{order}/bridge/push', [AdminOrderController::class, 'pushBridgeOrder'])
		->name('orders.bridge.push');
	Route::post('/orders/{order}/bridge/refresh', [AdminOrderController::class, 'refreshBridgeOrder'])
		->name('orders.bridge.refresh');
	Route::delete('/orders/{order}', [AdminOrderController::class, 'destroy'])
		->name('orders.destroy');
	Route::post('/products/import-alibaba/parse', [AdminAlibabaImportController::class, 'parse'])
		->name('products.import-alibaba.parse');
	Route::post('/products/import-alibaba/confirm', [AdminAlibabaImportController::class, 'confirm'])
		->name('products.import-alibaba.confirm');
	Route::post('/bridge/sync-all', [AdminBridgeController::class, 'syncAll'])->name('bridge.sync-all');
	Route::post('/bridge/products/{remoteProductId}/sync', [AdminBridgeController::class, 'syncProduct'])->name('bridge.sync-product');
	Route::post('/bridge/orders/{order}/push', [AdminBridgeController::class, 'pushOrder'])->name('bridge.push-order');
	Route::post('/bridge/orders/{order}/refresh', [AdminBridgeController::class, 'refreshOrder'])->name('bridge.refresh-order');
	Route::post('/product-quality', [AdminProductQualityController::class, 'store'])->name('product-quality.store');
	Route::post('/product-quality/{productQuality}/run', [AdminProductQualityController::class, 'run'])->name('product-quality.run');
	Route::post('/competitors/sources', [AdminCompetitorMonitorController::class, 'storeSource'])
		->name('competitors.sources.store');
	Route::post('/competitors/sources/{source}/refresh', [AdminCompetitorMonitorController::class, 'refresh'])
		->name('competitors.refresh');
	Route::post('/competitors/products/{competitorProduct}/mapping', [AdminCompetitorMonitorController::class, 'saveMapping'])
		->name('competitors.mapping');
	Route::resource('products', AdminProductController::class)
		->only(['store', 'update', 'destroy'])
		->names('products');
	Route::post('/gift-boxes', [AdminReadyGiftBoxController::class, 'store'])->name('gift-boxes.store');
	Route::put('/gift-boxes/{giftBox}', [AdminReadyGiftBoxController::class, 'update'])->name('gift-boxes.update');
	Route::patch('/gift-boxes/{giftBox}/status', [AdminReadyGiftBoxController::class, 'toggleStatus'])->name('gift-boxes.toggle-status');
	Route::delete('/gift-boxes/{giftBox}', [AdminReadyGiftBoxController::class, 'destroy'])->name('gift-boxes.destroy');
	Route::resource('articles', AdminArticleController::class)
		->only(['store', 'update', 'destroy'])
		->names('articles');
	Route::patch('/articles/{article}/toggle-publish', [AdminArticleController::class, 'togglePublish'])
		->name('articles.toggle-publish');
	Route::post('/products/{product}/images', [AdminProductImageController::class, 'store'])
		->name('products.images.store');
	Route::post('/products/{product}/images/{image}/primary', [AdminProductImageController::class, 'setPrimary'])
		->name('products.images.primary');
	Route::delete('/products/{product}/images/{image}', [AdminProductImageController::class, 'destroy'])
		->name('products.images.destroy');
	Route::post('/products/{product}/variants', [AdminProductController::class, 'storeVariant'])
		->name('products.variants.store');
	Route::patch('/products/variants/{variant}', [AdminProductController::class, 'updateVariant'])
		->name('products.variants.update');
	Route::patch('/products/variants/{variant}/toggle-listing', [AdminProductController::class, 'toggleVariantListing'])
		->name('products.variants.toggle-listing');
	Route::put('/products/variants/{variant}/images', [AdminProductController::class, 'syncVariantImages'])
		->name('products.variants.images.sync');
	Route::delete('/products/variants/{variant}', [AdminProductController::class, 'deleteVariant'])
		->name('products.variants.delete');
	Route::post('/variants/{variant}/adjust-stock', [AdminStockAdjustmentController::class, 'store'])
		->name('variants.adjust-stock');
	Route::post('/chatbot-content/faqs', [AdminChatbotContentController::class, 'storeFaq'])
		->name('chatbot-content.faqs.store');
	Route::patch('/chatbot-content/faqs/{faq}', [AdminChatbotContentController::class, 'updateFaq'])
		->name('chatbot-content.faqs.update');
	Route::delete('/chatbot-content/faqs/{faq}', [AdminChatbotContentController::class, 'destroyFaq'])
		->name('chatbot-content.faqs.destroy');
	Route::put('/chatbot-content/contacts', [AdminChatbotContentController::class, 'updateContacts'])
		->name('chatbot-content.contacts.update');
	Route::post('/chatbot-content/static-pages/sync', [AdminChatbotContentController::class, 'syncStaticPages'])
		->name('chatbot-content.static-pages.sync');
	Route::post('/chatbot-lab/manual', [AdminChatbotLabController::class, 'runManualTest'])
		->name('chatbot-lab.manual.run');
	Route::post('/chatbot-lab/manual/retry', [AdminChatbotLabController::class, 'retryManualResult'])
		->name('chatbot-lab.manual.retry');
	Route::post('/chatbot-lab/manual/reset', [AdminChatbotLabController::class, 'resetManualSession'])
		->name('chatbot-lab.manual.reset');
	Route::post('/chatbot-lab-cases', [AdminChatbotLabController::class, 'storeCase'])
		->name('chatbot-lab.cases.store');
	Route::post('/chatbot-lab-cases/preview-diagnostics', [AdminChatbotLabController::class, 'previewCaseDiagnostics'])
		->name('chatbot-lab.cases.preview-diagnostics');
	Route::post('/chatbot-lab-cases/{trainingCase}/preview-diagnostics', [AdminChatbotLabController::class, 'previewCaseDiagnostics'])
		->name('chatbot-lab.cases.preview-diagnostics-existing');
	Route::patch('/chatbot-lab-cases/{trainingCase}', [AdminChatbotLabController::class, 'updateCase'])
		->name('chatbot-lab.cases.update');
	Route::delete('/chatbot-lab-cases/{trainingCase}', [AdminChatbotLabController::class, 'destroyCase'])
		->name('chatbot-lab.cases.destroy');
	Route::post('/chatbot-lab-runs', [AdminChatbotLabController::class, 'startRun'])
		->name('chatbot-lab.runs.start');
	Route::get('/chatbot-lab-runs/{run}/status', [AdminChatbotLabController::class, 'runStatus'])
		->name('chatbot-lab.runs.status');
	Route::post('/chatbot-lab-runs/{run}/cancel', [AdminChatbotLabController::class, 'cancelRunAction'])
		->name('chatbot-lab.runs.cancel');
	Route::get('/chatbot-lab-runs/{run}/export', [AdminChatbotLabController::class, 'exportRunCsv'])
		->name('chatbot-lab.runs.export');
	Route::post('/chatbot-lab-results/{result}/observation', [AdminChatbotLabController::class, 'saveObservation'])
		->name('chatbot-lab.results.observation');
	Route::post('/chatbot-lab-results/{result}/rerun', [AdminChatbotLabController::class, 'rerunResult'])
		->name('chatbot-lab.results.rerun');
	Route::post('/chatbot-lab-results/{result}/promote', [AdminChatbotLabController::class, 'promoteResult'])
		->name('chatbot-lab.results.promote');
	Route::post('/chatbot-lab-results/{result}/promote-rerun', [AdminChatbotLabController::class, 'promoteAndRerunResult'])
		->name('chatbot-lab.results.promote-rerun');
	Route::resource('facebook-posts', AdminFacebookPostController::class)
		->only(['store', 'update', 'destroy'])
		->parameters(['facebook-posts' => 'facebookPost'])
		->names('facebook-posts');
	Route::post('/facebook-posts/{facebookPost}/publish', [AdminFacebookPostController::class, 'publish'])
		->name('facebook-posts.publish');
	Route::post('/facebook-posts/generate', [AdminFacebookPostController::class, 'generate'])
		->name('facebook-posts.generate');
	Route::post('/facebook-posts/enhance-prompt', [AdminFacebookPostController::class, 'enhancePrompt'])
		->name('facebook-posts.enhance-prompt');
	Route::post('/facebook-posts/suggest-hashtags', [AdminFacebookPostController::class, 'suggestHashtags'])
		->name('facebook-posts.suggest-hashtags');

	// Image Manager APIs
    Route::get('/products/{product}/images-json', [AdminProductImageController::class, 'getImagesJson'])->name('products.images.json');
    Route::get('/images/all-json', [AdminProductImageController::class, 'getAllImagesJson'])->name('images.all.json');
    Route::post('/images/upload-standalone', [AdminProductImageController::class, 'uploadStandalone'])->name('images.upload-standalone');

    Route::post('/media/upload-video', [AdminMediaController::class, 'uploadVideo'])->name('media.upload-video');
});

if (app()->environment('local')) {
    // Test route for real-time message broadcasting
    Route::get('/test/send-message', function () {
        $conversation = \App\Models\Conversation::with('customer')->first();

        if (!$conversation) {
            return response()->json(['error' => 'No conversation found. Please seed test data first.']);
        }

        $message = \App\Models\Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $conversation->customer_id,
            'platform_message_id' => 'test_live_' . uniqid(),
            'sender_type' => 'customer',
            'sender_id' => $conversation->customer_id,
            'sender_name' => $conversation->customer->name,
            'content' => 'This is a LIVE test message sent at ' . now()->format('H:i:s'),
        ]);

        // Update conversation
        $conversation->update([
            'last_message_at' => now(),
            'unread_count' => $conversation->unread_count + 1,
        ]);

        // Broadcast the event
        $event = new \App\Events\MessageReceived(
            $message,
            $conversation,
            $conversation->customer,
            $conversation->platform
        );

        logger()->info('Dispatching MessageReceived event', [
            'event_class' => get_class($event),
            'broadcast_as' => $event->broadcastAs(),
            'broadcast_on' => array_map(fn($ch) => get_class($ch) . ':' . $ch->name, $event->broadcastOn()),
            'message_id' => $message->id,
        ]);

        event($event);

        return response()->json([
            'success' => true,
            'message' => 'Test message created and broadcasted!',
            'data' => [
                'message_id' => $message->id,
                'content' => $message->content,
                'conversation_id' => $conversation->id,
            ]
        ]);
    })->name('test.send-message');
}
