<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Models\role;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;
use Tests\TestCase;

class ChatbotTest extends TestCase
{
    public function test_chatbot_suggestions(): void
    {
        $response = $this->postJson('/api/chatbot/suggestions', ['lang' => 'km']);
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'welcome_message',
                'suggestions',
            ],
        ]);
    }

    public function test_chatbot_conversations_pagination_and_history(): void
    {
        $userRole = role::firstOrCreate(['name' => 'user'], ['description' => 'Normal user']);
        $user = User::create([
            'name' => 'Chat User',
            'email' => 'chat_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role_id' => $userRole->id,
        ]);

        $conv = ChatConversation::create([
            'user_id' => $user->id,
            'session_id' => 'sess_' . uniqid(),
            'title' => 'Room Inquiry',
            'last_activity_at' => now(),
        ]);

        ChatMessage::create([
            'conversation_id' => $conv->id,
            'role' => 'user',
            'content' => 'Find me a room under $200',
        ]);

        /** @var JWTGuard $guard */
        $guard = auth('api');
        $token = $guard->login($user);

        // Test GET /api/chatbot/conversations (where the LengthAwarePaginator::map bug happened)
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/chatbot/conversations');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'last_message',
                    'last_activity_at',
                    'created_at',
                ],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'total',
            ],
        ]);

        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals('Room Inquiry', $response->json('data.0.title'));

        // Test GET /api/chatbot/conversations/{id}
        $histResp = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/chatbot/conversations/{$conv->id}");

        $histResp->assertStatus(200);
        $this->assertEquals($conv->id, $histResp->json('data.conversation.id'));

        // Test DELETE /api/chatbot/conversations/{id}
        $delResp = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/chatbot/conversations/{$conv->id}");

        $delResp->assertStatus(200);
        $this->assertDatabaseMissing('chat_conversations', ['id' => $conv->id]);
    }
}
