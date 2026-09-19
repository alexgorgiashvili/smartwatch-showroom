<?php

namespace App\Services;

use App\Models\ContentAuditEvent;
use App\Models\ContentItem;
use App\Models\ContentRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** The single state gate for owner review and agent submissions. */
class ContentStudioWorkflow
{
    private const TRANSITIONS = [
        'generated' => ['in_review', 'ready_for_owner', 'rejected'],
        'in_review' => ['changes_requested', 'ready_for_owner', 'rejected'],
        'changes_requested' => ['generated', 'in_review', 'ready_for_owner', 'rejected'],
        'ready_for_owner' => ['approved', 'changes_requested', 'rejected'],
        'approved' => ['scheduled', 'publishing', 'changes_requested'],
        'scheduled' => ['publishing', 'changes_requested'],
        'publishing' => ['published', 'failed'],
        'failed' => ['approved', 'scheduled', 'publishing', 'changes_requested'],
        'published' => [],
        'rejected' => ['generated', 'in_review'],
    ];

    public function submitRevision(ContentItem $item, array $payload, array $evidence = [], ?User $actor = null): ContentRevision
    {
        return DB::transaction(function () use ($item, $payload, $evidence, $actor) {
            $item->refresh();
            if (in_array($item->status, ['publishing', 'published'], true)) {
                throw ValidationException::withMessages(['item' => 'Published or publishing content cannot be revised. Create a new item.']);
            }
            $fingerprint = hash('sha256', json_encode([$item->id, $payload], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $existing = ContentRevision::where('fingerprint', $fingerprint)->first();
            if ($existing) return $existing;

            $revision = ContentRevision::create([
                'content_item_id' => $item->id,
                'revision_number' => ((int) $item->revisions()->max('revision_number')) + 1,
                'fingerprint' => $fingerprint,
                'payload' => $payload,
                'evidence' => $evidence,
                'validation' => $this->checklist($item->channel, $payload),
                'submitted_by_user_id' => $actor?->id,
                'submitted_at' => now(),
            ]);

            // A new revision is never silently approved. It invalidates any prior approval/schedule.
            $from = $item->status;
            $item->update([
                'current_revision_id' => $revision->id,
                'approved_revision_id' => null,
                'approved_at' => null,
                'scheduled_at' => null,
                'status' => 'ready_for_owner',
                'evidence' => $evidence,
                'verification_checked_at' => now(),
            ]);
            $this->audit($item, 'revision_submitted', $from, 'ready_for_owner', $revision, $actor, ['approval_invalidated' => true]);
            return $revision;
        });
    }

    public function transition(ContentItem $item, string $to, ?User $actor = null, ?string $notes = null, array $metadata = []): ContentItem
    {
        if (!in_array($to, ContentItem::STATES, true) || !in_array($to, self::TRANSITIONS[$item->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => "Invalid Content Studio transition from {$item->status} to {$to}."]);
        }
        return DB::transaction(function () use ($item, $to, $actor, $notes, $metadata) {
            $item->refresh();
            $from = $item->status;
            $item->update(array_filter([
                'status' => $to,
                'reviewer_notes' => $notes,
                'review_metadata' => $metadata ?: null,
                'approved_revision_id' => $to === 'approved' ? $item->current_revision_id : null,
                'approved_at' => $to === 'approved' ? now() : null,
            ], fn ($v) => $v !== null));
            $this->audit($item, 'state_changed', $from, $to, $item->currentRevision, $actor, $metadata);
            return $item->fresh();
        });
    }

    public function approve(ContentItem $item, User $owner, ?\DateTimeInterface $scheduledAt = null): ContentItem
    {
        if (!$item->current_revision_id || $item->status !== 'ready_for_owner') {
            throw ValidationException::withMessages(['item' => 'Only a submitted current revision can be approved.']);
        }
        $approved = $this->transition($item, 'approved', $owner, null, ['approved_revision_id' => $item->current_revision_id]);
        if ($scheduledAt) {
            $approved->update(['scheduled_at' => $scheduledAt]);
            return $this->transition($approved->fresh(), 'scheduled', $owner, null, ['timezone' => config('app.timezone')]);
        }
        return $approved;
    }

    public function audit(ContentItem $item, string $event, ?string $from, ?string $to, ?ContentRevision $revision = null, ?User $actor = null, array $metadata = []): void
    {
        ContentAuditEvent::create(['content_item_id' => $item->id, 'content_revision_id' => $revision?->id, 'actor_id' => $actor?->id, 'event' => $event, 'from_status' => $from, 'to_status' => $to, 'metadata' => $metadata, 'occurred_at' => now()]);
    }

    public function checklist(string $channel, array $payload): array
    {
        $checks = ['evidence_attached' => !empty($payload['evidence'])];
        if ($channel === 'article') {
            foreach (['title_ka', 'title_en', 'excerpt_ka', 'excerpt_en', 'body_ka', 'body_en', 'meta_title_ka', 'meta_title_en', 'meta_description_ka', 'meta_description_en'] as $field) $checks[$field] = filled($payload[$field] ?? null);
            $checks['seo_ready'] = !in_array(false, [$checks['meta_title_ka'], $checks['meta_title_en'], $checks['meta_description_ka'], $checks['meta_description_en']], true);
        } else {
            $checks['caption'] = filled($payload['message'] ?? null);
            $checks['public_https_media'] = $channel !== 'instagram' || filter_var($payload['media_url'] ?? null, FILTER_VALIDATE_URL) && str_starts_with((string) $payload['media_url'], 'https://');
        }
        return $checks;
    }
}
