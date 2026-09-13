<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class RoomResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $formatUrl = function ($url) {
            if (empty($url) || !is_string($url)) return $url;
            if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                return $url;
            }
            return url(ltrim($url, '/'));
        };

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
                'is_verified' => (bool) $this->user->is_verified,
                'location_tag' => $this->user->location_tag,
                'telegram' => $this->user->telegram,
            ] : null,
            'name' => $this->name,
            'type' => $this->type,
            'price' => (float) $this->price,
            'price_period' => $this->price_period,
            'is_negotiable' => (bool) $this->is_negotiable,
            'is_featured' => (bool) $this->is_featured,
            'status' => $this->status,
            'rating' => (float) $this->rating,
            'reviews_count' => (int) $this->reviews_count,
            'address' => $this->address,
            'image' => $formatUrl($this->image),
            'is_favorite' => $isFavorite,
            'latitude' => !is_null($this->latitude) ? (float) $this->latitude : ($this->relationLoaded('detail') && $this->detail ? (float) $this->detail->latitude : null),
            'longitude' => !is_null($this->longitude) ? (float) $this->longitude : ($this->relationLoaded('detail') && $this->detail ? (float) $this->detail->longitude : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

