<?php

use App\Http\Controllers\Api\V1\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DiscussionController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\RequestController;
use App\Http\Controllers\AttachmentDownloadController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\WorkloadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Athar API v1  (prefix /api/v1)
|--------------------------------------------------------------------------
| The same operations the web UI performs, for mobile apps, integrations and,
| later, an AI agent. Every endpoint runs through the same Actions and Policies.
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('auth/token', [AuthController::class, 'token'])
        ->middleware('throttle:api-login')
        ->name('auth.token');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

        Route::get('dashboards/{perspective}', [DashboardController::class, 'show'])->whereIn('perspective', ['requester', 'staff', 'management'])->name('dashboards.show');

        Route::get('workload', [WorkloadController::class, 'api'])->name('workload');

        Route::prefix('admin')->name('admin.')->group(function () {
            Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
            Route::post('users', [AdminUserController::class, 'store'])->name('users.store');
            Route::patch('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
            Route::post('users/{user}/reset-password', [AdminUserController::class, 'resetPassword'])->name('users.reset-password');
        });
        Route::get('categories', [LookupController::class, 'categories'])->name('categories');
        Route::get('teams', [LookupController::class, 'teams'])->name('teams');
        Route::get('users', [LookupController::class, 'users'])->name('users');

        Route::get('requests', [RequestController::class, 'index'])->name('requests.index');
        Route::post('requests', [RequestController::class, 'store'])
            ->middleware('throttle:create-request')
            ->name('requests.store');
        Route::get('requests/{serviceRequest}', [RequestController::class, 'show'])->name('requests.show');
        Route::patch('requests/{serviceRequest}', [RequestController::class, 'update'])->name('requests.update');
        Route::post('requests/{serviceRequest}/assign', [RequestController::class, 'assign'])->name('requests.assign');
        Route::post('requests/{serviceRequest}/status', [RequestController::class, 'status'])->name('requests.status');
        Route::get('requests/{serviceRequest}/activity', [RequestController::class, 'activity'])->name('requests.activity');
        Route::get('requests/{serviceRequest}/assignments', [RequestController::class, 'assignments'])->name('requests.assignments');

        Route::get('requests/{serviceRequest}/comments', [DiscussionController::class, 'comments'])->name('requests.comments.index');
        Route::post('requests/{serviceRequest}/comments', [DiscussionController::class, 'storeComment'])
            ->middleware('throttle:uploads')->name('requests.comments.store');
        Route::get('requests/{serviceRequest}/attachments', [DiscussionController::class, 'attachments'])->name('requests.attachments.index');
        Route::post('requests/{serviceRequest}/attachments', [DiscussionController::class, 'storeAttachment'])
            ->middleware('throttle:uploads')->name('requests.attachments.store');
        Route::get('requests/{serviceRequest}/attachments/{attachment}/download', AttachmentDownloadController::class)
            ->whereNumber('attachment')->name('requests.attachments.download');
        Route::get('requests/{serviceRequest}/reviews', [DiscussionController::class, 'reviews'])->name('requests.reviews');
        Route::post('requests/{serviceRequest}/review', [DiscussionController::class, 'decide'])->name('requests.review');
    });
});
