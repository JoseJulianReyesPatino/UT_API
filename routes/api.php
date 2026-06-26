<?php

use Illuminate\Support\Facades\Route;

// Ruta para servir avatares
Route::get('/users/{user}/avatar', function ($userId) {
    $user = \App\Models\User::find($userId);
    if (!$user) {
        abort(404);
    }
    
    $avatarUrl = $user->avatar_url;
    
    // Si es una URL base64, devolverla directamente
    if ($avatarUrl && str_starts_with($avatarUrl, 'data:image')) {
        return response($avatarUrl)->header('Content-Type', 'text/plain');
    }
    
    // Si es una URL de storage
    if ($avatarUrl && str_contains($avatarUrl, '/storage/')) {
        $path = str_replace('/storage/', '', $avatarUrl);
        $fullPath = storage_path('app/public/' . $path);
        if (file_exists($fullPath)) {
            return response()->file($fullPath, [
                'Content-Type' => mime_content_type($fullPath) ?: 'image/png',
            ]);
        }
    }
    
    // Buscar archivo de avatar en storage
    $avatarFile = "avatars/avatar_{$userId}_*";
    $files = glob(storage_path("app/public/{$avatarFile}"));
    if (!empty($files)) {
        $fullPath = $files[0];
        return response()->file($fullPath, [
            'Content-Type' => mime_content_type($fullPath) ?: 'image/png',
        ]);
    }
    
    // Si no hay avatar, devolver el avatar por defecto
    $defaultAvatar = public_path('assets/default-avatar.svg');
    if (file_exists($defaultAvatar)) {
        return response()->file($defaultAvatar, [
            'Content-Type' => 'image/svg+xml',
        ]);
    }
    
    abort(404);
});
use App\Http\Controllers\Api\AuthController;
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
        Route::patch('/profile', [AuthController::class, 'updateProfile']);
        Route::patch('/password', [AuthController::class, 'updatePassword']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

// Allow public read access to groups so the frontend can list available groups without authentication.
Route::get('/groups', [GroupController::class, 'index']);
Route::get('/groups/{group}', [GroupController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

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
    Route::delete('/conversations/{conversation}/messages/{message}', [MessageController::class, 'destroy']);
    Route::patch('/conversations/{conversation}/read', [MessageController::class, 'markAsRead']);

    // Document distribution and filtering endpoints
    Route::get('/documents-active-cycle', [DocumentController::class, 'byCycleActive']);
    Route::get('/documents-by-docente', [DocumentController::class, 'byDocente']);
    Route::get('/documents-pending-review', [DocumentController::class, 'pendingForReview']);
    Route::get('/documents-status-count', [DocumentController::class, 'countByStatus']);
});


