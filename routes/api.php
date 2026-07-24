<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\CycleController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\FormController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\SupervisorPermissionController;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/profile/stats', [AuthController::class, 'profileStats']);
        Route::patch('/profile', [AuthController::class, 'updateProfile']);
        Route::patch('/password', [AuthController::class, 'updatePassword']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

// Public endpoints
Route::get('/groups', [GroupController::class, 'index']);
Route::get('/groups/{group}', [GroupController::class, 'show']);
Route::get('/calendar', [CalendarController::class, 'index']);
Route::get('/calendar/file', [CalendarController::class, 'file']);
Route::get('/users/{userId}/avatar', [UserController::class, 'avatar']);

// Authenticated API
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/forms/active', [FormController::class, 'active']);
    Route::apiResource('forms', FormController::class)->only(['index', 'show']);

    Route::apiResource('documents', DocumentController::class);
    Route::get('/documents/{document}/file', [DocumentController::class, 'file'])->name('documents.file');
    Route::get('/documents/{document}/history', [DocumentController::class, 'history']);
    Route::patch('/documents/{document}/review', [DocumentController::class, 'review'])->middleware('role:administrador');
    Route::patch('/documents/{document}/return', [DocumentController::class, 'returnDocument'])->middleware('role:administrador');

    Route::get('/conversations', [MessageController::class, 'index']);
    Route::get('/conversations/peer', [MessageController::class, 'peer']);
    Route::post('/conversations', [MessageController::class, 'storeConversation']);
    Route::get('/conversations/{conversation}/messages', [MessageController::class, 'messages']);
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'storeMessage']);
    Route::delete('/conversations/{conversation}/messages/{message}', [MessageController::class, 'destroy']);
    Route::patch('/conversations/{conversation}/read', [MessageController::class, 'markAsRead']);
    Route::get('/message-attachments/{attachment}/file', [MessageController::class, 'downloadAttachment']);

    Route::get('/documents-active-cycle', [DocumentController::class, 'byCycleActive']);
    Route::get('/documents-by-docente', [DocumentController::class, 'byDocente']);
    Route::get('/documents-pending-review', [DocumentController::class, 'pendingForReview']);
    Route::get('/documents-status-count', [DocumentController::class, 'countByStatus']);
    Route::patch('/documents/{document}/hide', [DocumentController::class, 'hide']);
    Route::post('/documents/{document}/resubmit', [DocumentController::class, 'resubmit']);
    Route::post('/calendar', [CalendarController::class, 'store'])->middleware('role:administrador');

    // Admin-only resource management
    Route::middleware('role:administrador')->group(function () {
        Route::apiResource('cycles', CycleController::class);
        Route::apiResource('users', UserController::class);
        Route::apiResource('groups', GroupController::class)->except(['index', 'show']);
        Route::apiResource('forms', FormController::class)->only(['update']);
        Route::get('/supervisor-permissions', [SupervisorPermissionController::class, 'index']);
        Route::put('/supervisor-permissions/{user_id}', [SupervisorPermissionController::class, 'update']);
    });
});
