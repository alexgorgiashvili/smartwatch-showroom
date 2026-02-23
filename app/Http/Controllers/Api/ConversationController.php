<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Events\MessageReceived;
use App\Services\FacebookMessengerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ConversationController extends Controller
{
    /**
     * Get all conversations for authenticated user
     */
    public function index(Request $request)
    {
        $platform = $request->query('platform', 'all');

        $query = Conversation::with(['customer', 'messages'])
            ->orderByDesc('last_message_at');

        // Filter by platform if not 'all'
        if ($platform !== 'all') {
            $query->where('platform', $platform);
        }

        $conversations = $query->paginate(15);

        return response()->json($conversations);
    }

    /**
     * Get a specific conversation with all messages
     */
    public function show($id)
    {
        $conversation = Conversation::with(['customer', 'messages' => function ($query) {
            $query->orderBy('created_at', 'asc');
        }])->findOrFail($id);

        return response()->json([
            'conversation' => $conversation,
            'messages' => $conversation->messages
        ]);
    }

    /**
     * Send a message in a conversation
     */
    public function sendMessage(Request $request, $conversationId)
    {
        $validated = $request->validate([
            'content' => 'required|string|max:5000'
        ]);

        $conversation = Conversation::with('customer')->findOrFail($conversationId);

        // Create message in database
        $message = Message::create([
            'conversation_id' => $conversationId,
            'customer_id' => $conversation->customer_id,
            'sender_type' => 'admin',
            'sender_id' => Auth::id(),
            'sender_name' => Auth::user()->name,
            'content' => $validated['content'],
            'platform_message_id' => 'admin_' . uniqid(),
        ]);

        // If this is a Facebook Messenger conversation, send via Facebook API
        if (in_array($conversation->platform, ['messenger', 'facebook'])) {
            $customer = $conversation->customer;
            $platformUserIds = $customer->platform_user_ids ?? [];

            $messengerUserId = $platformUserIds['messenger'] ?? null;

            if ($messengerUserId) {
                $fbService = new FacebookMessengerService();

                // Send typing indicator first
                $fbService->sendTypingIndicator($messengerUserId, 'typing_on');

                // Send the actual message
                $result = $fbService->sendMessage($messengerUserId, $validated['content']);

                if (!$result['success']) {
                    Log::error('Failed to send message via Facebook', [
                        'conversation_id' => $conversationId,
                        'error' => $result['error']
                    ]);
                    // Don't fail the request, message is already saved in DB
                }
            } else {
                Log::warning('No messenger user ID found for customer', [
                    'customer_id' => $customer->id,
                    'conversation_id' => $conversationId
                ]);
            }
        }

        // Update conversation
        $conversation->update([
            'last_message_at' => now(),
        ]);

        // Broadcast event
        event(new MessageReceived(
            $message,
            $conversation,
            $conversation->customer,
            $conversation->platform
        ));

        return response()->json([
            'success' => true,
            'message' => $message
        ]);
    }

    /**
     * Mark conversation as read
     */
    public function markAsRead($conversationId)
    {
        $conversation = Conversation::findOrFail($conversationId);

        $conversation->update([
            'unread_count' => 0
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Conversation marked as read'
        ]);
    }

    /**
     * Update conversation status
     */
    public function updateStatus(Request $request, $conversationId)
    {
        $validated = $request->validate([
            'status' => 'required|in:active,archived,closed'
        ]);

        $conversation = Conversation::findOrFail($conversationId);

        $conversation->update([
            'status' => $validated['status']
        ]);

        return response()->json([
            'success' => true,
            'conversation' => $conversation
        ]);
    }

    /**
     * Toggle AI auto-reply for a conversation
     */
    public function toggleAi($conversationId)
    {
        $conversation = Conversation::findOrFail($conversationId);

        $newState = !$conversation->ai_enabled;

        $conversation->update([
            'ai_enabled' => $newState
        ]);

        Log::info('AI toggled for conversation', [
            'conversation_id' => $conversationId,
            'ai_enabled' => $newState
        ]);

        // If AI is being enabled, check for unanswered customer message and auto-reply
        if ($newState) {
            // Check if last message is from customer
            $lastMessage = $conversation->messages()
                ->orderBy('created_at', 'desc')
                ->first();

            if ($lastMessage && $lastMessage->sender_type === 'customer') {
                // Auto-reply to the existing message
                try {
                    $aiService = app(\App\Services\AiConversationService::class);
                    if ($aiService->shouldAutoReplyToConversation($conversation, $lastMessage)) {
                        $aiService->autoReply($conversation);
                    } else {
                        Log::info('AI selective policy skipped auto-reply on toggle', [
                            'conversation_id' => $conversationId,
                            'message_id' => $lastMessage->id,
                        ]);
                    }

                    Log::info('AI auto-reply triggered on toggle', [
                        'conversation_id' => $conversationId
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to auto-reply on toggle', [
                        'conversation_id' => $conversationId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'ai_enabled' => $newState,
            'message' => $newState ? 'AI auto-reply enabled' : 'AI auto-reply disabled'
        ]);
    }

    /**
     * Get AI suggestion for a response
     */
    public function aiSuggestion(Request $request, $conversationId)
    {
        $conversation = Conversation::with(['customer', 'messages' => function ($query) {
            $query->orderBy('created_at', 'desc')->limit(10);
        }])->findOrFail($conversationId);

        try {
            // Get the AI service
            $aiService = app(\App\Services\AiConversationService::class);

            // Generate suggestion
            $suggestion = $aiService->generateResponse($conversation);

            return response()->json([
                'success' => true,
                'suggestion' => $suggestion
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate AI suggestion', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate AI suggestion. Please try again.'
            ], 500);
        }
    }
}
