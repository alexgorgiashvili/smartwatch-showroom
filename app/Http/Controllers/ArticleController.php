<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function index(): View
    {
        $articles = Article::published()
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->get();

        return view('blog.index', compact('articles'));
    }

    public function show(Article $article): View
    {
        if (! $article->is_published) {
            abort(404);
        }

        $related = Article::published()
            ->whereKeyNot($article->getKey())
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return view('blog.show', compact('article', 'related'));
    }
}
