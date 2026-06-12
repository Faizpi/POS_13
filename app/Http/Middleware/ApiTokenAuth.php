<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $hashedToken = hash('sha256', $token);
        $accessToken = PersonalAccessToken::where('token', $hashedToken)->first();

        if (!$accessToken || $accessToken->isExpired()) {
            return response()->json(['message' => 'Token invalid atau sudah expired.'], 401);
        }

        $accessToken->update(['last_used_at' => now()]);

        $user = $accessToken->user;
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan.'], 401);
        }

        auth()->setUser($user);
        $request->merge(['api_token_id' => $accessToken->id]);

        return $next($request);
    }
}
