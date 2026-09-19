<?php

namespace Tests\Feature;

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthOtpTest extends TestCase
{
    protected string $testEmail = 'meanchandara1@gmail.com';
    protected string $testPassword = 'password123';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        User::where('email', $this->testEmail)->delete();
        OtpCode::where('email', $this->testEmail)->delete();
        DB::table('password_reset_tokens')->where('email', $this->testEmail)->delete();
    }

    public function test_1_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Meanchan Dara',
            'email' => $this->testEmail,
            'password' => $this->testPassword,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => $this->testEmail]);
        echo "\n[PASS] POST /api/register -> Status: " . $response->status();
    }

    public function test_2_login(): string
    {
        $user = User::firstOrCreate(
            ['email' => $this->testEmail],
            ['name' => 'Meanchan Dara', 'password' => bcrypt($this->testPassword)]
        );

        $response = $this->postJson('/api/login', [
            'email' => $this->testEmail,
            'password' => $this->testPassword,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['token', 'user']);
        echo "\n[PASS] POST /api/login -> Status: " . $response->status() . " (Token generated)";
        return $response->json('token');
    }

    public function test_3_send_otp(): void
    {
        $response = $this->postJson('/api/send_otp', [
            'email' => $this->testEmail,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('otp_codes', ['email' => $this->testEmail]);
        echo "\n[PASS] POST /api/send_otp -> Status: " . $response->status() . " (OTP sent & stored)";
    }

    public function test_4_verify_otp(): void
    {
        // Ensure OTP exists
        OtpCode::updateOrCreate(
            ['email' => $this->testEmail],
            ['code' => '654321', 'expire_at' => now()->addMinutes(10)]
        );

        $response = $this->postJson('/api/verify_otp', [
            'email' => $this->testEmail,
            'otp' => '654321',
        ]);

        $response->assertStatus(200);
        echo "\n[PASS] POST /api/verify_otp -> Status: " . $response->status();
    }

    public function test_5_login_with_otp(): void
    {
        User::firstOrCreate(
            ['email' => $this->testEmail],
            ['name' => 'Meanchan Dara', 'password' => bcrypt($this->testPassword)]
        );

        OtpCode::updateOrCreate(
            ['email' => $this->testEmail],
            ['code' => '888999', 'expire_at' => now()->addMinutes(10)]
        );

        $response = $this->postJson('/api/loginWithOtp', [
            'email' => $this->testEmail,
            'otp' => '888999',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['token', 'user']);
        echo "\n[PASS] POST /api/loginWithOtp -> Status: " . $response->status() . " (Logged in with OTP)";
    }

    public function test_6_reset_otp(): void
    {
        User::firstOrCreate(
            ['email' => $this->testEmail],
            ['name' => 'Meanchan Dara', 'password' => bcrypt($this->testPassword)]
        );

        // Expired OTP first
        OtpCode::updateOrCreate(
            ['email' => $this->testEmail],
            ['code' => '111222', 'expire_at' => now()->subMinutes(1)]
        );

        $response = $this->postJson('/api/reset_otp', [
            'email' => $this->testEmail,
        ]);

        $response->assertStatus(200);
        echo "\n[PASS] POST /api/reset_otp -> Status: " . $response->status() . " (New OTP regenerated)";
    }

    public function test_7_forgot_password(): void
    {
        User::firstOrCreate(
            ['email' => $this->testEmail],
            ['name' => 'Meanchan Dara', 'password' => bcrypt($this->testPassword)]
        );

        $response = $this->postJson('/api/forgot-password', [
            'email' => $this->testEmail,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'token', 'reset_link']);
        echo "\n[PASS] POST /api/forgot-password -> Status: " . $response->status();
    }

    public function test_8_reset_password(): void
    {
        User::firstOrCreate(
            ['email' => $this->testEmail],
            ['name' => 'Meanchan Dara', 'password' => bcrypt($this->testPassword)]
        );

        $token = 'test-token-123456789';
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $this->testEmail],
            ['token' => $token, 'created_at' => now()]
        );

        $response = $this->postJson("/api/reset-password/{$token}", [
            'password' => 'newpassword456',
        ]);

        $response->assertStatus(200);
        echo "\n[PASS] POST /api/reset-password/{token} -> Status: " . $response->status();
    }

    public function test_9_authenticated_routes(): void
    {
        $user = User::firstOrCreate(
            ['email' => $this->testEmail],
            ['name' => 'Meanchan Dara', 'password' => bcrypt($this->testPassword)]
        );
        $token = auth('api')->login($user);

        // GET /api/user
        $userResp = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/user');
        $userResp->assertStatus(200);
        echo "\n[PASS] GET /api/user -> Status: " . $userResp->status();

        // GET /api/profile
        $profileResp = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/profile');
        $profileResp->assertStatus(200);
        echo "\n[PASS] GET /api/profile -> Status: " . $profileResp->status();

        // POST /api/logout
        $logoutResp = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/logout');
        $logoutResp->assertStatus(200);
        echo "\n[PASS] POST /api/logout -> Status: " . $logoutResp->status();
    }
}
