<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\RoomDetail;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $privateRoomCat = \App\Models\Category::where('slug', 'private-room')->first();
        $studioCat = \App\Models\Category::where('slug', 'studio-apartment')->first();

        $ownerRole = \App\Models\role::where('name', 'owner')->first();
        $owner = \App\Models\User::firstOrCreate(
            ['email' => 'owner@roomdz.com'],
            [
                'role_id' => $ownerRole?->id,
                'name' => 'Meanchan Dara (Owner)',
                'password' => bcrypt('password123'),
                'phone' => '+855 12 345 678',
                'telegram' => '@roomdz_contact',
                'is_verified' => true,
                'location_tag' => 'Phnom Penh, Cambodia',
            ]
        );

        // 1. Exact Room from the design screenshot
        $room1 = Room::updateOrCreate(
            ['name' => 'Modern Private Room', 'address' => 'Toul Kork, Phnom Penh'],
            [
                'category_id' => $privateRoomCat?->id,
                'user_id' => $owner->id,
                'type' => 'Private Room',
                'price' => 150.00,
                'price_period' => 'month',
                'status' => 'AVAILABLE NOW',
                'rating' => 4.8,
                'reviews_count' => 24,
                'image' => 'https://images.unsplash.com/photo-1598928506311-c55ded91a20c?auto=format&fit=crop&w=800&q=80',
            ]
        );

        RoomDetail::updateOrCreate(
            ['room_id' => $room1->id],
            [
                'description' => 'Experience comfortable living in this newly renovated private room located in the heart of Toul Kork. Perfectly suited for young professionals or students, this space offers ample natural light, modern furnishings, and a quiet environment. The building is secure and conveniently located near major universities, cafes, and supermarkets.',
                'size' => '24 sqm',
                'floor' => '3rd Floor',
                'deposit' => '1 Month',
                'images' => [
                    'https://images.unsplash.com/photo-1598928506311-c55ded91a20c?auto=format&fit=crop&w=1000&q=80',
                    'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1000&q=80',
                    'https://images.unsplash.com/photo-1540518614846-7ede433c4550?auto=format&fit=crop&w=1000&q=80',
                    'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=1000&q=80',
                    'https://images.unsplash.com/photo-1584622650111-993a426fbf0a?auto=format&fit=crop&w=1000&q=80',
                    'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?auto=format&fit=crop&w=1000&q=80',
                    'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?auto=format&fit=crop&w=1000&q=80',
                    'https://images.unsplash.com/photo-1512918728675-ed5a9ecdebfd?auto=format&fit=crop&w=1000&q=80',
                ],
                'facilities' => [
                    'Free Wi-Fi',
                    'A/C',
                    'Parking',
                    'Private Bath',
                ],
                'house_rules' => [
                    'No smoking inside',
                    'No pets allowed',
                    'Quiet hours 10PM - 7AM',
                ],
                'latitude' => 11.5738000,
                'longitude' => 104.8984000,
            ]
        );

        // 2. Additional Room sample: Cozy Studio
        $room2 = Room::updateOrCreate(
            ['name' => 'Cozy Studio Apartment', 'address' => 'BKK1, Phnom Penh'],
            [
                'category_id' => $studioCat?->id,
                'user_id' => $owner->id,
                'type' => 'Studio',
                'price' => 220.00,
                'price_period' => 'month',
                'status' => 'AVAILABLE NOW',
                'rating' => 4.9,
                'reviews_count' => 38,
                'image' => 'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=800&q=80',
            ]
        );

        RoomDetail::updateOrCreate(
            ['room_id' => $room2->id],
            [
                'description' => 'Bright and modern studio in the center of BKK1 with high-speed internet, private balcony, elevator access, and 24/7 security guard.',
                'size' => '32 sqm',
                'floor' => '5th Floor',
                'deposit' => '1 Month',
                'images' => [
                    'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=1000&q=80',
                    'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=1000&q=80',
                    'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?auto=format&fit=crop&w=1000&q=80',
                ],
                'facilities' => [
                    'Free Wi-Fi',
                    'A/C',
                    'Elevator',
                    'Balcony',
                ],
                'house_rules' => [
                    'No smoking inside',
                    'Pets allowed with deposit',
                ],
                'latitude' => 11.5528000,
                'longitude' => 104.9281000,
            ]
        );
    }
}
