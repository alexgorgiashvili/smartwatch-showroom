<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\OmnichannelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    protected OmnichannelService $omnichannelService;

    public function __construct(OmnichannelService $omnichannelService)
    {
        $this->omnichannelService = $omnichannelService;
    }

    /**
     * Store - Send a reply message
     *
     * POST /admin/inbox/{conversation}/messages
     * Accepts JSON: { content: string, media_url?: string }
     *
     * @param Conversation $conversation
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store($conversationId, Request $request)
    {
        try {
            // Find conversation
            $conversation = Conversation::findOrFail($conversationId);

            // Validate input
            $validated = $request->validate([
                'content' => 'required|string|max:5000',
                'media_url' => 'nullable|url',
            ]);

            $adminId = Auth::id();

            // Call OmnichannelService to send reply
            $message = $this->omnichannelService->sendReply(
                $conversation->id,
                $adminId,
                $validated['content'],
                $validated['media_url'] ?? null
            );

            if (!$message) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send message. Please try again.',
                ], 422);
            }

            Log::info('Admin message sent', [
                'message_id' => $message->id,
                'conversation_id' => $conversation->id,
                'admin_id' => $adminId,
                'platform' => $conversation->platform,
            ]);

            return response()->json([
                'success' => true,
                'message' => $message,
                'id' => $message->id,
                'content' => $message->content,
                'sender_type' => $message->sender_type,
                'sender_name' => $message->sender_name,
                'created_at' => $message->created_at,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error sending message', [
                'conversation_id' => $conversationId,
                'admin_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error sending message',
            ], 500);
        }
    }

    /**
     * Delete - Remove a message (admin-only, recently sent)
     *
     * DELETE /admin/inbox/{conversation}/messages/{message}
     *
     * @param string $conversationId
     * @param string $messageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete($conversationId, $messageId)
    {
        try {
            $conversation = Conversation::findOrFail($conversationId);
            $message = Message::findOrFail($messageId);

            // Verify message belongs to this conversation
            if ($message->conversation_id != $conversation->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message does not belong to this conversation',
                ], 422);
            }

            // Verify message is from current admin
            $adminId = Auth::id();
            if ($message->sender_type !== 'admin' || $message->sender_id != $adminId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only delete your own messages',
                ], 403);
            }

            // Verify message is less than 1 hour old
            $createdAt = $message->created_at;
            $now = now();
            $ageInSeconds = $now->diffInSeconds($createdAt);

            if ($ageInSeconds > 3600) { // 1 hour = 3600 seconds
                return response()->json([
                    'success' => false,
                    'message' => 'Messages can only be deleted within 1 hour of sending',
                ], 422);
            }

            // Delete the message
            $message->delete();

            Log::info('Admin message deleted', [
                'message_id' => $messageId,
                'conversation_id' => $conversation->id,
                'admin_id' => $adminId,
            ]);

            return response()->json([
                'success' => true,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Message or conversation not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error deleting message', [
                'conversation_id' => $conversationId,
                'message_id' => $messageId,
                'admin_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting message',
            ], 500);
        }
    }

    /**
     * Mark As Read - Mark a single message as read
     *
     * PATCH /admin/inbox/{conversation}/messages/{message}/read
     *
     * @param string $conversationId
     * @param string $messageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsRead($conversationId, $messageId)
    {
        try {
            $conversation = Conversation::findOrFail($conversationId);
            $message = Message::findOrFail($messageId);

            // Verify message belongs to this conversation
            if ($message->conversation_id != $conversation->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message does not belong to this conversation',
                ], 422);
            }

            // Mark as read
            $message->update(['read_at' => now()]);

            Log::info('Message marked as read', [
                'message_id' => $messageId,
                'conversation_id' => $conversation->id,
                'admin_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'read_at' => $message->read_at,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Message or conversation not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error marking message as read', [
                'conversation_id' => $conversationId,
                'message_id' => $messageId,
                'admin_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error marking message as read',
            ], 500);
        }
    }
}
