<?php

use App\Http\Controllers\Admin\CreditPackController;
use App\Http\Controllers\Admin\StoryPlanController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Api\JvzooIpnController;
use App\Http\Controllers\Billing\CreditPurchaseController;
use App\Http\Controllers\Billing\PlanUpgradeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ResellerController;
use App\Http\Controllers\Story\PublicStoryController;
use App\Http\Controllers\Story\StoryPageController;
use App\Http\Controllers\Story\StoryProjectController;
use App\Http\Controllers\Story\StoryVideoLibraryController;
use App\Models\StoryPlan;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
        'plans' => StoryPlan::query()
            ->active()
            ->ordered()
            ->get([
                'id',
                'name',
                'description',
                'tier',
                'included_credits',
                'price_cents',
                'currency',
                'is_featured',
                'feature_list',
            ]),
    ]);
})->name('home');

Route::inertia('/jv', 'Jv')->name('jv');
Route::inertia('/sales', 'Sales')->name('sales');
Route::inertia('/oto1', 'Oto1')->name('oto1');
Route::inertia('/oto2', 'Oto2')->name('oto2');
Route::inertia('/thank-you', 'ThankYou')->name('thank-you');
Route::inertia('/terms', 'Terms')->name('terms');
Route::inertia('/privacy-policy', 'PrivacyPolicy')->name('privacy-policy');
Route::post('/api/ipn/jvzoo', JvzooIpnController::class)
    ->middleware('throttle:30,1')
    ->name('api.ipn.jvzoo');

Route::get('/read/{story:uuid}', [PublicStoryController::class, 'show'])->name('stories.public.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('credits')->name('credits.')->group(function () {
        Route::get('/', [CreditPurchaseController::class, 'index'])->name('index');
        Route::post('/paypal/order', [CreditPurchaseController::class, 'createPayPalOrder'])->name('paypal.order');
        Route::post('/paypal/capture', [CreditPurchaseController::class, 'capturePayPalOrder'])->name('paypal.capture');
    });

    Route::prefix('plans')->name('plans.')->group(function () {
        Route::get('/', [PlanUpgradeController::class, 'index'])->name('index');
        Route::post('/paypal/order', [PlanUpgradeController::class, 'createPayPalOrder'])->name('paypal.order');
        Route::post('/paypal/capture', [PlanUpgradeController::class, 'capturePayPalOrder'])->name('paypal.capture');
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

    Route::middleware('can:manage-plans')->prefix('admin/plans')->name('admin.plans.')->group(function () {
        Route::get('/', [StoryPlanController::class, 'index'])->name('index');
        Route::post('/', [StoryPlanController::class, 'store'])->name('store');
        Route::patch('/{plan}', [StoryPlanController::class, 'update'])->name('update');
        Route::delete('/{plan}', [StoryPlanController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('can:manage-users')->prefix('admin/users')->name('admin.users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::patch('/{user}', [UserController::class, 'update'])->name('update');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('elite')->prefix('reseller')->name('reseller.')->group(function () {
        Route::get('/', [ResellerController::class, 'index'])->name('index');
        Route::post('/accounts', [ResellerController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('accounts.store');
    });

    Route::middleware('pro_tier')->get('/video-library', [StoryVideoLibraryController::class, 'index'])->name('video-library.index');
});

require __DIR__.'/settings.php';
