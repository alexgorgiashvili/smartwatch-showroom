<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\AiSuggestionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class SuggestionController extends Controller
{
    protected AiSuggestionService $aiSuggestionService;

    public function __construct(AiSuggestionService $aiSuggestionService)
    {
        $this->aiSuggestionService = $aiSuggestionService;
        $this->middleware('auth');
    }

    /**
     * Get AI-powered suggestions for a conversation
     *
     * POST /admin/inbox/{conversationId}/suggestion
     *
     * @param string $conversationId
     * @return \Illuminate\Http\JsonResponse
     */
    public function suggest(string $conversationId)
    {
        try {
            // Rate limiting: 10 suggestions per minute per user
            $key = 'suggestions:' . auth()->id();
            if (RateLimiter::tooManyAttempts($key, 10)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many suggestions requested. Please wait a moment.',
                ], 429);
            }

            RateLimiter::hit($key, 60); // 60 second window

            // Find conversation
            $conversation = Conversation::with('customer')->findOrFail($conversationId);

            // Get the latest customer message
            $latestCustomerMessage = $conversation->messages()
                ->where('sender_type', 'customer')
                ->latest('created_at')
                ->first();

            if (!$latestCustomerMessage) {
                return response()->json([
                    'success' => false,
                    'message' => 'No customer message found in this conversation.',
                ], 400);
            }

            // Generate suggestions
            $suggestions = $this->aiSuggestionService->generateSuggestions(
                $conversation,
                $latestCustomerMessage,
                3
            );

            if (!$suggestions || empty($suggestions)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not generate suggestions. Please try again later.',
                ], 503);
            }

            Log::info('AI suggestions generated', [
                'conversation_id' => $conversation->id,
                'admin_id' => auth()->id(),
                'suggestion_count' => count($suggestions),
            ]);

            return response()->json([
                'success' => true,
                'suggestions' => $suggestions,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error generating suggestions', [
                'exception' => $e->getMessage(),
                'conversation_id' => $conversationId,
                'admin_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while generating suggestions.',
            ], 500);
        }
    }

    /**
     * Batch generate suggestions for multiple conversations
     *
     * POST /admin/inbox/suggestions/batch
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function batch(Request $request)
    {
        try {
            // Validate request
            $request->validate([
                'conversation_ids' => 'required|array|max:10',
                'conversation_ids.*' => 'integer|exists:conversations,id',
            ]);

            $conversationIds = $request->input('conversation_ids');

            // Rate limiting: max 10 conversations per batch per minute
            $key = 'batch_suggestions:' . auth()->id();
            if (RateLimiter::tooManyAttempts($key, 5)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many batch requests. Please wait a moment.',
                ], 429);
            }

            RateLimiter::hit($key, 60);

            $results = [];
            $successCount = 0;
            $failureCount = 0;

            foreach ($conversationIds as $conversationId) {
                try {
                    $conversation = Conversation::with('customer')->findOrFail($conversationId);

                    // Get the latest customer message
                    $latestCustomerMessage = $conversation->messages()
                        ->where('sender_type', 'customer')
                        ->latest('created_at')
                        ->first();

                    if (!$latestCustomerMessage) {
                        $results[$conversationId] = null;
                        $failureCount++;
                        continue;
                    }

                    // Generate suggestions
                    $suggestions = $this->aiSuggestionService->generateSuggestions(
                        $conversation,
                        $latestCustomerMessage,
                        3
                    );

                    if ($suggestions && !empty($suggestions)) {
                        $results[$conversationId] = $suggestions;
                        $successCount++;
                    } else {
                        $results[$conversationId] = null;
                        $failureCount++;
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to generate suggestions for conversation in batch', [
                        'conversation_id' => $conversationId,
                        'exception' => $e->getMessage(),
                    ]);
                    $results[$conversationId] = null;
                    $failureCount++;
                }
            }

            Log::info('Batch suggestions generated', [
                'admin_id' => auth()->id(),
                'total_conversations' => count($conversationIds),
                'successful' => $successCount,
                'failed' => $failureCount,
            ]);

            return response()->json([
                'success' => true,
                'results' => $results,
                'stats' => [
                    'total' => count($conversationIds),
                    'successful' => $successCount,
                    'failed' => $failureCount,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request parameters.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in batch suggestions', [
                'exception' => $e->getMessage(),
                'admin_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while generating batch suggestions.',
            ], 500);
        }
    }
}
