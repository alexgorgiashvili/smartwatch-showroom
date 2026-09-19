<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\PublishContentStudioItem;
use App\Models\ContentItem;
use App\Services\ContentStudioPublisher;
use App\Services\ContentStudioWorkflow;
use App\Services\ContentStudioAnalyticsService;
use App\Services\ContentStudioRevisionDiff;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContentStudioController extends Controller
{
    public function __construct(private ContentStudioWorkflow $workflow, private ContentStudioAnalyticsService $analytics, private ContentStudioRevisionDiff $diff) {}
    public function index(Request $request)
    {
        $items = ContentItem::with('campaign')->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))->when($request->filled('channel'), fn ($q) => $q->where('channel', $request->string('channel')))->latest()->paginate(25)->appends($request->query());
        $counters = ContentItem::selectRaw('status, count(*) total')->groupBy('status')->pluck('total', 'status');
        return $this->renderPjaxView($request, view('admin.content-studio.index', compact('items', 'counters')));
    }
    public function show(Request $request, ContentItem $contentItem)
    {
        $contentItem->load(['campaign', 'revisions' => fn ($q) => $q->latest('revision_number'), 'audits' => fn ($q) => $q->latest('occurred_at'), 'assets']);
        $analytics = $this->analytics->latest($contentItem, (int) $request->input('window', 28));
        $ascending = $contentItem->revisions->sortBy('revision_number')->values(); $revisionDiffs = [];
        foreach ($ascending as $index => $revision) $revisionDiffs[$revision->id] = $index ? $this->diff->between($ascending[$index - 1]->payload, $revision->payload) : [];
        return $this->renderPjaxView($request, view('admin.content-studio.show', ['item' => $contentItem, 'analytics' => $analytics, 'window' => (int) $request->input('window', 28), 'revisionDiffs' => $revisionDiffs]));
    }
    public function approveNow(Request $request, ContentItem $contentItem): RedirectResponse
    {
        $this->workflow->approve($contentItem, $request->user());
        PublishContentStudioItem::dispatch($contentItem->id);
        return back()->with('success', 'დამტკიცდა ზუსტი რევიზია და გამოქვეყნების სამუშაო დაემატა რიგში.');
    }
    public function schedule(Request $request, ContentItem $contentItem): RedirectResponse
    {
        $data = $request->validate(['scheduled_at' => ['required', 'date', 'after:now']]);
        $date = Carbon::parse($data['scheduled_at'], config('app.timezone'));
        $this->workflow->approve($contentItem, $request->user(), $date);
        return back()->with('success', 'დამტკიცდა და დაიგეგმა Asia/Tbilisi დროით.');
    }
    public function requestChanges(Request $request, ContentItem $contentItem): RedirectResponse
    {
        $data = $request->validate(['reviewer_notes' => ['required', 'string', 'max:5000']]);
        $this->workflow->transition($contentItem, 'changes_requested', $request->user(), $data['reviewer_notes']);
        return back()->with('success', 'ცვლილებები მოთხოვნილია.');
    }
    public function reject(Request $request, ContentItem $contentItem): RedirectResponse
    {
        $data = $request->validate(['reviewer_notes' => ['required', 'string', 'max:5000']]);
        $this->workflow->transition($contentItem, 'rejected', $request->user(), $data['reviewer_notes']);
        return back()->with('success', 'კონტენტი უარყოფილია.');
    }
    public function retry(ContentItem $contentItem): RedirectResponse
    {
        abort_unless($contentItem->status === 'failed' && $contentItem->approved_revision_id, 422, 'Only a failed approved revision can be retried.');
        PublishContentStudioItem::dispatch($contentItem->id);
        return back()->with('success', 'ხელახალი მცდელობა დაემატა რიგში; უკვე შექმნილი არხის post ID აღარ დუბლირდება.');
    }
}
