<?php

use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\FcmTokenController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/tasks', [TaskController::class, 'index']);
    Route::get('/tasks/{id}', [TaskController::class, 'show']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::put('/tasks/{id}', [TaskController::class, 'update']);
    Route::patch('/tasks/{id}/status', [TaskController::class, 'updateStatus']);
    Route::delete('/tasks/{id}', [TaskController::class, 'destroy']);

    Route::get('/users', [UserController::class, 'index']);

    Route::get('/users/{userId}/notifications', [NotificationController::class, 'index']);
    Route::put('/users/{userId}/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::put('/users/{userId}/notifications/read-all', [NotificationController::class, 'markAllRead']);

    Route::post('/users/{user}/fcm-tokens', [FcmTokenController::class, 'store']);
    Route::delete('/users/{user}/fcm-tokens/{token}', [FcmTokenController::class, 'destroy'])
        ->where('token', '.*');
    
    // NEW: Device token registration
    Route::post('/notifications/register-device', [NotificationController::class, 'registerDeviceToken']);
    
    // NEW: Send push notification (admin only)
    Route::post('/notifications/send-push', [NotificationController::class, 'sendPushNotification']);

    // Device Token routes for push notifications
    Route::post('/device-tokens', [DeviceTokenController::class, 'register']);
    Route::delete('/device-tokens', [DeviceTokenController::class, 'unregister']);
    Route::get('/device-tokens', [DeviceTokenController::class, 'index']);
    Route::post('/device-tokens/subscribe', [DeviceTokenController::class, 'subscribeToTopic']);
    Route::post('/device-tokens/unsubscribe', [DeviceTokenController::class, 'unsubscribeFromTopic']);
});

