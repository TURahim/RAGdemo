<?php

namespace BookStack\AI\Controllers;

use BookStack\AI\Services\ChatService;
use BookStack\Http\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for AI chat API endpoints.
 */
class AIController extends Controller
{
    public function __construct(
        private ChatService $chatService
    ) {
    }

    /**
     * Process a chat message.
     */
    public function chat(Request $request): JsonResponse
    {
        $this->preventGuestAccess();

        if (!config('ai.enabled')) {
            return response()->json([
                'success' => false,
                'error' => 'AI features are currently disabled',
            ], 503);
        }

        $request->validate([
            'query' => 'required|string|max:2000',
            'session_id' => 'nullable|string|max:64',
        ]);

        $sessionId = $request->input('session_id', bin2hex(random_bytes(16)));

        try {
            $response = $this->chatService->chat(
                $request->input('query'),
                user(),
                $sessionId
            );

            return response()->json([
                'success' => true,
                'session_id' => $sessionId,
                ...$response,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Unable to process your request. Please try again.',
            ], 500);
        }
    }

    /**
     * Submit feedback for a message.
     */
    public function feedback(Request $request): JsonResponse
    {
        $this->preventGuestAccess();

        $request->validate([
            'message_id' => 'required|integer',
            'feedback' => 'required|in:positive,negative',
        ]);

        $success = $this->chatService->submitFeedback(
            $request->input('message_id'),
            user(),
            $request->input('feedback')
        );

        if (!$success) {
            return response()->json([
                'success' => false,
                'error' => 'Unable to submit feedback',
            ], 403);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Get conversation history.
     */
    public function history(Request $request): JsonResponse
    {
        $this->preventGuestAccess();

        $request->validate([
            'session_id' => 'required|string|max:64',
        ]);

        $history = $this->chatService->getHistory(
            user(),
            $request->input('session_id')
        );

        return response()->json([
            'success' => true,
            'messages' => $history,
        ]);
    }

    /**
     * Clear conversation session.
     */
    public function clearSession(Request $request): JsonResponse
    {
        $this->preventGuestAccess();

        $request->validate([
            'session_id' => 'required|string|max:64',
        ]);

        $this->chatService->clearSession(
            user()->id,
            $request->input('session_id')
        );

        return response()->json(['success' => true]);
    }

    /**
     * Get AI feature status.
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'enabled' => config('ai.enabled', false),
            'rate_limits' => [
                'per_minute' => config('ai.rate_limits.per_minute'),
                'per_day' => config('ai.rate_limits.per_day'),
            ],
        ]);
    }
}

