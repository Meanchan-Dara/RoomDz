<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\role;
use App\Models\Room;
use App\Models\RoomDetail;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestRoom100RielSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Get or create Owner
        $owner = User::where('email', 'new_owner@roomdz.com')->first();
        if (!$owner) {
            $owner = User::where('email', 'owner@roomdz.com')->first();
        }

        if (!$owner) {
            $ownerRole = role::where('name', 'owner')->first() ?? role::first();
            $owner = User::create([
                'name' => 'Mean Chandara (RoomDz Owner)',
                'email' => 'owner@roomdz.com',
                'password' => Hash::make('password123'),
                'role_id' => $ownerRole?->id,
                'phone' => '+855 12 999 888',
                'telegram' => '@mean_chandara',
                'bakong_account_id' => 'mean_chandara@bkrt',
                'bakong_merchant_name' => 'Mean Chandara',
                'is_verified' => true,
            ]);
        } else {
            // Ensure owner has active Bakong config
            $owner->bakong_account_id = $owner->bakong_account_id ?: 'mean_chandara@bkrt';
            $owner->bakong_merchant_name = $owner->bakong_merchant_name ?: 'Mean Chandara';
            $owner->save();
        }

        // 2. Category
        $category = Category::where('slug', 'private-room')->first() ?? Category::first();

        // 3. High quality room images
        $images = [
            'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?auto=format&fit=crop&w=1000&q=80',
            'https://images.unsplash.com/photo-1598928506311-c55ded91a20c?auto=format&fit=crop&w=1000&q=80',
            'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1000&q=80',
            'https://images.unsplash.com/photo-1540518614846-7ede433c4550?auto=format&fit=crop&w=1000&q=80',
        ];

        // 4. Create or Update Test Room
        $room = Room::updateOrCreate(
            [
                'name' => 'បន្ទប់សាកល្បងបង់ប្រាក់កក់ 100៛ (Demo Bakong KHQR)',
            ],
            [
                'user_id' => $owner->id,
                'category_id' => $category?->id,
                'type' => 'Private Room',
                'price' => 120.00,
                'deposit_price' => 100.00,
                'deposit_currency' => 'KHR',
                'price_period' => 'month',
                'status' => 'AVAILABLE NOW',
                'total_units' => 1,
                'available_units' => 1,
                'is_negotiable' => true,
                'is_featured' => true,
                'listing_type' => 'featured',
                'rating' => 5.0,
                'reviews_count' => 30,
                'address' => 'St. 598, Sen Sok, Phnom Penh',
                'latitude' => 11.5738000,
                'longitude' => 104.8984000,
                'image' => $images[0],
            ]
        );

        // 5. Room Detail
        RoomDetail::updateOrCreate(
            ['room_id' => $room->id],
            [
                'description' => 'បន្ទប់ពិសេសបង្កើតឡើងសម្រាប់សាកល្បងការទូទាត់ប្រាក់កក់ផ្ទាល់តាមរយៈ Bakong KHQR ចំនួន 100៛ (100 KHR) សម្រាប់បង្ហាញជូនលោកគ្រូ និងធ្វើតេស្តមុខងារប្រព័ន្ធ។ លោកអ្នកអាចស្កេនបង់ប្រាក់ពិតប្រាកដជាមួយ ABA Mobile, Bakong App, ACLEDA ឬធនាគារនានាក្នុងប្រទេសកម្ពុជាបានភ្លាមៗ!',
                'size' => '30 sqm',
                'floor' => '2nd Floor',
                'deposit' => '100៛ (100 KHR)',
                'images' => $images,
                'facilities' => [
                    'Free High-Speed Wi-Fi',
                    'Air Conditioner',
                    'Private Bathroom',
                    'Balcony',
                    'Smart TV',
                    'Motorbike Parking',
                ],
                'house_rules' => [
                    'No smoking inside room',
                    'Quiet hours after 10:00 PM',
                    'Keep room clean',
                ],
                'utilities' => [
                    'electricity' => '1000 KHR / kWh',
                    'water' => '1500 KHR / m³',
                    'trash' => 'Free',
                ],
                'rental_terms' => [
                    'min_contract' => '1 Month',
                    'deposit_required' => '100៛ (Bakong KHQR)',
                    'max_occupants' => 2,
                ],
                'rules_permissions' => [
                    'cooking_allowed' => true,
                    'pet_friendly' => true,
                    'no_smoking' => true,
                    'guests_allowed' => true,
                ],
                'required_documents' => [
                    'National ID Card or Student Card',
                ],
                'payment_methods' => [
                    'Bakong KHQR (100៛ Test)',
                    'Cash',
                ],
                'payment_cycle' => '1st-5th of each month',
                'latitude' => 11.5738000,
                'longitude' => 104.8984000,
            ]
        );

        // Also update Room 1 from previous seeder if it exists so it has deposit_currency KHR as well
        $room1 = Room::where('name', 'Modern Private Suite - Toul Kork')->first();
        if ($room1) {
            $room1->deposit_currency = 'KHR';
            $room1->deposit_price = 100.00;
            $room1->save();
        }
    }
}
