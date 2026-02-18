<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ProductImageController as AdminProductImageController;
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

Route::prefix('admin')->name('admin.')->group(function () {
	Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
	Route::post('/login', [AdminAuthController::class, 'login'])->name('login.store');
	Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

	Route::middleware(['auth', 'admin'])->group(function () {
		Route::get('/', fn () => redirect()->route('admin.products.index'))
			->name('home');
		Route::resource('products', AdminProductController::class)->except(['show']);
		Route::post('products/{product}/images', [AdminProductImageController::class, 'store'])
			->name('products.images.store');
		Route::post('products/{product}/images/{image}/primary', [AdminProductImageController::class, 'setPrimary'])
			->name('products.images.primary');
		Route::delete('products/{product}/images/{image}', [AdminProductImageController::class, 'destroy'])
			->name('products.images.destroy');
	});
});
