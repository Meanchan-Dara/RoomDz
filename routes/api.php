<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\OtpCodeController;
use App\Http\Controllers\ResetOTPController;
use App\Http\Controllers\ResetPasswordController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
Route::get('/health', function () {
    return response()->json([
        'message' => 'OK',
    ]);
});
// Public Routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Auth with OTP
Route::post('/send_otp', [OtpCodeController::class, 'sendOtp']);
Route::post('/verify_otp', [OtpCodeController::class, 'verifyOtp']);
Route::post('/loginWithOtp', [OtpCodeController::class, 'loginWithOtp']);
Route::post('/reset_otp', [ResetOTPController::class, 'resetOtp']);

// Reset password with link
Route::post('/forgot-password', [ResetPasswordController::class, 'forgotPassword']);
Route::post('/reset-password/{token}', [ResetPasswordController::class, 'resetPassword']);

// Auth with Google
Route::get('/auth/google', [GoogleController::class, 'redirect']);
Route::get('/auth/google/callback', [GoogleController::class, 'callback']);

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

// Rooms & Room Details
Route::get('/room', [\App\Http\Controllers\RoomController::class, 'index']);
Route::get('/rooms', [\App\Http\Controllers\RoomController::class, 'index']);
Route::get('/room/{id}', [\App\Http\Controllers\RoomController::class, 'show']);
Route::get('/rooms/{id}', [\App\Http\Controllers\RoomController::class, 'show']);
Route::post('/room', [\App\Http\Controllers\RoomController::class, 'store']);
Route::post('/rooms', [\App\Http\Controllers\RoomController::class, 'store']);
Route::put('/room/{id}', [\App\Http\Controllers\RoomController::class, 'update']);
Route::put('/rooms/{id}', [\App\Http\Controllers\RoomController::class, 'update']);
Route::delete('/room/{id}', [\App\Http\Controllers\RoomController::class, 'destroy']);
Route::delete('/rooms/{id}', [\App\Http\Controllers\RoomController::class, 'destroy']);

// Room Detail & Viewing Request
Route::get('/room-detail/{id}', [\App\Http\Controllers\RoomDetailController::class, 'show']);
Route::get('/room-details/{id}', [\App\Http\Controllers\RoomDetailController::class, 'show']);
Route::put('/room-detail/{id}', [\App\Http\Controllers\RoomDetailController::class, 'update']);
Route::put('/room-details/{id}', [\App\Http\Controllers\RoomDetailController::class, 'update']);
Route::post('/room/{id}/request-viewing', [\App\Http\Controllers\RoomController::class, 'requestViewing']);
Route::post('/rooms/{id}/request-viewing', [\App\Http\Controllers\RoomController::class, 'requestViewing']);

// Categories
Route::get('/category', [\App\Http\Controllers\CategoryController::class, 'index']);
Route::get('/categories', [\App\Http\Controllers\CategoryController::class, 'index']);
Route::get('/category/{id}', [\App\Http\Controllers\CategoryController::class, 'show']);
Route::get('/categories/{id}', [\App\Http\Controllers\CategoryController::class, 'show']);
Route::post('/category', [\App\Http\Controllers\CategoryController::class, 'store']);
Route::post('/categories', [\App\Http\Controllers\CategoryController::class, 'store']);
Route::put('/category/{id}', [\App\Http\Controllers\CategoryController::class, 'update']);
Route::put('/categories/{id}', [\App\Http\Controllers\CategoryController::class, 'update']);
Route::delete('/category/{id}', [\App\Http\Controllers\CategoryController::class, 'destroy']);
Route::delete('/categories/{id}', [\App\Http\Controllers\CategoryController::class, 'destroy']);

