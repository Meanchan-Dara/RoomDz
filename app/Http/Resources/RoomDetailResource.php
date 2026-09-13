<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array matching the Room Detail UI.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $detail = $this->detail;

        $formatUrl = function ($url) {
            if (empty($url) || !is_string($url)) return $url;
            if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                return $url;
            }
            return url(ltrim($url, '/'));
        };

        $rawImages = $detail?->images ?? ($this->image ? [$this->image] : []);
        $images = collect($rawImages)->map($formatUrl)->values()->all();

        $facilities = collect($detail?->facilities ?? [])->map(function ($item) {
            if (is_array($item)) {
                return $item['name'] ?? $item['title'] ?? reset($item);
            }
            return (string) $item;
        })->values()->all();

        $houseRules = collect($detail?->house_rules ?? [])->map(function ($item) {
            if (is_array($item)) {
                return $item['rule'] ?? $item['title'] ?? reset($item);
            }
            return (string) $item;
        })->values()->all();

        $size = $detail?->size ?? '24 sqm';
        $floor = $detail?->floor ?? '3rd Floor';
        $deposit = $detail?->deposit ?? '1 Month';
        $latitude = $detail?->latitude ? (float) $detail->latitude : null;
        $longitude = $detail?->longitude ? (float) $detail->longitude : null;

        // Check if the authenticated user has favorited this room
        $isFavorite = false;
        $user = $request->user('sanctum');
        if ($user) {
            $isFavorite = $this->favorites()->where('user_id', $user->id)->exists();
        }

        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'user_id' => $this->user_id,
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
                'image' => $formatUrl($this->category->image),
            ] : null,
            'landlord' => $this->relationLoaded('user') && $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
                'avatar' => $formatUrl($this->user->avatar),
            ] : null,
            'name' => $this->name,
            'status' => $this->status ?? 'AVAILABLE NOW',
            'price' => (float) $this->price,
            'price_period' => $this->price_period,
            'rating' => (float) $this->rating,
            'reviews_count' => (int) $this->reviews_count,
            'address' => $this->address,
            'about' => $detail?->description ?? '',
            'description' => $detail?->description ?? '',
            'image' => $formatUrl($this->image),
            'images' => $images,
            'images_count' => count($images),
            'facilities' => $facilities,
            'room_information' => [
                'type' => $this->type ?? 'Private Room',
                'size' => $size,
                'floor' => $floor,
                'deposit' => $deposit,
            ],
            'house_rules' => $houseRules,
            'location' => [
                'address' => $this->address,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ],
            'is_favorite' => $isFavorite,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
