<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductQualityAnalysis;
use App\Models\ResearchTarget;
use App\Services\ProductQuality\ProductQualityComparisonService;
use App\Services\ProductQuality\ProductQualityResearchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductQualityController extends Controller
{
    public function index(Request $request)
    {
        $targets = ResearchTarget::query()
            ->with(['product', 'latestAnalysis'])
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total_targets' => ResearchTarget::query()->count(),
            'completed_targets' => ResearchTarget::query()
                ->whereHas('analyses', fn ($query) => $query->where('status', 'completed'))
                ->count(),
            'queued_or_running_targets' => ResearchTarget::query()
                ->whereHas('analyses', fn ($query) => $query->whereIn('status', ['queued', 'running']))
                ->count(),
            'ad_hoc_targets' => ResearchTarget::query()
                ->where('mode', 'ad_hoc')
                ->count(),
        ];

        $view = view('admin.product-quality.index', [
            'targets' => $targets,
            'stats' => $stats,
        ]);

        return $this->renderPjaxView($request, $view);
    }

    public function create(Request $request)
    {
        $products = Product::query()
            ->orderBy('name_en')
            ->get(['id', 'name_en', 'name_ka', 'brand', 'model', 'external_source', 'external_source_url', 'external_product_id']);

        $view = view('admin.product-quality.create', [
            'products' => $products,
        ]);

        return $this->renderPjaxView($request, $view);
    }

    public function store(Request $request, ProductQualityResearchService $researchService): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', 'in:catalog,ad_hoc'],
            'product_id' => ['nullable', 'required_if:mode,catalog', 'exists:products,id'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'external_source' => ['nullable', 'string', 'max:50'],
            'external_product_id' => ['nullable', 'string', 'max:120'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'apify_json' => ['nullable', 'string'],
            'manual_evidence_input' => ['nullable', 'string'],
        ]);

        if ($data['mode'] === 'ad_hoc' && !$this->hasAdHocIdentity($data)) {
            return back()
                ->withInput()
                ->withErrors([
                    'name' => 'Ad-hoc research needs at least a source URL, product name, or brand/model.',
                ]);
        }

        $target = $researchService->upsertTarget($data);
        $analysis = $researchService->queueAnalysis($target);

        return redirect()
            ->route('admin.product-quality.show', $target)
            ->with('status', "Research queued. Analysis #{$analysis->id} is now processing.");
    }

    public function show(Request $request, ResearchTarget $productQuality, ProductQualityComparisonService $comparisonService)
    {
        $productQuality->load([
            'product',
            'latestAnalysis',
            'analyses' => fn ($query) => $query->latest()->limit(10),
            'evidenceItems' => fn ($query) => $query->orderByDesc('published_at')->orderByDesc('id'),
        ]);

        $compareIds = collect((array) $request->query('compare_target_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->reject(fn ($id) => $id === $productQuality->id)
            ->values();

        $comparisonCandidates = ResearchTarget::query()
            ->with(['product', 'latestAnalysis'])
            ->where('id', '!=', $productQuality->id)
            ->whereHas('analyses', fn ($query) => $query->where('status', 'completed'))
            ->orderBy('name')
            ->get();

        $comparisonResult = null;

        if ($compareIds->isNotEmpty() && $productQuality->latestAnalysis?->status === 'completed') {
            $otherAnalyses = ProductQualityAnalysis::query()
                ->whereIn('research_target_id', $compareIds->all())
                ->where('status', 'completed')
                ->latest()
                ->get()
                ->groupBy('research_target_id')
                ->map(fn ($group) => $group->first())
                ->values();

            $comparisonResult = $comparisonService->compare(
                collect([$productQuality->latestAnalysis])->merge($otherAnalyses)
            );
        }

        $view = view('admin.product-quality.show', [
            'target' => $productQuality,
            'comparisonCandidates' => $comparisonCandidates,
            'selectedCompareIds' => $compareIds->all(),
            'comparisonResult' => $comparisonResult,
        ]);

        return $this->renderPjaxView($request, $view);
    }

    public function run(ResearchTarget $productQuality, ProductQualityResearchService $researchService): RedirectResponse
    {
        $analysis = $researchService->queueAnalysis($productQuality);

        return redirect()
            ->route('admin.product-quality.show', $productQuality)
            ->with('status', "Re-run queued. Analysis #{$analysis->id} is now processing.");
    }

    private function hasAdHocIdentity(array $data): bool
    {
        return filled($data['source_url'] ?? null)
            || filled($data['name'] ?? null)
            || filled($data['brand'] ?? null)
            || filled($data['model'] ?? null);
    }
}
