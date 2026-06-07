<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Exception;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('/login', function (Request $req) {
    $credentials = $req->validate([
        'email' => 'required|email',
        'password' => 'required'
    ]);

    if (!Auth::attempt($credentials)) {
        return response()->json([
            'message' => 'Invalid Credentials',
            'success' => false
        ], 401);
    }

    $req->session()->regenerate();

    return response()->json([
        'message' => 'success',
        'user' => Auth::user()
    ], 200);
});

// register
Route::post('/register', function (Request $req) {
    $payload = $req->validate([
        'name' => 'nullable',
        'email' => 'required|email',
        'password' => 'required|confirmed',
        'password_confirmation' => 'required'
    ]);

    if (User::exists($payload['email'])) {
        return response()->json([
            'message' => 'user already exists',
            'sucess' => false
        ], 409);
    }

    try {
        DB::transaction(function () use ($payload) {
            User::create([
                'name' => $payload['name'],
                'email' => $payload['email'],
                'password' => bcrypt($payload['password']) // Hash the password
            ]);
        });
        $req->session()->regenerate();

        return response()->json([
            'message' => 'User created successfull'
        ], 200);
    } catch (\Throwable $th) {
        Log::error('Registration failed: ' . $th->getMessage());

        return response()->json([
            'message' => 'Registration failed',
            'success' => false
        ], 500);
    }
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
