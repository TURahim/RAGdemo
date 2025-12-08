<?php

namespace BookStack\AI\Services;

use BookStack\AI\Models\AIConversation;
use BookStack\AI\Models\AIMessage;
use BookStack\Entities\Models\Page;
use BookStack\Users\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service for handling AI chat interactions.
 */
class ChatService
{
    /**
     * Send a chat message and get AI response.
     *
     * @throws Exception
     */
    public function chat(string $query, User $user, string $sessionId): array
    {
        $startTime = microtime(true);

        // Get or create conversation
        $conversation = AIConversation::firstOrCreate(
            ['user_id' => $user->id, 'session_id' => $sessionId],
            ['started_at' => now()]
        );

        // Get user's allowed entity IDs based on permissions
        $allowedEntityIds = $this->getAllowedEntityIds($user);

        // Call RAG service
        $response = $this->callRagService($query, $user->id, $sessionId, $allowedEntityIds);

        $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

        // Save user message
        AIMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $query,
        ]);

        // Save assistant message
        $assistantMessage = AIMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $response['answer'],
            'citations' => $response['citations'],
            'confidence' => $response['confidence'],
            'latency_ms' => $latencyMs,
        ]);

        // Update conversation stats
        $conversation->incrementMessageCount(2);

        // Add URLs to citations
        $citations = $this->formatCitations($response['citations']);

        return [
            'message_id' => $assistantMessage->id,
            'answer' => $response['answer'],
            'citations' => $citations,
            'confidence' => $response['confidence'],
            'latency_ms' => $latencyMs,
        ];
    }

    /**
     * Call the RAG service API.
     *
     * @throws Exception
     */
    private function callRagService(string $query, int $userId, string $sessionId, array $allowedEntityIds): array
    {
        $url = config('ai.rag_service.url');
        $timeout = config('ai.rag_service.timeout', 30);

        try {
            $response = Http::timeout($timeout)->post("{$url}/chat", [
                'query' => $query,
                'user_id' => $userId,
                'session_id' => $sessionId,
                'allowed_entity_ids' => $allowedEntityIds,
            ]);

            if (!$response->successful()) {
                Log::error('RAG service error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new Exception('RAG service error: ' . $response->status());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('RAG service connection failed', [
                'error' => $e->getMessage(),
            ]);
            throw new Exception('Unable to connect to AI service');
        }
    }

    /**
     * Get entity IDs the user has permission to view.
     */
    private function getAllowedEntityIds(User $user): array
    {
        // Get all page IDs the user can view through joint_permissions
        return DB::table('joint_permissions')
            ->whereIn('role_id', $user->roles->pluck('id'))
            ->where('entity_type', 'page')
            ->where('status', '>=', 1)  // Has view permission
            ->pluck('entity_id')
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Format citations with URLs.
     */
    private function formatCitations(array $citations): array
    {
        return array_map(function ($citation) {
            $page = Page::find($citation['entity_id']);
            return [
                ...$citation,
                'url' => $page?->getUrl(),
            ];
        }, $citations);
    }

    /**
     * Submit feedback for a message.
     */
    public function submitFeedback(int $messageId, User $user, string $feedback): bool
    {
        $message = AIMessage::findOrFail($messageId);

        // Verify ownership
        if ($message->conversation->user_id !== $user->id) {
            return false;
        }

        $message->update(['feedback' => $feedback]);
        return true;
    }

    /**
     * Get conversation history.
     */
    public function getHistory(User $user, string $sessionId): array
    {
        $conversation = AIConversation::where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->first();

        if (!$conversation) {
            return [];
        }

        return $conversation->messages->map(function ($message) {
            return [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'citations' => $message->hasCitations() ? $this->formatCitations($message->citations) : [],
                'confidence' => $message->confidence,
                'created_at' => $message->created_at->toISOString(),
            ];
        })->toArray();
    }

    /**
     * Clear session memory in RAG service.
     */
    public function clearSession(int $userId, string $sessionId): void
    {
        try {
            Http::post(config('ai.rag_service.url') . '/clear-session', [
                'user_id' => $userId,
                'session_id' => $sessionId,
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to clear RAG session', [
                'user_id' => $userId,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

