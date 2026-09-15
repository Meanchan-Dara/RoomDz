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

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'token' => $token,
            'user' => $user,
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Invalid email or password',
            ], 401);
        }

        /** @var User $user */
        $user = Auth::user();
        $user->load('role');
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user,
        ], 200);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $token = $user->currentAccessToken();
            if ($token && method_exists($token, 'delete')) {
                $token->delete();
            }
        }

        return response()->json([
            'message' => 'Logged out successfully',
        ], 200);
    }

    /**
     * Get the authenticated user's profile with stats counters.
     */
    public function profile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->load('role');

        $formatUrl = function ($url) {
            if (empty($url) || !is_string($url)) return $url;
            if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                return $url;
            }
            return url(ltrim($url, '/'));
        };

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
                'saved_count' => $user->favorites()->count(),
                'inquiries_count' => $user->viewingRequests()->count(),
                'rooms_count' => $user->rooms()->count(),
            ],
        ], 200);
    }

    /**
     * Update the authenticated user's profile (name, phone, email, avatar).
     */
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

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

        /** @var User $user */
        $user = $request->user();

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
