<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Room;
use App\Models\User;
use App\Models\role;
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
        $ownerRole = role::firstOrCreate(['name' => 'owner'], ['description' => 'Property or room owner']);
        $landlord = User::create([
            'name' => 'Test Landlord',
            'email' => 'landlord_' . uniqid() . '@example.com',
            'password' => bcrypt('secret123'),
            'role_id' => $ownerRole->id,
            'phone' => '+85512345678',
        ]);

        // 2. Create category
        $category = Category::create([
            'name' => 'Condo Deluxe ' . uniqid(),
            'slug' => 'condo-deluxe-' . uniqid(),
        ]);

        // 3. Create room with details
        $token = auth('api')->login($landlord);

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

    public function test_owner_multiple_room_units_and_dashboard(): void
    {
        $ownerRole = role::firstOrCreate(['name' => 'owner'], ['description' => 'Property or room owner']);
        $owner = User::create([
            'name' => 'Multi-Unit Landlord',
            'email' => 'multi_' . uniqid() . '@example.com',
            'password' => bcrypt('secret123'),
            'role_id' => $ownerRole->id,
            'phone' => '+85511223344',
            'telegram' => '@multi_landlord',
        ]);

        $category = Category::firstOrCreate(['name' => 'Standard Room', 'slug' => 'standard-room']);
        $token = auth('api')->login($owner);

        // Create 1 listing representing 10 identical rooms, 8 available (2 occupied)
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/owner/rooms', [
                'category_id' => $category->id,
                'name' => 'Budget Studio 10-Pack',
                'type' => 'Studio',
                'price' => 100.00,
                'price_period' => 'month',
                'total_units' => 10,
                'available_units' => 8,
                'address' => 'Tuol Tompoung, Phnom Penh',
                'description' => '10 identical fully furnished studio units',
            ]);

        $response->assertStatus(201);
        $this->assertEquals(10, $response->json('data.total_units'));
        $this->assertEquals(8, $response->json('data.available_units'));
        $this->assertTrue($response->json('data.is_available'));

        $roomId = $response->json('data.id');

        // Check Owner Dashboard calculations
        $dashResp = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/owner/dashboard');

        $dashResp->assertStatus(200);
        $this->assertEquals(10, $dashResp->json('data.stats.total_rooms'));
        $this->assertEquals(8, $dashResp->json('data.stats.available_rooms'));
        $this->assertEquals(2, $dashResp->json('data.stats.occupied_rooms'));
        $this->assertEquals(20, $dashResp->json('data.stats.occupancy_rate')); // 2 / 10 = 20%
        $this->assertEquals(200.0, $dashResp->json('data.earnings.current_revenue')); // 2 * $100 = $200

        // Now simulate all units becoming rented out (available_units = 0)
        $updateResp = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/owner/rooms/{$roomId}", [
                'available_units' => 0,
            ]);

        $updateResp->assertStatus(200);
        $this->assertEquals(0, $updateResp->json('data.available_units'));
        $this->assertFalse($updateResp->json('data.is_available'));

        // Check dashboard updated occupancy
        $dashResp2 = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/owner/dashboard');

        $this->assertEquals(10, $dashResp2->json('data.stats.total_rooms'));
        $this->assertEquals(0, $dashResp2->json('data.stats.available_rooms'));
        $this->assertEquals(10, $dashResp2->json('data.stats.occupied_rooms'));
        $this->assertEquals(100, $dashResp2->json('data.stats.occupancy_rate')); // 10 / 10 = 100%
        $this->assertEquals(1000.0, $dashResp2->json('data.earnings.current_revenue')); // 10 * $100 = $1000

        // Test Quick Action: release-unit (tenant vacates, +2 units become available)
        $releaseResp = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/owner/rooms/{$roomId}/release-unit", ['units' => 2]);

        $releaseResp->assertStatus(200);
        $this->assertEquals(2, $releaseResp->json('data.available_units'));
        $this->assertTrue($releaseResp->json('data.is_available'));

        // Test Quick Action: rent-out (another tenant rents, -1 unit)
        $rentResp = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/owner/rooms/{$roomId}/rent-out"); // default 1 unit

        $rentResp->assertStatus(200);
        $this->assertEquals(1, $rentResp->json('data.available_units'));
        $this->assertTrue($rentResp->json('data.is_available'));
    }
}
