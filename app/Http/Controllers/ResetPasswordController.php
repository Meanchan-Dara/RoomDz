<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ResetPasswordController extends Controller
{
    // Forgot Password
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            ['token' => $token, 'created_at' => now()]
        );

        $frontendUrl = env('FRONTEND_URL', config('app.url'));
        $link = rtrim($frontendUrl, '/') . '/reset-password?token=' . $token . '&email=' . urlencode($request->email);

        try {
            Mail::raw("You requested a password reset. Click the link below to set a new password:\n\n{$link}\n\nThis link will expire in 60 minutes.", function ($message) use ($request) {
                $message->to($request->email)
                        ->subject('Reset Password');
            });
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to send reset email: ' . $e->getMessage()
            ], 500);
        }

        $response = [
            'message' => 'Password reset link sent successfully to your email.'
        ];

        // Only expose token in non-production environments for automated testing
        if (app()->environment('local', 'testing')) {
            $response['token'] = $token;
            $response['reset_link'] = $link;
        }

        return response()->json($response, 200);
    }

    // Reset Password
    public function resetPassword(Request $request, string $token)
    {
        $request->validate([
            'password' => 'required|min:6'
        ]);

        $reset = DB::table('password_reset_tokens')->where('token', $token)->first();

        if (!$reset) {
            return response()->json(['message' => 'Invalid or expired token'], 400);
        }

        // Check if token has expired (60 minutes)
        if (now()->subMinutes(60)->isAfter($reset->created_at)) {
            DB::table('password_reset_tokens')->where('token', $token)->delete();
            return response()->json(['message' => 'Reset token has expired. Please request a new one.'], 400);
        }

        $user = User::where('email', $reset->email)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        DB::table('password_reset_tokens')->where('token', $token)->delete();

        return response()->json(['message' => 'Password successfully reset'], 200);
    }


    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
