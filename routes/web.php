<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\InboxController as AdminInboxController;
use App\Http\Controllers\Admin\InquiryController as AdminInquiryController;
use App\Http\Controllers\Admin\ChatbotMetricsController as AdminChatbotMetricsController;
use App\Http\Controllers\Admin\MessageController as AdminMessageController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ArticleController as AdminArticleController;
use App\Http\Controllers\Admin\AlibabaImportController as AdminAlibabaImportController;
use App\Http\Controllers\Admin\CompetitorMonitorController as AdminCompetitorMonitorController;
use App\Http\Controllers\Admin\ProductImageController as AdminProductImageController;
use App\Http\Controllers\Admin\StockAdjustmentController as AdminStockAdjustmentController;
use App\Http\Controllers\Admin\ChatbotContentController as AdminChatbotContentController;
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
Route::post('/chatbot', [ChatController::class, 'respond'])
	->name('chatbot.respond')
	->middleware('throttle:30,1');

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
Route::get('/webhook/facebook', [\App\Http\Controllers\FacebookWebhookController::class, 'verify']);
Route::post('/webhook/facebook', [\App\Http\Controllers\FacebookWebhookController::class, 'webhook']);

Route::prefix('admin')->name('admin.')->group(function () {
	Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
	Route::post('/login', [AdminAuthController::class, 'login'])->name('login.store');
	Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

	Route::middleware(['auth', 'admin'])->group(function () {
		Route::get('/', [AdminDashboardController::class, 'index'])
			->name('home');
		Route::get('/chatbot-metrics', [AdminChatbotMetricsController::class, 'summary'])
			->name('chatbot-metrics.summary');
		Route::get('/inquiries', [AdminInquiryController::class, 'index'])
			->name('inquiries.index');
		Route::get('/inquiries/{inquiry}', [AdminInquiryController::class, 'show'])
			->name('inquiries.show');

		Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])
			->name('push-subscriptions.store');
		Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])
			->name('push-subscriptions.destroy');
		Route::post('/push-subscriptions/test', [PushSubscriptionController::class, 'test'])
			->name('push-subscriptions.test');

		// Inbox Routes
		Route::prefix('inbox')->name('inbox.')->group(function () {
			Route::get('/', [AdminInboxController::class, 'index'])->name('index');
			Route::get('/search', [AdminInboxController::class, 'search'])->name('search');
			Route::post('/suggestions/batch', [AdminInboxController::class, 'batchSuggestions'])->name('suggestions.batch');
			Route::get('/{conversation}', [AdminInboxController::class, 'show'])->name('show');
			Route::post('/{conversation}/mark-read', [AdminInboxController::class, 'markConversationAsRead'])->name('mark-read');
			Route::post('/{conversation}/status', [AdminInboxController::class, 'updateConversationStatus'])->name('update-status');
			Route::post('/{conversation}/reply', [AdminInboxController::class, 'sendReply'])->name('reply');
			Route::get('/{conversation}/suggest-ai', [AdminInboxController::class, 'suggestAIResponse'])->name('suggest-ai');
			Route::post('/{conversation}/messages', [AdminMessageController::class, 'store'])->name('messages.store');
			Route::patch('/{conversation}/messages/{message}/read', [AdminMessageController::class, 'markAsRead'])->name('messages.mark-read');
			Route::delete('/{conversation}/messages/{message}', [AdminMessageController::class, 'delete'])->name('messages.destroy');
		});;

		Route::get('/users', [AdminUserController::class, 'index'])
			->name('users.index');
		Route::get('/users/create', [AdminUserController::class, 'create'])
			->name('users.create');
		Route::post('/users', [AdminUserController::class, 'store'])
			->name('users.store');
		Route::patch('/users/{user}/admin', [AdminUserController::class, 'toggleAdmin'])
			->name('users.toggle-admin');
		Route::get('/orders', [AdminOrderController::class, 'index'])
			->name('orders.index');
		Route::get('/orders/create', [AdminOrderController::class, 'create'])
			->name('orders.create');
		Route::post('/orders', [AdminOrderController::class, 'store'])
			->name('orders.store');
		Route::get('/orders/{order}', [AdminOrderController::class, 'show'])
			->name('orders.show');
		Route::patch('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])
			->name('orders.update-status');
		Route::patch('/orders/{order}/payment-status', [AdminOrderController::class, 'updatePaymentStatus'])
			->name('orders.update-payment-status');
		Route::delete('/orders/{order}', [AdminOrderController::class, 'destroy'])
			->name('orders.destroy');
		Route::get('/payments', [AdminPaymentController::class, 'index'])
			->name('payments.index');
		Route::get('products/import-alibaba', [AdminAlibabaImportController::class, 'index'])
			->name('products.import-alibaba');
		Route::post('products/import-alibaba/parse', [AdminAlibabaImportController::class, 'parse'])
			->name('products.import-alibaba.parse');
		Route::post('products/import-alibaba/confirm', [AdminAlibabaImportController::class, 'confirm'])
			->name('products.import-alibaba.confirm');
		Route::get('competitors', [AdminCompetitorMonitorController::class, 'index'])
			->name('competitors.index');
		Route::post('competitors/sources', [AdminCompetitorMonitorController::class, 'storeSource'])
			->name('competitors.sources.store');
		Route::post('competitors/sources/{source}/refresh', [AdminCompetitorMonitorController::class, 'refresh'])
			->name('competitors.refresh');
		Route::post('competitors/products/{competitorProduct}/mapping', [AdminCompetitorMonitorController::class, 'saveMapping'])
			->name('competitors.mapping');
		Route::resource('products', AdminProductController::class)->except(['show']);
		Route::resource('articles', AdminArticleController::class)->except(['show']);
		Route::patch('articles/{article}/toggle-publish', [AdminArticleController::class, 'togglePublish'])
			->name('articles.toggle-publish');
		Route::post('products/{product}/images', [AdminProductImageController::class, 'store'])
			->name('products.images.store');
		Route::post('products/{product}/images/{image}/primary', [AdminProductImageController::class, 'setPrimary'])
			->name('products.images.primary');
		Route::delete('products/{product}/images/{image}', [AdminProductImageController::class, 'destroy'])
			->name('products.images.destroy');
		Route::post('products/{product}/variants', [AdminProductController::class, 'storeVariant'])
			->name('products.variants.store');
		Route::patch('products/variants/{variant}', [AdminProductController::class, 'updateVariant'])
			->name('products.variants.update');
		Route::delete('products/variants/{variant}', [AdminProductController::class, 'deleteVariant'])
			->name('products.variants.delete');
		Route::post('variants/{variant}/adjust-stock', [AdminStockAdjustmentController::class, 'store'])
			->name('variants.adjust-stock');
		Route::get('/chatbot-content', [AdminChatbotContentController::class, 'index'])
			->name('chatbot-content.index');
		Route::post('/chatbot-content/faqs', [AdminChatbotContentController::class, 'storeFaq'])
			->name('chatbot-content.faqs.store');
		Route::patch('/chatbot-content/faqs/{faq}', [AdminChatbotContentController::class, 'updateFaq'])
			->name('chatbot-content.faqs.update');
		Route::delete('/chatbot-content/faqs/{faq}', [AdminChatbotContentController::class, 'destroyFaq'])
			->name('chatbot-content.faqs.destroy');
		Route::put('/chatbot-content/contacts', [AdminChatbotContentController::class, 'updateContacts'])
			->name('chatbot-content.contacts.update');
	});
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
