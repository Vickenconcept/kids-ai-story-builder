<?php

use App\Http\Controllers\Story\PublicStoryController;
use App\Http\Controllers\Story\StoryPageController;
use App\Http\Controllers\Story\StoryProjectController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::get('/read/{story:uuid}', [PublicStoryController::class, 'show'])->name('stories.public.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::prefix('stories')->name('stories.')->group(function () {
        Route::get('/', [StoryProjectController::class, 'index'])->name('index');
        Route::get('/create', [StoryProjectController::class, 'create'])->name('create');
        Route::post('/', [StoryProjectController::class, 'store'])->name('store');
        Route::get('/{story:uuid}', [StoryProjectController::class, 'show'])->name('show');
        Route::patch('/{story:uuid}', [StoryProjectController::class, 'updatePresentation'])->name('update');
        Route::post('/{story:uuid}/cover-upload', [StoryProjectController::class, 'uploadCover'])->name('cover.upload');
        Route::post('/{story:uuid}/cover-ai', [StoryProjectController::class, 'generateCoverAi'])->name('cover.ai');
        Route::patch('/{story:uuid}/pages/{page:uuid}', [StoryPageController::class, 'update'])->name('pages.update');
    });
});

require __DIR__.'/settings.php';
