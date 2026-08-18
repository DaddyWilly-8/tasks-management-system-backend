<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FcmToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FcmTokenController extends Controller
{
    public function store(Request $request, User $user): JsonResponse
    {
        $this->authorizeUser($request, $user);

        $validated = $request->validate([
            'fcm_token' => ['nullable', 'string'],
            'token' => ['nullable', 'string'],
            'platform' => ['nullable', 'string', 'max:50'],
        ]);

        $token = $validated['fcm_token'] ?? $validated['token'] ?? null;

        if (!$token) {
            return response()->json([
                'message' => 'FCM token is required.',
                'success' => false,
            ], 422);
        }

        FcmToken::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'token' => $token,
            ],
            [
                'platform' => $validated['platform'] ?? 'web',
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Device token registered successfully',
            'success' => true,
        ]);
    }

    public function destroy(Request $request, User $user, string $token): JsonResponse
    {
        $this->authorizeUser($request, $user);

        FcmToken::query()
            ->where('user_id', $user->id)
            ->where('token', urldecode($token))
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => 'Device token removed successfully',
            'success' => true,
        ]);
    }

    private function authorizeUser(Request $request, User $user): void
    {
        abort_unless((int) $request->user()->id === (int) $user->id, 403);
    }
}
