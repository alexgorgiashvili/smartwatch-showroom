<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FacebookPost;
use App\Models\Product;
use App\Models\User;
use App\Services\AiPostGeneratorService;
use App\Services\Chatbot\ModelCompletionService;
use App\Services\FacebookPageService;
use App\Services\InstagramPageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FacebookPostController extends Controller
{
    public function __construct(
        private FacebookPageService $facebookService,
        private InstagramPageService $instagramService,
        private AiPostGeneratorService $aiService,
    ) {}

    private function resolveAuthorId(Request $request): int
    {
        $userId = $request->user()?->id
            ?? auth()->id()
            ?? User::query()->where('is_admin', true)->value('id')
            ?? User::query()->value('id');

        abort_unless($userId, 403, 'No admin user available for post creation.');

        return (int) $userId;
    }

    public function index(Request $request)
    {
        $posts = FacebookPost::with(['user', 'product'])
            ->when(
                $request->filled('status') && in_array($request->string('status')->value(), ['draft', 'published', 'failed'], true),
                fn ($q) => $q->where('status', $request->string('status')->value())
            )
            ->orderByDesc('created_at')
            ->paginate(15)
            ->appends($request->query());

        $view = view('admin.facebook-posts.index', compact('posts'));

        return $this->renderPjaxView($request, $view);
    }

    public function create(Request $request)
    {
        $products = Product::active()
            ->orderBy('name_ka')
            ->get(['id', 'name_ka', 'name_en', 'price', 'sale_price']);

        $view = view('admin.facebook-posts.create', [
            'products' => $products,
            'fbConfigured' => $this->facebookService->isConfigured(),
            'igConfigured' => $this->instagramService->isConfigured(),
        ]);

        return $this->renderPjaxView($request, $view);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:5000',
            'product_id' => 'nullable|exists:products,id',
            'image_url' => 'nullable|url|max:2000',
            'media_type' => 'nullable|in:none,image,video',
            'video_url' => 'nullable|url|max:2000',
            'ai_prompt' => 'nullable|string|max:5000',
            'post_to_facebook' => 'nullable',
            'post_to_instagram' => 'nullable',
            'scheduled_at' => 'nullable|date|after:now',
            'action' => 'required|in:draft,schedule,publish',
        ]);

        $postToFb = $request->has('post_to_facebook');
        $postToIg = $request->has('post_to_instagram');

        $mediaType = $validated['media_type']
            ?? (!empty($validated['video_url']) ? 'video' : (!empty($validated['image_url']) ? 'image' : 'none'));

        if ($validated['action'] === 'publish' && $postToIg && $mediaType === 'none') {
            return back()->withInput()->with('error', 'Instagram-ზე გამოსაქვეყნებლად სურათი ან ვიდეო აუცილებელია.');
        }

        if ($validated['action'] === 'publish' && $postToIg && $mediaType === 'video' && empty($validated['video_url'])) {
            return back()->withInput()->with('error', 'Instagram-ზე ვიდეოს გამოსაქვეყნებლად ვიდეოს URL აუცილებელია.');
        }

        if ($validated['action'] === 'publish' && $postToIg && $mediaType === 'image' && empty($validated['image_url'])) {
            return back()->withInput()->with('error', 'Instagram-ზე გამოსაქვეყნებლად სურათის URL აუცილებელია.');
        }

        $isSchedule = $validated['action'] === 'schedule';
        $authorId = $this->resolveAuthorId($request);

        $post = FacebookPost::create([
            'user_id' => $authorId,
            'product_id' => $validated['product_id'] ?? null,
            'message' => $validated['message'],
            'image_url' => $validated['image_url'] ?? null,
            'media_type' => $mediaType,
            'video_url' => $validated['video_url'] ?? null,
            'post_to_facebook' => $postToFb,
            'post_to_instagram' => $postToIg,
            'ai_prompt' => $validated['ai_prompt'] ?? null,
            'status' => $isSchedule ? 'scheduled' : 'draft',
            'scheduled_at' => $isSchedule ? ($validated['scheduled_at'] ?? null) : null,
        ]);

        if ($validated['action'] === 'publish') {
            return $this->publishPost($post, $request);
        }

        if ($isSchedule) {
            $msg = 'პოსტი დაგეგმილდა';
            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => $msg, 'redirect' => route('admin.facebook-posts.index')]);
            }
            return redirect()->route('admin.facebook-posts.index')->with('success', $msg);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'პოსტი შეინახა დრაფტად',
                'redirect' => route('admin.facebook-posts.index')
            ]);
        }

        return redirect()
            ->route('admin.facebook-posts.index')
            ->with('success', 'პოსტი შეინახა დრაფტად');
    }

    public function edit(Request $request, FacebookPost $facebookPost)
    {
        $products = Product::active()
            ->orderBy('name_ka')
            ->get(['id', 'name_ka', 'name_en', 'price', 'sale_price']);

        $view = view('admin.facebook-posts.edit', [
            'post' => $facebookPost,
            'products' => $products,
            'fbConfigured' => $this->facebookService->isConfigured(),
            'igConfigured' => $this->instagramService->isConfigured(),
        ]);

        return $this->renderPjaxView($request, $view);
    }

    public function update(Request $request, FacebookPost $facebookPost): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:5000',
            'product_id' => 'nullable|exists:products,id',
            'image_url' => 'nullable|url|max:2000',
            'media_type' => 'nullable|in:none,image,video',
            'video_url' => 'nullable|url|max:2000',
            'post_to_facebook' => 'nullable',
            'post_to_instagram' => 'nullable',
            'scheduled_at' => 'nullable|date|after:now',
            'action' => 'required|in:save,schedule,publish',
        ]);

        $postToFb = $request->has('post_to_facebook');
        $postToIg = $request->has('post_to_instagram');

        $mediaType = $validated['media_type']
            ?? (!empty($validated['video_url']) ? 'video' : (!empty($validated['image_url']) ? 'image' : 'none'));

        if ($validated['action'] === 'publish' && $postToIg && $mediaType === 'none') {
            return back()->withInput()->with('error', 'Instagram-ზე გამოსაქვეყნებლად სურათი ან ვიდეო აუცილებელია.');
        }

        if ($validated['action'] === 'publish' && $postToIg && $mediaType === 'video' && empty($validated['video_url'])) {
            return back()->withInput()->with('error', 'Instagram-ზე ვიდეოს გამოსაქვეყნებლად ვიდეოს URL აუცილებელია.');
        }

        if ($validated['action'] === 'publish' && $postToIg && $mediaType === 'image' && empty($validated['image_url'])) {
            return back()->withInput()->with('error', 'Instagram-ზე გამოსაქვეყნებლად სურათის URL აუცილებელია.');
        }

        $facebookPost->update([
            'message' => $validated['message'],
            'product_id' => $validated['product_id'] ?? null,
            'image_url' => $validated['image_url'] ?? null,
            'media_type' => $mediaType,
            'video_url' => $validated['video_url'] ?? null,
            'post_to_facebook' => $postToFb,
            'post_to_instagram' => $postToIg,
        ]);

        if ($validated['action'] === 'publish') {
            return $this->publishPost($facebookPost, $request);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'პოსტი განახლდა',
                'redirect' => route('admin.facebook-posts.index')
            ]);
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
    public function publish(Request $request, FacebookPost $facebookPost)
    {
        return $this->publishPost($facebookPost, $request);
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
            'mode' => 'nullable|in:custom,autonomous',
        ]);

        $mode = $validated['mode'] ?? 'custom';
        $product = null;

        if (!empty($validated['product_id'])) {
            $product = Product::with('primaryImage')->findOrFail($validated['product_id']);
        }

        $result = $this->aiService->generateThreeVariants(
            $product,
            $validated['language'],
            $validated['description'] ?? null,
            $mode,
            $validated['tone']
        );

        if ($result['success'] && $product && $product->primaryImage) {
            $result['image_url'] = asset('storage/' . $product->primaryImage->path);
        }

        return response()->json($result);
    }

    /**
     * AI-suggest hashtags based on post message (AJAX).
     */
    public function suggestHashtags(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message'    => 'nullable|string|max:5000',
            'product_id' => 'nullable|exists:products,id',
        ]);

        $context = trim($validated['message'] ?? '');

        if (!empty($validated['product_id'])) {
            $product = Product::find($validated['product_id']);
            if ($product) {
                $context = ($product->name_ka ?: $product->name_en) . "\n\n" . $context;
            }
        }

        if (empty($context)) {
            return response()->json(['hashtags' => []]);
        }

        try {
            $completion = app(ModelCompletionService::class);
            $result = $completion->complete(
                'gpt-4.1-nano',
                [
                    [
                        'role'    => 'system',
                        'content' => 'Generate 12-15 relevant hashtags for a Facebook/Instagram post about kids smartwatches (SIM card, GPS tracking). Mix Georgian and English hashtags. Return ONLY a JSON array of strings, no markdown, no explanation.',
                    ],
                    [
                        'role'    => 'user',
                        'content' => mb_substr($context, 0, 500),
                    ],
                ],
                ['max_tokens' => 200, 'temperature' => 0.7]
            );

            $raw = trim($result['reply'] ?? '[]');
            $raw = preg_replace('/^```[a-z]*\n?|\n?```$/', '', $raw);
            $hashtags = json_decode($raw, true);

            if (!is_array($hashtags)) {
                $hashtags = [];
            }

            return response()->json(['hashtags' => array_values(array_filter($hashtags, 'is_string'))]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'AI generation failed'], 500);
        }
    }

    /**
     * Enhance custom instructions via Georgian Prompt Enhancer (AJAX).
     */
    public function enhancePrompt(Request $request, \App\Services\AI\GeorgianPromptEnhancerService $enhancer): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => 'required|string|max:2000',
            'tone' => 'required|string|in:professional,casual,exciting,urgent',
        ]);

        $result = $enhancer->enhancePrompt($validated['prompt'], $validated['tone']);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'enhanced_prompt' => $result['enhanced_prompt'],
                'metadata' => $result['metadata'],
                'log_id' => $result['log_id']
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error']
        ]);
    }

    private function publishPost(FacebookPost $post, Request $request = null)
    {
        $errors = [];
        $successes = [];
        $fbPostId = $post->facebook_post_id;
        $igPostId = $post->instagram_post_id;
        $mediaType = $post->media_type ?: ($post->video_url ? 'video' : ($post->image_url ? 'image' : 'none'));

        // Publish to Facebook
        if ($post->post_to_facebook) {
            if (!$this->facebookService->isConfigured()) {
                $errors[] = 'Facebook API არ არის კონფიგურირებული';
            } else {
                $post->forceFill(['facebook_publish_status' => 'publishing'])->save();

                $fbResult = $this->facebookService->publishPost(
                    $post->message,
                    $mediaType === 'image' ? $post->image_url : null,
                    $mediaType === 'video' ? $post->video_url : null,
                );
                if ($fbResult['success']) {
                    $fbPostId = $fbResult['post_id'];
                    $successes[] = 'Facebook';
                    $post->forceFill([
                        'facebook_post_id' => $fbPostId,
                        'facebook_publish_status' => 'published',
                        'facebook_error' => null,
                    ])->save();
                } else {
                    $errors[] = 'Facebook: ' . $fbResult['error'];
                    $post->forceFill([
                        'facebook_publish_status' => 'failed',
                        'facebook_error' => $fbResult['error'],
                    ])->save();
                }
            }
        }

        // Publish to Instagram
        if ($post->post_to_instagram) {
            if (!$this->instagramService->isConfigured()) {
                $errors[] = 'Instagram API არ არის კონფიგურირებული (INSTAGRAM_BUSINESS_ACCOUNT_ID)';
            } elseif ($mediaType === 'none') {
                $errors[] = 'Instagram-ისთვის სურათი ან ვიდეო აუცილებელია';
            } else {
                $post->forceFill(['instagram_publish_status' => 'publishing'])->save();

                $mediaUrl = $mediaType === 'video' ? $post->video_url : $post->image_url;
                $igResult = $this->instagramService->publishPost($post->message, (string) $mediaUrl, $mediaType);

                if ($igResult['success']) {
                    $igPostId = $igResult['post_id'];
                    $successes[] = 'Instagram';
                    $post->forceFill([
                        'instagram_post_id' => $igPostId,
                        'instagram_container_id' => $igResult['container_id'] ?? null,
                        'instagram_publish_status' => 'published',
                        'instagram_error' => null,
                    ])->save();
                } else {
                    $isRetryable = !empty($igResult['retryable']);

                    $post->forceFill([
                        'instagram_container_id' => $igResult['container_id'] ?? null,
                        'instagram_publish_status' => $isRetryable ? 'publishing' : 'failed',
                        'instagram_error' => $isRetryable ? null : ($igResult['error'] ?? 'Instagram publish failed'),
                    ])->save();

                    if ($isRetryable) {
                        $successes[] = 'Instagram (processing)';
                    } else {
                        $errors[] = 'Instagram: ' . ($igResult['error'] ?? 'Publish failed');
                    }
                }
            }
        }

        if (!$post->post_to_facebook && !$post->post_to_instagram) {
            if ($request && $request->wantsJson()) {
                return response()->json(['success' => false, 'error' => 'აირჩიეთ მინიმუმ ერთი პლატფორმა']);
            }
            return redirect()
                ->route('admin.facebook-posts.index')
                ->with('error', 'აირჩიეთ მინიმუმ ერთი პლატფორმა');
        }

        $post->refresh();

        $hasAnyPlatformProgress = ($post->facebook_publish_status === 'published')
            || ($post->instagram_publish_status === 'published')
            || ($post->instagram_publish_status === 'publishing');

        if ($hasAnyPlatformProgress) {
            $post->update([
                'status' => 'published',
                'facebook_post_id' => $fbPostId,
                'instagram_post_id' => $igPostId,
                'published_at' => $post->published_at ?: now(),
                'error_message' => !empty($errors) ? implode('; ', $errors) : null,
                'last_publish_check_at' => now(),
            ]);

            $msg = implode(' & ', $successes) . '-ზე წარმატებით გამოქვეყნდა!';
            if (!empty($errors)) {
                $msg .= ' (შეცდომა: ' . implode('; ', $errors) . ')';
            }

            if ($request && $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'redirect' => route('admin.facebook-posts.index')
                ]);
            }

            return redirect()
                ->route('admin.facebook-posts.index')
                ->with('success', $msg);
        }

        $post->update([
            'status' => 'failed',
            'error_message' => implode('; ', $errors),
            'last_publish_check_at' => now(),
        ]);

        if ($request && $request->wantsJson()) {
            return response()->json(['success' => false, 'error' => 'გამოქვეყნება ვერ მოხერხდა: ' . implode('; ', $errors)]);
        }

        return redirect()
            ->route('admin.facebook-posts.index')
            ->with('error', 'გამოქვეყნება ვერ მოხერხდა: ' . implode('; ', $errors));
    }
}
