<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Kreait\Laravel\Firebase\Facades\Firebase;

class FirebaseAuth
{
    public function handle(Request $request, Closure $next)
    {
        $idToken = $request->bearerToken();
        
        if (!$idToken) {
            return response()->json([
                'error' => 'Unauthorized - No token provided'
            ], 401);
        }

        try {
            $auth = Firebase::auth();
            $verifiedIdToken = $auth->verifyIdToken($idToken);
            $uid = $verifiedIdToken->claims()->get('sub');
            
            // Attach UID to request
            $request->merge(['firebase_uid' => $uid]);
            
            return $next($request);
        } catch (FailedToVerifyToken $e) {
            return response()->json([
                'error' => 'Invalid token: ' . $e->getMessage()
            ], 401);
        }
    }
}