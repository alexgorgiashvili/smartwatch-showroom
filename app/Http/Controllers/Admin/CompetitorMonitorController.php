<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompetitorMapping;
use App\Models\CompetitorProduct;
use App\Models\CompetitorSource;
use App\Models\Product;
use App\Services\CompetitorMonitorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompetitorMonitorController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureDefaultSourceExists();

        $sources = CompetitorSource::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($sources->isEmpty()) {
            $sources = CompetitorSource::query()
                ->orderBy('name')
                ->get();
        }

        $selectedSourceId = (int) $request->integer('source_id');
        $source = $sources->firstWhere('id', $selectedSourceId) ?? $sources->first();

        $products = CompetitorProduct::query()
            ->where('competitor_source_id', $source?->id)
            ->with(['mapping.product'])
            ->withCount('snapshots')
            ->orderByDesc('last_seen_at')
            ->paginate(40)
            ->appends(['source_id' => $source?->id]);

        $localProducts = Product::query()
            ->select(['id', 'name_ka', 'name_en'])
            ->orderBy('name_ka')
            ->limit(1000)
            ->get();

        return view('admin.products.competitor-monitor', [
            'sources' => $sources,
            'source' => $source,
            'products' => $products,
            'localProducts' => $localProducts,
        ]);
    }

    public function storeSource(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_url' => ['required', 'url', 'max:2048', 'unique:competitor_sources,category_url'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $categoryUrl = trim((string) $data['category_url']);
        try {
            $domain = $this->extractDomain($categoryUrl);
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors(['category_url' => $exception->getMessage()])->withInput();
        }

        $source = CompetitorSource::query()->create([
            'name' => trim((string) $data['name']),
            'domain' => $domain,
            'category_url' => $categoryUrl,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return redirect()
            ->route('admin.competitors.index', ['source_id' => $source->id])
            ->with('status', 'Source created successfully.');
    }

    public function refresh(CompetitorSource $source, CompetitorMonitorService $monitor): RedirectResponse
    {
        try {
            $result = $monitor->refreshSource($source);

            return redirect()
                ->route('admin.competitors.index', ['source_id' => $source->id])
                ->with('status', 'Refresh completed. Scraped: ' . $result['total_scraped'] . ', created: ' . $result['created'] . ', updated: ' . $result['updated'] . '.');
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->route('admin.competitors.index', ['source_id' => $source->id])
                ->withErrors(['competitor' => 'Refresh failed: ' . $exception->getMessage()]);
        }
    }

    public function saveMapping(Request $request, CompetitorProduct $competitorProduct): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['nullable', 'exists:products,id'],
            'source_id' => ['nullable', 'integer', 'exists:competitor_sources,id'],
        ]);

        $sourceId = (int) ($data['source_id'] ?? $competitorProduct->competitor_source_id);
        if ($sourceId !== (int) $competitorProduct->competitor_source_id) {
            return back()->withErrors(['competitor' => 'Invalid source context for mapping operation.']);
        }

        $productId = $data['product_id'] ?? null;

        if ($productId === null || $productId === '') {
            CompetitorMapping::query()
                ->where('competitor_product_id', $competitorProduct->id)
                ->delete();

            return redirect()
                ->route('admin.competitors.index', ['source_id' => $sourceId])
                ->with('status', 'Mapping removed.');
        }

        CompetitorMapping::updateOrCreate(
            ['competitor_product_id' => $competitorProduct->id],
            ['product_id' => (int) $productId]
        );

        return redirect()
            ->route('admin.competitors.index', ['source_id' => $sourceId])
            ->with('status', 'Mapping saved.');
    }

    private function ensureDefaultSourceExists(): void
    {
        CompetitorSource::query()->firstOrCreate(
            ['category_url' => 'https://i-mobile.ge/categories/27'],
            [
                'name' => 'i-mobile — საბავშვო საათები',
                'domain' => 'i-mobile.ge',
                'is_active' => true,
            ]
        );
    }

    private function extractDomain(string $categoryUrl): string
    {
        $host = parse_url($categoryUrl, PHP_URL_HOST);
        if (!is_string($host) || trim($host) === '') {
            throw new \InvalidArgumentException('Invalid category URL.');
        }

        $domain = mb_strtolower(trim($host));

        return str_starts_with($domain, 'www.') ? substr($domain, 4) : $domain;
    }
}
