<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\ChatbotMetricsController as AdminChatbotMetricsController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ArticleController as AdminArticleController;
use App\Http\Controllers\Admin\AlibabaImportController as AdminAlibabaImportController;
use App\Http\Controllers\Admin\CompetitorMonitorController as AdminCompetitorMonitorController;
use App\Http\Controllers\Admin\WebhookController as AdminWebhookController;
use App\Http\Controllers\Admin\ProductImageController as AdminProductImageController;
use App\Http\Controllers\Admin\StockAdjustmentController as AdminStockAdjustmentController;
use App\Http\Controllers\Admin\ChatbotContentController as AdminChatbotContentController;
use App\Http\Controllers\Admin\ChatbotLabController as AdminChatbotLabController;
use App\Http\Controllers\Admin\FacebookPostController as AdminFacebookPostController;
use App\Http\Controllers\Admin\PushSubscriptionController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\HomeController;
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
Route::delete('/cart/remove', [CartController::class, 'remove'])->name('cart.remove');
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

// Legacy admin routes (will be removed after Filament migration is complete)
Route::prefix('admin-legacy')->name('admin.')->group(function () {
	Route::get('/login', fn () => redirect('/admin/login'))->name('login');
	Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

	Route::middleware(['auth', 'admin'])->group(function () {
		Route::get('/', fn () => redirect()->route('filament.admin.pages.dashboard'))
			->name('home');
		Route::get('/inquiries', fn () => redirect()->route('filament.admin.resources.inquiries.index'))
			->name('inquiries.index');
		Route::get('/inquiries/{inquiry}', fn (\App\Models\Inquiry $inquiry) => redirect()->route('filament.admin.resources.inquiries.view', ['record' => $inquiry]))
			->name('inquiries.show');
		Route::get('/users', fn () => redirect()->route('filament.admin.resources.users.index'))
			->name('users.index');
		Route::get('/users/create', fn () => redirect()->route('filament.admin.resources.users.create'))
			->name('users.create');
		Route::get('/orders', fn () => redirect()->route('filament.admin.resources.orders.index'))
			->name('orders.index');
		Route::get('/orders/create', fn () => redirect()->route('filament.admin.resources.orders.create'))
			->name('orders.create');
		Route::get('/orders/{order}', fn (\App\Models\Order $order) => redirect()->route('filament.admin.resources.orders.view', ['record' => $order]))
			->name('orders.show');
		Route::get('/payments', fn () => redirect()->route('filament.admin.resources.payments.index'))
			->name('payments.index');
		Route::get('products/import-alibaba', fn () => redirect()->route('filament.admin.pages.alibaba-import'))
			->name('products.import-alibaba');
		Route::get('competitors', fn () => redirect()->route('filament.admin.pages.competitor-monitor'))
			->name('competitors.index');
		Route::get('products', fn () => redirect()->route('filament.admin.resources.products.index'))
			->name('products.index');
		Route::get('products/create', fn () => redirect()->route('filament.admin.resources.products.create'))
			->name('products.create');
		Route::get('products/{product}/edit', fn (\App\Models\Product $product) => redirect()->route('filament.admin.resources.products.edit', ['record' => $product]))
			->name('products.edit');
		Route::get('articles', fn () => redirect()->route('filament.admin.resources.articles.index'))
			->name('articles.index');
		Route::get('articles/create', fn () => redirect()->route('filament.admin.resources.articles.create'))
			->name('articles.create');
		Route::get('articles/{article}/edit', fn (\App\Models\Article $article) => redirect()->route('filament.admin.resources.articles.edit', ['record' => $article]))
			->name('articles.edit');
		Route::get('/chatbot-content', fn () => redirect()->route('filament.admin.pages.chatbot-content'))
			->name('chatbot-content.index');

		Route::get('/chatbot-lab', fn (\Illuminate\Http\Request $request) => redirect()->to(route('filament.admin.pages.chatbot-lab') . ($request->getQueryString() ? ('?' . $request->getQueryString()) : '')))
			->name('chatbot-lab.index');
		Route::get('/chatbot-lab/cases', fn (\Illuminate\Http\Request $request) => redirect()->to(route('filament.admin.pages.chatbot-lab-cases') . ($request->getQueryString() ? ('?' . $request->getQueryString()) : '')))
			->name('chatbot-lab.cases.index');
		Route::get('/chatbot-lab/runs', fn (\Illuminate\Http\Request $request) => redirect()->to(route('filament.admin.pages.chatbot-lab-runs') . ($request->getQueryString() ? ('?' . $request->getQueryString()) : '')))
			->name('chatbot-lab.runs.index');
		Route::get('/chatbot-lab/runs/{run}', function (\Illuminate\Http\Request $request, string $run) {
			$runModel = \App\Models\ChatbotTestRun::query()
				->with('results')
				->findOrFail((int) $run);

			if ($request->boolean('filament')) {
				return redirect()->to(route('filament.admin.pages.chatbot-lab-runs.show', ['run' => $runModel->getKey()]) . ($request->getQueryString() ? ('?' . $request->getQueryString()) : ''));
			}

			return response()->view('admin.chatbot-lab.run-detail-compat', [
				'run' => $runModel,
				'results' => $runModel->results,
			]);
		})
			->name('chatbot-lab.runs.show');

		// Facebook Posts
		Route::get('/facebook-posts', fn () => redirect()->route('filament.admin.resources.facebook-posts.index'))
			->name('facebook-posts.index');
		Route::get('/facebook-posts/create', fn () => redirect()->route('filament.admin.resources.facebook-posts.create'))
			->name('facebook-posts.create');
		Route::get('/facebook-posts/{facebookPost}/edit', fn (\App\Models\FacebookPost $facebookPost) => redirect()->route('filament.admin.resources.facebook-posts.edit', ['record' => $facebookPost]))
			->name('facebook-posts.edit');
	});
});

