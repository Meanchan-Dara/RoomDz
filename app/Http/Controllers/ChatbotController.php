<?php

namespace App\Http\Controllers;

use App\Http\Resources\ChatMessageResource;
use App\Models\ChatConversation;
use App\Services\ChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatbotController extends Controller
{
    private ChatbotService $chatbotService;

    public function __construct(ChatbotService $chatbotService)
    {
        $this->chatbotService = $chatbotService;
    }

    /**
     * Send a message to the chatbot and get a response.
     *
     * POST /api/chatbot/message
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:1000',
            'conversation_id' => 'nullable|integer|exists:chat_conversations,id',
            'session_id' => 'nullable|string|max:255',
        ]);

        $userId = auth('api')->user()?->id ?? $request->user()?->id;
        $sessionId = $validated['session_id'] ?? $request->header('X-Session-Id') ?? null;

        // Ensure we have either a user or a session
        if (!$userId && !$sessionId) {
            $sessionId = 'guest_' . uniqid();
        }

        // Find or create conversation
        $conversation = $this->chatbotService->findOrCreateConversation(
            $userId,
            $sessionId,
            $validated['conversation_id'] ?? null
        );

        // Process message through AI
        $result = $this->chatbotService->processMessage(
            $validated['message'],
            $conversation
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Get suggested questions for the chatbot.
     *
     * POST /api/chatbot/suggestions
     */
    public function suggestions(Request $request): JsonResponse
    {
        $lang = $request->input('lang', 'km');

        $welcome = $this->chatbotService->getWelcomeMessage($lang);

        return response()->json([
            'success' => true,
            'data' => [
                'welcome_message' => $welcome['content'],
                'suggestions' => $welcome['suggestions'],
            ],
        ]);
    }

    /**
     * List all conversations for the authenticated user.
     *
     * GET /api/chatbot/conversations
     */
    public function conversations(Request $request): JsonResponse
    {
        $user = auth('api')->user() ?? $request->user();

        $conversations = ChatConversation::where('user_id', $user->id)
            ->with('latestMessage')
            ->orderBy('last_activity_at', 'desc')
            ->paginate(20);

        $data = collect($conversations->items())->map(function ($conv) {
            return [
                'id' => $conv->id,
                'title' => $conv->title,
                'last_message' => $conv->latestMessage ? [
                    'content' => mb_substr($conv->latestMessage->content, 0, 100),
                    'role' => $conv->latestMessage->role,
                ] : null,
                'last_activity_at' => $conv->last_activity_at?->toISOString(),
                'created_at' => $conv->created_at?->toISOString(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'total' => $conversations->total(),
            ],
        ]);
    }

    /**
     * Get the chat history for a specific conversation.
     *
     * GET /api/chatbot/conversations/{id}
     */
    public function history(Request $request, string $id): JsonResponse
    {
        $user = auth('api')->user() ?? $request->user();

        $conversation = ChatConversation::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $messages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'data' => [
                'conversation' => [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                    'created_at' => $conversation->created_at?->toISOString(),
                ],
                'messages' => ChatMessageResource::collection($messages),
            ],
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    /**
     * Delete a conversation and all its messages.
     *
     * DELETE /api/chatbot/conversations/{id}
     */
    public function deleteConversation(Request $request, string $id): JsonResponse
    {
        $user = auth('api')->user() ?? $request->user();

        $conversation = ChatConversation::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $conversation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Conversation deleted successfully.',
        ]);
    }
}
