<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Room;
use App\Models\User;
use App\Models\role;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AllEndpointsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_all_api_endpoints(): void
    {
        // 1. Health check
        $health = $this->getJson('/api/health');
        $health->assertStatus(200);
        echo "\n[PASS] GET /api/health -> 200";

        // 2. Roles list
        $roles = $this->getJson('/api/roles');
        $roles->assertStatus(200);
        $roles->assertJsonStructure(['success', 'data']);
        echo "\n[PASS] GET /api/roles -> 200";

        // 3. User Register
        $regEmail = 'test_runner_' . uniqid() . '@example.com';
        $register = $this->postJson('/api/register', [
            'name' => 'API Tester',
            'email' => $regEmail,
            'password' => 'secret123',
            'role' => 'owner',
            'phone' => '012345678',
        ]);
        $register->assertStatus(201);
        $token = $register->json('token');
        $userId = $register->json('user.id');
        $this->assertNotNull($token);
        echo "\n[PASS] POST /api/register -> 201 (role: owner, token issued)";

        // 4. User Login
        $login = $this->postJson('/api/login', [
            'email' => $regEmail,
            'password' => 'secret123',
        ]);
        $login->assertStatus(200);
        $authToken = $login->json('token');
        echo "\n[PASS] POST /api/login -> 200";

        // 5. Auth Profile
        $profile = $this->withHeader('Authorization', "Bearer {$authToken}")
            ->getJson('/api/profile');
        $profile->assertStatus(200);
        $this->assertEquals($regEmail, $profile->json('user.email'));
        echo "\n[PASS] GET /api/profile -> 200";

        // 6. Auth User
        $userResp = $this->withHeader('Authorization', "Bearer {$authToken}")
            ->getJson('/api/user');
        $userResp->assertStatus(200);
        echo "\n[PASS] GET /api/user -> 200";

        // 7. Category List
        $catList = $this->getJson('/api/category');
        $catList->assertStatus(200);
        echo "\n[PASS] GET /api/category -> 200";

        // 8. Category Store
        $catStore = $this->withHeader('Authorization', "Bearer {$authToken}")
            ->postJson('/api/category', [
                'name' => 'Villa ' . uniqid(),
                'description' => 'Luxury private villas',
            ]);
        $catStore->assertStatus(201);
        $catId = $catStore->json('data.id');
        echo "\n[PASS] POST /api/category -> 201";

        // 9. Category Show
        $catShow = $this->getJson("/api/category/{$catId}");
        $catShow->assertStatus(200);
        echo "\n[PASS] GET /api/category/{id} -> 200";

        // 10. Category Update
        $catUpdate = $this->withHeader('Authorization', "Bearer {$authToken}")
            ->putJson("/api/category/{$catId}", [
                'name' => 'Updated Villa ' . uniqid(),
                'description' => 'Updated description',
            ]);
        $catUpdate->assertStatus(200);
        echo "\n[PASS] PUT /api/category/{id} -> 200";

        // 11. Room List
        $roomList = $this->getJson('/api/room');
        $roomList->assertStatus(200);
        echo "\n[PASS] GET /api/room -> 200";

        // 12. Room Store
        $roomStore = $this->withHeader('Authorization', "Bearer {$authToken}")
            ->postJson('/api/room', [
                'name' => 'Grand Ocean Suite',
                'category_id' => $catId,
                'price' => 350.00,
                'price_period' => 'month',
                'type' => 'Villa',
                'address' => 'Sihanoukville Beach',
                'description' => 'Stunning ocean views with private pool',
                'size' => '80 sqm',
                'floor' => '2nd Floor',
                'deposit' => '1 Month',
            ]);
        $roomStore->assertStatus(201);
        $roomId = $roomStore->json('data.id');
        echo "\n[PASS] POST /api/room -> 201";

        // 13. Room Show
        $roomShow = $this->getJson("/api/room/{$roomId}");
        $roomShow->assertStatus(200);
        echo "\n[PASS] GET /api/room/{id} -> 200";

        // 14. Room Update
        $roomUpdate = $this->withHeader('Authorization', "Bearer {$authToken}")
            ->putJson("/api/room/{$roomId}", [
                'name' => 'Grand Ocean Suite Deluxe',
                'price' => 380.00,
            ]);
        $roomUpdate->assertStatus(200);
        echo "\n[PASS] PUT /api/room/{id} -> 200";

        // 15. Room Details Show
        $roomDetailShow = $this->getJson("/api/room-details/{$roomId}");
        $roomDetailShow->assertStatus(200);
        echo "\n[PASS] GET /api/room-details/{id} -> 200";

        // 16. Room Details Update
        $roomDetailUpdate = $this->withHeader('Authorization', "Bearer {$authToken}")
            ->putJson("/api/room-details/{$roomId}", [
                'size' => '85 sqm',
                'deposit' => '2 Months',
            ]);
        $roomDetailUpdate->assertStatus(200);
        echo "\n[PASS] PUT /api/room-details/{id} -> 200";

        // 17. Room Request Viewing
        $viewing = $this->postJson("/api/room/{$roomId}/request-viewing", [
            'name' => 'Jane Tenant',
            'phone' => '098112233',
            'email' => 'jane@example.com',
            'preferred_date' => now()->addDays(3)->format('Y-m-d'),
            'preferred_time' => '10:00',
            'notes' => 'Looking to move next week',
        ]);
        $viewing->assertStatus(201);
        echo "\n[PASS] POST /api/room/{id}/request-viewing -> 201";

        // 18. Room Delete
        $roomDelete = $this->withHeader('Authorization', "Bearer {$authToken}")
            ->deleteJson("/api/room/{$roomId}");
        $roomDelete->assertStatus(200);
        echo "\n[PASS] DELETE /api/room/{id} -> 200";

        // 19. Category Delete
        $catDelete = $this->withHeader('Authorization', "Bearer {$authToken}")
            ->deleteJson("/api/category/{$catId}");
        $catDelete->assertStatus(200);
        echo "\n[PASS] DELETE /api/category/{id} -> 200";

        // 20. Logout
        $logout = $this->withHeader('Authorization', "Bearer {$authToken}")
            ->postJson('/api/logout');
        $logout->assertStatus(200);
        echo "\n[PASS] POST /api/logout -> 200";
    }
}
