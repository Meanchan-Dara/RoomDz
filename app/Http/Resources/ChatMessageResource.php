<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'content' => $this->content,
            'rooms' => $this->room_results ? $this->loadRooms() : [],
            'rooms_count' => $this->room_results ? count($this->room_results) : 0,
            'suggestions' => $this->metadata['suggestions'] ?? [],
            'intent' => $this->metadata['intent'] ?? 'general',
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * Load room data for the matched room IDs.
     */
    private function loadRooms(): array
    {
        if (empty($this->room_results)) {
            return [];
        }

        $rooms = \App\Models\Room::with(['category', 'user'])
            ->whereIn('id', $this->room_results)
            ->get();

        $formatUrl = function ($url) {
            if (empty($url) || !is_string($url)) return $url;
            if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                return $url;
            }
            return url(ltrim($url, '/'));
        };

        return $rooms->map(function ($room) use ($formatUrl) {
            return [
                'id' => $room->id,
                'name' => $room->name,
                'type' => $room->type,
                'price' => (float) $room->price,
                'price_period' => $room->price_period,
                'status' => $room->status,
                'rating' => (float) $room->rating,
                'reviews_count' => (int) $room->reviews_count,
                'address' => $room->address,
                'image' => $formatUrl($room->image),
                'is_negotiable' => (bool) $room->is_negotiable,
                'listing_type' => $room->listing_type ?? 'standard',
                'available_units' => (int) ($room->available_units ?? 1),
                'is_available' => (bool) (($room->available_units ?? 1) > 0),
                'category' => $room->category ? [
                    'id' => $room->category->id,
                    'name' => $room->category->name,
                ] : null,
                // Deep link for mobile app navigation (1-click to room detail)
                'deep_link' => "roomdz://room/{$room->id}",
                'api_url' => url("/api/room/{$room->id}"),
                'action' => [
                    'type' => 'navigate_to_room',
                    'room_id' => $room->id,
                    'screen' => 'RoomDetail',
                ],
            ];
        })->toArray();
    }
}
