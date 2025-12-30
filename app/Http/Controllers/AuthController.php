<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cookie;

class AuthController extends Controller
{
    /**
     * Get a JWT via given credentials.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only(['email', 'password']);

        if (!$token = auth()->attempt($credentials)) {
            Log::warning('Failed login attempt', [
                'email' => $request->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        Log::info('Successful login', [
            'email' => $request->email,
            'ip' => $request->ip(),
        ]);

        return $this->respondWithToken($token);
    }   

    /**
     * Get the authenticated User.
     */
    public function me(): JsonResponse
    {
        $user = auth()->user()->load(['roles', 'permissions']);
        return response()->json(new UserResource($user));
    }

    /**
     * Log the user out (Invalidate the token).
     */
    public function logout(): JsonResponse
    {
        Log::info('User logged out', [
            'user_id' => auth()->id(),
            'ip' => request()->ip(),
        ]);

        // Invalidate token
        auth()->logout();

        // Clear both cookies
        return response()->json(['message' => 'Successfully logged out'])
            ->cookie(Cookie::forget('access_token'))
            ->cookie(Cookie::forget('refresh_token'));
    }

    /**
     * Refresh access token using refresh_token from cookie.
     */
    public function refresh(): JsonResponse
    {
        try {
            // Get refresh token from cookie
            $refreshTokenValue = request()->cookie('refresh_token');

            if (! $refreshTokenValue) {
                return response()->json(['error' => 'Refresh token not found'], 401);
            }

            // Validate refresh token from database
            $refreshToken = \App\Models\RefreshToken::where('token', $refreshTokenValue)
                ->where('expires_at', '>', now())
                ->first();

            if (! $refreshToken || $refreshToken->isExpired()) {
                return response()->json(['error' => 'Invalid or expired refresh token'], 401);
            }

            // Generate new access token for the user
            $newAccessToken = auth()->login($refreshToken->user);

            Log::info('Access token refreshed via refresh_token', [
                'user_id' => $refreshToken->user_id,
                'ip' => request()->ip(),
            ]);

            // Create access token cookie
            $accessTokenCookie = cookie(
                'access_token',
                $newAccessToken,
                config('jwt.ttl', 5), // TTL in minutes
                '/',
                'localhost',
                false, // secure
                true,  // httpOnly
                false, // raw
                'lax'  // sameSite
            );

            // Return new access token (keep existing refresh token)
            return response()->json([
                'access_token' => $newAccessToken,
                'expires_in' => config('jwt.ttl', 5),
                'message' => 'Token refreshed successfully',
            ])->cookie($accessTokenCookie);
        } catch (\Exception $e) {
            Log::error('Token refresh failed', [
                'error' => $e->getMessage(),
                'ip' => request()->ip(),
            ]);

            return response()->json(['error' => 'Token refresh failed'], 401);
        }
    }

    /**
     * Get the token array structure with cookies.
     */
    protected function respondWithToken(string $token): JsonResponse
    {
        // Generate refresh token
        $refreshToken = bin2hex(random_bytes(32));

        // Revoke old refresh tokens for this user (optional security measure)
        \App\Models\RefreshToken::where('user_id', auth()->id())
            ->where('expires_at', '<', now())
            ->delete();

        // Store refresh token in database
        \App\Models\RefreshToken::create([
            'user_id' => auth()->id(),
            'token' => $refreshToken,
            'expires_at' => now()->addDays(14), // 2 weeks
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Create access token cookie (JWT - short lived)
        $accessTokenCookie = cookie(
            'access_token',
            $token,
            config('jwt.ttl', 5), // TTL in minutes
            '/',
            'localhost',
            false, // secure - false for localhost HTTP
            true,  // httpOnly - SECURE
            false, // raw
            'lax'  // sameSite
        );

        // Create refresh token cookie (random token - long lived)
        $refreshTokenCookie = cookie(
            'refresh_token',
            $refreshToken,
            20160, // 2 weeks (in minutes)
            '/',
            'localhost', // domain - 'localhost' works across all ports
            false, // secure - false for localhost HTTP
            true,  // httpOnly - SECURE
            false, // raw
            'lax'  // sameSite - 'lax' works with same domain
        );

        return response()->json([
            'user' => new UserResource(auth()->user()),
            'message' => 'Success',
            'expires_in' => config('jwt.ttl', 5),
            'access_token' => $token,
        ])->cookie($accessTokenCookie)->cookie($refreshTokenCookie);
    }

}
