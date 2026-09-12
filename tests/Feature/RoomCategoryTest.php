<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RoomCategoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Category::where('slug', 'like', 'studio-apartment%')->delete();
    }

    public function test_category_crud_and_slug_auto_generation(): void
    {
        $response = $this->postJson('/api/category', [
            'name' => 'Studio Apartment',
            'description' => 'Compact and modern studio rooms',
        ]);

        $response->assertStatus(201);
        $categoryId = $response->json('data.id');
        $this->assertEquals('studio-apartment', $response->json('data.slug'));

        // Check index
        $index = $this->getJson('/api/category');
        $index->assertStatus(200);

        // Show category
        $show = $this->getJson("/api/category/{$categoryId}");
        $show->assertStatus(200);
        $this->assertEquals('Studio Apartment', $show->json('data.name'));
    }

    public function test_room_atomic_creation_with_landlord_and_details(): void
    {
        // 1. Create a landlord user
        $landlord = User::create([
            'name' => 'Test Landlord',
            'email' => 'landlord_' . uniqid() . '@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'owner',
            'phone' => '+85512345678',
        ]);

        // 2. Create category
        $category = Category::create([
            'name' => 'Condo Deluxe ' . uniqid(),
            'slug' => 'condo-deluxe-' . uniqid(),
        ]);

        // 3. Create room with details
        $token = $landlord->createToken('auth')->plainTextToken;

        $roomPayload = [
            'category_id' => $category->id,
            'name' => 'Sunset View Suite',
            'type' => 'Full Condo',
            'price' => 350.00,
            'price_period' => 'month',
            'status' => 'AVAILABLE NOW',
            'address' => 'BKK1, Phnom Penh',
            'description' => 'Luxurious studio with pool and gym access',
            'size' => '45 sqm',
            'floor' => '12th Floor',
            'deposit' => '2 Months',
            'facilities' => ['WiFi', 'Air Conditioning', 'Swimming Pool', 'Gym'],
            'house_rules' => ['No Smoking', 'No Pets'],
            'latitude' => 11.5564,
            'longitude' => 104.9282,
        ];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/room', $roomPayload);

        $response->assertStatus(201);
        $roomId = $response->json('data.id');

        $this->assertNotNull($roomId);
        $this->assertEquals($landlord->id, $response->json('data.user_id'));
        $this->assertEquals('Sunset View Suite', $response->json('data.name'));
        $this->assertEquals('Luxurious studio with pool and gym access', $response->json('data.about'));
        $this->assertEquals('45 sqm', $response->json('data.room_information.size'));
        $this->assertEquals('Test Landlord', $response->json('data.landlord.name'));

        // 4. Test viewing request
        $viewingResp = $this->postJson("/api/room/{$roomId}/request-viewing", [
            'name' => 'Prospective Tenant',
            'phone' => '098765432',
            'preferred_date' => now()->addDays(2)->format('Y-m-d'),
            'preferred_time' => '14:00',
            'notes' => 'Would love to inspect the balcony view',
        ]);

        $viewingResp->assertStatus(201);
        $this->assertDatabaseHas('viewing_requests', [
            'room_id' => $roomId,
            'name' => 'Prospective Tenant',
        ]);

        // 5. Test clean deletion
        $delResp = $this->deleteJson("/api/room/{$roomId}");
        $delResp->assertStatus(200);

        // Verify cascade deletion
        $this->assertDatabaseMissing('rooms', ['id' => $roomId]);
        $this->assertDatabaseMissing('room_details', ['room_id' => $roomId]);
        $this->assertDatabaseMissing('viewing_requests', ['room_id' => $roomId]);
    }
}
