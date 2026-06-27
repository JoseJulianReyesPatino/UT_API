<?php

use Illuminate\Support\Facades\Route;

// Ruta para servir avatares
Route::get('/users/{user}/avatar', function ($userId) {
    $user = \App\Models\User::find($userId);
    if (!$user) {
        abort(404);
    }

    $avatarUrl = $user->avatar_url;

    // Base64 data URI stored directly in DB — decode and return as image
    if ($avatarUrl && str_starts_with($avatarUrl, 'data:image')) {
        if (preg_match('/^data:([^;]+);base64,(.+)$/', $avatarUrl, $m)) {
            return response(base64_decode($m[2]), 200)->header('Content-Type', $m[1]);
        }
    }

    // 1. Look in public/uploads/avatars/ (original storage location)
    $publicFiles = glob(public_path("uploads/avatars/avatar_{$userId}_*")) ?: [];
    if (!empty($publicFiles)) {
        // Use the most recently modified file
        usort($publicFiles, fn($a, $b) => filemtime($b) - filemtime($a));
        $fullPath = $publicFiles[0];
        return response()->file($fullPath, ['Content-Type' => mime_content_type($fullPath) ?: 'image/png']);
    }

    // 2. Look in storage/app/public/avatars/ (Laravel public disk)
    $storageFiles = glob(storage_path("app/public/avatars/avatar_{$userId}_*")) ?: [];
    if (!empty($storageFiles)) {
        usort($storageFiles, fn($a, $b) => filemtime($b) - filemtime($a));
        $fullPath = $storageFiles[0];
        return response()->file($fullPath, ['Content-Type' => mime_content_type($fullPath) ?: 'image/png']);
    }

    // 3. Try to locate file from an absolute/relative storage URL in avatar_url
    if ($avatarUrl && str_contains($avatarUrl, '/storage/')) {
        // Extract path after last /storage/ occurrence
        $relativePath = preg_replace('/^.*\/storage\//', '', $avatarUrl);
        $fullPath = storage_path('app/public/' . $relativePath);
        if (file_exists($fullPath)) {
            return response()->file($fullPath, ['Content-Type' => mime_content_type($fullPath) ?: 'image/png']);
        }
    }

    abort(404);
});
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\CycleController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\FormController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\GroupController;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/profile/stats', [AuthController::class, 'profileStats']);
        Route::match(['PATCH', 'POST'], '/profile', [AuthController::class, 'updateProfile']);
        Route::patch('/password', [AuthController::class, 'updatePassword']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

// Allow public read access to groups so the frontend can list available groups without authentication.
Route::get('/groups', [GroupController::class, 'index']);
Route::get('/groups/{group}', [GroupController::class, 'show']);

// Public: serve active calendar PDF (opened via window.open with no auth token)
Route::get('/calendar/file', [CalendarController::class, 'file']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    Route::get('/calendar', [CalendarController::class, 'index']);
    Route::post('/calendar', [CalendarController::class, 'store']);

    // Rutas de formularios (incluyendo la nueva ruta 'active')
    Route::get('/forms/active', [FormController::class, 'active']);
    Route::apiResource('forms', FormController::class)->only(['index', 'show', 'update']);

    Route::apiResource('cycles', CycleController::class);
    Route::apiResource('users', UserController::class);
    Route::apiResource('groups', GroupController::class)->except(['index','show']);
    Route::apiResource('documents', DocumentController::class);
    Route::get('/documents/{document}/file', [DocumentController::class, 'file'])->name('documents.file');

    Route::get('/documents/{document}/history', [DocumentController::class, 'history']);
    Route::patch('/documents/{document}/review', [DocumentController::class, 'review']);
    Route::patch('/documents/{document}/return', [DocumentController::class, 'returnDocument']);

    Route::get('/conversations', [MessageController::class, 'index']);
    Route::post('/conversations', [MessageController::class, 'storeConversation']);
    Route::get('/conversations/{conversation}/messages', [MessageController::class, 'messages']);
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'storeMessage']);
    Route::patch('/conversations/{conversation}/messages/{message}', [MessageController::class, 'updateMessage']);
    Route::delete('/conversations/{conversation}/messages/{message}', [MessageController::class, 'destroy']);
    Route::patch('/conversations/{conversation}/read', [MessageController::class, 'markAsRead']);
    Route::get('/message-attachments/{attachment}/file', [MessageController::class, 'downloadAttachment']);

    // Document distribution and filtering endpoints
    Route::get('/documents-active-cycle', [DocumentController::class, 'byCycleActive']);
    Route::get('/documents-by-docente', [DocumentController::class, 'byDocente']);
    Route::get('/documents-pending-review', [DocumentController::class, 'pendingForReview']);
    Route::get('/documents-status-count', [DocumentController::class, 'countByStatus']);
});


