<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FacebookCompetitorAnalysis;
use App\Models\FacebookCompetitorInsight;
use App\Models\FacebookCompetitorPage;
use App\Models\FacebookCompetitorPost;
use App\Services\FacebookApifyScraperService;
use App\Services\FacebookCompetitorAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class FacebookCompetitorController extends Controller
{
    public function __construct(
        private FacebookApifyScraperService $scraperService,
        private FacebookCompetitorAiService $aiService
    ) {}

    /**
     * Main dashboard view.
     */
    public function index(Request $request)
    {
        $pages = FacebookCompetitorPage::withCount(['posts', 'relevantPosts'])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $insights = FacebookCompetitorInsight::with('competitorPage')
            ->where('status', 'new')
            ->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $recentAnalysis = FacebookCompetitorAnalysis::latest()
            ->first();

        $lastScrapedPage = FacebookCompetitorPage::whereNotNull('last_scraped_at')
            ->orderByDesc('last_scraped_at')
            ->first();

        $stats = [
            'total_pages' => $pages->count(),
            'active_pages' => $pages->where('is_active', true)->count(),
            'total_posts' => FacebookCompetitorPost::count(),
            'relevant_posts' => FacebookCompetitorPost::where('is_relevant', true)->count(),
            'pending_insights' => FacebookCompetitorInsight::where('status', 'new')->count(),
            'last_scrape' => $lastScrapedPage?->last_scraped_at,
        ];

        $recentPosts = FacebookCompetitorPost::with('competitorPage')
            ->where('is_relevant', true)
            ->orderByDesc('posted_at')
            ->limit(15)
            ->get();

        $view = view('admin.fb-competitors.index', compact(
            'pages',
            'insights',
            'recentAnalysis',
            'stats',
            'recentPosts'
        ));

        return $this->renderPjaxView($request, $view);
    }

    /**
     * Show specific competitor page details.
     */
    public function show(Request $request, FacebookCompetitorPage $page)
    {
        $posts = $page->posts()
            ->orderByDesc('posted_at')
            ->paginate(20);

        $insights = $page->insights()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $stats = [
            'total_posts' => $page->posts()->count(),
            'relevant_posts' => $page->relevantPosts()->count(),
            'avg_engagement' => $page->posts()->avg('likes_count'),
            'last_scraped' => $page->last_scraped_at?->diffForHumans(),
        ];

        $view = view('admin.fb-competitors.show', compact('page', 'posts', 'insights', 'stats'));

        return $this->renderPjaxView($request, $view);
    }

    /**
     * Store new competitor page.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'facebook_url' => 'required|url|max:2048',
            'category' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'scraping_frequency' => 'required|in:daily,weekly,manual',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        FacebookCompetitorPage::create($validated);

        return redirect()
            ->route('admin.fb-competitors')
            ->with('success', 'კონკურენტი წარმატებით დაემატა');
    }

    /**
     * Update competitor page.
     */
    public function update(Request $request, FacebookCompetitorPage $page): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'facebook_url' => 'required|url|max:2048',
            'category' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'scraping_frequency' => 'required|in:daily,weekly,manual',
        ]);

        $validated['is_active'] = $request->boolean('is_active', $page->is_active);

        $page->update($validated);

        return redirect()
            ->route('admin.fb-competitors')
            ->with('success', 'კონკურენტი განახლდა');
    }

    /**
     * Delete competitor page.
     */
    public function destroy(FacebookCompetitorPage $page): JsonResponse
    {
        try {
            $page->delete();

            return response()->json([
                'success' => true,
                'message' => 'კონკურენტი წაიშალა'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete competitor page', [
                'page_id' => $page->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'წაშლა ვერ მოხერხდა'
            ], 500);
        }
    }

    /**
     * Trigger manual scrape for a specific page.
     */
    public function scrape(Request $request, FacebookCompetitorPage $page): JsonResponse
    {
        try {
            $maxPosts = $request->integer('max_posts', 50);

            $result = $this->scraperService->scrapeCompetitorPage($page, $maxPosts);

            return response()->json([
                'success' => true,
                'message' => "გაპარსილია {$result['new_posts']} ახალი პოსტი, განახლდა {$result['updated_posts']}",
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('FB Competitor scrape failed', [
                'page_id' => $page->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'გაპარსვა ვერ მოხერხდა: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Trigger AI analysis for unfiltered posts.
     */
    public function analyze(Request $request): JsonResponse
    {
        try {
            $pageId = $request->integer('page_id');

            $query = FacebookCompetitorPost::whereNull('is_relevant');

            if ($pageId) {
                $query->where('competitor_page_id', $pageId);
            }

            $posts = $query->orderByDesc('scraped_at')->limit(200)->get();

            if ($posts->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'ყველა პოსტი უკვე გაანალიზებულია',
                    'filtered' => 0,
                    'relevant' => 0,
                ]);
            }

            $filtered = $this->aiService->filterRelevantPosts($posts);
            $relevant = $posts->fresh()->where('is_relevant', true)->count();

            return response()->json([
                'success' => true,
                'message' => "გაფილტრულია {$filtered} პოსტი, რელევანტურია {$relevant}",
                'filtered' => $filtered,
                'relevant' => $relevant,
            ]);
        } catch (\Throwable $e) {
            Log::error('FB Competitor AI analysis failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'AI ანალიზი ვერ მოხერხდა: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Run weekly comprehensive analysis.
     */
    public function weeklyAnalysis(Request $request): JsonResponse
    {
        try {
            $analysis = $this->aiService->runWeeklyAnalysis();

            if (!$analysis) {
                return response()->json([
                    'success' => false,
                    'message' => 'არ არის საკმარისი მონაცემები ანალიზისთვის',
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => "კვირეული ანალიზი დასრულდა. გაანალიზებულია {$analysis->posts_analyzed_count} პოსტი",
                'analysis_id' => $analysis->id,
                'recommendations_count' => count($analysis->recommendations_json ?? []),
            ]);
        } catch (\Throwable $e) {
            Log::error('FB Competitor weekly analysis failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'კვირეული ანალიზი ვერ მოხერხდა: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * View analysis details.
     */
    public function showAnalysis(Request $request, FacebookCompetitorAnalysis $analysis)
    {
        $pages = FacebookCompetitorPage::whereIn('id', $analysis->competitor_page_ids_json ?? [])
            ->get();

        $view = view('admin.fb-competitors.analysis', compact('analysis', 'pages'));

        return $this->renderPjaxView($request, $view);
    }

    /**
     * Update insight status.
     */
    public function updateInsight(Request $request, FacebookCompetitorInsight $insight): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:new,acknowledged,actioned,dismissed',
        ]);

        $insight->update([
            'status' => $validated['status'],
            'acknowledged_at' => in_array($validated['status'], ['acknowledged', 'actioned']) ? now() : $insight->acknowledged_at,
            'actioned_at' => $validated['status'] === 'actioned' ? now() : $insight->actioned_at,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Insight სტატუსი განახლდა',
        ]);
    }

    /**
     * Get scraping cost estimate.
     */
    public function estimateCost(Request $request): JsonResponse
    {
        $pages = FacebookCompetitorPage::active()->count();
        $maxPosts = $request->integer('max_posts', 50);
        $totalPosts = $pages * $maxPosts;

        $cost = $this->scraperService->estimateCost($totalPosts);

        return response()->json([
            'success' => true,
            'pages' => $pages,
            'posts_per_page' => $maxPosts,
            'total_posts' => $totalPosts,
            'estimated_cost' => $cost,
        ]);
    }

    /**
     * Analytics charts page.
     */
    public function charts(Request $request)
    {
        $view = view('admin.fb-competitors.charts');
        return $this->renderPjaxView($request, $view);
    }

    /**
     * Get analytics data for charts.
     */
    public function analytics(Request $request): JsonResponse
    {
        $days = $request->integer('days', 30);
        $startDate = now()->subDays($days);

        $engagementTrends = $this->getEngagementTrends($startDate);
        $competitorComparison = $this->getCompetitorComparison($startDate);

        $topPosts = FacebookCompetitorPost::with('competitorPage')
            ->where('posted_at', '>=', $startDate)
            ->where('is_relevant', true)
            ->orderByRaw('(likes_count + comments_count + shares_count) DESC')
            ->limit(10)
            ->get()
            ->map(fn($post) => [
                'competitor' => $post->competitorPage->name ?? 'N/A',
                'text' => $post->text,
                'total_engagement' => $post->likes_count + $post->comments_count + $post->shares_count,
            ]);

        $postingFrequency = FacebookCompetitorPage::withCount([
            'posts' => fn($q) => $q->where('posted_at', '>=', $startDate)
        ])->get()->map(fn($page) => [
            'name' => $page->name,
            'posts_per_week' => round($page->posts_count / ($days / 7), 1),
        ]);

        return response()->json([
            'success' => true,
            'engagement_trends' => $engagementTrends,
            'competitor_comparison' => $competitorComparison,
            'top_posts' => $topPosts,
            'posting_frequency' => $postingFrequency,
        ]);
    }

    /**
     * Export to Excel.
     */
    public function export(Request $request)
    {
        $type = $request->input('type', 'posts');
        $days = $request->integer('days', 30);

        $filename = "fb-competitors-{$type}-" . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($type, $days) {
            $file = fopen('php://output', 'w');

            if ($type === 'posts') {
                fputcsv($file, ['Competitor', 'Post Text', 'Posted At', 'Likes', 'Comments', 'Shares', 'Relevant', 'Score', 'URL']);

                FacebookCompetitorPost::with('competitorPage')
                    ->where('posted_at', '>=', now()->subDays($days))
                    ->orderByDesc('posted_at')
                    ->chunk(100, function($posts) use ($file) {
                        foreach ($posts as $post) {
                            fputcsv($file, [
                                $post->competitorPage->name ?? 'N/A',
                                $post->text,
                                $post->posted_at,
                                $post->likes_count,
                                $post->comments_count,
                                $post->shares_count,
                                $post->is_relevant ? 'Yes' : 'No',
                                $post->relevance_score,
                                $post->post_url,
                            ]);
                        }
                    });
            } elseif ($type === 'competitors') {
                fputcsv($file, ['Name', 'URL', 'Category', 'Total Posts', 'Relevant Posts', 'Avg Engagement', 'Last Scraped', 'Status']);

                FacebookCompetitorPage::withCount(['posts', 'relevantPosts'])
                    ->get()
                    ->each(function($page) use ($file) {
                        fputcsv($file, [
                            $page->name,
                            $page->facebook_url,
                            $page->category,
                            $page->posts_count,
                            $page->relevant_posts_count,
                            $page->avg_engagement_rate,
                            $page->last_scraped_at,
                            $page->is_active ? 'Active' : 'Inactive',
                        ]);
                    });
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function getEngagementTrends($startDate): array
    {
        $dates = [];
        $current = $startDate->copy();

        while ($current <= now()) {
            $dates[] = $current->format('M d');
            $current->addDay();
        }

        $competitors = FacebookCompetitorPage::active()->get();
        $competitorData = [];

        foreach ($competitors as $competitor) {
            $engagement = [];
            $current = $startDate->copy();

            while ($current <= now()) {
                $dayEngagement = FacebookCompetitorPost::where('competitor_page_id', $competitor->id)
                    ->whereDate('posted_at', $current)
                    ->sum(DB::raw('likes_count + comments_count + shares_count'));

                $engagement[] = $dayEngagement;
                $current->addDay();
            }

            $competitorData[] = [
                'name' => $competitor->name,
                'engagement' => $engagement,
            ];
        }

        return [
            'dates' => $dates,
            'competitors' => $competitorData,
        ];
    }

    private function getCompetitorComparison($startDate): array
    {
        return FacebookCompetitorPage::withCount([
            'posts' => fn($q) => $q->where('posted_at', '>=', $startDate),
            'relevantPosts' => fn($q) => $q->where('posted_at', '>=', $startDate),
        ])->get()->map(function($page) use ($startDate) {
            $avgEngagement = FacebookCompetitorPost::where('competitor_page_id', $page->id)
                ->where('posted_at', '>=', $startDate)
                ->avg(DB::raw('likes_count + comments_count + shares_count'));

            return [
                'name' => $page->name,
                'total_posts' => $page->posts_count,
                'relevant_posts' => $page->relevant_posts_count,
                'avg_engagement' => round($avgEngagement ?? 0, 1),
            ];
        })->toArray();
    }
}
