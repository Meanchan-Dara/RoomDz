<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

class GoogleController extends Controller
{
    /**
     * Get the authenticated JWT guard.
     */
    private function guard(): JWTGuard
    {
        /** @var JWTGuard $guard */
        $guard = auth('api');
        return $guard;
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'RoleID' => 'required'
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'RoleID' => $request->RoleID,
            'google_id' => $request->google_id
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user
        ]);
    }
    public function redirect()
    {
        /** @var \Laravel\Socialite\Two\GoogleProvider $driver */
        $driver = Socialite::driver('google');
        return $driver->stateless()->redirect();
    }
    public function callback(Request $request)
    {
        try {
            $token = $request->input('token') ?? $request->input('access_token');
            $idToken = $request->input('id_token');

            $googleEmail = null;
            $googleName = null;
            $googleId = null;
            $googleAvatar = null;

            // 1. Try to authenticate via Socialite access_token
            if (!empty($token)) {
                try {
                    /** @var \Laravel\Socialite\Two\GoogleProvider $driver */
                    $driver = Socialite::driver('google');
                    $googleUser = $driver->stateless()->userFromToken($token);
                    $googleEmail = $googleUser->getEmail();
                    $googleName = $googleUser->getName();
                    $googleId = $googleUser->getId();
                    $googleAvatar = $googleUser->getAvatar();
                } catch (\Throwable $socErr) {
                    // Fallback to tokeninfo or idToken below
                }
            }

            // 2. If access_token failed or id_token provided, verify with Google tokeninfo
            if (empty($googleEmail)) {
                $verifyToken = !empty($idToken) ? $idToken : $token;
                if (!empty($verifyToken)) {
                    $response = \Illuminate\Support\Facades\Http::get('https://oauth2.googleapis.com/tokeninfo', [
                        'id_token' => $verifyToken,
                    ]);

                    if ($response->successful()) {
                        $data = $response->json();
                        $googleEmail = $data['email'] ?? null;
                        $googleName = $data['name'] ?? null;
                        $googleId = $data['sub'] ?? null;
                        $googleAvatar = $data['picture'] ?? null;
                    }
                }
            }

            if (empty($googleEmail)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to verify Google credentials. Invalid token.',
                ], 400);
            }

            $customerRole = role::where('name', 'customer')->first();

            $user = User::where('email', $googleEmail)->first();
            if ($user) {
                $user->update([
                    'google_id' => $googleId ?? $user->google_id,
                    'avatar' => $googleAvatar ?? $user->avatar,
                ]);
            } else {
                $user = User::create([
                    'name' => $googleName ?? 'Google User',
                    'email' => $googleEmail,
                    'google_id' => $googleId,
                    'avatar' => $googleAvatar,
                    'role_id' => $customerRole?->id,
                    'password' => Hash::make(Str::random(24)),
                ]);
            }

            $user->load('role');

            $authToken = $this->guard()->login($user);

            return response()->json([
                'success' => true,
                'message' => 'Google authentication successful',
                'token' => $authToken,
                'token_type' => 'bearer',
                'expires_in' => $this->guard()->getTTL() * 60,
                'user' => $user,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Google login failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
