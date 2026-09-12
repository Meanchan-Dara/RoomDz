<?php
namespace App\Http\Controllers;

use App\Mail\OtpMail;
use App\Models\OtpCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ResetOTPController extends Controller
{
    public function resetOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        $otpRecord = OtpCode::where('email', $request->email)->first();

        // If no previous record, create one
        if (!$otpRecord) {
            $otpRecord = new OtpCode();
            $otpRecord->email = $request->email;
        } else {
            // Check 30 seconds rate-limit between resends (except in testing)
            if (!app()->environment('testing') && $otpRecord->updated_at && now()->diffInSeconds($otpRecord->updated_at) < 30) {
                $secondsLeft = 30 - now()->diffInSeconds($otpRecord->updated_at);
                return response()->json([
                    'message' => "Please wait {$secondsLeft} seconds before requesting a new OTP."
                ], 429);
            }
        }

        $otp = rand(100000, 999999);
        $otpRecord->code = $otp;
        $otpRecord->expire_at = now()->addMinutes(10);
        $otpRecord->save();

        try {
            Mail::to($request->email)->send(new OtpMail($otp));
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to send OTP email: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'message' => 'New OTP generated and sent successfully'
        ], 200);
    }
}
