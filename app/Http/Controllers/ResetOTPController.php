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
    try {

        $request->validate([
            'email' => 'required|email'
        ]);

        // check existing otp
        $otpRecord = OtpCode::where('email', $request->email)->first();

        if (!$otpRecord) {
            return response()->json([
                'message' => 'OTP not found. Please request a new OTP.'
            ],404);
        }

        // check if OTP still valid
        if (now()->isBefore($otpRecord->expire_at)) {
            return response()->json([
                'message' => 'OTP is still valid.'
            ],400);
        }

        // generate new otp
        $otp = rand(100000,999999);

        $otpRecord->update([
            'code' => $otp,
            'expire_at' => now()->addMinutes(10)
        ]);

        Mail::to($request->email)->send(new OtpMail($otp));

        return response()->json([
            'message' => 'New OTP generated and sent successfully'
        ]);

    } catch (\Throwable $th) {

        return response()->json([
            'error' => $th->getMessage()
        ],500);

    }
}
}
