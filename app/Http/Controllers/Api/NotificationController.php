<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use App\Services\FirebaseNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    protected FirebaseNotificationService $firebaseNotification;

    public function __construct(FirebaseNotificationService $firebaseNotification)
    {
        $this->firebaseNotification = $firebaseNotification;
    }

    public function index(Request $request, int $userId): JsonResponse
    {
        $authorizationError = $this->authorizeUserScope($request, $userId, 'access');
        if ($authorizationError) {
            return $authorizationError;
        }

        $validated = $request->validate([
            'status' => ['nullable', 'in:unread,read,all'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $status = $validated['status'] ?? 'unread';
        $perPage = (int) ($validated['per_page'] ?? 15);

        $notifications = Notification::query()
            ->where('user_id', $userId)
            ->when($status !== 'all', function ($query) use ($status) {
                $query->where('is_read', $status === 'read');
            })
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => Notification::query()
                ->where('user_id', $userId)
                ->where('is_read', false)
                ->count(),
        ]);
    }

    public function markRead(Request $request, int $userId, int $id): JsonResponse
    {
        $authorizationError = $this->authorizeUserScope($request, $userId, 'update');
        if ($authorizationError) {
            return $authorizationError;
        }

        $notification = Notification::query()
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$notification) {
            return response()->json([
                'message' => 'Notification not found',
                'success' => false,
            ], 404);
        }

        if (!$notification->is_read) {
            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Notification marked as read',
            'success' => true,
            'unread_count' => Notification::query()
                ->where('user_id', $userId)
                ->where('is_read', false)
                ->count(),
        ]);
    }

    public function markAllRead(Request $request, int $userId): JsonResponse
    {
        $authorizationError = $this->authorizeUserScope($request, $userId, 'update');
        if ($authorizationError) {
            return $authorizationError;
        }

        $updatedCount = Notification::query()
            ->where('user_id', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => 'All notifications marked as read',
            'success' => true,
            'updated_count' => $updatedCount,
            'unread_count' => 0,
        ]);
    }

    // NEW: Register device token for push notifications
    public function registerDeviceToken(Request $request): JsonResponse
    {
        $request->validate([
            'device_token' => 'required|string',
            'device_type' => 'nullable|in:ios,android,web'
        ]);

        $user = $request->user();
        
        // Store device token in your database
        $user->device_tokens()->updateOrCreate(
            ['device_token' => $request->device_token],
            [
                'device_type' => $request->device_type ?? 'web',
                'last_used_at' => now()
            ]
        );

        return response()->json([
            'message' => 'Device token registered successfully',
            'success' => true
        ]);
    }

    // NEW: Send push notification using Firebase
    public function sendPushNotification(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'data' => 'nullable|array'
        ]);

        try {
            // Get user's device tokens
            $user = User::find($request->user_id);
            if (!$user) {
                return response()->json([
                    'message' => 'User not found',
                    'success' => false
                ], 404);
            }

            $result = $this->firebaseNotification->sendToUser(
                $user,
                $request->title,
                $request->body,
                $request->data ?? []
            );

            return response()->json([
                'message' => 'Push notification sent successfully',
                'success' => true,
                'result' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send push notification: ' . $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    private function authorizeUserScope(Request $request, int $userId, string $action): ?JsonResponse
    {
        $authUser = $request->user();

        if ((int) $authUser->id === (int) $userId) {
            return null;
        }

        $message = $action === 'access'
            ? 'You are not allowed to access these notifications.'
            : 'You are not allowed to update these notifications.';

        return response()->json([
            'message' => $message,
            'success' => false,
        ], 403);
    }
}
