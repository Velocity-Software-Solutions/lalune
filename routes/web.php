<?php

use App\Http\Controllers\CouponController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CartController;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

Route::middleware(['web'])->group(function () {
    Route::get('/checkout/confirmation/{order}', [CheckoutController::class, 'confirmation'])->name('checkout.confirmation');
});



Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{id}', [ProductController::class, 'show'])->name('products.show');


Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/add/{id}', [CartController::class, 'add'])->name('cart.add');
Route::put('/cart/update/{id}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/remove/{id}', [CartController::class, 'remove'])->name('cart.remove');
Route::post('/cart/apply-coupon', [CartController::class, 'applyCoupon'])->name('cart.applyCoupon');
Route::post('/coupon/apply', [CouponController::class, 'apply'])->name('coupon.apply');
Route::post('/coupon/remove', [CouponController::class, 'remove'])->name('coupon.remove');

 Route::get('/checkout', [CheckoutController::class, 'show'])->name(name: 'checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'process'])->name('checkout.process');
Route::get('/checkout', [CheckoutController::class, 'showCheckout'])->name('checkout.show');
Route::get('/order/confirmation/{order}', [CheckoutController::class, 'confirmation'])->name('checkout.confirmation');
Route::get('/checkout/{order}/receipt', [CheckoutController::class, 'downloadReceipt'])->name('checkout.receipt');

Route::middleware(['guest'])->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'show'])->name(name: 'checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'process'])->name('checkout.process');
Route::get('/checkout', [CheckoutController::class, 'showCheckout'])->name('checkout.show');

});
    Route::get('/checkout', [CheckoutController::class, 'index'])->name(name: 'checkout.index');
 Route::get('/', [StoreController::class, 'home'])->name('home');

Route::get('/dashboard', function () {
    return view('admin.dashboard');
})->middleware(['auth'])->name('dashboard');

Route::get('/lang/{locale}', function ($locale) {
    if (in_array($locale, ['en', 'ar'])) {
        Session::put('locale', $locale);
        App::setLocale($locale); // optional: apply immediately
    }
    return redirect()->back();
})->name('lang.switch');

require __DIR__.'/auth.php';
