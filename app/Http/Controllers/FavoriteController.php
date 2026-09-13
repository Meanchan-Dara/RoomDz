<?php

namespace App\Http\Controllers;

use App\Http\Resources\RoomResource;
use App\Models\Favorite;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FavoriteController extends Controller
{
    /**
     * Toggle favorite status for a room.
     * If already favorited → remove. If not → add.
     */
    public function toggle(Request $request, string $roomId): JsonResponse
    {
        $user = $request->user();
        $room = Room::findOrFail($roomId);

        $existing = Favorite::where('user_id', $user->id)
            ->where('room_id', $room->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return response()->json([
                'message' => 'Room removed from favorites',
                'is_favorite' => false,
                'favorites_count' => $user->favorites()->count(),
            ]);
        }

        Favorite::create([
            'user_id' => $user->id,
            'room_id' => $room->id,
        ]);

        return response()->json([
            'message' => 'Room added to favorites',
            'is_favorite' => true,
            'favorites_count' => $user->favorites()->count(),
        ], 201);
    }

    /**
     * List all rooms favorited by the authenticated user.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = $user->favoriteRooms()->with(['category', 'user']);

        $sort = $request->input('sort', 'favorites.created_at');
        $order = $request->input('order', 'desc');
        $query->orderBy($sort, $order);

        $perPage = (int) $request->input('per_page', 15);
        $rooms = $query->paginate($perPage);

        return RoomResource::collection($rooms);
    }

    /**
     * Check if a specific room is favorited by the authenticated user.
     */
    public function check(Request $request, string $roomId): JsonResponse
    {
        $user = $request->user();

        $isFavorite = Favorite::where('user_id', $user->id)
            ->where('room_id', $roomId)
            ->exists();

        return response()->json([
            'is_favorite' => $isFavorite,
        ]);
    }
}
