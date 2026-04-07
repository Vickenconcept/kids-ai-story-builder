<?php

use App\Http\Controllers\Api\JvzooIpnController;
use App\Http\Controllers\Admin\CreditPackController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Billing\CreditPurchaseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Story\PublicStoryController;
use App\Http\Controllers\Story\StoryPageController;
use App\Http\Controllers\Story\StoryProjectController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::inertia('/jv', 'Jv')->name('jv');
Route::inertia('/sales', 'Sales')->name('sales');
Route::inertia('/oto1', 'Oto1')->name('oto1');
Route::inertia('/oto2', 'Oto2')->name('oto2');
Route::inertia('/thank-you', 'ThankYou')->name('thank-you');
Route::post('/api/ipn/jvzoo', JvzooIpnController::class)->name('api.ipn.jvzoo');

Route::get('/read/{story:uuid}', [PublicStoryController::class, 'show'])->name('stories.public.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('credits')->name('credits.')->group(function () {
        Route::get('/', [CreditPurchaseController::class, 'index'])->name('index');
        Route::post('/paypal/order', [CreditPurchaseController::class, 'createPayPalOrder'])->name('paypal.order');
        Route::post('/paypal/capture', [CreditPurchaseController::class, 'capturePayPalOrder'])->name('paypal.capture');
    });

    Route::prefix('stories')->name('stories.')->group(function () {
        Route::get('/', [StoryProjectController::class, 'index'])->name('index');
        Route::get('/create', [StoryProjectController::class, 'create'])->name('create');
        Route::post('/', [StoryProjectController::class, 'store'])->name('store');
        Route::get('/{story:uuid}/page-media-status', [StoryProjectController::class, 'pageMediaStatus'])->name('page-media-status');
        Route::get('/{story:uuid}', [StoryProjectController::class, 'show'])->name('show');
        Route::patch('/{story:uuid}', [StoryProjectController::class, 'updatePresentation'])->name('update');
        Route::delete('/{story:uuid}', [StoryProjectController::class, 'destroy'])->name('destroy');
        Route::post('/bulk-destroy', [StoryProjectController::class, 'bulkDestroy'])->name('bulk-destroy');
        Route::post('/{story:uuid}/cover-upload', [StoryProjectController::class, 'uploadCover'])->name('cover.upload');
        Route::post('/{story:uuid}/cover-ai', [StoryProjectController::class, 'generateCoverAi'])->name('cover.ai');
        Route::post('/{story:uuid}/start-media', [StoryProjectController::class, 'startMediaGeneration'])->name('start-media');
        Route::get('/{story:uuid}/export/kdp', [StoryProjectController::class, 'exportKdpPackage'])->name('export.kdp');
        Route::post('/{story:uuid}/pages/{page:uuid}/generate-video', [StoryProjectController::class, 'generatePageVideo'])->name('pages.video');
        Route::patch('/{story:uuid}/pages/{page:uuid}', [StoryPageController::class, 'update'])->name('pages.update');
    });

    Route::middleware('can:manage-credit-packs')->prefix('admin/credit-packs')->name('admin.credit-packs.')->group(function () {
        Route::get('/', [CreditPackController::class, 'index'])->name('index');
        Route::post('/', [CreditPackController::class, 'store'])->name('store');
        Route::patch('/{pack}', [CreditPackController::class, 'update'])->name('update');
        Route::delete('/{pack}', [CreditPackController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('can:manage-users')->prefix('admin/users')->name('admin.users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::patch('/{user}', [UserController::class, 'update'])->name('update');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
    });
});

require __DIR__.'/settings.php';