Route::prefix('admin')->middleware(['auth', 'admin'])->group(function () {
	Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store']);
	Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy']);
	Route::post('/push-subscriptions/test', [PushSubscriptionController::class, 'test']);
	Route::get('/chatbot-metrics', [AdminChatbotMetricsController::class, 'summary'])
		->name('admin.chatbot-metrics.summary');
	Route::post('/users', [AdminUserController::class, 'store'])
		->name('admin.users.store');
	Route::patch('/users/{user}/admin', [AdminUserController::class, 'toggleAdmin'])
		->name('admin.users.toggle-admin');
	Route::post('/orders', [AdminOrderController::class, 'store'])
		->name('admin.orders.store');
	Route::patch('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])
		->name('admin.orders.update-status');
	Route::patch('/orders/{order}/payment-status', [AdminOrderController::class, 'updatePaymentStatus'])
		->name('admin.orders.update-payment-status');
	Route::delete('/orders/{order}', [AdminOrderController::class, 'destroy'])
		->name('admin.orders.destroy');
	Route::post('/products/import-alibaba/parse', [AdminAlibabaImportController::class, 'parse'])
		->name('admin.products.import-alibaba.parse');
	Route::post('/products/import-alibaba/confirm', [AdminAlibabaImportController::class, 'confirm'])
		->name('admin.products.import-alibaba.confirm');
	Route::post('/competitors/sources', [AdminCompetitorMonitorController::class, 'storeSource'])
		->name('admin.competitors.sources.store');
	Route::post('/competitors/sources/{source}/refresh', [AdminCompetitorMonitorController::class, 'refresh'])
		->name('admin.competitors.refresh');
	Route::post('/competitors/products/{competitorProduct}/mapping', [AdminCompetitorMonitorController::class, 'saveMapping'])
		->name('admin.competitors.mapping');
	Route::resource('products', AdminProductController::class)
		->only(['store', 'update', 'destroy'])
		->names('admin.products');
	Route::resource('articles', AdminArticleController::class)
		->only(['store', 'update', 'destroy'])
		->names('admin.articles');
	Route::patch('/articles/{article}/toggle-publish', [AdminArticleController::class, 'togglePublish'])
		->name('admin.articles.toggle-publish');
	Route::post('/products/{product}/images', [AdminProductImageController::class, 'store'])
		->name('admin.products.images.store');
	Route::post('/products/{product}/images/{image}/primary', [AdminProductImageController::class, 'setPrimary'])
		->name('admin.products.images.primary');
	Route::delete('/products/{product}/images/{image}', [AdminProductImageController::class, 'destroy'])
		->name('admin.products.images.destroy');
	Route::post('/products/{product}/variants', [AdminProductController::class, 'storeVariant'])
		->name('admin.products.variants.store');
	Route::patch('/products/variants/{variant}', [AdminProductController::class, 'updateVariant'])
		->name('admin.products.variants.update');
	Route::delete('/products/variants/{variant}', [AdminProductController::class, 'deleteVariant'])
		->name('admin.products.variants.delete');
	Route::post('/variants/{variant}/adjust-stock', [AdminStockAdjustmentController::class, 'store'])
		->name('admin.variants.adjust-stock');
	Route::post('/chatbot-content/faqs', [AdminChatbotContentController::class, 'storeFaq'])
		->name('admin.chatbot-content.faqs.store');
	Route::patch('/chatbot-content/faqs/{faq}', [AdminChatbotContentController::class, 'updateFaq'])
		->name('admin.chatbot-content.faqs.update');
	Route::delete('/chatbot-content/faqs/{faq}', [AdminChatbotContentController::class, 'destroyFaq'])
		->name('admin.chatbot-content.faqs.destroy');
	Route::put('/chatbot-content/contacts', [AdminChatbotContentController::class, 'updateContacts'])
		->name('admin.chatbot-content.contacts.update');
	Route::post('/chatbot-lab/manual', [AdminChatbotLabController::class, 'runManualTest'])
		->name('admin.chatbot-lab.manual.run');
	Route::post('/chatbot-lab/manual/retry', [AdminChatbotLabController::class, 'retryManualResult'])
		->name('admin.chatbot-lab.manual.retry');
	Route::post('/chatbot-lab/manual/reset', [AdminChatbotLabController::class, 'resetManualSession'])
		->name('admin.chatbot-lab.manual.reset');
	Route::post('/chatbot-lab-cases', [AdminChatbotLabController::class, 'storeCase'])
		->name('admin.chatbot-lab.cases.store');
	Route::post('/chatbot-lab-cases/preview-diagnostics', [AdminChatbotLabController::class, 'previewCaseDiagnostics'])
		->name('admin.chatbot-lab.cases.preview-diagnostics');
	Route::post('/chatbot-lab-cases/{trainingCase}/preview-diagnostics', [AdminChatbotLabController::class, 'previewCaseDiagnostics'])
		->name('admin.chatbot-lab.cases.preview-diagnostics-existing');
	Route::patch('/chatbot-lab-cases/{trainingCase}', [AdminChatbotLabController::class, 'updateCase'])
		->name('admin.chatbot-lab.cases.update');
	Route::delete('/chatbot-lab-cases/{trainingCase}', [AdminChatbotLabController::class, 'destroyCase'])
		->name('admin.chatbot-lab.cases.destroy');
	Route::post('/chatbot-lab-runs', [AdminChatbotLabController::class, 'startRun'])
		->name('admin.chatbot-lab.runs.start');
	Route::get('/chatbot-lab-runs/{run}/status', [AdminChatbotLabController::class, 'runStatus'])
		->name('admin.chatbot-lab.runs.status');
	Route::post('/chatbot-lab-runs/{run}/cancel', [AdminChatbotLabController::class, 'cancelRunAction'])
		->name('admin.chatbot-lab.runs.cancel');
	Route::get('/chatbot-lab-runs/{run}/export', [AdminChatbotLabController::class, 'exportRunCsv'])
		->name('filament.admin.chatbot-lab-runs.export');
	Route::post('/chatbot-lab-results/{result}/observation', [AdminChatbotLabController::class, 'saveObservation'])
		->name('admin.chatbot-lab.results.observation');
	Route::post('/chatbot-lab-results/{result}/rerun', [AdminChatbotLabController::class, 'rerunResult'])
		->name('admin.chatbot-lab.results.rerun');
	Route::post('/chatbot-lab-results/{result}/promote', [AdminChatbotLabController::class, 'promoteResult'])
		->name('admin.chatbot-lab.results.promote');
	Route::post('/chatbot-lab-results/{result}/promote-rerun', [AdminChatbotLabController::class, 'promoteAndRerunResult'])
		->name('admin.chatbot-lab.results.promote-rerun');
	Route::resource('facebook-posts', AdminFacebookPostController::class)
		->only(['store', 'update', 'destroy'])
		->parameters(['facebook-posts' => 'facebookPost'])
		->names('admin.facebook-posts');
	Route::post('/facebook-posts/{facebookPost}/publish', [AdminFacebookPostController::class, 'publish'])
		->name('admin.facebook-posts.publish');
	Route::post('/facebook-posts/generate', [AdminFacebookPostController::class, 'generate'])
		->name('admin.facebook-posts.generate');
});

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
