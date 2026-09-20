<?php

namespace App\Http\Controllers;

use App\Http\Resources\RoomDetailResource;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use App\Models\RoomDetail;
use App\Models\ViewingRequest;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RoomController extends Controller
{
    /**
     * Upload an image file to Cloudinary, with fallback to public disk URL.
     *
     * @param UploadedFile $file
     * @param string $folder
     * @return string
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
     * Display a listing of rooms.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Room::with([
            'category',
            'user',
            'detail',
            'payments' => fn($q) => $q->where('status', 'completed')->where('payment_type', 'booking_deposit')->latest(),
        ]);

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

        if ($request->filled('category')) {
            $category = $request->input('category');
            $query->whereHas('category', function ($q) use ($category) {
                $q->where('name', 'ilike', "%{$category}%")
                    ->orWhere('slug', $category);
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
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
     * Store a newly created room and its detail.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'nullable|integer|exists:categories,id',
            'user_id' => 'nullable|integer|exists:users,id',
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

        $parseJsonField = function ($val, $default = []) {
            if (is_array($val)) return $val;
            if (is_string($val)) {
                $decoded = json_decode($val, true);
                return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
            }
            return $default;
        };

        // Handle main image upload to Cloudinary or URL
        $mainImageUrl = null;
        if ($request->hasFile('image')) {
            $mainImageUrl = $this->uploadImageFile($request->file('image'), 'rooms');
        } elseif (!empty($validated['image']) && is_string($validated['image'])) {
            $mainImageUrl = $validated['image'];
        }

        // Handle gallery images upload to Cloudinary or URLs
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

        $userId = auth('api')->user()?->id ?? $request->user()?->id ?? ($validated['user_id'] ?? null);
        $totalUnits = isset($validated['total_units']) ? (int) $validated['total_units'] : 1;
        $availableUnits = isset($validated['available_units']) ? (int) $validated['available_units'] : $totalUnits;

        $room = DB::transaction(function () use ($validated, $userId, $mainImageUrl, $galleryUrls, $parseJsonField, $totalUnits, $availableUnits) {
            $room = Room::create([
                'category_id' => $validated['category_id'] ?? null,
                'user_id' => $userId,
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
     * Display the specified room with all details.
     */
    public function show(string $id): RoomDetailResource
    {
        $room = Room::with([
            'detail',
            'category',
            'user',
            'payments' => fn($q) => $q->where('status', 'completed')->where('payment_type', 'booking_deposit')->latest(),
        ])->findOrFail($id);

        return new RoomDetailResource($room);
    }

    /**
     * Update the specified room and its details.
     */
    public function update(Request $request, string $id): RoomDetailResource
    {
        $room = Room::with(['detail', 'category', 'user'])->findOrFail($id);

        $validated = $request->validate([
            'category_id' => 'nullable|integer|exists:categories,id',
            'user_id' => 'nullable|integer|exists:users,id',
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
                'user_id' => array_key_exists('user_id', $validated) ? $validated['user_id'] : $room->user_id,
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
            ], fn($val) => !is_null($val)));

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
            ], fn($val) => !is_null($val));

            if (!empty($detailData)) {
                $room->detail()->updateOrCreate(
                    ['room_id' => $room->id],
                    $detailData
                );
            }
        });

        $room->load(['detail', 'category', 'user']);

        return new RoomDetailResource($room);
    }

    /**
     * Remove the specified room.
     */
    public function destroy(string $id): JsonResponse
    {
        $room = Room::findOrFail($id);
        $room->delete();

        return response()->json([
            'message' => 'Room deleted successfully',
        ]);
    }

    /**
     * Handle "Request Viewing" action from the bottom bar of Room Detail screen.
     */
    public function requestViewing(Request $request, string $id): JsonResponse
    {
        $room = Room::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'nullable|email|max:255',
            'preferred_date' => 'nullable|date',
            'preferred_time' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
        ]);

        $viewingRequest = ViewingRequest::create([
            'room_id' => $room->id,
            'user_id' => auth('api')->user()?->id ?? $request->user()?->id,
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'preferred_date' => $validated['preferred_date'] ?? null,
            'preferred_time' => $validated['preferred_time'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Viewing request submitted successfully',
            'data' => $viewingRequest,
        ], 201);
    }
}
