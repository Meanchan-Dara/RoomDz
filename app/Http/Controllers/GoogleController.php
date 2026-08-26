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
    return Socialite::driver('google')->stateless()->redirect();
}
      public function callback(Request $request)
    {
        $token = $request->input('token'); 
        try {
            $googleUser = Socialite::driver('google')->stateless()->userFromToken($token);

            $user = User::updateOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'name' => $googleUser->getName(),
                    'google_id' => $googleUser->getId(),
                    'role' => 1,
                    'password' => bcrypt(Str::random(16)),
                ]
            );

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'token' => $token,
                'user' => $user
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Google login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
}
