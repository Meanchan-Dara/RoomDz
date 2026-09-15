<?php

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Room;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatbotService
{
    private string $apiKey;
    private string $model;
    private string $endpoint;
    private string $systemPrompt;
    private int $maxHistory;
    private int $maxRoomResults;

    public function __construct()
    {
        $this->apiKey = (string) (config('chatbot.gemini.api_key') ?? '');
        $this->model = (string) (config('chatbot.gemini.model') ?? 'gemini-2.0-flash');
        $this->endpoint = (string) (config('chatbot.gemini.endpoint') ?? 'https://generativelanguage.googleapis.com/v1beta/models');
        $this->systemPrompt = (string) (config('chatbot.system_prompt') ?? '');
        $this->maxHistory = (int) (config('chatbot.max_history') ?? 10);
        $this->maxRoomResults = (int) (config('chatbot.max_room_results') ?? 6);
    }

    /**
     * Process a user message and return the AI response with optional room results.
     */
    public function processMessage(string $userMessage, ChatConversation $conversation): array
    {
        // 1. Save user message
        $userMsg = $conversation->messages()->create([
            'role' => 'user',
            'content' => $userMessage,
        ]);

        // 2. Get conversation history for context
        $history = $this->getConversationContext($conversation);

        // 3. Call Gemini AI to extract intent and generate reply
        $aiResult = $this->callGeminiAI($userMessage, $history);

        // 4. If intent is search_room, query the database
        $rooms = [];
        $roomIds = [];
        if ($aiResult['intent'] === 'search_room' && !empty($aiResult['filters'])) {
            $rooms = $this->searchRooms($aiResult['filters']);
            $roomIds = collect($rooms)->pluck('id')->toArray();

            // If rooms found, enhance the AI reply with room context
            if (!empty($rooms)) {
                $aiResult = $this->enhanceResponseWithRooms($userMessage, $aiResult, $rooms, $history);
            } else {
                // No rooms found — generate a helpful "no results" reply
                $aiResult = $this->generateNoResultsResponse($userMessage, $aiResult['filters'], $history);
            }
        }

        // 5. Save assistant message
        $assistantMsg = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $aiResult['reply'] ?? 'Sorry, I could not process your request.',
            'room_results' => !empty($roomIds) ? $roomIds : null,
            'metadata' => [
                'intent' => $aiResult['intent'] ?? 'general',
                'filters' => $aiResult['filters'] ?? null,
                'suggestions' => $aiResult['suggestions'] ?? [],
            ],
        ]);

        // 6. Update conversation
        $conversation->update([
            'last_activity_at' => now(),
            'title' => $conversation->title ?? $this->generateTitle($userMessage),
        ]);

        return [
            'conversation_id' => $conversation->id,
            'message' => [
                'id' => $assistantMsg->id,
                'role' => 'assistant',
                'content' => $assistantMsg->content,
                'rooms' => $rooms,
                'rooms_count' => count($rooms),
                'suggestions' => $aiResult['suggestions'] ?? [],
                'intent' => $aiResult['intent'] ?? 'general',
                'created_at' => $assistantMsg->created_at->toISOString(),
            ],
        ];
    }

    /**
     * Call Gemini AI API with the user message and conversation context.
     */
    private function callGeminiAI(string $userMessage, array $history): array
    {
        try {
            $contents = [];

            // Add conversation history
            foreach ($history as $msg) {
                $contents[] = [
                    'role' => $msg['role'] === 'assistant' ? 'model' : 'user',
                    'parts' => [['text' => $msg['content']]],
                ];
            }

            // Add current user message
            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => $userMessage]],
            ];

            $url = $this->endpoint . $this->model . ':generateContent?key=' . $this->apiKey;

            $response = Http::timeout(30)->post($url, [
                'system_instruction' => [
                    'parts' => [['text' => $this->systemPrompt]],
                ],
                'contents' => $contents,
                'generationConfig' => [
                    'temperature' => 0.7,
                    'topP' => 0.95,
                    'maxOutputTokens' => 1024,
                    'responseMimeType' => 'application/json',
                ],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

                // Parse JSON response from AI
                $parsed = json_decode($text, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($parsed['intent'])) {
                    return $parsed;
                }

                // If AI didn't return proper JSON, wrap it
                return [
                    'intent' => 'general',
                    'filters' => null,
                    'reply' => $text ?: 'ខ្ញុំមិនយល់សំណួររបស់អ្នកទេ។ សូមសួរម្តងទៀត! 😊',
                    'suggestions' => config('chatbot.default_suggestions.km'),
                ];
            }

            Log::error('Gemini API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $this->getFallbackResponse($userMessage);

        } catch (\Throwable $e) {
            Log::error('Chatbot AI error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->getFallbackResponse($userMessage);
        }
    }

    /**
     * Enhance AI response by re-calling with actual room data for a natural reply.
     */
    private function enhanceResponseWithRooms(string $userMessage, array $aiResult, array $rooms, array $history): array
    {
        try {
            $roomSummary = collect($rooms)->map(function ($room, $index) {
                $num = $index + 1;
                return "{$num}. {$room['name']} — \${$room['price']}/{$room['price_period']} — {$room['address']} — Rating: {$room['rating']}⭐";
            })->implode("\n");

            $enhancePrompt = "Based on the user's request: \"{$userMessage}\"\n\n" .
                "I found these rooms:\n{$roomSummary}\n\n" .
                "Please write a friendly, natural response presenting these rooms to the user. " .
                "Keep the same language as the user's message (Khmer or English). " .
                "Include emojis for a lively feel. " .
                "Respond in JSON format: {\"intent\": \"search_room\", \"filters\": null, \"reply\": \"your response\", \"suggestions\": [\"suggestion1\", \"suggestion2\", \"suggestion3\"]}";

            $contents = [];
            foreach ($history as $msg) {
                $contents[] = [
                    'role' => $msg['role'] === 'assistant' ? 'model' : 'user',
                    'parts' => [['text' => $msg['content']]],
                ];
            }
            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => $enhancePrompt]],
            ];

            $url = $this->endpoint . $this->model . ':generateContent?key=' . $this->apiKey;

            $response = Http::timeout(30)->post($url, [
                'system_instruction' => [
                    'parts' => [['text' => $this->systemPrompt]],
                ],
                'contents' => $contents,
                'generationConfig' => [
                    'temperature' => 0.8,
                    'topP' => 0.95,
                    'maxOutputTokens' => 1024,
                    'responseMimeType' => 'application/json',
                ],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                $parsed = json_decode($text, true);

                if (json_last_error() === JSON_ERROR_NONE && isset($parsed['reply'])) {
                    $parsed['intent'] = 'search_room';
                    $parsed['filters'] = $aiResult['filters'];
                    return $parsed;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to enhance response: ' . $e->getMessage());
        }

        // Fallback: use the original AI result
        return $aiResult;
    }

    /**
     * Generate a helpful response when no rooms match the filters.
     */
    private function generateNoResultsResponse(string $userMessage, ?array $filters, array $history): array
    {
        $isKhmer = $this->isKhmerText($userMessage);

        $reply = $isKhmer
            ? "សុំទោស! 😔 ខ្ញុំមិនរកឃើញបន្ទប់ដែលត្រូវនឹងលក្ខខណ្ឌរបស់អ្នកទេ។ សូមសាកល្បងផ្លាស់ប្តូរតម្រង (តម្លៃ, ទីតាំង) ឬសួរខ្ញុំដើម្បីជួយស្វែងរកបន្ទប់ផ្សេងទៀត! 🔍"
            : "Sorry! 😔 I couldn't find any rooms matching your criteria. Try adjusting your filters (price, location) or ask me to help find other options! 🔍";

        $suggestions = $isKhmer
            ? ['បង្ហាញបន្ទប់ទាំងអស់', 'បន្ទប់តម្លៃក្រោម $300', 'បន្ទប់ថ្មីៗបំផុត']
            : ['Show all rooms', 'Rooms under $300', 'Newest listings'];

        return [
            'intent' => 'search_room',
            'filters' => $filters,
            'reply' => $reply,
            'suggestions' => $suggestions,
        ];
    }

    /**
     * Search rooms based on AI-extracted filters.
     */
    private function searchRooms(?array $filters): array
    {
        if (empty($filters)) {
            return [];
        }

        $query = Room::with(['category', 'user', 'detail']);

        // Price filters
        if (!empty($filters['max_price'])) {
            $query->where('price', '<=', (float) $filters['max_price']);
        }
        if (!empty($filters['min_price'])) {
            $query->where('price', '>=', (float) $filters['min_price']);
        }

        // Location filter (search in address)
        if (!empty($filters['location'])) {
            $location = $filters['location'];
            $query->where('address', 'ilike', "%{$location}%");
        }

        // Type filter
        if (!empty($filters['type'])) {
            $type = $filters['type'];
            $query->where(function ($q) use ($type) {
                $q->where('type', 'ilike', "%{$type}%")
                  ->orWhere('name', 'ilike', "%{$type}%");
            });
        }

        // Category filter
        if (!empty($filters['category'])) {
            $category = $filters['category'];
            $query->whereHas('category', function ($q) use ($category) {
                $q->where('name', 'ilike', "%{$category}%")
                  ->orWhere('slug', 'ilike', "%{$category}%");
            });
        }

        // Status filter
        if (!empty($filters['status'])) {
            $query->where('status', 'ilike', "%{$filters['status']}%");
        }

        // Facilities filter (search in room detail)
        if (!empty($filters['facilities']) && is_array($filters['facilities'])) {
            foreach ($filters['facilities'] as $facility) {
                $query->whereHas('detail', function ($q) use ($facility) {
                    $q->whereJsonContains('facilities', $facility)
                      ->orWhere('facilities', 'ilike', "%{$facility}%");
                });
            }
        }

        // Sorting
        $sort = $filters['sort'] ?? 'created_at';
        $order = $filters['order'] ?? 'desc';
        $allowedSorts = ['price', 'rating', 'created_at', 'reviews_count'];
        if (in_array($sort, $allowedSorts)) {
            $query->orderBy($sort, $order === 'asc' ? 'asc' : 'desc');
        }

        $rooms = $query->limit($this->maxRoomResults)->get();

        // Format room results for response
        return $rooms->map(function ($room) {
            $formatUrl = function ($url) {
                if (empty($url) || !is_string($url)) return $url;
                if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                    return $url;
                }
                return url(ltrim($url, '/'));
            };

            return [
                'id' => $room->id,
                'name' => $room->name,
                'type' => $room->type,
                'price' => (float) $room->price,
                'price_period' => $room->price_period,
                'status' => $room->status,
                'rating' => (float) $room->rating,
                'reviews_count' => (int) $room->reviews_count,
                'address' => $room->address,
                'image' => $formatUrl($room->image),
                'is_negotiable' => (bool) $room->is_negotiable,
                'is_featured' => (bool) $room->is_featured,
                'listing_type' => $room->listing_type ?? 'standard',
                'available_units' => (int) ($room->available_units ?? 1),
                'is_available' => (bool) (($room->available_units ?? 1) > 0),
                'category' => $room->category ? [
                    'id' => $room->category->id,
                    'name' => $room->category->name,
                ] : null,
                'landlord' => $room->user ? [
                    'name' => $room->user->name,
                    'phone' => $room->user->phone,
                ] : null,
                // Deep link for mobile app navigation (1-click to room detail)
                'deep_link' => "roomdz://room/{$room->id}",
                'api_url' => url("/api/room/{$room->id}"),
                'action' => [
                    'type' => 'navigate_to_room',
                    'room_id' => $room->id,
                    'screen' => 'RoomDetail',
                ],
            ];
        })->toArray();
    }

    /**
     * Get conversation context (previous messages) for AI.
     */
    private function getConversationContext(ChatConversation $conversation): array
    {
        return $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->limit($this->maxHistory)
            ->get()
            ->reverse()
            ->map(fn ($msg) => [
                'role' => $msg->role,
                'content' => $msg->content,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Generate a short title from the first user message.
     */
    private function generateTitle(string $message): string
    {
        $title = mb_substr($message, 0, 50);
        if (mb_strlen($message) > 50) {
            $title .= '...';
        }
        return $title;
    }

    /**
     * Get default suggestions based on detected language.
     */
    public function getDefaultSuggestions(?string $lang = null): array
    {
        if ($lang === 'en') {
            return config('chatbot.default_suggestions.en', []);
        }
        return config('chatbot.default_suggestions.km', []);
    }

    /**
     * Generate a welcome message for new conversations.
     */
    public function getWelcomeMessage(?string $lang = null): array
    {
        $isKhmer = $lang !== 'en';

        if ($isKhmer) {
            return [
                'content' => "ស្វាគមន៍មកកាន់ RoomDz! 🏠✨\n\nខ្ញុំជាជំនួយការស្វែងរកបន្ទប់ជួលដ៏ល្អរបស់អ្នក។ អ្នកអាចសួរខ្ញុំអំពី:\n\n🔍 ស្វែងរកបន្ទប់ — \"បន្ទប់តម្លៃក្រោម \$200 នៅ BKK\"\n📍 ស្វែងរកតាមទីតាំង — \"បន្ទប់នៅ Toul Kork\"\n💡 ទិន្នន័យបន្ទប់ — \"បន្ទប់មាន WiFi និង AC\"\n💰 ប្រៀបធៀបតម្លៃ — \"បន្ទប់ថោកបំផុត\"\n\nសូមសួរខ្ញុំអ្វីក៏បាន! 😊",
                'suggestions' => config('chatbot.default_suggestions.km', []),
            ];
        }

        return [
            'content' => "Welcome to RoomDz! 🏠✨\n\nI'm your room-finding assistant. You can ask me about:\n\n🔍 Room Search — \"Rooms under \$200 in BKK\"\n📍 Location — \"Rooms in Toul Kork\"\n💡 Facilities — \"Rooms with WiFi and AC\"\n💰 Pricing — \"Cheapest rooms available\"\n\nFeel free to ask me anything! 😊",
            'suggestions' => config('chatbot.default_suggestions.en', []),
        ];
    }

    /**
     * Detect if text contains Khmer characters.
     */
    private function isKhmerText(string $text): bool
    {
        return (bool) preg_match('/[\x{1780}-\x{17FF}]/u', $text);
    }

    /**
     * Fallback response when AI is unavailable.
     */
    private function getFallbackResponse(string $userMessage): array
    {
        $isKhmer = $this->isKhmerText($userMessage);

        return [
            'intent' => 'general',
            'filters' => null,
            'reply' => $isKhmer
                ? "សុំទោស! ខ្ញុំកំពុងមានបញ្ហាបច្ចេកទេសបន្តិច។ 😅 សូមព្យាយាមម្តងទៀតក្នុងពេលបន្តិចទៀត ឬអ្នកអាចប្រើមុខងារស្វែងរកដោយផ្ទាល់។"
                : "Sorry! I'm experiencing a technical issue right now. 😅 Please try again in a moment, or you can use the search feature directly.",
            'suggestions' => $isKhmer
                ? config('chatbot.default_suggestions.km')
                : config('chatbot.default_suggestions.en'),
        ];
    }

    /**
     * Find or create a conversation by session_id or user_id.
     */
    public function findOrCreateConversation(?int $userId, ?string $sessionId, ?int $conversationId = null): ChatConversation
    {
        // If conversation_id provided, find it
        if ($conversationId) {
            $conversation = ChatConversation::find($conversationId);
            if ($conversation) {
                // Verify ownership
                if ($userId && $conversation->user_id === $userId) {
                    return $conversation;
                }
                if ($sessionId && $conversation->session_id === $sessionId) {
                    return $conversation;
                }
            }
        }

        // Create new conversation
        return ChatConversation::create([
            'user_id' => $userId,
            'session_id' => $sessionId ?? ('guest_' . uniqid()),
            'last_activity_at' => now(),
        ]);
    }
}
