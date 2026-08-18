<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Services\FirebaseNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    protected FirebaseNotificationService $firebaseNotification;

    public function __construct(FirebaseNotificationService $firebaseNotification)
    {
        $this->firebaseNotification = $firebaseNotification;
    }

    /**
     * Register or update device token for push notifications
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'device_token' => 'required|string|max:500',
            'device_type' => 'nullable|in:ios,android,web',
            'device_name' => 'nullable|string|max:255'
        ]);

        $user = $request->user();

        // Check if token already exists for this user
        $existingToken = DeviceToken::where('device_token', $request->device_token)
            ->where('user_id', $user->id)
            ->first();

        if ($existingToken) {
            $existingToken->update([
                'last_used_at' => now(),
                'device_type' => $request->device_type ?? $existingToken->device_type,
                'device_name' => $request->device_name ?? $existingToken->device_name,
            ]);
        } else {
            // Delete old tokens if user has too many (optional)
            $tokenCount = DeviceToken::where('user_id', $user->id)->count();
            if ($tokenCount >= 5) {
                DeviceToken::where('user_id', $user->id)
                    ->oldest()
                    ->limit($tokenCount - 4)
                    ->delete();
            }

            DeviceToken::create([
                'user_id' => $user->id,
                'device_token' => $request->device_token,
                'device_type' => $request->device_type ?? 'web',
                'device_name' => $request->device_name,
                'last_used_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Device token registered successfully',
            'success' => true
        ]);
    }

    /**
     * Unregister device token (when user logs out or uninstalls app)
     */
    public function unregister(Request $request): JsonResponse
    {
        $request->validate([
            'device_token' => 'required|string'
        ]);

        $user = $request->user();

        DeviceToken::where('user_id', $user->id)
            ->where('device_token', $request->device_token)
            ->delete();

        return response()->json([
            'message' => 'Device token unregistered successfully',
            'success' => true
        ]);
    }

    /**
     * Get all device tokens for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()
            ->deviceTokens()
            ->select('device_token', 'device_type', 'device_name', 'last_used_at')
            ->get();

        return response()->json([
            'tokens' => $tokens,
            'success' => true
        ]);
    }

    /**
     * Subscribe device to a topic
     */
    public function subscribeToTopic(Request $request): JsonResponse
    {
        $request->validate([
            'device_token' => 'required|string',
            'topic' => 'required|string'
        ]);

        $result = $this->firebaseNotification->subscribeToTopic(
            $request->device_token,
            $request->topic
        );

        return response()->json($result);
    }

    /**
     * Unsubscribe device from a topic
     */
    public function unsubscribeFromTopic(Request $request): JsonResponse
    {
        $request->validate([
            'device_token' => 'required|string',
            'topic' => 'required|string'
        ]);

        $result = $this->firebaseNotification->unsubscribeFromTopic(
            $request->device_token,
            $request->topic
        );

        return response()->json($result);
    }
}