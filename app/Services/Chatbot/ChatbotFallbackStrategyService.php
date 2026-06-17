<?php

namespace App\Services\Chatbot;

use App\Models\ContactSetting;
use App\Models\Product;
use App\Services\Chatbot\ChatbotFallbackResolution;
use Illuminate\Support\Facades\Log;

class ChatbotFallbackStrategyService
{
    private const STATIC_REASON_REPLIES = [
        ChatbotOutcomeReason::CHATBOT_DISABLED => 'ჩატბოტი დროებით გამორთულია. სცადეთ მოგვიანებით.',
        ChatbotOutcomeReason::PROVIDER_UNAVAILABLE => 'ბოდიში, სერვისი დროებით მიუწვდომელია.',
        ChatbotOutcomeReason::PROVIDER_EXCEPTION => 'ბოდიში, დროებით პრობლემა გვაქვს. სცადეთ მოგვიანებით.',
        ChatbotOutcomeReason::EMPTY_MODEL_OUTPUT => 'ბოდიში, პასუხი ვერ მივიღე. სცადეთ კიდევ ერთხელ.',
    ];

    public function __construct(
        private UnifiedAiPolicyService $policy,
        private ResponseValidatorService $responseValidator
    ) {
    }

    public function resolveGuardOutcome(InputGuardResult $guardResult): ChatbotFallbackResolution
    {
        $reply = $guardResult->safeReply() ?: $this->policy->strictGeorgianFallback();

        return $this->resolution(
            $reply,
            ChatbotOutcomeReason::INPUT_GUARD,
            true,
            [],
            $this->policy->passesStrictGeorgianQa($reply)
        );
    }

    public function resolveGreetingOutcome(): ChatbotFallbackResolution
    {
        $reply = $this->policy->websiteGreetingReply();

        return $this->resolution(
            $reply,
            ChatbotOutcomeReason::GREETING_ONLY,
            true,
            [],
            $this->policy->passesStrictGeorgianQa($reply)
        );
    }

    public function resolveIntentOutcome(IntentResult $intentResult, string $reply): ChatbotFallbackResolution
    {
        $reason = match ($intentResult->intent()) {
            'out_of_domain' => ChatbotOutcomeReason::OUT_OF_DOMAIN,
            'clarification_needed' => ChatbotOutcomeReason::CLARIFICATION_NEEDED,
            default => null,
        };

        return $this->resolution(
            $reply,
            $reason,
            true,
            [],
            $this->policy->passesStrictGeorgianQa($reply)
        );
    }

    public function resolveStaticReason(string $reason, ?string $reply = null): ChatbotFallbackResolution
    {
        $resolvedReply = $reply ?? $this->replyForReason($reason);

        return $this->resolution(
            $resolvedReply,
            $reason,
            true,
            [],
            $this->policy->passesStrictGeorgianQa($resolvedReply)
        );
    }

    public function resolveProviderFailureOutcome(
        ?IntentResult $intentResult,
        array $validationContext,
        array $history = [],
        array $preferences = []
    ): ChatbotFallbackResolution {
        $reply = $this->buildProviderFailureReply($intentResult, $validationContext, $history, $preferences);

        if (trim($reply) === '') {
            $reply = $this->policy->strictGeorgianFallback();
        }

        if (!$this->policy->passesStrictGeorgianQa($reply)) {
            $reply = $this->policy->strictGeorgianFallback();
        }

        $validation = $this->responseValidator->validateAll($reply, $validationContext, $intentResult);

        if (!$validation->isValid()) {
            $reply = $this->policy->strictGeorgianFallback();
            $validation = $this->responseValidator->validateAll($reply, $validationContext, $intentResult);
        }

        return $this->resolution(
            $reply,
            null,
            $validation->isValid(),
            $validation->violations(),
            $this->policy->passesStrictGeorgianQa($reply)
        );
    }

