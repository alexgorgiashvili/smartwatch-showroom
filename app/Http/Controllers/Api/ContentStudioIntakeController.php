<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentAsset;
use App\Models\ContentCampaign;
use App\Models\ContentItem;
use App\Services\ContentStudioMediaValidator;
use App\Services\ContentStudioWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Draft-only Sanctum boundary. There are intentionally no owner action routes here. */
class ContentStudioIntakeController extends Controller
{
    public function __construct(private ContentStudioWorkflow $workflow, private ContentStudioMediaValidator $media) {}

    public function storeCampaign(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'week_key' => ['required', 'regex:/^\\d{4}-W(?:0[1-9]|[1-4]\\d|5[0-3])$/'], 'utm' => ['nullable', 'array'], 'metadata' => ['nullable', 'array'], 'idempotency_key' => ['required', 'string', 'max:255']]);
        $fingerprint = hash('sha256', 'campaign|'.$data['idempotency_key']);
        $existing = ContentCampaign::where('fingerprint', $fingerprint)->first();
        if ($existing) return response()->json(['campaign' => $existing, 'idempotent' => true]);
        $campaign = ContentCampaign::create(['name' => $data['name'], 'week_key' => $data['week_key'], 'fingerprint' => $fingerprint, 'utm' => $data['utm'] ?? [], 'metadata' => $data['metadata'] ?? []]);
        return response()->json(['campaign' => $campaign], 201);
    }

    public function storeItem(Request $request, ContentCampaign $campaign): JsonResponse
    {
        $data = $request->validate(['channel' => ['required', Rule::in(ContentItem::CHANNELS)], 'title' => ['nullable', 'string', 'max:255'], 'idempotency_key' => ['required', 'string', 'max:255']]);
        $fingerprint = hash('sha256', "item|{$campaign->id}|{$data['channel']}|{$data['idempotency_key']}");
        $existing = ContentItem::where('fingerprint', $fingerprint)->first();
        if ($existing) return response()->json(['item' => $existing, 'idempotent' => true]);
        if (ContentItem::where('campaign_id', $campaign->id)->where('channel', $data['channel'])->exists()) return response()->json(['message' => 'This campaign already has that independently approved channel item.'], 422);
        if ($data['channel'] === 'article' && ContentItem::where('channel', 'article')->whereHas('campaign', fn ($q) => $q->where('week_key', $campaign->week_key))->exists()) return response()->json(['message' => 'Weekly cap reached: one bilingual article.'], 422);
        if ($data['channel'] !== 'article' && ContentItem::whereIn('channel', ['facebook', 'instagram'])->whereHas('campaign', fn ($q) => $q->where('week_key', $campaign->week_key))->count() >= 6) return response()->json(['message' => 'Weekly cap reached: three social packages.'], 422);
        $item = ContentItem::create(['campaign_id' => $campaign->id, 'channel' => $data['channel'], 'title' => $data['title'] ?? null, 'fingerprint' => $fingerprint, 'status' => 'generated']);
        return response()->json(['item' => $item], 201);
    }

    public function storeRevision(Request $request, ContentItem $item): JsonResponse
    {
        $payload = $request->validate(['payload' => ['required', 'array'], 'evidence' => ['nullable', 'array']])['payload'];
        $this->validatePayload($item->channel, $payload);
        if (isset($payload['media_url'])) $this->media->validatePublicUrl($payload['media_url'], $item->channel);
        $revision = $this->workflow->submitRevision($item, $payload, $request->input('evidence', []), $request->user());
        return response()->json(['revision' => $revision, 'item' => $item->fresh()], 201);
    }

    public function storeAsset(Request $request, ContentItem $item): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:20480']]);
        $file = $request->file('file'); $validation = $this->media->validateUpload($file);
        $path = $file->store('content-studio/'.$item->id, 'local');
        $asset = ContentAsset::create(['content_item_id' => $item->id, 'disk' => 'local', 'path' => $path, 'mime_type' => $validation['mime_type'], 'size_bytes' => $validation['size_bytes'], 'width' => $validation['width'], 'height' => $validation['height'], 'validation' => $validation]);
        return response()->json(['asset' => $asset, 'notice' => 'Stored privately; publishable social content still needs a public HTTPS URL.'], 201);
    }

    public function showStatus(ContentItem $item): JsonResponse
    {
        return response()->json(['item' => $item->load(['campaign', 'currentRevision:id,content_item_id,revision_number,validation,submitted_at', 'approvedRevision:id,content_item_id,revision_number'])]);
    }

    private function validatePayload(string $channel, array $payload): void
    {
        $rules = $channel === 'article'
            ? ['title_ka' => ['required', 'string', 'max:255'], 'title_en' => ['required', 'string', 'max:255'], 'excerpt_ka' => ['required', 'string'], 'excerpt_en' => ['required', 'string'], 'body_ka' => ['required', 'string'], 'body_en' => ['required', 'string'], 'meta_title_ka' => ['required', 'string', 'max:160'], 'meta_title_en' => ['required', 'string', 'max:160'], 'meta_description_ka' => ['required', 'string', 'max:160'], 'meta_description_en' => ['required', 'string', 'max:160'], 'cover_image' => ['nullable', 'url', 'max:2000'], 'schema_type' => ['nullable', Rule::in(['Article', 'HowTo', 'ItemList'])]]
            : ['message' => ['required', 'string', 'max:5000'], 'media_type' => ['required', Rule::in(['none', 'image', 'video'])], 'media_url' => ['nullable', 'url', 'max:2000']];
        validator($payload, $rules)->validate();
        if ($channel === 'instagram' && (($payload['media_type'] ?? 'none') === 'none' || empty($payload['media_url']))) abort(422, 'Instagram requires image/video media.');
    }
}
