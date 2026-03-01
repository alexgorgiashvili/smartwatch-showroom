<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function index(Request $request): View
    {
        $articles = Article::query()
            ->when(
                $request->filled('q'),
                function ($query) use ($request) {
                    $q = trim($request->string('q')->value());

                    $query->where(function ($nested) use ($q) {
                        $nested->where('title_ka', 'like', "%{$q}%")
                            ->orWhere('title_en', 'like', "%{$q}%")
                            ->orWhere('slug', 'like', "%{$q}%");
                    });
                }
            )
            ->when(
                $request->filled('status') && in_array($request->string('status')->value(), ['published', 'draft'], true),
                function ($query) use ($request) {
                    $status = $request->string('status')->value();

                    if ($status === 'published') {
                        $query->where('is_published', true);
                    }

                    if ($status === 'draft') {
                        $query->where('is_published', false);
                    }
                }
            )
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->appends($request->query());

        return view('admin.articles.index', [
            'articles' => $articles,
            'q' => $request->string('q')->value(),
            'status' => $request->string('status')->value(),
        ]);
    }

    public function create(): View
    {
        return view('admin.articles.create', [
            'article' => new Article(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateArticle($request);
        $data['slug'] = $this->ensureSlug($data['slug'] ?? null, $data['title_en'] ?? $data['title_ka']);
        $this->applyPublishState($request, $data);

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('images/articles', 'public');
        }

        $article = Article::create($data);

        return redirect()->route('admin.articles.edit', $article)
            ->with('status', 'Article created.');
    }

    public function edit(Article $article): View
    {
        return view('admin.articles.edit', [
            'article' => $article,
        ]);
    }

    public function update(Request $request, Article $article): RedirectResponse
    {
        $data = $this->validateArticle($request, $article->id);
        $data['slug'] = $this->ensureSlug($data['slug'] ?? null, $data['title_en'] ?? $data['title_ka'], $article->id);
        $this->applyPublishState($request, $data);

        if ($request->boolean('remove_cover') && $article->cover_image) {
            $this->deleteCoverIfExists($article->cover_image);
            $data['cover_image'] = null;
        }

        if ($request->hasFile('cover_image')) {
            if ($article->cover_image) {
                $this->deleteCoverIfExists($article->cover_image);
            }

            $data['cover_image'] = $request->file('cover_image')->store('images/articles', 'public');
        }

        $article->update($data);

        return redirect()->route('admin.articles.edit', $article)
            ->with('status', 'Article updated.');
    }

    public function togglePublish(Article $article): RedirectResponse
    {
        $nextState = !$article->is_published;

        $article->update([
            'is_published' => $nextState,
            'published_at' => $nextState ? ($article->published_at ?? now()) : null,
        ]);

        return redirect()->route('admin.articles.index')
            ->with('status', $nextState ? 'Article published.' : 'Article moved to draft.');
    }

    public function destroy(Article $article): RedirectResponse
    {
        if ($article->cover_image) {
            $this->deleteCoverIfExists($article->cover_image);
        }

        $article->delete();

        return redirect()->route('admin.articles.index')
            ->with('status', 'Article deleted.');
    }

    private function validateArticle(Request $request, ?int $articleId = null): array
    {
        return $request->validate([
            'slug' => [
                'nullable',
                'string',
                'max:200',
                Rule::unique('articles', 'slug')->ignore($articleId),
            ],
            'title_ka' => ['required', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'excerpt_ka' => ['nullable', 'string'],
            'excerpt_en' => ['nullable', 'string'],
            'body_ka' => ['required', 'string'],
            'body_en' => ['nullable', 'string'],
            'meta_title_ka' => ['nullable', 'string', 'max:160'],
            'meta_title_en' => ['nullable', 'string', 'max:160'],
            'meta_description_ka' => ['nullable', 'string', 'max:160'],
            'meta_description_en' => ['nullable', 'string', 'max:160'],
            'schema_type' => ['required', Rule::in(['Article', 'HowTo', 'ItemList'])],
            'published_at' => ['nullable', 'date'],
            'cover_image' => ['nullable', 'image', 'max:4096'],
            'is_published' => ['nullable', 'boolean'],
            'remove_cover' => ['nullable', 'boolean'],
        ]);
    }

    private function applyPublishState(Request $request, array &$data): void
    {
        $published = $request->boolean('is_published');
        $data['is_published'] = $published;

        if ($published) {
            $data['published_at'] = !empty($data['published_at'])
                ? $data['published_at']
                : now();
        } else {
            $data['published_at'] = null;
        }
    }

    private function ensureSlug(?string $slug, string $title, ?int $articleId = null): string
    {
        $baseSlug = Str::slug($slug ?: $title);
        if ($baseSlug === '') {
            $baseSlug = 'article';
        }

        $candidate = $baseSlug;
        $counter = 1;

        while ($this->slugExists($candidate, $articleId)) {
            $candidate = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $candidate;
    }

    private function slugExists(string $slug, ?int $articleId = null): bool
    {
        $query = Article::query()->where('slug', $slug);

        if ($articleId) {
            $query->where('id', '!=', $articleId);
        }

        return $query->exists();
    }

    private function deleteCoverIfExists(string $path): void
    {
        $normalized = Str::startsWith($path, 'storage/')
            ? Str::after($path, 'storage/')
            : $path;

        Storage::disk('public')->delete($normalized);
    }
}
