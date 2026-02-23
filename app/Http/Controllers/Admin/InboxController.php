<?php

namespace App\Http\Controllers\Admin;

use App\Events\ConversationStatusChanged;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class InboxController extends Controller
{
    /**
     * Index - Display paginated list of all conversations
     *
     * Parameters:
     * - status: active|archived|closed
     * - platform: facebook|instagram|whatsapp|messenger|home
     * - unread: true (only unread conversations)
     * - q: search term (customer name or message content)
     */
    public function index(Request $request)
    {
        $query = Conversation::with([
            'customer',
            'messages' => function ($query) {
                $query->latest('created_at')->limit(1);
            }
        ])->orderBy('last_message_at', 'desc');

        // Filter by status
        if ($request->filled('status')) {
            $status = $request->query('status');
            if (in_array($status, ['active', 'archived', 'closed'])) {
                $query->where('status', $status);
            }
        }

        // Filter by platform
        if ($request->filled('platform')) {
            $platform = $request->query('platform');
            if (in_array($platform, ['facebook', 'instagram', 'whatsapp', 'messenger', 'home'])) {
                $query->where('platform', $platform);
            }
        }

        // Filter by unread
        if ($request->boolean('unread')) {
            $query->where('unread_count', '>', 0);
        }

        // Search in customer name or message content
        if ($request->filled('q')) {
            $searchTerm = $request->query('q');
            $query->whereHas('customer', function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('email', 'like', "%{$searchTerm}%")
                  ->orWhere('phone', 'like', "%{$searchTerm}%");
            })
            ->orWhereHas('messages', function ($q) use ($searchTerm) {
                $q->where('content', 'like', "%{$searchTerm}%");
            });
        }

        $conversations = $query->paginate(20);

        // Pass filter params to view
        $filters = [
            'status' => $request->query('status'),
            'platform' => $request->query('platform'),
            'unread' => $request->query('unread'),
            'q' => $request->query('q'),
        ];

        return view('inbox.nobleui-inbox', [
            'conversations' => $conversations,
            'filters' => $filters,
        ]);
    }

    /**
     * Show - Display single conversation with all messages
     *
     * Eager-loads conversation, customer, and all messages ordered by created_at ASC
     * Marks conversation as read
     * Supports pagination: 50 messages per page
     */
    public function show($conversationId, Request $request)
    {
        $conversation = Conversation::with([
            'customer',
            'messages' => function ($query) {
                $query->orderBy('created_at', 'asc');
            }
        ])->findOrFail($conversationId);

        // Mark conversation as read
        if ($conversation->unread_count > 0) {
            $conversation->markAsRead();

            Log::info('Conversation marked as read', [
                'conversation_id' => $conversation->id,
                'admin_id' => Auth::id(),
            ]);
        }

        // Paginate messages
        $messages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->paginate(50);

        $messageCount = $conversation->messages()->count();

        return view('inbox.show', [
            'conversation' => $conversation,
            'customer' => $conversation->customer,
            'messages' => $messages,
            'messageCount' => $messageCount,
        ]);
    }

    /**
     * Mark conversation as read - AJAX endpoint
     */
    public function markConversationAsRead($conversationId)
    {
        $conversation = Conversation::findOrFail($conversationId);

        if ($conversation->unread_count > 0) {
            $conversation->markAsRead();

            Log::info('Conversation marked as read via AJAX', [
                'conversation_id' => $conversation->id,
                'admin_id' => Auth::id(),
            ]);
        }

        return response()->json([
            'success' => true,
            'unreadCount' => 0,
        ]);
    }

    /**
     * Update conversation status
     *
     * Accepts POST with 'status' parameter (active|archived|closed)
     * Broadcasts ConversationStatusChanged event
     */
    public function updateConversationStatus($conversationId, Request $request)
    {
        $request->validate([
            'status' => 'required|in:active,archived,closed',
        ]);

        $conversation = Conversation::findOrFail($conversationId);
        $oldStatus = $conversation->status;
        $newStatus = $request->input('status');

        // Update status
        $conversation->update(['status' => $newStatus]);

        // Broadcast event
        ConversationStatusChanged::dispatch(
            $conversation,
            $oldStatus,
            $newStatus
        );

        Log::info('Conversation status changed', [
            'conversation_id' => $conversation->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'admin_id' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'status' => $newStatus,
        ]);
    }

    /**
     * Search conversations
     *
     * GET parameters:
     * - q: search term
     * - platform: facebook|instagram|whatsapp
     *
     * Returns JSON array of matching conversations
     */
    public function search(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2',
            'platform' => 'nullable|in:facebook,instagram,whatsapp',
        ]);

        $searchTerm = $request->query('q');
        $platform = $request->query('platform');

        $query = Conversation::with('customer')
            ->orderBy('last_message_at', 'desc');

        // Filter by platform if provided
        if ($platform) {
            $query->where('platform', $platform);
        }

        // Search in customer names and message content
        $conversations = $query
            ->whereHas('customer', function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('email', 'like', "%{$searchTerm}%")
                  ->orWhere('phone', 'like', "%{$searchTerm}%");
            })
            ->orWhereHas('messages', function ($q) use ($searchTerm) {
                $q->where('content', 'like', "%{$searchTerm}%");
            })
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $conversations->items(),
            'pagination' => [
                'total' => $conversations->total(),
                'perPage' => $conversations->perPage(),
                'currentPage' => $conversations->currentPage(),
                'lastPage' => $conversations->lastPage(),
            ],
        ]);
    }

    /**
     * Get AI-powered suggestions for a conversation
     *
     * GET /admin/inbox/{conversation}/suggest-ai
     *
     * @param string $conversationId
     * @return \Illuminate\Http\JsonResponse
     */
    public function suggestAIResponse($conversationId)
    {
        try {
            // Get the suggestion service
            $aiSuggestionService = app(\App\Services\AiSuggestionService::class);

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
            $suggestions = $aiSuggestionService->generateSuggestions(
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
                'admin_id' => Auth::id(),
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
                'admin_id' => Auth::id(),
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
    public function batchSuggestions(Request $request)
    {
        try {
            // Validate request
            $request->validate([
                'conversation_ids' => 'required|array|max:10',
                'conversation_ids.*' => 'integer|exists:conversations,id',
            ]);

            $conversationIds = $request->input('conversation_ids');

            // Get the suggestion service
            $aiSuggestionService = app(\App\Services\AiSuggestionService::class);

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
                    $suggestions = $aiSuggestionService->generateSuggestions(
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
                'admin_id' => Auth::id(),
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
                'admin_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while generating batch suggestions.',
            ], 500);
        }
    }
}
