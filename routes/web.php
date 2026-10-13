<?php

use App\Http\Controllers\Admin\CategoryPageController;
use App\Http\Controllers\Admin\DepartmentPageController;
use App\Http\Controllers\Admin\TeamPageController;
use App\Http\Controllers\Admin\UserPageController;
use App\Http\Controllers\AttachmentDownloadController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Kpi\KpiAnalysisController;
use App\Http\Controllers\Kpi\KpiPageController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RequestDiscussionController;
use App\Http\Controllers\RequestPageController;
use App\Http\Controllers\WorkloadController;
use Illuminate\Support\Facades\Route;

// Language switch works before sign-in too (login screen).
Route::post('locale', [LocaleController::class, 'update'])->name('locale.update');

// The front door: signed-in people land on their requests, everyone else on the login form.
Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{id}/open', [NotificationController::class, 'open'])->name('notifications.open');

    Route::get('workload', [WorkloadController::class, 'index'])->name('workload.index');

    // KPI. Everyone has a sheet; who sees whose, and who may sign one off, is KpiPolicy's call.
    Route::prefix('kpi')->name('kpi.')->group(function () {
        Route::get('/', [KpiPageController::class, 'index'])->name('index');
        Route::get('analysis', [KpiAnalysisController::class, 'index'])->name('analysis');
        Route::post('/', [KpiPageController::class, 'store'])->name('store');
        Route::post('generate', [KpiPageController::class, 'generate'])->name('generate');
        Route::patch('{kpi}/achievements', [KpiPageController::class, 'fill'])->name('fill');
        Route::post('{kpi}/submit', [KpiPageController::class, 'submit'])->name('submit');
        Route::post('{kpi}/review', [KpiPageController::class, 'review'])->name('review');
    });

    // People and reference data (ADMIN only, enforced by the policies inside the Actions).
    // Nothing here deletes: rows are switched off with `is_active` so history keeps its referents.
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('users', [UserPageController::class, 'index'])->name('users.index');
        Route::post('users', [UserPageController::class, 'store'])->name('users.store');
        Route::patch('users/{user}', [UserPageController::class, 'update'])->name('users.update');
        Route::post('users/{user}/reset-password', [UserPageController::class, 'resetPassword'])->name('users.reset-password');

        Route::get('teams', [TeamPageController::class, 'index'])->name('teams.index');
        Route::post('teams', [TeamPageController::class, 'store'])->name('teams.store');
        Route::patch('teams/{team}', [TeamPageController::class, 'update'])->name('teams.update');

        Route::get('categories', [CategoryPageController::class, 'index'])->name('categories.index');
        Route::post('categories', [CategoryPageController::class, 'store'])->name('categories.store');
        Route::patch('categories/{category}', [CategoryPageController::class, 'update'])->name('categories.update');

        Route::get('departments', [DepartmentPageController::class, 'index'])->name('departments.index');
        Route::post('departments', [DepartmentPageController::class, 'store'])->name('departments.store');
        Route::patch('departments/{department}', [DepartmentPageController::class, 'update'])->name('departments.update');
    });

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
