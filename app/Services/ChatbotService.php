<?php

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatbotService
{
    private string $apiKey;

    private string $model;

    private array $fallbackModels;

    private string $endpoint;

    private string $systemPrompt;

    private int $maxHistory;

    private int $maxRoomResults;

    public function __construct()
    {
        $this->apiKey = (string) (config('chatbot.gemini.api_key') ?? '');
        $this->model = (string) (config('chatbot.gemini.model') ?? 'gemini-flash-lite-latest');
        $this->fallbackModels = (array) (config('chatbot.gemini.fallback_models') ?? ['gemini-3.5-flash-lite', 'gemini-3.6-flash', 'gemini-flash-latest']);
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
        if ($aiResult['intent'] === 'search_room' && ! empty($aiResult['filters'])) {
            $rooms = $this->searchRooms($aiResult['filters']);
            $roomIds = collect($rooms)->pluck('id')->toArray();

            // If rooms found, enhance the AI reply with room context
            if (! empty($rooms)) {
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
            'room_results' => ! empty($roomIds) ? $roomIds : null,
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

        $payload = [
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
        ];

        $result = $this->executeGeminiRequest($payload);

        if ($result !== null) {
            if (isset($result['intent'])) {
                return $result;
            }

            if (isset($result['raw_text'])) {
                return [
                    'intent' => 'general',
                    'filters' => null,
                    'reply' => $result['raw_text'],
                    'suggestions' => $this->getDefaultSuggestions($this->isKhmerText($userMessage) ? 'km' : 'en'),
                ];
            }
        }

        // If all AI models failed, use smart local fallback to parse intent & search DB
        return $this->smartLocalFallback($userMessage);
    }

    /**
     * Execute Gemini API request with fallback models and retry on transient errors.
     */
    private function executeGeminiRequest(array $payload, int $timeout = 15): ?array
    {
        $modelsToTry = array_values(array_unique(array_filter(array_merge([$this->model], $this->fallbackModels))));

        foreach ($modelsToTry as $model) {
            $url = rtrim($this->endpoint, '/') . '/' . ltrim($model, '/') . ':generateContent?key=' . $this->apiKey;

            for ($attempt = 1; $attempt <= 2; $attempt++) {
                try {
                    $response = Http::timeout($timeout)->post($url, $payload);

                    if ($response->successful()) {
                        $data = $response->json();
                        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                        $parsed = json_decode($text, true);

                        if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                            return $parsed;
                        }

                        if (! empty($text)) {
                            return ['raw_text' => $text];
                        }
                    }

                    $status = $response->status();
                    Log::warning("Gemini model {$model} returned HTTP {$status} (attempt {$attempt})", [
                        'body' => mb_substr($response->body(), 0, 300),
                    ]);

                    // Transient errors (503 Service Unavailable / high demand, 429 Rate Limit)
                    if ($status === 503 || $status === 429) {
                        usleep(300000); // 300ms pause before retry or next model
                        continue;
                    }

                    // For client/not found errors (e.g. 404), move to next model immediately
                    break;
                } catch (\Throwable $e) {
                    Log::warning("Gemini request exception for model {$model}: " . $e->getMessage());
                    usleep(300000);
                }
            }
        }

        return null;
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
                'Please write a friendly, natural response presenting these rooms to the user. ' .
                "Keep the same language as the user's message (Khmer or English). " .
                'Include emojis for a lively feel. ' .
                'Respond in JSON format: {"intent": "search_room", "filters": null, "reply": "your response", "suggestions": ["suggestion1", "suggestion2", "suggestion3"]}';

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

            $payload = [
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
            ];

            $parsed = $this->executeGeminiRequest($payload, 10);
            if ($parsed && isset($parsed['reply'])) {
                $parsed['intent'] = 'search_room';
                $parsed['filters'] = $aiResult['filters'];

                return $parsed;
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to enhance response with AI: ' . $e->getMessage());
        }

        // Graceful fallback: synthesize a friendly room reply without technical error
        $count = count($rooms);
        $isKhmer = $this->isKhmerText($userMessage);
        $loc = ! empty($aiResult['filters']['location']) ? " នៅ {$aiResult['filters']['location']}" : '';
        $locEn = ! empty($aiResult['filters']['location']) ? " in {$aiResult['filters']['location']}" : '';

        $reply = $isKhmer
            ? "ខ្ញុំបានរកឃើញបន្ទប់ចំនួន {$count} ដែលស័ក្តិសមសម្រាប់អ្នក{$loc}! 🏠✨"
            : "Here are {$count} great available options{$locEn} for you! 🏠✨";

        return [
            'intent' => 'search_room',
            'filters' => $aiResult['filters'] ?? null,
            'reply' => $reply,
            'suggestions' => $this->getDefaultSuggestions($isKhmer ? 'km' : 'en'),
        ];
    }

    /**
     * Generate a helpful response when no rooms match the filters.
     */
    private function generateNoResultsResponse(string $userMessage, ?array $filters, array $history): array
    {
        $isKhmer = $this->isKhmerText($userMessage);

        $reply = $isKhmer
            ? 'សុំទោស! 😔 ខ្ញុំមិនរកឃើញបន្ទប់ដែលត្រូវនឹងលក្ខខណ្ឌរបស់អ្នកទេ។ សូមសាកល្បងផ្លាស់ប្តូរតម្រង (តម្លៃ, ទីតាំង) ឬសួរខ្ញុំដើម្បីជួយស្វែងរកបន្ទប់ផ្សេងទៀត! 🔍'
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
        $like = $this->caseInsensitiveLikeOperator();

        // Price filters
        if (! empty($filters['max_price'])) {
            $query->where('price', '<=', (float) $filters['max_price']);
        }
        if (! empty($filters['min_price'])) {
            $query->where('price', '>=', (float) $filters['min_price']);
        }

        // Location filter (search in address with common location variations)
        if (! empty($filters['location'])) {
            $location = $filters['location'];
            $locVariations = $this->getLocationVariations($location);
            $query->where(function ($q) use ($locVariations, $like) {
                foreach ($locVariations as $var) {
                    $q->orWhere('address', $like, "%{$var}%");
                }
            });
        }

        // Type filter
        if (! empty($filters['type'])) {
            $type = $filters['type'];
            $query->where(function ($q) use ($type, $like) {
                $q->where('type', $like, "%{$type}%")
                    ->orWhere('name', $like, "%{$type}%");
            });
        }

        // Category filter
        if (! empty($filters['category'])) {
            $category = $filters['category'];
            $query->whereHas('category', function ($q) use ($category, $like) {
                $q->where('name', $like, "%{$category}%")
                    ->orWhere('slug', $like, "%{$category}%");
            });
        }

        // Status filter
        if (! empty($filters['status'])) {
            $query->where('status', $like, "%{$filters['status']}%");
        }

        // Facilities filter (search in room detail with cross-platform JSON support)
        if (! empty($filters['facilities']) && is_array($filters['facilities'])) {
            $isPgsql = DB::connection()->getDriverName() === 'pgsql';
            foreach ($filters['facilities'] as $facility) {
                $terms = $this->getFacilitySearchTerms($facility);
                $query->whereHas('detail', function ($q) use ($terms, $like, $isPgsql) {
                    $q->where(function ($subQ) use ($terms, $like, $isPgsql) {
                        foreach ($terms as $term) {
                            if ($isPgsql) {
                                $subQ->orWhereRaw('CAST(facilities AS TEXT) ILIKE ?', [$term]);
                            } else {
                                $subQ->orWhere('facilities', $like, $term);
                            }
                        }
                    });
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
                if (empty($url) || ! is_string($url)) {
                    return $url;
                }
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

    private function geminiUrl(): string
    {
        return rtrim($this->endpoint, '/') . '/' . ltrim($this->model, '/') . ':generateContent?key=' . $this->apiKey;
    }

    private function caseInsensitiveLikeOperator(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
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
            ->map(fn($msg) => [
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
     * Smart local fallback to parse search intent and filters when AI is unavailable.
     */
    private function smartLocalFallback(string $userMessage): array
    {
        $isKhmer = $this->isKhmerText($userMessage);
        $lower = mb_strtolower($userMessage);

        // Check if message is a simple greeting
        if (preg_match('/^(hi|hello|hey|greetings|help|សួស្តី|ជំរាបសួរ)\b/iu', trim($userMessage))) {
            return [
                'intent' => 'general',
                'filters' => null,
                'reply' => $isKhmer
                    ? "ស្វាគមន៍មកកាន់ RoomDz! 🏠✨ តើខ្ញុំអាចជួយអ្នកស្វែងរកបន្ទប់បែបណាដែរ?"
                    : "Welcome to RoomDz! 🏠✨ How can I help you find your ideal room today?",
                'suggestions' => $this->getDefaultSuggestions($isKhmer ? 'km' : 'en'),
            ];
        }

        // Price extraction
        $maxPrice = null;
        $minPrice = null;
        if (preg_match('/(?:under|below|less\s+than|ក្រោម|<)\s*\$?(\d+)/i', $userMessage, $m)) {
            $maxPrice = (float) $m[1];
        } elseif (preg_match('/(?:over|above|more\s+than|លើស|លើសពី|>)\s*\$?(\d+)/i', $userMessage, $m)) {
            $minPrice = (float) $m[1];
        } elseif (preg_match('/(?:between|ចន្លោះពី)\s*\$?(\d+)\s*(?:and|to|ដល់|-)\s*\$?(\d+)/i', $userMessage, $m)) {
            $minPrice = (float) $m[1];
            $maxPrice = (float) $m[2];
        } elseif (preg_match('/\$(\d+)/', $userMessage, $m)) {
            $maxPrice = (float) $m[1];
        }

        // Location extraction
        $location = null;
        if (preg_match('/(bkk1?|bkk2?|bkk3?|boeung\s*keng\s*kang|បឹងកេងកង)/i', $lower)) {
            $location = 'BKK';
        } elseif (preg_match('/(toul\s*kork|tuol\s*kork|ទួលគោក)/i', $lower)) {
            $location = 'Toul Kork';
        } elseif (preg_match('/(tuol\s*tompoung|toul\s*tompoung|russian\s*market|ទួលទំពូង)/i', $lower)) {
            $location = 'Tuol Tompoung';
        } elseif (preg_match('/(daun\s*penh|doun\s*penh|ដូនពេញ)/i', $lower)) {
            $location = 'Daun Penh';
        } elseif (preg_match('/(chamkarmon|chamkar\s*mon|ចំការមន)/i', $lower)) {
            $location = 'Chamkarmon';
        } elseif (preg_match('/(sen\s*sok|សែនសុខ)/i', $lower)) {
            $location = 'Sen Sok';
        } elseif (preg_match('/(chbar\s*ampov|ច្បារអំពៅ)/i', $lower)) {
            $location = 'Chbar Ampov';
        } elseif (preg_match('/(7\s*makara|prampi\s*makara|៧មករា)/i', $lower)) {
            $location = '7 Makara';
        }

        // Facilities extraction
        $facilities = [];
        if (preg_match('/(wifi|wi-fi|internet|វ៉ាយហ្វាយ)/i', $lower)) {
            $facilities[] = 'WiFi';
        }
        if (preg_match('/(a[\/\.]?c|air\s*con|air\s*conditioning|ម៉ាស៊ីនត្រជាក់)/i', $lower)) {
            $facilities[] = 'AC';
        }
        if (preg_match('/(parking|park|ចំណត|ចំណតឡាន)/i', $lower)) {
            $facilities[] = 'Parking';
        }
        if (preg_match('/(elevator|lift|ជណ្តើរយន្ត)/i', $lower)) {
            $facilities[] = 'Elevator';
        }
        if (preg_match('/(balcony|យ៉រ)/i', $lower)) {
            $facilities[] = 'Balcony';
        }
        if (preg_match('/(gym|fitness|ហាត់ប្រាណ)/i', $lower)) {
            $facilities[] = 'Gym';
        }
        if (preg_match('/(pool|swimming\s*pool|អាងហែលទឹក)/i', $lower)) {
            $facilities[] = 'Pool';
        }

        // Sorting
        $sort = 'created_at';
        $order = 'desc';
        if (preg_match('/(cheap|cheapest|low\s*price|ថោក|ថោកបំផុត)/i', $lower)) {
            $sort = 'price';
            $order = 'asc';
        } elseif (preg_match('/(best|top|rating|popular|ល្អបំផុត)/i', $lower)) {
            $sort = 'rating';
            $order = 'desc';
        }

        // Check if query is looking for rooms
        $isRoomSearch = ! empty($location) || ! empty($facilities) || $maxPrice !== null || $minPrice !== null || preg_match('/(room|rooms|studio|apartment|house|បន្ទប់|ផ្ទះ)/i', $lower);

        if ($isRoomSearch) {
            return [
                'intent' => 'search_room',
                'filters' => [
                    'max_price' => $maxPrice,
                    'min_price' => $minPrice,
                    'location' => $location,
                    'type' => null,
                    'facilities' => $facilities,
                    'status' => 'AVAILABLE NOW',
                    'category' => null,
                    'sort' => $sort,
                    'order' => $order,
                ],
                'reply' => $isKhmer
                    ? 'ខ្ញុំកំពុងស្វែងរកបន្ទប់ដែលស័ក្តិសមសម្រាប់អ្នក... 🏠✨'
                    : 'Searching available rooms matching your request... 🏠✨',
                'suggestions' => $this->getDefaultSuggestions($isKhmer ? 'km' : 'en'),
            ];
        }

        // Default friendly fallback
        return [
            'intent' => 'general',
            'filters' => null,
            'reply' => $isKhmer
                ? 'តើអ្នកចង់ស្វែងរកបន្ទប់បែបណាដែរ? អ្នកអាចសួរអំពីទីតាំង (ឧ. Toul Kork, BKK), តម្លៃ (ឧ. ក្រោម $200), ឬឧបករណ៍ប្រើប្រាស់ (ឧ. មាន WiFi, AC)! 🏠✨'
                : "What kind of room are you looking for? You can ask by location (e.g. Toul Kork, BKK), price (e.g. under $200), or amenities (e.g. with WiFi, AC)! 🏠✨",
            'suggestions' => $this->getDefaultSuggestions($isKhmer ? 'km' : 'en'),
        ];
    }

    /**
     * Get variations for location search in Cambodia.
     */
    private function getLocationVariations(string $location): array
    {
        $loc = trim($location);
        $variations = [$loc];

        if (stripos($loc, 'BKK') !== false || stripos($loc, 'Boeung Keng Kang') !== false) {
            return ['BKK', 'Boeung Keng Kang', 'បឹងកេងកង'];
        }
        if (stripos($loc, 'Toul Kork') !== false || stripos($loc, 'Tuol Kork') !== false) {
            return ['Toul Kork', 'Tuol Kork', 'ទួលគោក'];
        }
        if (stripos($loc, 'Tuol Tompoung') !== false || stripos($loc, 'Toul Tompoung') !== false || stripos($loc, 'Russian Market') !== false) {
            return ['Tuol Tompoung', 'Toul Tompoung', 'Russian Market', 'ទួលទំពូង'];
        }
        if (stripos($loc, 'Daun Penh') !== false || stripos($loc, 'Doun Penh') !== false) {
            return ['Daun Penh', 'Doun Penh', 'ដូនពេញ'];
        }

        return $variations;
    }

    /**
     * Get search term variations for facilities.
     */
    private function getFacilitySearchTerms(string $facility): array
    {
        $clean = strtolower(trim($facility));
        $normalized = str_replace(['-', ' ', '_', '/'], '', $clean);

        if (in_array($normalized, ['wifi', 'internet', 'freewifi'])) {
            return ['%wi-fi%', '%wifi%', '%internet%'];
        }
        if (in_array($normalized, ['ac', 'aircon', 'aircondition', 'airconditioner'])) {
            return ['%a/c%', '%ac%', '%air%'];
        }
        if (in_array($normalized, ['parking', 'park', 'motorbikeparking', 'carparking'])) {
            return ['%parking%', '%park%'];
        }
        if (in_array($normalized, ['gym', 'fitness'])) {
            return ['%gym%', '%fitness%'];
        }
        if (in_array($normalized, ['pool', 'swimmingpool'])) {
            return ['%pool%'];
        }
        if (in_array($normalized, ['elevator', 'lift'])) {
            return ['%elevator%', '%lift%'];
        }
        if (in_array($normalized, ['balcony', 'terrace'])) {
            return ['%balcony%', '%terrace%'];
        }

        return ["%{$clean}%"];
    }

    /**
     * Find or create a conversation by session_id or user_id.
     */
    public function findOrCreateConversation(?int $userId, ?string $sessionId, ?int $conversationId = null): ChatConversation
    {
        // 1. If conversation_id provided, find it
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

        // 2. Check if a conversation already exists with this session_id
        if ($sessionId) {
            $existing = ChatConversation::where('session_id', $sessionId)->first();
            if ($existing) {
                // If the user just logged in, link user_id to the existing guest conversation
                if ($userId && !$existing->user_id) {
                    $existing->update(['user_id' => $userId]);
                }
                return $existing;
            }
        }

        // 3. Ensure the session_id is unique before creating a new record
        $finalSessionId = $sessionId;
        if (!$finalSessionId || ChatConversation::where('session_id', $finalSessionId)->exists()) {
            $finalSessionId = 'guest_' . uniqid() . '_' . bin2hex(random_bytes(4));
        }

        // 4. Create new conversation
        return ChatConversation::create([
            'user_id' => $userId,
            'session_id' => $finalSessionId,
            'last_activity_at' => now(),
        ]);
    }
}
