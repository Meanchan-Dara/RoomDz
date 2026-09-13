<?php

namespace App\Http\Controllers;

use App\Http\Resources\RoomDetailResource;
use App\Models\Room;
use App\Models\RoomDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomDetailController extends Controller
{
    /**
     * Display the specified room detail by room_id or room_detail_id.
     */
    public function show(string $id): RoomDetailResource
    {
        // Try finding by room_id first, or by room_details.id
        $room = Room::with(['detail', 'user', 'category'])
            ->where('id', $id)
            ->orWhereHas('detail', function ($query) use ($id) {
                $query->where('id', $id);
            })
            ->firstOrFail();

        return new RoomDetailResource($room);
    }

    /**
     * Update room details directly.
     */
    public function update(Request $request, string $id): RoomDetailResource
    {
        $room = Room::with('detail')->where('id', $id)->first();

        if (!$room) {
            $detail = RoomDetail::findOrFail($id);
            $room = $detail->room()->with('detail')->firstOrFail();
        }

        $validated = $request->validate([
            'description' => 'nullable|string',
            'size' => 'nullable|string|max:50',
            'floor' => 'nullable|string|max:50',
            'deposit' => 'nullable|string|max:50',
            'images' => 'nullable|array',
            'images.*' => 'string',
            'facilities' => 'nullable|array',
            'house_rules' => 'nullable|array',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $room->detail()->updateOrCreate(
            ['room_id' => $room->id],
            $validated
        );

        $room->load(['detail', 'user', 'category']);

        return new RoomDetailResource($room);
    }
}