    public function resolveModelOutcome(
        string $modelReply,
        ?string $initialReason,
        array $validationContext,
        ?IntentResult $intentResult,
        callable $regenerate,
        int $conversationId
    ): ChatbotFallbackResolution {
        if ($initialReason !== null) {
            return $this->resolveStaticReason($initialReason);
        }

        if (!$this->policy->passesStrictGeorgianQa($modelReply)) {
            return $this->resolveStaticReason(ChatbotOutcomeReason::STRICT_GEORGIAN);
        }

        $validation = $this->responseValidator->validateAll($modelReply, $validationContext, $intentResult);

        if ($validation->isValid()) {
            return $this->resolution($modelReply, null, true, [], true);
        }

        Log::warning('Chat pipeline validator blocked reply; attempting regeneration', [
            'conversation_id' => $conversationId,
            'violations' => $validation->violations(),
        ]);

        $regenerated = $regenerate($validation->violations());
        $regeneratedReason = $regenerated['reason'] ?? null;

        if ($regeneratedReason === null) {
            $candidateReply = (string) ($regenerated['reply'] ?? '');

            if ($this->policy->passesStrictGeorgianQa($candidateReply)) {
                $candidateValidation = $this->responseValidator->validateAll($candidateReply, $validationContext, $intentResult);

                if ($candidateValidation->isValid()) {
                    return $this->resolution($candidateReply, null, true, [], true, true, true);
                }
            }
        }

        $fallbackReason = $regeneratedReason === ChatbotOutcomeReason::STRICT_GEORGIAN
            ? ChatbotOutcomeReason::STRICT_GEORGIAN
            : ChatbotOutcomeReason::VALIDATOR_RETRY_FAILED;

        $fallback = $this->resolveStaticReason($fallbackReason);

        return $this->resolution(
            $fallback->reply(),
            $fallback->fallbackReason(),
            false,
            $validation->violations(),
            $fallback->georgianPassed(),
            true,
            false
        );
    }

    /**
     * @param array<string, mixed> $validationContext
     * @param array<int, array<string, mixed>> $history
     * @param array<string, mixed> $preferences
     */
    private function buildProviderFailureReply(
        ?IntentResult $intentResult,
        array $validationContext,
        array $history,
        array $preferences
    ): string {
        $query = mb_strtolower(trim((string) ($intentResult?->standaloneQuery() ?? '')));
        $historyText = mb_strtolower(implode(' ', array_filter(array_map(
            static function (array $entry): string {
                $role = (string) ($entry['role'] ?? '');
                $content = trim((string) ($entry['content'] ?? ''));

                if (!in_array($role, ['user', 'assistant'], true) || $content === '') {
                    return '';
                }

                return $content;
            },
            collect($history)
                ->filter(fn ($entry): bool => is_array($entry))
                ->take(-6)
                ->values()
                ->all()
        ))));
        $contextText = trim($query . ' ' . $historyText);

        $contextProducts = $this->normaliseValidationProducts($validationContext);
        $catalogProducts = $contextProducts !== []
            ? $contextProducts
            : $this->fallbackCatalogProducts($preferences, 3);

        if ($this->mentionsTwoGContext($contextText)) {
            $twoGProducts = $this->twoGCatalogProducts(3);

            if ($twoGProducts !== []) {
                return implode("\n", [
                    'გირჩევთ შემდეგ 2G მოდელებს:',
                    $this->formatProductBullets($twoGProducts),
                    '',
                    'თუ სხვა ტიპის მოდელს ეძებთ, მომწერეთ ბიუჯეტი ან სასურველი ფუნქცია და უფრო ზუსტად შეგირჩევთ.',
                ]);
            }

            return $this->buildTwoGFallbackReply($catalogProducts);
        }

        if ($intentResult?->intent() === 'recommendation' || $this->looksLikeRecommendationRequest($contextText)) {
            if ($catalogProducts !== []) {
                return implode("\n", [
                    'ამ ეტაპზე რამდენიმე აქტიურ მოდელს გირჩევთ:',
                    $this->formatProductBullets($catalogProducts),
                    '',
                    'თუ გინდათ, მომწერეთ ბიუჯეტი ან სასურველი ფუნქცია (GPS, SOS, ზარები, კამერა) და უფრო ზუსტად შეგირჩევთ.',
                ]);
            }

            return 'ამ ეტაპზე ზუსტ მოდელს ვერ ვპოულობ. მომწერეთ ბიუჯეტი ან რომელი ფუნქცია გჭირდებათ (GPS, SOS, ზარები, კამერა), და უფრო ზუსტად შეგირჩევთ.';
        }

        if (in_array($intentResult?->intent(), ['price_query', 'stock_query'], true)) {
            if ($contextProducts !== []) {
                return implode("\n", [
                    $intentResult?->intent() === 'stock_query'
                        ? 'მარაგის მიხედვით რამდენიმე ვარიანტია:'
                        : 'ფასის მიხედვით რამდენიმე ვარიანტია:',
                    $this->formatProductBullets($contextProducts),
                    '',
                    'თუ კონკრეტულ მოდელს მომწერთ, ფასსა და მარაგს ზუსტად გეტყვით.',
                ]);
            }

            return 'თუ კონკრეტულ მოდელს მომწერთ, ფასსა და მარაგს ზუსტად გეტყვით.';
        }

        if ($intentResult?->intent() === 'comparison') {
            if (count($catalogProducts) >= 2) {
                return implode("\n", [
                    'შედარებისთვის შეგიძლიათ გადახედოთ ამ მოდელებს:',
                    $this->formatProductBullets($catalogProducts),
                    '',
                    'თუ ორი კონკრეტული მოდელი გაქვთ მხედველობაში, მომწერეთ და პირდაპირ შევადარებ.',
                ]);
            }

            return 'თუ ორი კონკრეტული მოდელი გაქვთ მხედველობაში, მომწერეთ და პირდაპირ შევადარებ.';
        }

        return 'დამეხმარეთ ცოტათი მეტად: მომწერეთ ბიუჯეტი, სასურველი ფუნქცია ან კონკრეტული მოდელი, და ზუსტად შეგირჩევთ.';
    }

