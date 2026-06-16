<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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
        throw ValidationException::withMessages([
            'email' => 'These credentials do not match our records.',
        ]);
    }
    $user = User::where('email', $credentials['email'])->first();
    Auth::login($user);
    // $req->session()->regenerate();

    return response()->json([
        'message' => 'Success loging in',
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

    if (User::where('email', $payload['email'])->exists()) {
        return response()->json([
            'message' => 'user already exists',
            'sucess' => false
        ], 409);
    }

    try {
        DB::transaction(function () use ($payload, $req) {
            $user = User::create([
                'name' => $payload['name'],
                'email' => $payload['email'],
                'password' => bcrypt($payload['password']) // Hash the password
            ]);
            Auth::login($user);
            // $req->session()->regenerate();
        });

        return response()->json([
            'message' => 'User created successfull',
            'success' => false,
            'user' => Auth::user(),
        ], 200);
    } catch (\Throwable $th) {
        Log::error('Registration failed: ' . $th->getMessage());

        return response()->json([
            'message' => 'Registration failed',
        ], 500);
    }
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
