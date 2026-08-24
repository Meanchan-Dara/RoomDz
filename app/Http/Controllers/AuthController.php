<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            $request->validate([
                "name" => ["required"],
                "email" => ["required", "email", "unique:users,email"],
                "password" => ["required", "min:6"],
            ]);

            $user = new User();
            $user->name = $request["name"];
            $user->email = $request["email"];
            $user->password = Hash::make($request["password"]);
            $user->save();

            return response()->json($user, 201);
        } catch (\Throwable $th) {
            return response()->json([
                "message" => $th->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        try {
            $credentials = $request->validate([
                "email" => ["required"],
                "password" => ["required"],
            ]);

            if (!Auth::attempt($credentials)) {
                return response()->json([
                    "message" => "Invalid Password or Email",
                ], 401);
            }
            $user = Auth::user();
            $token = $user->createToken('device-name')->plainTextToken;

            return response()->json([
                "token" => $token,
                "user" => $user,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                "message" => $th->getMessage()
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                "message" => "Logged out successfully"
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                "message" => $th->getMessage()
            ], 500);
        }
    }

    public function profile(Request $request)
    {
        try {
            return response()->json([
                "user" => $request->user()
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                "message" => $th->getMessage()
            ], 500);
        }
    }
}