    /**
     * @param array<int, array<string, mixed>> $products
     */
    private function buildTwoGFallbackReply(array $products): string
    {
        $reply = 'ამჟამად ჩვენს კატალოგში 2G სმარტსაათების არჩევანი არ გვაქვს.';

        if ($products !== []) {
            $reply .= "\n\n" . 'თუმცა სხვა აქტიურ მოდელებიდან გირჩევთ:';
            $reply .= "\n" . $this->formatProductBullets($products);
            $reply .= "\n\n" . 'თუ გინდათ, მომწერეთ ბიუჯეტი ან სასურველი ფუნქცია და უფრო ზუსტად შეგირჩევთ.';
        } else {
            $contacts = $this->contactInfoLine();

            if ($contacts !== '') {
                $reply .= ' დამატებითი ინფორმაციისთვის: ' . $contacts . '.';
            }
        }

        return $reply;
    }

    /**
     * @param array<int, array<string, mixed>> $validationProducts
     * @return array<int, array<string, mixed>>
     */
    private function normaliseValidationProducts(array $validationProducts): array
    {
        return collect($validationProducts)
            ->filter(fn ($product): bool => is_array($product))
            ->map(function (array $product): array {
                $price = $product['sale_price'] ?? $product['price'] ?? null;

                return [
                    'name' => trim((string) ($product['name'] ?? '')),
                    'price' => is_numeric($price) ? (float) $price : null,
                    'sale_price' => is_numeric($product['sale_price'] ?? null) ? (float) $product['sale_price'] : null,
                    'is_in_stock' => (bool) ($product['is_in_stock'] ?? false),
                    'slug' => trim((string) ($product['slug'] ?? '')),
                ];
            })
            ->filter(fn (array $product): bool => $product['name'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $preferences
     * @return array<int, array<string, mixed>>
     */
    private function fallbackCatalogProducts(array $preferences, int $limit = 3): array
    {
        $budget = isset($preferences['budget_max_gel']) && is_numeric($preferences['budget_max_gel'])
            ? (float) $preferences['budget_max_gel']
            : null;

        $products = Product::query()
            ->active()
            ->withSum('variants as total_stock', 'quantity')
            ->orderByDesc('featured')
            ->orderBy('id')
            ->limit(12)
            ->get()
            ->filter(function (Product $product) use ($budget): bool {
                $effectivePrice = is_numeric($product->sale_price) && (float) $product->sale_price > 0
                    ? (float) $product->sale_price
                    : (is_numeric($product->price) ? (float) $product->price : null);

                if ($effectivePrice === null || $effectivePrice < 0.5) {
                    return false;
                }

                if ($budget === null) {
                    return true;
                }

                return $effectivePrice <= $budget * 1.25 || $effectivePrice <= $budget + 100;
            })
            ->map(function (Product $product): array {
                $price = is_numeric($product->sale_price) && (float) $product->sale_price > 0
                    ? (float) $product->sale_price
                    : (is_numeric($product->price) ? (float) $product->price : null);

                return [
                    'name' => trim((string) $product->name),
                    'price' => $price,
                    'sale_price' => is_numeric($product->sale_price) && (float) $product->sale_price > 0
                        ? (float) $product->sale_price
                        : null,
                    'is_in_stock' => (int) ($product->total_stock ?? 0) > 0,
                    'slug' => (string) $product->slug,
                ];
            })
            ->filter(fn (array $product): bool => $product['name'] !== '')
            ->take($limit)
            ->values()
            ->all();

        return $products;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function twoGCatalogProducts(int $limit = 3): array
    {
        return Product::query()
            ->active()
            ->withSum('variants as total_stock', 'quantity')
            ->orderByDesc('featured')
            ->orderBy('id')
            ->limit(20)
            ->get()
            ->filter(function (Product $product): bool {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    (string) $product->name,
                    (string) $product->name_en,
                    (string) $product->name_ka,
                    (string) $product->slug,
                    (string) $product->brand,
                    (string) $product->model,
                ])));

                return $this->mentionsTwoGContext($haystack);
            })
            ->map(function (Product $product): array {
                $price = is_numeric($product->sale_price) && (float) $product->sale_price > 0
                    ? (float) $product->sale_price
                    : (is_numeric($product->price) ? (float) $product->price : null);

                return [
                    'name' => trim((string) $product->name),
                    'price' => $price,
                    'sale_price' => is_numeric($product->sale_price) && (float) $product->sale_price > 0
                        ? (float) $product->sale_price
                        : null,
                    'is_in_stock' => (int) ($product->total_stock ?? 0) > 0,
                    'slug' => (string) $product->slug,
                ];
            })
            ->filter(fn (array $product): bool => $product['name'] !== '')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $products
     */
    private function formatProductBullets(array $products): string
    {
        return collect($products)
            ->take(3)
            ->map(function (array $product): string {
                $parts = [trim((string) ($product['name'] ?? ''))];
                $price = $this->formatPrice($product['sale_price'] ?? $product['price'] ?? null);

                if ($price !== null) {
                    $parts[] = $price;
                }

                return '- ' . implode(' — ', array_filter($parts, fn (string $part): bool => $part !== ''));
            })
            ->implode("\n");
    }

    private function formatPrice(mixed $price): ?string
    {
        if (!is_numeric($price)) {
            return null;
        }

        $normalized = rtrim(rtrim(number_format((float) $price, 2, '.', ''), '0'), '.');

        return $normalized . ' ₾';
    }

    private function contactInfoLine(): string
    {
        $contactSettings = ContactSetting::allKeyed();
        $parts = [];

        $phone = trim((string) ($contactSettings['phone_display'] ?? ''));
        if ($phone !== '') {
            $parts[] = $phone;
        }

        $email = trim((string) ($contactSettings['email'] ?? ''));
        if ($email !== '') {
            $parts[] = $email;
        }

        return implode(' ან ', $parts);
    }

    private function mentionsTwoGContext(string $text): bool
    {
        $normalized = preg_replace('/\s+/u', ' ', mb_strtolower(trim($text))) ?? mb_strtolower(trim($text));

        return $normalized !== '' && (
            str_contains($normalized, '2g')
            || str_contains($normalized, '2 გ')
            || str_contains($normalized, '2გ')
        );
    }

    private function looksLikeRecommendationRequest(string $text): bool
    {
        return $this->containsAnyNeedle(mb_strtolower($text), [
            'მირჩ',
            'რეკომენდ',
            'suggest',
            'recommend',
            'alternative',
            'სხვა',
            'ვარიანტ',
            'model',
            'modelebi',
        ]);
    }

    /**
     * @param array<int, string> $needles
     */
    private function containsAnyNeedle(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (!is_string($needle) || trim($needle) === '') {
                continue;
            }

            if (mb_stripos($haystack, mb_strtolower(trim($needle))) !== false) {
                return true;
            }
        }

        return false;
    }

    private function replyForReason(string $reason): string
    {
        return match ($reason) {
            ChatbotOutcomeReason::STRICT_GEORGIAN => $this->policy->strictGeorgianFallback(),
            ChatbotOutcomeReason::VALIDATOR_FAILED,
            ChatbotOutcomeReason::VALIDATOR_RETRY_FAILED => $this->responseValidator->integrityFallback(),
            default => self::STATIC_REASON_REPLIES[$reason] ?? $this->responseValidator->integrityFallback(),
        };
    }

    private function resolution(
        string $reply,
        ?string $fallbackReason,
        bool $validationPassed,
        array $validationViolations,
        bool $georgianPassed,
        bool $regenerationAttempted = false,
        bool $regenerationSucceeded = false
    ): ChatbotFallbackResolution {
        return new ChatbotFallbackResolution(
            $reply,
            $fallbackReason,
            $validationPassed,
            $validationViolations,
            $georgianPassed,
            $regenerationAttempted,
            $regenerationSucceeded
        );
    }
}
