<?php

namespace App\Http\Controllers;

use App\Http\Resources\RoomDetailResource;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OwnerRoomController extends Controller
{
    /**
     * Upload an image file to Cloudinary, with fallback to public disk URL.
     */
    private function uploadImageFile(UploadedFile $file, string $folder = 'rooms'): string
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

    /**
     * List all rooms owned by the authenticated owner.
     * Supports search, filter by category/status, and pagination.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Room::with(['category', 'user', 'detail'])
            ->where('user_id', $request->user()->id);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('address', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->input('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->input('max_price'));
        }

        $sort = $request->input('sort', 'created_at');
        $order = $request->input('order', 'desc');
        $query->orderBy($sort, $order);

        $perPage = (int) $request->input('per_page', 15);
        $rooms = $query->paginate($perPage);

        return RoomResource::collection($rooms);
    }

    /**
     * Create a new room for the authenticated owner.
     * Automatically sets user_id to the owner's ID.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'nullable|integer|exists:categories,id',
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:100',
            'price' => 'required|numeric|min:0',
            'price_period' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:50',
            'total_units' => 'nullable|integer|min:1',
            'available_units' => 'nullable|integer|min:0',
            'is_negotiable' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'listing_type' => 'nullable|string|in:standard,featured,premium',
            'rating' => 'nullable|numeric|between:0,5',
            'reviews_count' => 'nullable|integer|min:0',
            'address' => 'required|string|max:255',
            'image' => 'nullable',

            // Detail fields
            'description' => 'nullable|string',
            'size' => 'nullable|string|max:50',
            'floor' => 'nullable|string|max:50',
            'deposit' => 'nullable|string|max:50',
            'images' => 'nullable',
            'facilities' => 'nullable',
            'house_rules' => 'nullable',
            'utilities' => 'nullable',
            'rental_terms' => 'nullable',
            'rules_permissions' => 'nullable',
            'required_documents' => 'nullable',
            'payment_methods' => 'nullable',
            'payment_cycle' => 'nullable|string|max:255',
            'contact_info' => 'nullable',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        // Helper to parse array/json fields if sent as JSON strings from Flutter
        $parseJsonField = function ($val, $default = []) {
            if (is_array($val)) return $val;
            if (is_string($val)) {
                $decoded = json_decode($val, true);
                return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
            }
            return $default;
        };

        // Handle main image upload
        $mainImageUrl = null;
        if ($request->hasFile('image')) {
            $mainImageUrl = $this->uploadImageFile($request->file('image'), 'rooms');
        } elseif (!empty($validated['image']) && is_string($validated['image'])) {
            $mainImageUrl = $validated['image'];
        }

        // Handle gallery images
        $galleryUrls = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                if ($file && $file->isValid()) {
                    $galleryUrls[] = $this->uploadImageFile($file, 'rooms');
                }
            }
        } elseif (!empty($validated['images'])) {
            $galleryUrls = $parseJsonField($validated['images']);
        }

        if (empty($mainImageUrl) && !empty($galleryUrls)) {
            $mainImageUrl = $galleryUrls[0];
        }

        if (!empty($mainImageUrl) && empty($galleryUrls)) {
            $galleryUrls = [$mainImageUrl];
        }

        $totalUnits = isset($validated['total_units']) ? (int) $validated['total_units'] : 1;
        $availableUnits = isset($validated['available_units']) ? (int) $validated['available_units'] : $totalUnits;

        $room = DB::transaction(function () use ($request, $validated, $mainImageUrl, $galleryUrls, $parseJsonField, $totalUnits, $availableUnits) {
            $room = Room::create([
                'category_id' => $validated['category_id'] ?? null,
                'user_id' => $request->user()->id, // Always set to the authenticated owner
                'name' => $validated['name'],
                'type' => $validated['type'] ?? 'Private Room',
                'price' => $validated['price'],
                'price_period' => $validated['price_period'] ?? 'month',
                'status' => $validated['status'] ?? ($availableUnits > 0 ? 'AVAILABLE NOW' : 'OCCUPIED'),
                'total_units' => $totalUnits,
                'available_units' => $availableUnits,
                'is_negotiable' => filter_var($validated['is_negotiable'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'is_featured' => filter_var($validated['is_featured'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'listing_type' => $validated['listing_type'] ?? 'standard',
                'rating' => $validated['rating'] ?? 5.0,
                'reviews_count' => $validated['reviews_count'] ?? 0,
                'address' => $validated['address'],
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'image' => $mainImageUrl,
            ]);

            $room->detail()->create([
                'description' => $validated['description'] ?? null,
                'size' => $validated['size'] ?? null,
                'floor' => $validated['floor'] ?? null,
                'deposit' => $validated['deposit'] ?? null,
                'images' => $galleryUrls,
                'facilities' => isset($validated['facilities']) ? $parseJsonField($validated['facilities']) : [],
                'house_rules' => isset($validated['house_rules']) ? $parseJsonField($validated['house_rules']) : [],
                'utilities' => isset($validated['utilities']) ? $parseJsonField($validated['utilities']) : null,
                'rental_terms' => isset($validated['rental_terms']) ? $parseJsonField($validated['rental_terms']) : null,
                'rules_permissions' => isset($validated['rules_permissions']) ? $parseJsonField($validated['rules_permissions']) : null,
                'required_documents' => isset($validated['required_documents']) ? $parseJsonField($validated['required_documents']) : null,
                'payment_methods' => isset($validated['payment_methods']) ? $parseJsonField($validated['payment_methods']) : null,
                'payment_cycle' => $validated['payment_cycle'] ?? null,
                'contact_info' => isset($validated['contact_info']) ? $parseJsonField($validated['contact_info']) : null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
            ]);

            return $room;
        });

        $room->load(['detail', 'category', 'user']);

        return (new RoomDetailResource($room))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a specific room owned by the authenticated owner.
     */
    public function show(Request $request, string $id): RoomDetailResource
    {
        $room = Room::with(['detail', 'category', 'user'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return new RoomDetailResource($room);
    }

    /**
     * Update a room owned by the authenticated owner.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $room = Room::with(['detail', 'category', 'user'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'category_id' => 'nullable|integer|exists:categories,id',
            'name' => 'sometimes|required|string|max:255',
            'type' => 'nullable|string|max:100',
            'price' => 'sometimes|required|numeric|min:0',
            'price_period' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:50',
            'total_units' => 'nullable|integer|min:1',
            'available_units' => 'nullable|integer|min:0',
            'is_negotiable' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'listing_type' => 'nullable|string|in:standard,featured,premium',
            'rating' => 'nullable|numeric|between:0,5',
            'reviews_count' => 'nullable|integer|min:0',
            'address' => 'sometimes|required|string|max:255',
            'image' => 'nullable',

            // Detail fields
            'description' => 'nullable|string',
            'size' => 'nullable|string|max:50',
            'floor' => 'nullable|string|max:50',
            'deposit' => 'nullable|string|max:50',
            'images' => 'nullable',
            'facilities' => 'nullable',
            'house_rules' => 'nullable',
            'utilities' => 'nullable',
            'rental_terms' => 'nullable',
            'rules_permissions' => 'nullable',
            'required_documents' => 'nullable',
            'payment_methods' => 'nullable',
            'payment_cycle' => 'nullable|string|max:255',
            'contact_info' => 'nullable',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $parseJsonField = function ($val, $default = null) {
            if (is_null($val)) return $default;
            if (is_array($val)) return $val;
            if (is_string($val)) {
                $decoded = json_decode($val, true);
                return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
            }
            return $default;
        };

        $mainImageUrl = $room->image;
        if ($request->hasFile('image')) {
            $mainImageUrl = $this->uploadImageFile($request->file('image'), 'rooms');
        } elseif (array_key_exists('image', $validated) && is_string($validated['image'])) {
            $mainImageUrl = $validated['image'];
        }

        $galleryUrls = $room->detail?->images ?? [];
        if ($request->hasFile('images')) {
            $uploadedGallery = [];
            foreach ($request->file('images') as $file) {
                if ($file && $file->isValid()) {
                    $uploadedGallery[] = $this->uploadImageFile($file, 'rooms');
                }
            }
            if (!empty($uploadedGallery)) {
                $galleryUrls = $uploadedGallery;
            }
        } elseif (array_key_exists('images', $validated)) {
            $parsed = $parseJsonField($validated['images']);
            if (!empty($parsed)) {
                $galleryUrls = $parsed;
            }
        }

        DB::transaction(function () use ($room, $validated, $mainImageUrl, $galleryUrls, $parseJsonField) {
            $room->update(array_filter([
                'category_id' => array_key_exists('category_id', $validated) ? $validated['category_id'] : $room->category_id,
                'name' => $validated['name'] ?? null,
                'type' => $validated['type'] ?? null,
                'price' => $validated['price'] ?? null,
                'price_period' => $validated['price_period'] ?? null,
                'status' => $validated['status'] ?? null,
                'total_units' => array_key_exists('total_units', $validated) ? (int) $validated['total_units'] : null,
                'available_units' => array_key_exists('available_units', $validated) ? (int) $validated['available_units'] : null,
                'is_negotiable' => array_key_exists('is_negotiable', $validated) ? filter_var($validated['is_negotiable'], FILTER_VALIDATE_BOOLEAN) : null,
                'is_featured' => array_key_exists('is_featured', $validated) ? filter_var($validated['is_featured'], FILTER_VALIDATE_BOOLEAN) : null,
                'listing_type' => $validated['listing_type'] ?? null,
                'rating' => $validated['rating'] ?? null,
                'reviews_count' => $validated['reviews_count'] ?? null,
                'address' => $validated['address'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'image' => $mainImageUrl,
            ], fn ($val) => !is_null($val)));

            $detailData = array_filter([
                'description' => $validated['description'] ?? null,
                'size' => $validated['size'] ?? null,
                'floor' => $validated['floor'] ?? null,
                'deposit' => $validated['deposit'] ?? null,
                'images' => $galleryUrls,
                'facilities' => array_key_exists('facilities', $validated) ? $parseJsonField($validated['facilities']) : null,
                'house_rules' => array_key_exists('house_rules', $validated) ? $parseJsonField($validated['house_rules']) : null,
                'utilities' => array_key_exists('utilities', $validated) ? $parseJsonField($validated['utilities']) : null,
                'rental_terms' => array_key_exists('rental_terms', $validated) ? $parseJsonField($validated['rental_terms']) : null,
                'rules_permissions' => array_key_exists('rules_permissions', $validated) ? $parseJsonField($validated['rules_permissions']) : null,
                'required_documents' => array_key_exists('required_documents', $validated) ? $parseJsonField($validated['required_documents']) : null,
                'payment_methods' => array_key_exists('payment_methods', $validated) ? $parseJsonField($validated['payment_methods']) : null,
                'payment_cycle' => $validated['payment_cycle'] ?? null,
                'contact_info' => array_key_exists('contact_info', $validated) ? $parseJsonField($validated['contact_info']) : null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
            ], fn ($val) => !is_null($val));

            if (!empty($detailData)) {
                $room->detail()->updateOrCreate(
                    ['room_id' => $room->id],
                    $detailData
                );
            }
        });

        $room->load(['detail', 'category', 'user']);

        return response()->json([
            'message' => 'Room updated successfully',
            'data' => new RoomDetailResource($room),
        ]);
    }

    /**
     * Delete a room owned by the authenticated owner.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $room = Room::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $room->delete();

        return response()->json([
            'message' => 'Room deleted successfully',
        ]);
    }

    /**
     * Quick action: Deduct one or more units when rented out.
     */
    public function rentOut(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'units' => 'nullable|integer|min:1',
        ]);

        $unitsToDeduct = $validated['units'] ?? 1;

        $room = Room::where('user_id', $request->user()->id)
            ->findOrFail($id);

        if ($room->available_units < $unitsToDeduct) {
            return response()->json([
                'message' => 'Not enough available units to rent out.',
                'available_units' => (int) $room->available_units,
            ], 422);
        }

        $newAvailable = max(0, $room->available_units - $unitsToDeduct);
        $newStatus = $newAvailable === 0 ? 'OCCUPIED' : $room->status;

        $room->update([
            'available_units' => $newAvailable,
            'status' => $newStatus,
        ]);

        return response()->json([
            'message' => "Successfully rented out {$unitsToDeduct} unit(s).",
            'data' => [
                'id' => $room->id,
                'total_units' => (int) $room->total_units,
                'available_units' => (int) $room->available_units,
                'is_available' => (bool) ($room->available_units > 0),
                'status' => $room->status,
            ],
        ]);
    }

    /**
     * Quick action: Release one or more units when tenant vacates.
     */
    public function releaseUnit(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'units' => 'nullable|integer|min:1',
        ]);

        $unitsToAdd = $validated['units'] ?? 1;

        $room = Room::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $newAvailable = min($room->total_units, $room->available_units + $unitsToAdd);
        $newStatus = $newAvailable > 0 && $room->status === 'OCCUPIED' ? 'AVAILABLE NOW' : $room->status;

        $room->update([
            'available_units' => $newAvailable,
            'status' => $newStatus,
        ]);

        return response()->json([
            'message' => "Successfully released {$unitsToAdd} unit(s).",
            'data' => [
                'id' => $room->id,
                'total_units' => (int) $room->total_units,
                'available_units' => (int) $room->available_units,
                'is_available' => (bool) ($room->available_units > 0),
                'status' => $room->status,
            ],
        ]);
    }
}
