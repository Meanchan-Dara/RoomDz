<?php

namespace App\Http\Controllers;

use App\Mail\OtpMail;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class OtpCodeController extends Controller
{
    public function loginWithOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required|digits:6'
        ]);

        $data = OtpCode::where('email', $request->email)
            ->where('code', $request->otp)
            ->first();

        if (!$data || now()->isAfter($data->expire_at)) {
            return response()->json([
                'message' => 'Invalid or expired OTP code.'
            ], 400);
        }

        $data->delete();

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found.'
            ], 404);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user
        ]);
    }
    
    public function sendOtp(Request $request)
    {
        // Validate the incoming email address
        $request->validate(['email' => 'required|email']);

        // Generate a 6-digit random code and set expiration (10 minutes)
        $otp = rand(100000, 999999);
        $expire_at = now()->addMinutes(10);

        // Store or update the OTP in the database
        OtpCode::updateOrCreate(
            ['email' => $request->email],
            ['code' => $otp, 'expire_at' => $expire_at]
        );

        // Dispatch the email using the SMTP configuration
        try {
            Mail::to($request->email)->send(new OtpMail($otp));
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to send OTP email: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'message' => 'OTP has been sent successfully to ' . $request->email
        ]);
    }

    /**
     * Verify the provided OTP.
     */
    public function verifyOtp(Request $request)
    {
        // Validate input format
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required|digits:6'
        ]);

        // Find the record matching email and code
        $data = OtpCode::where('email', $request->email)
            ->where('code', $request->otp)
            ->first();

        // Check if code exists and is not expired
        if (!$data || now()->isAfter($data->expire_at)) {
            return response()->json([
                'message' => 'Invalid or expired OTP code.'
            ], 400);
        }

        // Return success, but DO NOT delete the code here.
        // It will be deleted during the actual login step in loginWithOtp.
        
        return response()->json([
            'message' => 'OTP verification successful!'
        ]);
    }
}
