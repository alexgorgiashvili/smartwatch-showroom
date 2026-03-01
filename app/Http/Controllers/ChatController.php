<?php

namespace App\Http\Controllers;

use App\Events\MessageReceived;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Conversation;
use App\Models\ContactSetting;
use App\Models\Message;
use App\Models\ProductVariant;
use App\Services\Chatbot\ChatbotQualityMetricsService;
use App\Services\Chatbot\UnifiedAiPolicyService;
use App\Services\Chatbot\RagContextBuilder;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    /**
     * Get or create a session-based customer for the widget
     */
    private function getWidgetCustomer(Request $request): Customer
    {
        $sessionId = session()->getId();
        $identifier = 'widget_' . $sessionId;

        return Customer::firstOrCreate(
            ['platform_user_ids->home' => $identifier],
            [
                'name' => 'Widget User (' . substr($identifier, 0, 8) . ')',
                'platform_user_ids' => ['home' => $identifier],
                'avatar_url' => null,
                'metadata' => [
                    'widget_session_id' => $sessionId,
                    'first_interaction' => now()->toIso8601String(),
                ],
            ]
        );
    }

    /**
     * Get or create conversation for widget session
     */
    private function getWidgetConversation(Customer $customer): Conversation
    {
        $conversation = $customer->conversations()
            ->where('platform', 'home')
            ->where('status', 'active')
            ->latest('last_message_at')
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'customer_id' => $customer->id,
                'platform' => 'home',
                'platform_conversation_id' => 'widget_' . Str::uuid(),
                'subject' => 'Widget Chat',
                'status' => 'active',
                'unread_count' => 0,
                'last_message_at' => now(),
            ]);
        }

        return $conversation;
    }

    public function respond(
        Request $request,
        RagContextBuilder $ragBuilder,
        UnifiedAiPolicyService $policy,
        ChatbotQualityMetricsService $metrics
    ): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        // Get or create widget customer and conversation
        $customer = $this->getWidgetCustomer($request);
        $conversation = $this->getWidgetConversation($customer);

        // Save user message
        $customerMessage = Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'sender_type' => 'customer',
            'sender_id' => $customer->id,
            'sender_name' => $customer->name,
            'content' => $request->input('message'),
            'platform_message_id' => 'home_' . Str::uuid(),
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'unread_count' => $conversation->unread_count + 1,
        ]);

        event(new MessageReceived(
            $customerMessage,
            $conversation,
            $customer,
            'home'
        ));

        app(PushNotificationService::class)->sendToAdmins(
            'New message from ' . ($customer->name ?: 'Customer'),
            mb_substr((string) $customerMessage->content, 0, 120),
            url('/admin/inbox?conversation=' . $conversation->id),
            [
                'conversation_id' => $conversation->id,
                'message_id' => $customerMessage->id,
                'platform' => 'home',
            ]
        );


        $apiKey = config('services.openai.key');
        $model = config('services.openai.model', 'gpt-4.1-mini');
        $baseUrl = rtrim(config('services.openai.base_url', 'https://api.openai.com/v1'), '/');

        if (!$apiKey) {
            return response()->json([
                'message' => 'ჩატბოტი დროებით გამორთულია. სცადეთ მოგვიანებით.',
            ], 503);
        }

        $systemPrompt = $policy->websiteSystemPrompt();
        $normalizedMessage = $policy->normalizeIncomingMessage($request->input('message'));

        $ragContext = $ragBuilder->build($normalizedMessage);

        $products = Product::active()
            ->with(['primaryImage', 'variants'])
            ->withSum('variants as total_stock', 'quantity')
            ->orderByDesc('updated_at')
            ->take(6)
            ->get()
            ->sortByDesc(fn (Product $product): bool => $this->messageMentionsProduct($normalizedMessage, $product))
            ->values();

        $requestedProduct = $products->first(
            fn (Product $product): bool => $this->messageMentionsProduct($normalizedMessage, $product)
        );

        $productLines = $products->map(function (Product $product): string {
            $price = $product->sale_price
                ? $product->sale_price . ' (ფასდაკლება, ძველი ფასი ' . $product->price . ')'
                : (string) $product->price;

            $imagePath = $product->primaryImage?->url
                ? $product->primaryImage->url
                : null;

            $imagePart = $imagePath ? ' | image: ' . $imagePath : '';
            $stockTotal = max(0, (int) ($product->total_stock ?? 0));
            $stockStatus = $stockTotal > 0 ? 'მარაგშია' : 'ამოწურულია';

            return '- ' . $product->name
                . ' | slug: ' . $product->slug
                . ' | price: ' . $price
                . ' | stock: ' . $stockStatus . ' (' . $stockTotal . ' ცალი)'
                . $imagePart;
        })->implode("\n");

        $contextSections = [
            'Site links:',
            '- Home: ' . route('home'),
            '- Catalog: ' . route('products.index'),
            '- Contact: ' . route('contact'),
        ];

        $contactSettings = ContactSetting::allKeyed();
        $contextSections[] = 'Contact info (live from admin settings):';
        $contextSections[] = '- Phone: ' . ($contactSettings['phone_display'] ?? '');
        $contextSections[] = '- WhatsApp: ' . ($contactSettings['whatsapp_url'] ?? '');
        $contextSections[] = '- Email: ' . ($contactSettings['email'] ?? '');
        $contextSections[] = '- Location: ' . ($contactSettings['location'] ?? '');
        $contextSections[] = '- Working hours: ' . ($contactSettings['hours'] ?? '');

        if ($ragContext) {
            $contextSections[] = 'Knowledge base:';
            $contextSections[] = $ragContext;
        }

        if ($requestedProduct instanceof Product) {
            $requestedProductContext = $this->buildRequestedProductContext($normalizedMessage, $requestedProduct);
            if ($requestedProductContext !== '') {
                $contextSections[] = 'Requested product (exact match from live database):';
                $contextSections[] = $requestedProductContext;
            }
        }

        $contextSections[] = 'Products (live stock from database):';
        $contextSections[] = $productLines !== '' ? $productLines : 'No products available.';

        $context = implode("\n", $contextSections);

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $context . "\n\nUser question: " . $normalizedMessage],
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
                    'conversation_id' => $conversation->id,
                ], 502);
            }

            $reply = data_get($response->json(), 'choices.0.message.content');

            if (!$reply) {
                return response()->json([
                    'message' => 'ბოდიში, პასუხი ვერ მივიღე. სცადეთ კიდევ ერთხელ.',
                    'conversation_id' => $conversation->id,
                ], 502);
            }

            $reply = trim($reply);
            $modelOutput = $reply;
            $strictQaPassed = $policy->passesStrictGeorgianQa($modelOutput);
            $nonGeorgianModelOutput = preg_match('/\p{Georgian}/u', $modelOutput) !== 1;

            if (!$strictQaPassed) {
                $reply = $policy->strictGeorgianFallback();
            }

            $metrics->recordWidgetResponseQuality(
                $conversation->id,
                $customer->id,
                !$strictQaPassed,
                $nonGeorgianModelOutput
            );

            // Save bot's reply message
            Message::create([
                'conversation_id' => $conversation->id,
                'customer_id' => $customer->id,
                'sender_type' => 'bot',
                'sender_id' => 0,
                'sender_name' => 'MyTechnic Assistant',
                'content' => $reply,
                'platform_message_id' => 'home_' . Str::uuid(),
            ]);

            // Update conversation last message time and mark unread
            $conversation->update([
                'last_message_at' => now(),
            ]);

            return response()->json([
                'message' => $reply,
                'conversation_id' => $conversation->id,
            ]);
        } catch (\Throwable $exception) {
            Log::error('OpenAI exception', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'ბოდიში, დროებით პრობლემა გვაქვს. სცადეთ მოგვიანებით.',
                'conversation_id' => $conversation->id,
            ], 500);
        }
    }

    private function messageMentionsProduct(string $message, Product $product): bool
    {
        $haystack = Str::lower($message);

        $candidates = array_filter([
            Str::lower((string) $product->name_en),
            Str::lower((string) $product->name_ka),
            Str::lower((string) $product->slug),
            Str::replace('-', ' ', Str::lower((string) $product->slug)),
        ]);

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && Str::contains($haystack, $candidate)) {
                return true;
            }
        }

        $slugTokens = collect(preg_split('/[-\s]+/u', (string) $product->slug))
            ->filter(fn ($token) => is_string($token) && mb_strlen($token) >= 4)
            ->map(fn ($token) => Str::lower($token))
            ->values();

        if ($slugTokens->isEmpty()) {
            return false;
        }

        $matchedTokens = $slugTokens
            ->filter(fn (string $token): bool => Str::contains($haystack, $token))
            ->count();

        if ($slugTokens->count() === 1) {
            return $matchedTokens === 1;
        }

        return $matchedTokens >= 2;
    }

    private function buildRequestedProductContext(string $message, Product $product): string
    {
        $stockTotal = max(0, (int) ($product->total_stock ?? 0));
        $stockStatus = $stockTotal > 0 ? 'მარაგშია' : 'ამოწურულია';

        $lines = [
            '- Product: ' . $product->name . ' | slug: ' . $product->slug,
            '- Total stock: ' . $stockStatus . ' (' . $stockTotal . ' ცალი)',
        ];

        $matchedVariant = $this->matchVariantFromMessage($message, $product);
        if ($matchedVariant instanceof ProductVariant) {
            $variantQty = max(0, (int) $matchedVariant->quantity);
            $variantStatus = $variantQty > 0 ? 'მარაგშია' : 'ამოწურულია';

            $lines[] = '- Requested variant: ' . $matchedVariant->name;
            $lines[] = '- Variant stock: ' . $variantStatus . ' (' . $variantQty . ' ცალი)';
        }

        $variantLines = $product->variants
            ->map(function (ProductVariant $variant): string {
                $qty = max(0, (int) $variant->quantity);
                $status = $qty > 0 ? 'მარაგშია' : 'ამოწურულია';
                return $variant->name . ' => ' . $status . ' (' . $qty . ' ცალი)';
            })
            ->values();

        if ($variantLines->isNotEmpty()) {
            $lines[] = '- Variants:';
            foreach ($variantLines as $variantLine) {
                $lines[] = '  - ' . $variantLine;
            }
        }

        return implode("\n", $lines);
    }

    private function matchVariantFromMessage(string $message, Product $product): ?ProductVariant
    {
        $haystack = Str::lower($message);
        $searchPool = collect([$haystack, ...$this->colorAliasExpansions($haystack)])->unique()->values();

        foreach ($product->variants as $variant) {
            $fullVariantName = Str::lower((string) $variant->name);
            if ($fullVariantName === '') {
                continue;
            }

            $matched = $searchPool->contains(
                fn (string $searchText): bool => Str::contains($searchText, $fullVariantName)
            );

            if ($matched) {
                return $variant;
            }
        }

        foreach ($product->variants as $variant) {
            $tokens = collect(preg_split('/[\s,:;\/\-]+/u', (string) $variant->name))
                ->filter(fn ($token) => is_string($token) && mb_strlen($token) >= 3)
                ->map(fn ($token) => Str::lower($token))
                ->reject(fn (string $token) => in_array($token, ['color', 'size'], true))
                ->values();

            if ($tokens->isEmpty()) {
                continue;
            }

            $matched = $tokens
                ->filter(function (string $token) use ($searchPool): bool {
                    return $searchPool->contains(
                        fn (string $searchText): bool => Str::contains($searchText, $token)
                    );
                })
                ->count();

            if ($matched >= 1) {
                return $variant;
            }
        }

        return null;
    }

    private function colorAliasExpansions(string $message): array
    {
        $aliasGroups = [
            ['blue', 'ლურჯი'],
            ['black', 'შავი'],
            ['white', 'თეთრი'],
            ['pink', 'ვარდისფერი'],
            ['green', 'მწვანე'],
            ['red', 'წითელი'],
            ['gold', 'ოქროსფერი'],
            ['silver', 'ვერცხლისფერი'],
            ['gray', 'grey', 'ნაცრისფერი'],
        ];

        $expansions = [];

        foreach ($aliasGroups as $group) {
            $present = false;
            foreach ($group as $alias) {
                if (Str::contains($message, $alias)) {
                    $present = true;
                    break;
                }
            }

            if (!$present) {
                continue;
            }

            foreach ($group as $alias) {
                $expansions[] = $message . ' ' . $alias;
            }
        }

        return $expansions;
    }
}
