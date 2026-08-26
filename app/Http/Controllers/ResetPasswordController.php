<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Models\User;

class ResetPasswordController extends Controller
{

    // Forgot Password
    public function forgotPassword(Request $request)
    {
        $token = Str::random(60);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            ['token' => $token, 'created_at' => now()]
        );

        $link = url('/reset-password/'.$token);

        Mail::raw("Reset password: ".$link, function($message) use ($request) {
            $message->to($request->email)
                    ->subject('Reset Password');
        });

        return response()->json([
            'message' => 'Email sent',
            'token' => $token,        
            'reset_link' => $link    
        ]);
    }

    // Reset Password
    public function resetPassword(Request $request, $token)
    {
        $reset = DB::table('password_reset_tokens')->where('token', $token)->first();

        if (!$reset) {
            return response()->json(['message' => 'Invalid token'], 400);
        }

        $user = User::where('email', $reset->email)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->password = bcrypt($request->password);
        $user->save();

        DB::table('password_reset_tokens')->where('token', $token)->delete();

        return response()->json(['message' => 'Password successfully reset']);
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
