<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\PromoCodeController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ShippingOptionController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Admin\CollectionController;
use App\Http\Controllers\ProfileController;
use App\Http\Middleware\IsAdmin;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\OrderItemController;

Route::middleware(['auth', IsAdmin::class])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('categories', CategoryController::class);
    Route::resource('collections', CollectionController::class);
    Route::resource('products', ProductController::class);
    Route::resource('promo-codes', PromoCodeController::class);
    // Route::resource('shipping-options', ShippingOptionController::class);
    Route::resource('orders', OrderController::class);
    Route::post('/product/image/delete', [ProductController::class, 'deleteImage'])->name('product.image.delete');

});