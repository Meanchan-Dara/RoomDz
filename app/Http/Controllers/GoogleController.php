<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
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
            $token = $request->input('token');

            /** @var \Laravel\Socialite\Two\GoogleProvider $driver */
            $driver = Socialite::driver('google');

            if (!empty($token)) {
                $googleUser = $driver->stateless()->userFromToken($token);
            } else {
                $googleUser = $driver->stateless()->user();
            }

            $user = User::updateOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'name' => $googleUser->getName(),
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'role' => 'customer',
                    'password' => Hash::make(Str::random(24)),
                ]
            );

            $authToken = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Google authentication successful',
                'token' => $authToken,
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
