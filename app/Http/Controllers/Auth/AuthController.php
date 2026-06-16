<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::findByEmail($credentials['email']);

        if (!$user) {
            return response()->json([
                'message' => 'User not found for the provided email.',
                'success' => false,
            ], 404);
        }

        if (!Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid password.',
                'success' => false,
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'success' => true,
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed'],
        ]);

        if (User::emailExists($payload['email'])) {
            return response()->json([
                'message' => 'User already exists',
                'success' => false,
            ], 409);
        }

        $user = DB::transaction(function () use ($payload) {
            return User::create([
                'name' => $payload['name'] ?? null,
                'email' => $payload['email'],
                'password' => Hash::make($payload['password']),
            ]);
        });

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User created successfully',
            'success' => true,
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'message' => 'Logout successful',
        ]);
    }
}
