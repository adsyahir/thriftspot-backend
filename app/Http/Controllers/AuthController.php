<?php

namespace App\Http\Controllers;

use App\Models\RefreshToken;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\UserResource;
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
        $userId = auth()->id();

        // Delete refresh token from database
        $refreshTokenValue = request()->cookie('refresh_token');
        if ($refreshTokenValue) {
            \App\Models\RefreshToken::where('token', $refreshTokenValue)->delete();
        }

        // Invalidate JWT access token
        auth()->logout();

        // Clear both cookies from browser
        return response()->json(['message' => 'Successfully logged out'])
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

            // SECURITY: Refresh Token Rotation
            // Delete old refresh token (invalidates it immediately)
            $refreshToken->delete();

            // Generate new refresh token
            $newRefreshTokenValue = bin2hex(random_bytes(32));
            RefreshToken::create([
                'user_id' => $refreshToken->user_id,
                'token' => $newRefreshTokenValue,
                'expires_at' => now()->addMinutes(config('jwt.refresh_ttl')),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            // Create new refresh token cookie
            $ttlMinutes = config('jwt.refresh_ttl');
            $refreshTokenCookie = cookie(
                'refresh_token',
                $newRefreshTokenValue,
                $ttlMinutes,
                '/',
                '', // domain - empty string for current domain without subdomain restrictions
                false, // secure - false for localhost HTTP
                true,  // httpOnly - SECURE
                false, // raw
                'lax'  // sameSite - lax for cross-origin cookie sharing
            );

            // Return new access token (refresh token sent via cookie)
            return response()->json([
                'access_token' => $newAccessToken,
                'expires_in' => config('jwt.ttl'),
                'message' => 'Token refreshed successfully',
            ])->cookie($refreshTokenCookie);
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
        RefreshToken::where('user_id', auth()->id())
            ->where('expires_at', '<', now())
            ->delete();

        // Store refresh token in database
        RefreshToken::create([
            'user_id' => auth()->id(),
            'token' => $refreshToken,
            'expires_at' => now()->addDays(14), // 2 weeks
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
        // Create refresh token cookie (random token - long lived)
        $refreshTokenCookie = cookie(
            'refresh_token',
            $refreshToken,
            config('jwt.refresh_ttl'), // 2 weeks (in minutes)
            '/',
            '', // domain - empty string for current domain without subdomain restrictions
            false, // secure - false for localhost HTTP
            true,  // httpOnly - SECURE
            false, // raw
            'lax'  // sameSite - lax for cross-origin cookie sharing
        );

        return response()->json([
            'user' => new UserResource(auth()->user()),
            'message' => 'Success',
            'expires_in' => config('jwt.ttl'),
            'access_token' => $token,
        ])->cookie($refreshTokenCookie);
    }

}
