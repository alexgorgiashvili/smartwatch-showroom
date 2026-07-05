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
        $budget = $this->extractBudgetFromContext($contextText, $preferences);

        $contextProducts = $this->rankFallbackProducts(
            $this->normaliseValidationProducts($validationContext),
            $budget,
            $contextText,
            8
        );
        $catalogProducts = $contextProducts !== []
            ? $contextProducts
            : $this->fallbackCatalogProducts($preferences, $contextText, 8);

        if ($this->looksLikeWarrantyRequest($contextText)) {
            return $this->buildWarrantyFallbackReply($contextText);
        }

        if ($this->looksLikeSliderExplanationRequest($contextText)) {
            return $this->buildSliderExplanationReply();
        }

        if ($this->looksLikeAvailabilityAssumptionRequest($contextText) && $catalogProducts !== []) {
            return $this->buildAvailabilitySoftCorrectionReply($catalogProducts);
        }

        if (!$intentResult?->hasSpecificProduct()) {
            $facetReply = $this->buildCatalogFacetReply($contextText);

            if ($facetReply !== null) {
                return $facetReply;
            }
        }

        if ($this->looksLikeNewModelsRequest($contextText)) {
            $newModelProducts = $this->catalogFacetProducts(false, true, false, 8);

            if ($newModelProducts === []) {
                $newModelProducts = $catalogProducts;
            }

            if ($newModelProducts !== []) {
                return $this->buildNewModelsReply($newModelProducts);
            }
        }

        if ($budget !== null && $catalogProducts !== []) {
            return $this->buildBudgetAwareReply($budget, $catalogProducts);
        }

        if ($intentResult?->intent() === 'recommendation' || $this->looksLikeRecommendationRequest($contextText)) {
            if ($catalogProducts !== []) {
                return implode("\n", [
                    'ახლა აქტიურად ხელმისაწვდომი მოდელებიდან ეს ვარიანტებია:',
                    $this->formatProductBullets($catalogProducts, 4),
                    '',
                    'თუ გინდა, ბიუჯეტით ან ფუნქციებითაც დაგილაგებ.',
                ]);
            }

            return 'მომწერე ბიუჯეტი ან სასურველი ფუნქცია (GPS, SOS, ზარები, კამერა) და უფრო ზუსტად შეგირჩევ.';
        }

        if (in_array($intentResult?->intent(), ['price_query', 'stock_query'], true)) {
            if ($contextProducts !== []) {
                return implode("\n", [
                    $intentResult?->intent() === 'stock_query'
                        ? 'მარაგის მიხედვით ეს ვარიანტებია:'
                        : 'ფასის მიხედვით ეს ვარიანტებია:',
                    $this->formatProductBullets($contextProducts, 4),
                    '',
                    'თუ კონკრეტულ მოდელს მომწერ, ფასსა და მარაგს ზუსტად გეტყვი.',
                ]);
            }

            return 'თუ კონკრეტულ მოდელს მომწერ, ფასსა და მარაგს ზუსტად გეტყვი.';
        }

        if ($intentResult?->intent() === 'comparison') {
            if (count($catalogProducts) >= 2) {
                return implode("\n", [
                    'შედარებისთვის ეს მოდელები ნახე:',
                    $this->formatProductBullets($catalogProducts, 4),
                    '',
                    'თუ ორ კონკრეტულ მოდელს მომწერ, პირდაპირ შევადარებ.',
                ]);
            }

            return 'თუ ორ კონკრეტულ მოდელს მომწერ, პირდაპირ შევადარებ.';
        }

        return 'მომწერე ბიუჯეტი, სასურველი ფუნქცია ან კონკრეტული მოდელი და ზუსტად დაგეხმარები.';
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

    private function buildCatalogFacetReply(string $contextText): ?string
    {
        $twoG = $this->mentionsTwoGContext($contextText);
        $fourG = $this->mentionsFourGContext($contextText);
        $discounted = $this->mentionsDiscountContext($contextText);

        if (!$twoG && !$fourG && !$discounted) {
            return null;
        }

        $facetProducts = $this->catalogFacetProducts($twoG, $fourG, $discounted, 8);
        $generationLabel = $this->catalogFacetGenerationLabel($twoG, $fourG);

        if ($facetProducts !== []) {
            $heading = $discounted
                ? ($generationLabel !== ''
                    ? 'გთავაზობთ შემდეგ ' . $generationLabel . ' ფასდაკლებულ მოდელებს:'
                    : 'გთავაზობთ შემდეგ ფასდაკლებულ მოდელებს:')
                : ($generationLabel !== ''
                    ? 'გირჩევთ შემდეგ ' . $generationLabel . ' მოდელებს:'
                    : 'გირჩევთ შემდეგ მოდელებს:');

            $closing = $discounted
                ? 'თუ გინდათ, შემიძლია ფასდაკლებულ და არაფასდაკლებულ ვარიანტებსაც შეგადაროთ.'
                : 'თუ სხვა ტიპის მოდელს ეძებთ, მომწერეთ ბიუჯეტი ან სასურველი ფუნქცია და უფრო ზუსტად შეგირჩევთ.';

            return implode("\n", [
                $heading,
                $this->formatProductBullets($facetProducts, null),
                '',
                $closing,
            ]);
        }

        if ($discounted) {
            return $generationLabel !== ''
                ? 'ამჟამად ' . $generationLabel . ' ფასდაკლებულ მოდელებს ვერ მოვიძიე.'
                : 'ამჟამად ფასდაკლებულ მოდელებს ვერ მოვიძიე.';
        }

        if ($twoG && $fourG) {
            return 'ამჟამად 2G და 4G მოდელებს ვერ მოვიძიე.';
        }

        if ($fourG) {
            return 'ამჟამად ჩვენს კატალოგში 4G სმარტსაათების არჩევანი არ გვაქვს.';
        }

        return 'ამჟამად ჩვენს კატალოგში 2G სმარტსაათების არჩევანი არ გვაქვს.';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function catalogFacetProducts(bool $twoG, bool $fourG, bool $discounted, int $limit = 3): array
    {
        return Product::query()
            ->active()
            ->withSum('variants as total_stock', 'quantity')
            ->orderByDesc('featured')
            ->orderBy('id')
            ->limit(20)
            ->get()
            ->filter(function (Product $product) use ($twoG, $fourG, $discounted): bool {
                return $this->productMatchesCatalogFacet($product, $twoG, $fourG, $discounted);
            })
            ->map(function (Product $product): array {
                return [
                    'name' => trim((string) $product->name),
                    'price' => is_numeric($product->price) ? (float) $product->price : null,
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

    private function productMatchesCatalogFacet(Product $product, bool $twoG, bool $fourG, bool $discounted): bool
    {
        if ($discounted && !$this->productIsDiscounted($product)) {
            return false;
        }

        if ($twoG && $this->productHasGeneration($product, '2g')) {
            return true;
        }

        if ($fourG && $this->productHasGeneration($product, '4g')) {
            return true;
        }

        return !$twoG && !$fourG;
    }

    private function catalogFacetGenerationLabel(bool $twoG, bool $fourG): string
    {
        $labels = [];

        if ($twoG) {
            $labels[] = '2G';
        }

        if ($fourG) {
            $labels[] = '4G';
        }

        return implode(' და ', $labels);
    }

    private function productHasGeneration(Product $product, string $generation): bool
    {
        $haystack = $this->normalizeProductText(implode(' ', array_filter([
            (string) $product->name,
            (string) $product->name_en,
            (string) $product->name_ka,
            (string) $product->slug,
            (string) $product->brand,
            (string) $product->model,
        ])));

        $patterns = $generation === '2g'
            ? [
                '/(?:^|\s)2\s*g(?:\s|$)/u',
                '/(?:^|\s)2\s*გ(?:\s|$)/u',
                '/(?:^|\s)2გ(?:\s|$)/u',
            ]
            : [
                '/(?:^|\s)4\s*g(?:\s|$)/u',
                '/(?:^|\s)4\s*გ(?:\s|$)/u',
                '/(?:^|\s)4გ(?:\s|$)/u',
            ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $haystack) === 1) {
                return true;
            }
        }

        return false;
    }

    private function productIsDiscounted(Product $product): bool
    {
        return is_numeric($product->sale_price) && (float) $product->sale_price > 0;
    }

    private function normalizeProductText(string $value): string
    {
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($value));

        return trim((string) $normalized);
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
    private function fallbackCatalogProducts(array $preferences, string $contextText = '', int $limit = 3): array
    {
        $budget = $this->extractBudgetFromContext($contextText, $preferences);

        $products = Product::query()
            ->active()
            ->withSum('variants as total_stock', 'quantity')
            ->orderByDesc('featured')
            ->orderBy('id')
            ->limit(20)
            ->get()
            ->filter(function (Product $product): bool {
                $effectivePrice = is_numeric($product->sale_price) && (float) $product->sale_price > 0
                    ? (float) $product->sale_price
                    : (is_numeric($product->price) ? (float) $product->price : null);

                return $effectivePrice !== null && $effectivePrice >= 0.5;
            })
            ->map(function (Product $product): array {
                return [
                    'name' => trim((string) $product->name),
                    'price' => is_numeric($product->price) ? (float) $product->price : null,
                    'sale_price' => is_numeric($product->sale_price) && (float) $product->sale_price > 0
                        ? (float) $product->sale_price
                        : null,
                    'is_in_stock' => (int) ($product->total_stock ?? 0) > 0,
                    'slug' => (string) $product->slug,
                ];
            })
            ->filter(fn (array $product): bool => $product['name'] !== '')
            ->values()
            ->all();

        return $this->rankFallbackProducts($products, $budget, $contextText, $limit);
    }

    private function buildWarrantyFallbackReply(string $contextText): string
    {
        $reply = 'კი, გარანტია გვაქვს. ' . UnifiedAiPolicyService::canonicalWarrantySummary('ka') . '.';

        if ($this->containsAnyNeedle($contextText, ['დაბრუნებ', 'გაცვლ', 'return', 'exchange'])) {
            $reply .= ' დაბრუნება/გაცვლა შესაძლებელია 14 კალენდარული დღის განმავლობაში, თუ პროდუქტი არ არის გამოყენებული და ორიგინალური შეფუთვა აქვს.';
        }

        return $reply;
    }

    /**
     * @param array<int, array<string, mixed>> $products
     */
    private function buildAvailabilitySoftCorrectionReply(array $products): string
    {
        return implode("\n", [
            'კი, გვაქვს. მაგალითად:',
            $this->formatProductBullets($products, 4),
            '',
            'თუ გინდა, 2G, 4G ან ბიუჯეტის მიხედვითაც დაგილაგებ.',
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $products
     */
    private function buildBudgetAwareReply(float $budget, array $products): string
    {
        $label = rtrim(rtrim(number_format($budget, 2, '.', ''), '0'), '.');
        $withinBudget = collect($products)
            ->filter(fn (array $product): bool => ($this->effectiveProductPrice($product) ?? INF) <= $budget)
            ->values()
            ->all();

        if ($withinBudget !== []) {
            return implode("\n", [
                'კი, ' . $label . ' ₾ ფარგლებში გვაქვს რამდენიმე ვარიანტი:',
                $this->formatProductBullets($withinBudget, 4),
                '',
                'თუ გინდა, cheapest-first ან 2G/4G მიხედვითაც დაგილაგებ.',
            ]);
        }

        return implode("\n", [
            'ზუსტად ' . $label . ' ₾ ფარგლებში ვერ ვნახე, მაგრამ უახლოესი ვარიანტებია:',
            $this->formatProductBullets($products, 3),
            '',
            'თუ გინდა, ოდნავ უფრო დაბალ ან მაღალ ბიუჯეტზეც დაგილაგებ ვარიანტებს.',
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $products
     */
    private function buildNewModelsReply(array $products): string
    {
        return implode("\n", [
            'ახლა აქტიურად ხელმისაწვდომი მოდელებიდან ეს ვარიანტებია:',
            $this->formatProductBullets($products, 4),
            '',
            'თუ გინდა, 2G, 4G ან cheapest-first მიხედვითაც დაგილაგებ.',
        ]);
    }

    private function buildSliderExplanationReply(): string
    {
        return 'სლაიდერში მსგავსი მოდელებიც გამოვიტანეთ, რომ მარტივად შეადარო ზომა, ფასი და ფუნქციები.';
    }

    /**
     * @param array<int, array<string, mixed>> $products
     * @return array<int, array<string, mixed>>
     */
    private function rankFallbackProducts(array $products, ?float $budget, string $contextText, int $limit = 4): array
    {
        $twoG = $this->mentionsTwoGContext($contextText);
        $fourG = $this->mentionsFourGContext($contextText);
        $discounted = $this->mentionsDiscountContext($contextText);

        return collect($products)
            ->filter(function (array $product) use ($twoG, $fourG, $discounted): bool {
                return $this->productArrayMatchesCatalogFacet($product, $twoG, $fourG, $discounted);
            })
            ->sortBy(function (array $product) use ($budget): array {
                $effectivePrice = $this->effectiveProductPrice($product) ?? INF;
                $withinBudget = $budget !== null && $effectivePrice <= $budget;
                $distance = $budget !== null ? abs($effectivePrice - $budget) : $effectivePrice;

                return [
                    !($product['is_in_stock'] ?? false) ? 1 : 0,
                    $budget !== null ? ($withinBudget ? 0 : 1) : 0,
                    $distance,
                    $effectivePrice,
                    trim((string) ($product['name'] ?? '')),
                ];
            })
            ->take($limit)
            ->values()
            ->all();
    }

    private function extractBudgetFromContext(string $contextText, array $preferences): ?float
    {
        if (preg_match('/(\d+(?:[\.,]\d+)?)\s*(?:₾|ლარ(?:ი|ამდე|ის)?|gel|lari)/iu', $contextText, $matches) === 1) {
            return (float) str_replace(',', '.', (string) $matches[1]);
        }

        if (isset($preferences['budget_max_gel']) && is_numeric($preferences['budget_max_gel'])) {
            return (float) $preferences['budget_max_gel'];
        }

        return null;
    }

    private function effectiveProductPrice(array $product): ?float
    {
        if (is_numeric($product['sale_price'] ?? null) && (float) $product['sale_price'] > 0) {
            return (float) $product['sale_price'];
        }

        return is_numeric($product['price'] ?? null) ? (float) $product['price'] : null;
    }

    private function looksLikeWarrantyRequest(string $contextText): bool
    {
        return $this->containsAnyNeedle($contextText, ['გარანტ', 'warranty', 'return', 'exchange', 'დაბრუნებ', 'გაცვლ']);
    }

    private function looksLikeNewModelsRequest(string $contextText): bool
    {
        return $this->containsAnyNeedle($contextText, ['ახალი მოდელ', 'new model', 'current model', 'ახლა რა გაქვთ']);
    }

    private function looksLikeSliderExplanationRequest(string $contextText): bool
    {
        return $this->containsAnyNeedle($contextText, ['სლაიდერ', 'slider']);
    }

    private function looksLikeAvailabilityAssumptionRequest(string $contextText): bool
    {
        return $this->containsAnyNeedle($contextText, ['პროდუქცია არ გაქვთ', 'არაფერი არ გაქვთ', 'საერთოდ გაქვთ რამე']);
    }

    private function productArrayMatchesCatalogFacet(array $product, bool $twoG, bool $fourG, bool $discounted): bool
    {
        $haystack = $this->normalizeProductText(implode(' ', array_filter([
            (string) ($product['name'] ?? ''),
            (string) ($product['slug'] ?? ''),
        ])));

        if ($discounted && !(is_numeric($product['sale_price'] ?? null) && (float) $product['sale_price'] > 0)) {
            return false;
        }

        if ($twoG) {
            return $this->mentionsTwoGContext($haystack);
        }

        if ($fourG) {
            return $this->mentionsFourGContext($haystack);
        }

        return true;
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
    private function formatProductBullets(array $products, ?int $limit = 3): string
    {
        $items = $limit === null
            ? collect($products)
            : collect($products)->take($limit);

        return $items
            ->map(function (array $product): string {
                $parts = [trim((string) ($product['name'] ?? ''))];
                $price = $this->formatPrice($product['sale_price'] ?? null, $product['price'] ?? null);

                if ($price !== null) {
                    $parts[] = $price;
                }

                return '- ' . implode(' — ', array_filter($parts, fn (string $part): bool => $part !== ''));
            })
            ->implode("\n");
    }

    private function formatPrice(mixed $salePrice, mixed $regularPrice = null): ?string
    {
        if (is_numeric($salePrice) && (float) $salePrice > 0) {
            $sale = rtrim(rtrim(number_format((float) $salePrice, 2, '.', ''), '0'), '.');

            if (is_numeric($regularPrice) && (float) $regularPrice > (float) $salePrice) {
                $regular = rtrim(rtrim(number_format((float) $regularPrice, 2, '.', ''), '0'), '.');

                return '~~' . $regular . ' ₾~~ → **' . $sale . ' ₾**';
            }

            return $sale . ' ₾';
        }

        if (!is_numeric($regularPrice)) {
            return null;
        }

        $normalized = rtrim(rtrim(number_format((float) $regularPrice, 2, '.', ''), '0'), '.');

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
            preg_match('/(?:^|\s)2\s*g(?:\s|$)/u', $normalized) === 1
            || preg_match('/(?:^|\s)2\s*გ(?:\s|$)/u', $normalized) === 1
            || preg_match('/(?:^|\s)2გ(?:\s|$)/u', $normalized) === 1
        );
    }

    private function mentionsFourGContext(string $text): bool
    {
        $normalized = preg_replace('/\s+/u', ' ', mb_strtolower(trim($text))) ?? mb_strtolower(trim($text));

        return $normalized !== '' && (
            preg_match('/(?:^|\s)4\s*g(?:\s|$)/u', $normalized) === 1
            || preg_match('/(?:^|\s)4\s*გ(?:\s|$)/u', $normalized) === 1
            || preg_match('/(?:^|\s)4გ(?:\s|$)/u', $normalized) === 1
        );
    }

    private function mentionsDiscountContext(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));

        if ($normalized === '') {
            return false;
        }

        return $this->containsAnyNeedle($normalized, [
            'ფასდაკლ',
            'discount',
            'sale',
            'offer',
            'reduc',
        ]);
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
