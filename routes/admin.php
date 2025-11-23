<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\NewsletterCampaignController;
use App\Http\Controllers\Admin\NewsletterSubscriberController;
use App\Http\Controllers\Admin\ProductReviewController;
use App\Http\Controllers\Admin\PromoCodeController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ShippingOptionController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Admin\CollectionController;
use App\Http\Controllers\Admin\GeneralSetupController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\SummernoteController;
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
    Route::get('/reviews', [ProductReviewController::class, 'index'])->name('reviews.index');
    Route::patch('/reviews/{review}/approve', [ProductReviewController::class, 'approve'])->name('reviews.approve');
    Route::patch('/reviews/{review}/reject', [ProductReviewController::class, 'reject'])->name('reviews.reject');
    Route::delete('/reviews/{review}', [ProductReviewController::class, 'destroy'])->name('reviews.destroy');
    Route::resource('orders', OrderController::class);
    Route::post('/product/image/delete', [ProductController::class, 'deleteImage'])->name('product.image.delete');

    Route::post('/upload-summernote-image', [SummernoteController::class, 'store']);
    Route::delete('/delete-summernote-image', [SummernoteController::class, 'destroy']);


    Route::post('/general/index-hero', [GeneralSetupController::class, 'updateIndexHero'])
        ->name('general.index-hero.update');
    Route::delete('/general/index-hero', [GeneralSetupController::class, 'resetIndexHero'])
        ->name('general.index-hero.reset');
    Route::prefix('newsletter')
        ->name('newsletter.')
        ->group(function () {
            Route::get('/subscribers', [NewsletterSubscriberController::class, 'index'])->name('subscribers.index');
            Route::get('/subscribers/{subscriber}', [NewsletterSubscriberController::class, 'show'])->name('subscribers.show');
            Route::post('/subscribers/{subscriber}/resend-confirm', [NewsletterSubscriberController::class, 'resendConfirm'])
                ->name('subscribers.resend-confirm');
            Route::post('/subscribers/send-pending', [NewsletterSubscriberController::class, 'confirmAllPending'])
                ->name('subscribers.send-pending');

            Route::post('/subscribers/{subscriber}/unsubscribe', [NewsletterSubscriberController::class, 'unsubscribe'])
                ->name('subscribers.unsubscribe');
            Route::get('/campaigns', [NewsletterCampaignController::class, 'index'])
                ->name('campaigns.index');

            Route::get('/campaigns/create', [NewsletterCampaignController::class, 'create'])
                ->name('campaigns.create');

            Route::post('/campaigns', [NewsletterCampaignController::class, 'store'])
                ->name('campaigns.store');

            Route::get('/campaigns/{campaign}/edit', [NewsletterCampaignController::class, 'edit'])
                ->name('campaigns.edit');

            Route::put('/campaigns/{campaign}', [NewsletterCampaignController::class, 'update'])
                ->name('campaigns.update');

            Route::get('campaigns/{campaign}/preview', [NewsletterCampaignController::class, 'preview'])
                ->name('campaigns.preview');
            Route::post('campaigns/{campaign}/send-test', [NewsletterCampaignController::class, 'sendTest'])
                ->name('campaigns.send-test');
            Route::post('campaigns/{campaign}/send', [NewsletterCampaignController::class, 'send'])
                ->name('campaigns.send');

            Route::resource('templates', NewsletterTemplateController::class)
                ->except(['show', 'destroy']); // up to you
        });

});