<?php

use App\Http\Controllers\AttachmentDownloadController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RequestDiscussionController;
use App\Http\Controllers\RequestPageController;
use Illuminate\Support\Facades\Route;

// Language switch works before sign-in too (login screen).
Route::post('locale', [LocaleController::class, 'update'])->name('locale.update');

// The front door: signed-in people land on their requests, everyone else on the login form.
Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{id}/open', [NotificationController::class, 'open'])->name('notifications.open');

    Route::get('requests', [RequestPageController::class, 'index'])->name('requests.index');
    Route::get('requests/create', [RequestPageController::class, 'create'])->name('requests.create');
    Route::post('requests', [RequestPageController::class, 'store'])
        ->middleware('throttle:create-request')
        ->name('requests.store');

    Route::get('requests/{serviceRequest:request_number}', [RequestPageController::class, 'show'])->name('requests.show');
    Route::patch('requests/{serviceRequest:request_number}', [RequestPageController::class, 'update'])->name('requests.update');
    Route::post('requests/{serviceRequest:request_number}/assign', [RequestPageController::class, 'assign'])->name('requests.assign');
    Route::post('requests/{serviceRequest:request_number}/status', [RequestPageController::class, 'status'])->name('requests.status');
    Route::post('requests/{serviceRequest:request_number}/comments', [RequestDiscussionController::class, 'comment'])
        ->middleware('throttle:uploads')->name('requests.comments.store');
    Route::post('requests/{serviceRequest:request_number}/attachments', [RequestDiscussionController::class, 'attach'])
        ->middleware('throttle:uploads')->name('requests.attachments.store');
    Route::get('requests/{serviceRequest:request_number}/attachments/{attachment}/download', AttachmentDownloadController::class)
        ->whereNumber('attachment')->name('requests.attachments.download');
    Route::post('requests/{serviceRequest:request_number}/review', [RequestDiscussionController::class, 'review'])->name('requests.review');
});

require __DIR__.'/settings.php';
