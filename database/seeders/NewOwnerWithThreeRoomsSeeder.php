<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\role;
use App\Models\Room;
use App\Models\RoomDetail;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class NewOwnerWithThreeRoomsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Find or verify owner role
        $ownerRole = role::where('name', 'owner')->first();
        if (!$ownerRole) {
            $ownerRole = role::create([
                'name' => 'owner',
                'description' => 'Property or room owner who can list and manage rooms',
            ]);
        }

        // Get bakong account from .env
        $bakongAccountId = env('BAKONG_ACCOUNT_ID', 'mean_chandara@bkrt');
        $bakongMerchantName = env('BAKONG_MERCHANT_NAME', 'RoomDz');

        // 2. Create the new owner
        $ownerEmail = 'new_owner@roomdz.com';
        $owner = User::updateOrCreate(
            ['email' => $ownerEmail],
            [
                'role_id' => $ownerRole->id,
                'name' => 'Sokha Property (New Owner)',
                'password' => Hash::make('password123'),
                'phone' => '+855 12 999 888',
                'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=400&q=80',
                'telegram' => '@sokha_property',
                'bakong_account_id' => $bakongAccountId,
                'bakong_merchant_name' => $bakongMerchantName,
                'is_verified' => true,
                'location_tag' => 'Phnom Penh, Cambodia',
            ]
        );

        // Categories
        $privateRoomCat = Category::where('slug', 'private-room')->first() ?? Category::first();
        $condoCat = Category::where('slug', 'condo')->first() ?? $privateRoomCat;

        // 3. Room 1: 4 images and deposit 100 Riels (100 KHR), using Bakong in .env
        $room1Images = [
            'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?auto=format&fit=crop&w=1000&q=80',
            'https://images.unsplash.com/photo-1598928506311-c55ded91a20c?auto=format&fit=crop&w=1000&q=80',
            'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1000&q=80',
            'https://images.unsplash.com/photo-1540518614846-7ede433c4550?auto=format&fit=crop&w=1000&q=80',
        ];

        $room1 = Room::updateOrCreate(
            [
                'user_id' => $owner->id,
                'name' => 'Modern Private Suite - Toul Kork',
            ],
            [
                'category_id' => $privateRoomCat?->id,
                'type' => 'Private Room',
                'price' => 120.00,
                'deposit_price' => 100.00, // 100 Riels deposit
                'price_period' => 'month',
                'status' => 'AVAILABLE NOW',
                'total_units' => 1,
                'available_units' => 1,
                'is_negotiable' => true,
                'is_featured' => true,
                'listing_type' => 'featured',
                'rating' => 4.9,
                'reviews_count' => 18,
                'address' => 'St. 289, Toul Kork, Phnom Penh',
                'latitude' => 11.5738000,
                'longitude' => 104.8984000,
                'image' => $room1Images[0],
            ]
        );

        RoomDetail::updateOrCreate(
            ['room_id' => $room1->id],
            [
                'description' => 'បន្ទប់ទំនើបស្អាត និងមានផាសុកភាពនៅខណ្ឌទួលគោក មានពន្លឺធម្មជាតិគ្រប់គ្រាន់ និងបំពាក់គ្រឿងសង្ហារឹមរួចជាស្រេច។ កក់ប្រាក់ត្រឹមតែ 100 រៀលតាមរយៈ Bakong KHQR!',
                'size' => '28 sqm',
                'floor' => '2nd Floor',
                'deposit' => '100 Riels (100 KHR)',
                'images' => $room1Images,
                'facilities' => [
                    'Free High-Speed Wi-Fi',
                    'Air Conditioner',
                    'Private Bathroom',
                    'Motorbike Parking',
                    'Balcony',
                ],
                'house_rules' => [
                    'No smoking inside room',
                    'Quiet hours after 10:00 PM',
                    'Keep shared areas clean',
                ],
                'utilities' => [
                    'electricity' => '1000 KHR / kWh',
                    'water' => '1500 KHR / m³',
                    'trash' => 'Free',
                ],
                'rental_terms' => [
                    'min_contract' => '3 Months',
                    'deposit_required' => '100 KHR (Bakong)',
                    'max_occupants' => 2,
                ],
                'rules_permissions' => [
                    'cooking_allowed' => true,
                    'pet_friendly' => false,
                    'no_smoking' => true,
                    'guests_allowed' => true,
                ],
                'required_documents' => [
                    'National ID Card or Passport',
                ],
                'payment_methods' => [
                    'Bakong KHQR (' . $bakongAccountId . ')',
                    'Cash',
                ],
                'payment_cycle' => '1st-5th of each month',
                'contact_info' => [
                    'contact_name' => $owner->name,
                    'phone' => $owner->phone,
                    'telegram' => $owner->telegram,
                    'bakong_id' => $bakongAccountId,
                ],
                'latitude' => 11.5738000,
                'longitude' => 104.8984000,
            ]
        );

        // 4. Room 2: 4 images
        $room2Images = [
            'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=1000&q=80',
            'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=1000&q=80',
            'https://images.unsplash.com/photo-1584622650111-993a426fbf0a?auto=format&fit=crop&w=1000&q=80',
            'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?auto=format&fit=crop&w=1000&q=80',
        ];

        $room2 = Room::updateOrCreate(
            [
                'user_id' => $owner->id,
                'name' => 'Cozy Studio Room - BKK1',
            ],
            [
                'category_id' => $privateRoomCat?->id,
                'type' => 'Studio',
                'price' => 180.00,
                'deposit_price' => 50.00,
                'price_period' => 'month',
                'status' => 'AVAILABLE NOW',
                'total_units' => 1,
                'available_units' => 1,
                'is_negotiable' => false,
                'is_featured' => false,
                'listing_type' => 'standard',
                'rating' => 4.7,
                'reviews_count' => 12,
                'address' => 'St. 57, BKK1, Chamkarmon, Phnom Penh',
                'latitude' => 11.5528000,
                'longitude' => 104.9281000,
                'image' => $room2Images[0],
            ]
        );

        RoomDetail::updateOrCreate(
            ['room_id' => $room2->id],
            [
                'description' => 'បន្ទប់ស្ទូឌីយោដ៏ស្រស់ស្អាតនៅបេះដូងនៃបឹងកេងកង១ (BKK1) ជិតហាងកាហ្វេ ភោជនីយដ្ឋាន និងផ្សារទំនើប។ មានប្រព័ន្ធសុវត្ថិភាព 24 ម៉ោង និងជណ្តើរយន្ត។',
                'size' => '32 sqm',
                'floor' => '4th Floor',
                'deposit' => '1 Month ($50 booking deposit)',
                'images' => $room2Images,
                'facilities' => [
                    'High-Speed Wi-Fi',
                    'Inverter Air Conditioner',
                    'Refrigerator',
                    'Elevator Access',
                    '24/7 Security Guard',
                ],
                'house_rules' => [
                    'No smoking inside',
                    'Small pets allowed with advance notice',
                    'Quiet hours after 11:00 PM',
                ],
                'utilities' => [
                    'electricity' => '$0.25 / kWh',
                    'water' => '$0.50 / m³',
                    'trash' => 'Free',
                ],
                'rental_terms' => [
                    'min_contract' => '6 Months',
                    'max_occupants' => 2,
                ],
                'rules_permissions' => [
                    'cooking_allowed' => true,
                    'pet_friendly' => true,
                    'no_smoking' => true,
                    'guests_allowed' => true,
                ],
                'required_documents' => [
                    'National ID Card or Passport',
                    'Employment Letter / Student ID',
                ],
                'payment_methods' => [
                    'Bakong KHQR',
                    'Cash',
                ],
                'payment_cycle' => '1st-5th of each month',
                'contact_info' => [
                    'contact_name' => $owner->name,
                    'phone' => $owner->phone,
                    'telegram' => $owner->telegram,
                ],
                'latitude' => 11.5528000,
                'longitude' => 104.9281000,
            ]
        );

        // 5. Room 3: 4 images
        $room3Images = [
            'https://images.unsplash.com/photo-1512918728675-ed5a9ecdebfd?auto=format&fit=crop&w=1000&q=80',
            'https://images.unsplash.com/photo-1502005229762-ee1b2da97c0f?auto=format&fit=crop&w=1000&q=80',
            'https://images.unsplash.com/photo-1493809842364-78817add7ffb?auto=format&fit=crop&w=1000&q=80',
            'https://images.unsplash.com/photo-1484154218962-a197022b5858?auto=format&fit=crop&w=1000&q=80',
        ];

        $room3 = Room::updateOrCreate(
            [
                'user_id' => $owner->id,
                'name' => 'Premium Condo Suite - Sen Sok',
            ],
            [
                'category_id' => $condoCat?->id,
                'type' => 'Condo',
                'price' => 250.00,
                'deposit_price' => 100.00,
                'price_period' => 'month',
                'status' => 'AVAILABLE NOW',
                'total_units' => 1,
                'available_units' => 1,
                'is_negotiable' => true,
                'is_featured' => true,
                'listing_type' => 'premium',
                'rating' => 5.0,
                'reviews_count' => 9,
                'address' => 'Near Aeon Mall 2, Sen Sok, Phnom Penh',
                'latitude' => 11.5938000,
                'longitude' => 104.8884000,
                'image' => $room3Images[0],
            ]
        );

        RoomDetail::updateOrCreate(
            ['room_id' => $room3->id],
            [
                'description' => 'ខុនដូបែបប្រណិតនៅសែនសុខ ក្បែរផ្សារទំនើបអ៊ីអន២ (Aeon Mall Sen Sok)។ បំពាក់ដោយអាងហែលទឹក កន្លែងហាត់ប្រាណ កន្លែងចតរថយន្តធំទូលាយ និងទេសភាពទីក្រុងស្រស់ស្អាត។',
                'size' => '45 sqm',
                'floor' => '8th Floor',
                'deposit' => '1 Month',
                'images' => $room3Images,
                'facilities' => [
                    'Swimming Pool Access',
                    'Fitness Gym',
                    'Free High-Speed Wi-Fi',
                    'Washing Machine',
                    'Car & Motorbike Parking',
                    'Balcony with City View',
                ],
                'house_rules' => [
                    'No smoking inside condo',
                    'No loud parties after 10:00 PM',
                    'Pool hours 6:00 AM - 9:00 PM',
                ],
                'utilities' => [
                    'electricity' => '$0.25 / kWh',
                    'water' => '$0.60 / m³',
                    'management_fee' => 'Free',
                ],
                'rental_terms' => [
                    'min_contract' => '6 Months',
                    'max_occupants' => 3,
                ],
                'rules_permissions' => [
                    'cooking_allowed' => true,
                    'pet_friendly' => true,
                    'no_smoking' => true,
                    'guests_allowed' => true,
                ],
                'required_documents' => [
                    'National ID Card or Passport',
                ],
                'payment_methods' => [
                    'Bakong KHQR',
                    'Cash',
                ],
                'payment_cycle' => '1st-5th of each month',
                'contact_info' => [
                    'contact_name' => $owner->name,
                    'phone' => $owner->phone,
                    'telegram' => $owner->telegram,
                ],
                'latitude' => 11.5938000,
                'longitude' => 104.8884000,
            ]
        );

        echo "Successfully created Owner and 3 Published Rooms with 4 images each!\n";
        echo "Owner ID: {$owner->id}\n";
        echo "Owner Email: {$owner->email}\n";
        echo "Bakong Account: {$owner->bakong_account_id}\n";
        echo "Room 1 ID: {$room1->id}, Deposit: {$room1->deposit_price} KHR, Images: " . count($room1Images) . "\n";
        echo "Room 2 ID: {$room2->id}, Deposit: {$room2->deposit_price}, Images: " . count($room2Images) . "\n";
        echo "Room 3 ID: {$room3->id}, Deposit: {$room3->deposit_price}, Images: " . count($room3Images) . "\n";
    }
}
