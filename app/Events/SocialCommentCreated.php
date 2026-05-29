<?php

namespace App\Events;

use App\Models\SocialComment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SocialCommentCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public SocialComment $comment;

    public function __construct(SocialComment $comment)
    {
        $this->comment = $comment;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('social.comments'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'social.comment.created';
    }

    public function broadcastWith(): array
    {
        return [
            'comment' => [
                'id' => $this->comment->id,
                'facebook_post_id' => $this->comment->facebook_post_id,
                'platform' => $this->comment->platform,
                'author_name' => $this->comment->author_name,
                'author_id' => $this->comment->author_id,
                'message' => $this->comment->message,
                'status' => $this->comment->status,
                'commented_at' => $this->comment->commented_at,
            ],
        ];
    }
}

