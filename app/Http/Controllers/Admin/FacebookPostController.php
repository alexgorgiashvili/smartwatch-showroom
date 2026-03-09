<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FacebookPost;
use App\Models\Product;
use App\Services\AiPostGeneratorService;
use App\Services\FacebookPageService;
use App\Services\InstagramPageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FacebookPostController extends Controller
{
    public function __construct(
        private FacebookPageService $facebookService,
        private InstagramPageService $instagramService,
        private AiPostGeneratorService $aiService,
    ) {}

    public function index(Request $request): View
    {
        $posts = FacebookPost::with(['user', 'product'])
            ->when(
                $request->filled('status') && in_array($request->string('status')->value(), ['draft', 'published', 'failed'], true),
                fn ($q) => $q->where('status', $request->string('status')->value())
            )
            ->orderByDesc('created_at')
            ->paginate(15)
            ->appends($request->query());

        return view('admin.facebook-posts.index', compact('posts'));
    }

    public function create(): View
    {
        $products = Product::active()
            ->orderBy('name_ka')
            ->get(['id', 'name_ka', 'name_en', 'price', 'sale_price']);

        return view('admin.facebook-posts.create', [
            'products' => $products,
            'fbConfigured' => $this->facebookService->isConfigured(),
            'igConfigured' => $this->instagramService->isConfigured(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:5000',
            'product_id' => 'nullable|exists:products,id',
            'image_url' => 'nullable|url|max:2000',
            'ai_prompt' => 'nullable|string|max:5000',
            'post_to_facebook' => 'nullable',
            'post_to_instagram' => 'nullable',
            'action' => 'required|in:draft,publish',
        ]);

        $postToFb = $request->has('post_to_facebook');
        $postToIg = $request->has('post_to_instagram');

        if ($validated['action'] === 'publish' && $postToIg && empty($validated['image_url'])) {
            return back()->withInput()->with('error', 'Instagram-ზე გამოსაქვეყნებლად სურათის URL აუცილებელია.');
        }

        $post = FacebookPost::create([
            'user_id' => $request->user()->id,
            'product_id' => $validated['product_id'] ?? null,
            'message' => $validated['message'],
            'image_url' => $validated['image_url'] ?? null,
            'post_to_facebook' => $postToFb,
            'post_to_instagram' => $postToIg,
            'ai_prompt' => $validated['ai_prompt'] ?? null,
            'status' => 'draft',
        ]);

        if ($validated['action'] === 'publish') {
            return $this->publishPost($post);
        }

        return redirect()
            ->route('admin.facebook-posts.index')
            ->with('success', 'პოსტი შეინახა დრაფტად');
    }

    public function edit(FacebookPost $facebookPost): View
    {
        $products = Product::active()
            ->orderBy('name_ka')
            ->get(['id', 'name_ka', 'name_en', 'price', 'sale_price']);

        return view('admin.facebook-posts.edit', [
            'post' => $facebookPost,
            'products' => $products,
            'fbConfigured' => $this->facebookService->isConfigured(),
            'igConfigured' => $this->instagramService->isConfigured(),
        ]);
    }

    public function update(Request $request, FacebookPost $facebookPost): RedirectResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:5000',
            'product_id' => 'nullable|exists:products,id',
            'image_url' => 'nullable|url|max:2000',
            'post_to_facebook' => 'nullable',
            'post_to_instagram' => 'nullable',
            'action' => 'required|in:save,publish',
        ]);

        $postToFb = $request->has('post_to_facebook');
        $postToIg = $request->has('post_to_instagram');

        if ($validated['action'] === 'publish' && $postToIg && empty($validated['image_url'])) {
            return back()->withInput()->with('error', 'Instagram-ზე გამოსაქვეყნებლად სურათის URL აუცილებელია.');
        }

        $facebookPost->update([
            'message' => $validated['message'],
            'product_id' => $validated['product_id'] ?? null,
            'image_url' => $validated['image_url'] ?? null,
            'post_to_facebook' => $postToFb,
            'post_to_instagram' => $postToIg,
        ]);

        if ($validated['action'] === 'publish') {
            return $this->publishPost($facebookPost);
        }

        return redirect()
            ->route('admin.facebook-posts.index')
            ->with('success', 'პოსტი განახლდა');
    }

    public function destroy(FacebookPost $facebookPost): RedirectResponse
    {
        $facebookPost->delete();

        return redirect()
            ->route('admin.facebook-posts.index')
            ->with('success', 'პოსტი წაიშალა');
    }

    /**
     * Publish a draft post to Facebook.
     */
    public function publish(FacebookPost $facebookPost): RedirectResponse
    {
        return $this->publishPost($facebookPost);
    }

    /**
     * AI-generate post content (AJAX).
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'description' => 'nullable|string|max:1000',
            'language' => 'required|in:ka,en',
            'tone' => 'required|in:professional,casual,exciting,urgent',
        ]);

        if (!empty($validated['product_id'])) {
            $product = Product::with('primaryImage')->findOrFail($validated['product_id']);
            $result = $this->aiService->generateProductPost(
                $product,
                $validated['language'],
                $validated['tone']
            );

            if ($result['success']) {
                $imageUrl = null;
                if ($product->primaryImage) {
                    $imageUrl = asset('storage/' . $product->primaryImage->path);
                }
                $result['image_url'] = $imageUrl;
            }
        } else {
            $description = $validated['description'] ?? 'სმარტ საათების მაღაზია MyTechnic.ge';
            $result = $this->aiService->generateCustomPost(
                $description,
                $validated['language'],
                $validated['tone']
            );
        }

        return response()->json($result);
    }

    private function publishPost(FacebookPost $post): RedirectResponse
    {
        $errors = [];
        $successes = [];
        $fbPostId = $post->facebook_post_id;
        $igPostId = $post->instagram_post_id;

        // Publish to Facebook
        if ($post->post_to_facebook) {
            if (!$this->facebookService->isConfigured()) {
                $errors[] = 'Facebook API არ არის კონფიგურირებული';
            } else {
                $fbResult = $this->facebookService->publishPost($post->message, $post->image_url);
                if ($fbResult['success']) {
                    $fbPostId = $fbResult['post_id'];
                    $successes[] = 'Facebook';
                } else {
                    $errors[] = 'Facebook: ' . $fbResult['error'];
                }
            }
        }

        // Publish to Instagram
        if ($post->post_to_instagram) {
            if (!$this->instagramService->isConfigured()) {
                $errors[] = 'Instagram API არ არის კონფიგურირებული (INSTAGRAM_BUSINESS_ACCOUNT_ID)';
            } elseif (empty($post->image_url)) {
                $errors[] = 'Instagram-ისთვის სურათი აუცილებელია';
            } else {
                $igResult = $this->instagramService->publishPost($post->message, $post->image_url);
                if ($igResult['success']) {
                    $igPostId = $igResult['post_id'];
                    $successes[] = 'Instagram';
                } else {
                    $errors[] = 'Instagram: ' . $igResult['error'];
                }
            }
        }

        if (!$post->post_to_facebook && !$post->post_to_instagram) {
            return redirect()
                ->route('admin.facebook-posts.index')
                ->with('error', 'აირჩიეთ მინიმუმ ერთი პლატფორმა');
        }

        // Update post record
        if (!empty($successes)) {
            $post->update([
                'status' => empty($errors) ? 'published' : 'published',
                'facebook_post_id' => $fbPostId,
                'instagram_post_id' => $igPostId,
                'published_at' => now(),
                'error_message' => !empty($errors) ? implode('; ', $errors) : null,
            ]);

            $msg = implode(' & ', $successes) . '-ზე წარმატებით გამოქვეყნდა!';
            if (!empty($errors)) {
                $msg .= ' (შეცდომა: ' . implode('; ', $errors) . ')';
            }

            return redirect()
                ->route('admin.facebook-posts.index')
                ->with('success', $msg);
        }

        $post->update([
            'status' => 'failed',
            'error_message' => implode('; ', $errors),
        ]);

        return redirect()
            ->route('admin.facebook-posts.index')
            ->with('error', 'გამოქვეყნება ვერ მოხერხდა: ' . implode('; ', $errors));
    }
}
