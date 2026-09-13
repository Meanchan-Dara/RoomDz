<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\OtpCodeController;
use App\Http\Controllers\OwnerDashboardController;
use App\Http\Controllers\OwnerRoomController;
use App\Http\Controllers\OwnerViewingRequestController;
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
| API Routesh
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

// Owner Protected Routes (Owner & Admin)
Route::middleware(['auth:sanctum', 'role:owner,admin'])->prefix('owner')->group(function () {
    // Dashboard Stats
    Route::get('/dashboard', [OwnerDashboardController::class, 'index']);

    // Owner Room Management (CRUD on own rooms)
    Route::get('/rooms', [OwnerRoomController::class, 'index']);
    Route::post('/rooms', [OwnerRoomController::class, 'store']);
    Route::get('/rooms/{id}', [OwnerRoomController::class, 'show']);
    Route::put('/rooms/{id}', [OwnerRoomController::class, 'update']);
    Route::post('/rooms/{id}', [OwnerRoomController::class, 'update']); // for multipart/form-data
    Route::delete('/rooms/{id}', [OwnerRoomController::class, 'destroy']);
    Route::post('/rooms/{id}/rent-out', [OwnerRoomController::class, 'rentOut']);
    Route::post('/rooms/{id}/release-unit', [OwnerRoomController::class, 'releaseUnit']);

    // Owner Viewing Request Management (Viewing requests on own rooms)
    Route::get('/viewing-requests', [OwnerViewingRequestController::class, 'index']);
    Route::get('/viewing-requests/{id}', [OwnerViewingRequestController::class, 'show']);
    Route::put('/viewing-requests/{id}/confirm', [OwnerViewingRequestController::class, 'confirm']);
    Route::put('/viewing-requests/{id}/reject', [OwnerViewingRequestController::class, 'reject']);
});


