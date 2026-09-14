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
        $latitude = !is_null($this->latitude) ? (float) $this->latitude : ($detail?->latitude ? (float) $detail->latitude : null);
        $longitude = !is_null($this->longitude) ? (float) $this->longitude : ($detail?->longitude ? (float) $detail->longitude : null);

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
            'type' => $this->type ?? 'Private Room',
            'status' => $this->status ?? 'AVAILABLE NOW',
            'total_units' => (int) ($this->total_units ?? 1),
            'available_units' => (int) ($this->available_units ?? 1),
            'is_available' => (bool) (($this->available_units ?? 1) > 0),
            'price' => (float) $this->price,
            'price_period' => $this->price_period,
            'is_negotiable' => (bool) $this->is_negotiable,
            'is_featured' => (bool) $this->is_featured,
            'listing_type' => $this->listing_type ?? 'standard',
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
            'rules_permissions' => $detail?->rules_permissions ?? [
                'cooking_allowed' => true,
                'pet_friendly' => false,
                'no_smoking' => true,
                'guests_allowed' => true,
            ],
            'utilities' => $detail?->utilities ?? [
                'electricity' => '$0.25 / kWh',
                'water' => '$0.50 / m³',
                'trash' => 'Free',
            ],
            'rental_terms' => $detail?->rental_terms ?? [
                'min_contract' => '6 Months',
                'max_occupants' => 2,
                'gate_hours' => '24/7 Free Access',
            ],
            'required_documents' => $detail?->required_documents ?? [],
            'payment_methods' => $detail?->payment_methods ?? ['KHQR / Bakong', 'Cash'],
            'payment_cycle' => $detail?->payment_cycle ?? '1-5 of each month',
            'contact_info' => $detail?->contact_info ? array_merge(
                $detail->contact_info,
                empty($detail->contact_info['telegram']) && $this->relationLoaded('user') && $this->user?->telegram
                    ? ['telegram' => $this->user->telegram]
                    : []
            ) : ($this->relationLoaded('user') && $this->user ? [
                'contact_name' => $this->user->name,
                'phone' => $this->user->phone,
                'telegram' => $this->user->telegram,
                'preferred_contact' => 'Telegram',
            ] : null),
            'location' => [
                'address' => $this->address,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ],
            'latitude' => $latitude,
            'longitude' => $longitude,
            'is_favorite' => $isFavorite,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
