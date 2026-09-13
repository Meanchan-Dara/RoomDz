<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\OtpCodeController;
use App\Http\Controllers\ResetOTPController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomDetailController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ViewingRequestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
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
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/profile', [AuthController::class, 'updateProfile']); // For multipart/form-data (avatar upload)
    Route::put('/change-password', [AuthController::class, 'changePassword']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Favorites
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites/toggle/{roomId}', [FavoriteController::class, 'toggle']);
    Route::get('/favorites/check/{roomId}', [FavoriteController::class, 'check']);

    // My Viewing Requests (Appointments)
    Route::get('/my-viewing-requests', [ViewingRequestController::class, 'myRequests']);
    Route::get('/my-viewing-requests/{id}', [ViewingRequestController::class, 'show']);
    Route::put('/my-viewing-requests/{id}/cancel', [ViewingRequestController::class, 'cancel']);
});

// Room
Route::get('/room', [RoomController::class, 'index']);
Route::get('/room/{id}', [RoomController::class, 'show']);
Route::post('/room', [RoomController::class, 'store']);
Route::put('/room/{id}', [RoomController::class, 'update']);
Route::delete('/room/{id}', [RoomController::class, 'destroy']);
Route::post('/room/{id}/request-viewing', [RoomController::class, 'requestViewing']);

// Room Detail
Route::get('/room-details/{id}', [RoomDetailController::class, 'show']);
Route::put('/room-details/{id}', [RoomDetailController::class, 'update']);
Route::get('/room-detail/{id}', [RoomDetailController::class, 'show']);
Route::put('/room-detail/{id}', [RoomDetailController::class, 'update']);

// Category
Route::get('/category', [CategoryController::class, 'index']);
Route::get('/category/{id}', [CategoryController::class, 'show']);
Route::post('/category', [CategoryController::class, 'store']);
Route::put('/category/{id}', [CategoryController::class, 'update']);
Route::delete('/category/{id}', [CategoryController::class, 'destroy']);

// Roles
Route::get('/roles', [RoleController::class, 'index']);


