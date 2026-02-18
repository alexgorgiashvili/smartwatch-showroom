<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\InquiryController as AdminInquiryController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ProductImageController as AdminProductImageController;
use App\Http\Controllers\Admin\StockAdjustmentController as AdminStockAdjustmentController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\ProductController;
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
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('products.show');
Route::get('/contact', [HomeController::class, 'contact'])->name('contact');
Route::get('/about', fn () => view('pages.about'))->name('about');
Route::get('/privacy', fn () => view('pages.privacy'))->name('privacy');
Route::get('/terms', fn () => view('pages.terms'))->name('terms');
Route::get('/lang/{locale}', [HomeController::class, 'locale'])->name('locale');
Route::post('/inquiries', [InquiryController::class, 'store'])->name('inquiries.store');
Route::post('/chatbot', [ChatController::class, 'respond'])
	->name('chatbot.respond')
	->middleware('throttle:30,1');

Route::prefix('admin')->name('admin.')->group(function () {
	Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
	Route::post('/login', [AdminAuthController::class, 'login'])->name('login.store');
	Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

	Route::middleware(['auth', 'admin'])->group(function () {
		Route::get('/', [AdminDashboardController::class, 'index'])
			->name('home');
		Route::get('/inquiries', [AdminInquiryController::class, 'index'])
			->name('inquiries.index');
		Route::get('/inquiries/{inquiry}', [AdminInquiryController::class, 'show'])
			->name('inquiries.show');
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
		Route::delete('/orders/{order}', [AdminOrderController::class, 'destroy'])
			->name('orders.destroy');
		Route::resource('products', AdminProductController::class)->except(['show']);
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
	});
});
