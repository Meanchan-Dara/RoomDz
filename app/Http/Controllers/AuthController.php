<?php

namespace App\Http\Controllers;

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use App\Models\User;
use App\Models\role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * Upload an image file to Cloudinary, with fallback to public disk URL.
     */
    private function uploadImageFile(UploadedFile $file, string $folder = 'avatars'): string
    {
        try {
            if (env('CLOUDINARY_URL')) {
                return Cloudinary::upload($file->getRealPath(), [
                    'folder' => $folder,
                ])->getSecurePath();
            }
        } catch (\Throwable $e) {
            Log::warning('Cloudinary upload error: ' . $e->getMessage());
        }

        $path = $file->store($folder, 'public');
        return url('storage/' . $path);
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:6'],
            'role' => ['nullable', 'string', 'in:admin,owner,customer'],
            'role_id' => ['nullable', 'exists:roles,id'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $roleId = $validated['role_id'] ?? null;
        if (!$roleId) {
            $roleName = $validated['role'] ?? 'customer';
            $roleObj = role::where('name', $roleName)->first();
            $roleId = $roleObj?->id;
        }

        $user = User::create([
            'role_id' => $roleId,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
        ]);

        $user->load('role');

        $token = auth('api')->login($user);

        return response()->json([
            'message' => 'User registered successfully',
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => $user,
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (!$token = auth('api')->attempt($credentials)) {
            return response()->json([
                'message' => 'Invalid email or password',
            ], 401);
        }

        /** @var User $user */
        $user = auth('api')->user();
        $user->load('role');

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => $user,
        ], 200);
    }

    public function logout(Request $request)
    {
        try {
            auth('api')->logout();
        } catch (\Throwable $e) {
            // In case token is already invalid/expired
        }

        return response()->json([
            'message' => 'Logged out successfully',
        ], 200);
    }

    /**
     * Refresh an authenticated JWT token.
     */
    public function refresh(): JsonResponse
    {
        try {
            $token = auth('api')->refresh();
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Token could not be refreshed',
                'error' => $e->getMessage(),
            ], 401);
        }

        /** @var User $user */
        $user = auth('api')->user();
        if ($user) {
            $user->load('role');
        }

        return response()->json([
            'message' => 'Token refreshed successfully',
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => $user,
        ], 200);
    }

    /**
     * Get the authenticated user's profile with stats counters.
     */
    public function profile(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = auth('api')->user() ?? $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        $user->load('role');

        $formatUrl = function ($url) {
            if (empty($url) || !is_string($url)) return $url;
            if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                return $url;
            }
            return url(ltrim($url, '/'));
        };

        $isOwner = $user->role?->name === 'owner';
        $savedCount = $user->favorites()->count();

        if ($isOwner) {
            $ownerRooms = $user->rooms()->get();
            $ownerRoomIds = $ownerRooms->pluck('id');

            $rentingCount = $ownerRooms->count();

            $contactedCount = \App\Models\ViewingRequest::whereIn('room_id', $ownerRoomIds)->count();
        } else {
            $rentingCount = $user->viewingRequests()->where('status', 'confirmed')->count();
            $contactedCount = $user->viewingRequests()->count();
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $formatUrl($user->avatar),
                'google_id' => $user->google_id,
                'is_verified' => (bool) $user->is_verified,
                'location_tag' => $user->location_tag,
                'telegram' => $user->telegram,
                'bakong_account_id' => $user->bakong_account_id,
                'bakong_merchant_name' => $user->bakong_merchant_name,
                'email_verified_at' => $user->email_verified_at?->toISOString(),
                'role' => $user->role ? [
                    'id' => $user->role->id,
                    'name' => $user->role->name,
                ] : null,
                'created_at' => $user->created_at?->toISOString(),
                'updated_at' => $user->updated_at?->toISOString(),
            ],
            'stats' => [
                'saved_count' => $savedCount,
                'favorites_count' => $savedCount,
                'renting_count' => $rentingCount,
                'contacted_count' => $contactedCount,
                'inquiries_count' => $contactedCount,
                'rooms_count' => $user->rooms()->count(),
            ],
        ], 200);
    }

    /**
     * Update the authenticated user's profile (name, phone, email, avatar).
     */
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = auth('api')->user() ?? $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'unique:users,email,' . $user->id],
            'phone' => ['nullable', 'string', 'max:50'],
            'location_tag' => ['nullable', 'string', 'max:255'],
            'telegram' => ['nullable', 'string', 'max:100'],
            'bakong_account_id' => ['nullable', 'string', 'max:100'],
            'bakong_merchant_name' => ['nullable', 'string', 'max:50'],
            'avatar' => ['nullable'],
            'password' => ['nullable', 'min:6'],
        ]);

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            $validated['avatar'] = $this->uploadImageFile($request->file('avatar'), 'avatars');
        } elseif (array_key_exists('avatar', $validated) && is_string($validated['avatar'])) {
            // Accept a URL string directly
        } else {
            unset($validated['avatar']);
        }

        // Handle password change
        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);
        $user->load('role');

        $formatUrl = function ($url) {
            if (empty($url) || !is_string($url)) return $url;
            if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                return $url;
            }
            return url(ltrim($url, '/'));
        };

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $formatUrl($user->avatar),
                'google_id' => $user->google_id,
                'is_verified' => (bool) $user->is_verified,
                'location_tag' => $user->location_tag,
                'telegram' => $user->telegram,
                'bakong_account_id' => $user->bakong_account_id,
                'bakong_merchant_name' => $user->bakong_merchant_name,
                'email_verified_at' => $user->email_verified_at?->toISOString(),
                'role' => $user->role ? [
                    'id' => $user->role->id,
                    'name' => $user->role->name,
                ] : null,
                'created_at' => $user->created_at?->toISOString(),
                'updated_at' => $user->updated_at?->toISOString(),
            ],
        ], 200);
    }

    /**
     * Change the authenticated user's password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        /** @var User|null $user */
        $user = auth('api')->user() ?? $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        return response()->json([
            'message' => 'Password changed successfully',
        ], 200);
    }
}
