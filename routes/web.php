<?php

use App\Http\Controllers\CollectionController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ProductReviewController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CartController;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;



// Storefront home page
Route::get('/', [StoreController::class, 'home'])->name('home');

// Product listing & details
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{id}', [ProductController::class, 'show'])->name('products.show'); // Show single product

// Collections 
Route::get('/collections', [CollectionController::class, 'index'])->name('collections');

//About Us
Route::get('/about-us', function () {
    return view('about');
})->name('about-us');

//Contact Form Submisssion
Route::post('contact', [ContactController::class, 'submit'])->name('contact.submit');

//Return Policy
Route::get('/return-policy', function () {
    return view('return-policy');
})->name('return-policy');

//Terms & Conditions
Route::get('/terms-conditions', function () {
    return view('terms-conditions');
})->name('terms-conditions');

//Privacy Policy
Route::get('/privacy-policy', function () {
    return view('privacy-policy');
})->name('privacy-policy');

/*
|--------------------------------------------------------------------------
| Cart & Coupon Routes
|--------------------------------------------------------------------------
*/

// View cart
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');

// Cart actions
Route::post('/cart/add/{id}', [CartController::class, 'add'])->name('cart.add');         // Add product to cart
Route::put('/cart/update/{id}', [CartController::class, 'update'])->name('cart.update'); // Update quantity
Route::delete('/cart/remove/{id}', [CartController::class, 'remove'])->name('cart.remove'); // Remove item

// Coupon actions (two possible endpoints for flexibility)
Route::post('/cart/apply-promo', [CartController::class, 'applyPromo'])->name('cart.applyPromo');
Route::delete('/cart/remove-promo/{code}', [CartController::class, 'removePromo'])->name('cart.removePromo');

//Ratings and reviews
Route::post('/products/{product}/reviews', [ProductReviewController::class, 'store'])
    ->name('products.reviews.store');


/*
|--------------------------------------------------------------------------
| Checkout & Orders
|--------------------------------------------------------------------------
*/

// Checkout process
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');   // Checkout page
Route::post('/checkout', [CheckoutController::class, 'process'])->name('checkout.process');

// Order confirmation & rating

Route::get('/order/confirmation', [CheckoutController::class, 'confirmation'])->name('checkout.confirmation');
Route::post('/order/confirmation/rate', [CheckoutController::class, 'rate'])->name('checkout.rate');

// Download receipt
Route::get('/checkout/{order}/receipt', [CheckoutController::class, 'downloadReceipt'])->name('checkout.receipt');

/*
|--------------------------------------------------------------------------
| Admin & Authenticated Routes
|--------------------------------------------------------------------------
*/

// Admin dashboard (only for authenticated users)
Route::get('/dashboard', function () {
    return view('admin.dashboard');
})->middleware(['auth'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| Language Switcher
|--------------------------------------------------------------------------
*/

// Switch site language (en / ar)
Route::get('/lang/{locale}', function ($locale) {
    if (in_array($locale, ['en', 'ar'])) {
        Session::put('locale', $locale);
        App::setLocale($locale); // Apply immediately (optional)
    }
    return redirect()->back();
})->name('lang.switch');

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

require __DIR__ . '/auth.php';