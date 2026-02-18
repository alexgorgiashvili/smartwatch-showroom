<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\Chatbot\RagContextBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    public function respond(Request $request, RagContextBuilder $ragBuilder): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $apiKey = config('services.openai.key');
        $model = config('services.openai.model', 'gpt-4.1-mini');
        $baseUrl = rtrim(config('services.openai.base_url', 'https://api.openai.com/v1'), '/');

        if (!$apiKey) {
            return response()->json([
                'message' => 'ჩატბოტი დროებით გამორთულია. სცადეთ მოგვიანებით.',
            ], 503);
        }

        $systemPrompt = implode("\n", [
            'ROLE: You are the KidSIM Watch assistant.',
            'LANGUAGE: Reply only in fluent Georgian.',
            'TONE: Friendly, warm, and helpful.',
            'STYLE: Short paragraphs; avoid slang; be clear and practical.',
            'FACTS: Use the provided context when possible. If unsure, suggest contacting the team.',
            'PRODUCTS: When recommending, mention name, key features, and price if available.',
            'LINKS: Share product or contact links when helpful.',
        ]);

        $ragContext = $ragBuilder->build($request->input('message'));

        $contextSections = [
            'Site links:',
            '- Home: ' . route('home'),
            '- Catalog: ' . route('products.index'),
            '- Contact: ' . route('contact'),
        ];

        if ($ragContext) {
            $contextSections[] = 'Knowledge base:';
            $contextSections[] = $ragContext;
        } else {
            $products = Product::active()
                ->with('primaryImage')
                ->orderByDesc('updated_at')
                ->take(6)
                ->get();

            $productLines = $products->map(function (Product $product): string {
                $price = $product->sale_price
                    ? $product->sale_price . ' (ფასდაკლება, ძველი ფასი ' . $product->price . ')'
                    : (string) $product->price;

                $imagePath = $product->primaryImage?->path
                    ? url('storage/' . $product->primaryImage->path)
                    : null;

                $imagePart = $imagePath ? ' | image: ' . $imagePath : '';

                return '- ' . $product->name . ' | slug: ' . $product->slug . ' | price: ' . $price . $imagePart;
            })->implode("\n");

            $contextSections[] = 'Products:';
            $contextSections[] = $productLines !== '' ? $productLines : 'No products available.';
        }

        $context = implode("\n", $contextSections);

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $context . "\n\nUser question: " . $request->input('message')],
            ],
            'temperature' => 0.4,
            'max_tokens' => 400,
        ];

        try {
            $response = Http::withToken($apiKey)
                ->timeout(20)
                ->post($baseUrl . '/chat/completions', $payload);

            if (!$response->successful()) {
                Log::warning('OpenAI request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'message' => 'ბოდიში, სერვისი დროებით მიუწვდომელია.',
                ], 502);
            }

            $reply = data_get($response->json(), 'choices.0.message.content');

            if (!$reply) {
                return response()->json([
                    'message' => 'ბოდიში, პასუხი ვერ მივიღე. სცადეთ კიდევ ერთხელ.',
                ], 502);
            }

            return response()->json([
                'message' => trim($reply),
            ]);
        } catch (\Throwable $exception) {
            Log::error('OpenAI exception', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'ბოდიში, დროებით პრობლემა გვაქვს. სცადეთ მოგვიანებით.',
            ], 500);
        }
    }
}
