<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FcmToken as FcmTokenModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FcmTokenController extends Controller
{
    public function store(Request $request, int $userId): JsonResponse
    {
        $authorizationError = $this->authorizeUserScope($request, $userId);
        if ($authorizationError) {
            return $authorizationError;
        }

        $validated = $request->validate([
            'token' => ['required', 'string', 'max:2048'],
        ]);

        $fcmToken = FcmTokenModel::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'token' => $validated['token'],
            ],
            [
                'is_active' => true,
            ]
        );

        return response()->json([
            'message' => 'FCM token registered successfully',
            'success' => true,
            'fcm_token_id' => $fcmToken->id,
        ], 201);
    }

    public function destroy(Request $request, int $userId, string $token): JsonResponse
    {
        $authorizationError = $this->authorizeUserScope($request, $userId);
        if ($authorizationError) {
            return $authorizationError;
        }

        $fcmToken = FcmTokenModel::query()
            ->where('user_id', $userId)
            ->where('token', $token)
            ->first();

        if (!$fcmToken) {
            return response()->json([
                'message' => 'FCM token not found',
                'success' => false,
            ], 404);
        }

        $fcmToken->update([
            'is_active' => false,
        ]);

        return response()->json([
            'message' => 'FCM token deactivated successfully',
            'success' => true,
        ]);
    }

    private function authorizeUserScope(Request $request, int $userId): ?JsonResponse
    {
        $authUser = $request->user();

        if ((int) $authUser->id === (int) $userId) {
            return null;
        }

        return response()->json([
            'message' => 'You are not allowed to manage these FCM tokens.',
            'success' => false,
        ], 403);
    }
}
