<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\ConversationRepository;
use App\Repositories\MessageRepository;
use App\Services\Business\ConversationManager;
use App\Services\Business\MessageDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InboxController extends Controller
{
    public function __construct(
        private readonly ConversationRepository $conversationRepository,
        private readonly MessageRepository $messageRepository,
        private readonly ConversationManager $conversationManager,
    ) {}

    public function index(Request $request)
    {
        /** @var View $view */
        $view = view('admin.inbox.index');

        if ($request->header('X-PJAX')) {
            $sections = $view->renderSections();

            return $sections['content'] ?? $view->render();
        }

        return $view;
    }

    // ── JSON API endpoints ──────────────────────────────────────────

    public function conversations(Request $request): JsonResponse
    {
        $filters = array_filter([
            'platform' => $request->query('platform') !== 'all' ? $request->query('platform') : null,
            'status'   => $request->query('status') !== 'all' ? $request->query('status') : null,
            'search'   => trim((string) $request->query('search', '')) !== '' ? trim((string) $request->query('search')) : null,
        ]);

        $conversations = $this->conversationRepository->getActiveConversations($filters, 30);
        $data = collect($conversations->items())->map(fn ($c) => [
            'id'              => $c->id,
            'customer_name'   => $c->customer?->name ?? 'Unknown',
            'customer_avatar' => $c->customer?->avatar_url,
            'customer_email'  => $c->customer?->email,
            'customer_phone'  => $c->customer?->phone,
            'platform'        => $c->platform,
            'status'          => $c->status,
            'priority'        => $c->priority ?? 'normal',
            'ai_mode'         => $c->ai_mode ?? 'off',
            'unread_count'    => (int) $c->unread_count,
            'last_message'    => $c->latestMessage ? [
                'content'    => mb_substr((string) $c->latestMessage->content, 0, 80),
                'sender'     => $c->latestMessage->sender_type,
                'created_at' => $c->latestMessage->created_at?->diffForHumans(),
            ] : null,
            'last_message_at' => $c->last_message_at?->diffForHumans(),
        ])->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page'    => $conversations->lastPage(),
                'total'        => $conversations->total(),
            ],
        ]);
    }

    public function messages(Request $request, int $conversationId): JsonResponse
    {
        $conversation = $this->conversationRepository->findForChat($conversationId);

        if (!$conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        $messages = $this->messageRepository->getConversationMessages($conversationId, 60);

        // Mark as read
        if ($conversation->unread_count > 0) {
            $this->conversationRepository->markAsRead($conversationId);
            $this->messageRepository->markConversationMessagesAsRead($conversationId);
        }

        return response()->json([
            'conversation' => [
                'id'              => $conversation->id,
                'customer_name'   => $conversation->customer?->name ?? 'Unknown',
                'customer_avatar' => $conversation->customer?->avatar_url,
                'customer_email'  => $conversation->customer?->email,
                'customer_phone'  => $conversation->customer?->phone,
                'platform'        => $conversation->platform,
                'status'          => $conversation->status,
                'priority'        => $conversation->priority ?? 'normal',
                'ai_mode'         => $conversation->ai_mode ?? 'off',
                'is_ai_enabled'   => (bool) $conversation->is_ai_enabled,
            ],
            'messages' => $messages->map(fn ($m) => [
                'id'              => $m->id,
                'sender_type'     => $m->sender_type,
                'sender_name'     => $m->sender_name,
                'content'         => $m->content,
                'media_url'       => $m->media_url,
                'media_type'      => $m->media_type,
                'delivery_status' => $m->delivery_status,
                'created_at'      => $m->created_at?->format('H:i'),
                'created_date'    => $m->created_at?->format('M d, Y'),
                'is_customer'     => $m->sender_type === 'customer',
                'is_bot'          => $m->sender_type === 'bot',
            ]),
        ]);
    }

    public function sendMessage(Request $request, int $conversationId): JsonResponse
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $conversation = $this->conversationRepository->findForChat($conversationId);

        if (!$conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        try {
            $message = app(MessageDispatcher::class)->sendOutgoingMessage(
                $conversation,
                trim($data['content']),
                null,
                $request->user(),
                'admin'
            );

            if (!$message) {
                return response()->json(['error' => 'Failed to send message'], 500);
            }

            // Broadcast to other admins
            broadcast(new \App\Events\MessageReceived($message, $conversation, $conversation->customer, $conversation->platform))->toOthers();

            return response()->json([
                'message' => [
                    'id'              => $message->id,
                    'sender_type'     => 'admin',
                    'sender_name'     => $request->user()->name,
                    'content'         => $message->content,
                    'delivery_status' => $message->delivery_status,
                    'created_at'      => $message->created_at?->format('H:i'),
                    'created_date'    => $message->created_at?->format('M d, Y'),
                    'is_customer'     => false,
                    'is_bot'          => false,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to send: ' . $e->getMessage()], 500);
        }
    }

    public function updateStatus(Request $request, int $conversationId): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,archived,closed'],
        ]);

        $this->conversationRepository->updateStatus($conversationId, $data['status']);

        return response()->json(['ok' => true]);
    }

    public function updatePriority(Request $request, int $conversationId): JsonResponse
    {
        $data = $request->validate([
            'priority' => ['required', 'in:low,normal,high,urgent'],
        ]);

        $this->conversationRepository->updatePriority($conversationId, $data['priority']);

        return response()->json(['ok' => true]);
    }

    public function toggleAi(int $conversationId): JsonResponse
    {
        $success = $this->conversationManager->toggleAiMode($conversationId);

        return response()->json(['ok' => $success]);
    }

    public function markRead(int $conversationId): JsonResponse
    {
        $conversation = $this->conversationRepository->findForChat($conversationId);

        if (!$conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        $this->conversationRepository->markAsRead($conversationId);
        $this->messageRepository->markConversationMessagesAsRead($conversationId);

        return response()->json([
            'ok' => true,
            'conversation_id' => $conversationId,
            'unread' => 0,
        ]);
    }

    public function counts(): JsonResponse
    {
        return response()->json([
            'unread'     => $this->conversationRepository->getUnreadCount(),
            'unassigned' => $this->conversationRepository->getUnassignedCount(),
        ]);
    }
}
