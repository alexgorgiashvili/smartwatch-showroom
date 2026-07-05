<?php

namespace App\Services\Chatbot;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

class PromptBuilderService
{
    public function __construct(
        private UnifiedAiPolicyService $policy,
        private AdaptiveLearningService $adaptiveLearning
    ) {
    }

    /**
     * Build system prompt with preferences and intent context
     */
    public function buildSystemPrompt(array $preferences, IntentResult $intentResult): string
    {
        $systemPrompt = $this->policy->websiteSystemPrompt();

        $learningLessons = $this->adaptiveLearning->buildLessonsText($intentResult->intent());
        if ($learningLessons !== '') {
            $systemPrompt .= "\n\n" . $learningLessons;
        }

        if ($preferences !== []) {
            $preferenceParts = [];

            if (isset($preferences['budget_max_gel'])) {
                $preferenceParts[] = 'ბიუჯეტი: ' . $preferences['budget_max_gel'] . ' ₾-მდე';
            }

            if (!empty($preferences['color'])) {
                $preferenceParts[] = 'სასურველი ფერი: ' . $preferences['color'];
            }

            if (!empty($preferences['size'])) {
                $preferenceParts[] = 'სასურველი ზომა: ' . $preferences['size'];
            }

            if (!empty($preferences['features']) && is_array($preferences['features'])) {
                $preferenceParts[] = 'საინტერესო ფუნქციები: ' . implode(', ', $preferences['features']);
            }

            if (!empty($preferences['excluded_features']) && is_array($preferences['excluded_features'])) {
                $preferenceParts[] = 'არასასურველი ფუნქციები: ' . implode(', ', $preferences['excluded_features']);
                $preferenceParts[] = 'არ თქვა, რომ კონკრეტულ მოდელს ეს ფუნქცია არ აქვს, თუ ეს ლაივ კონტექსტში აშკარად არ ჩანს';
                $preferenceParts[] = 'არ გამოიყენო ფორმულირებები "არ აქვს", "არ მოიცავს" ან "გარეშეა", თუ კონტექსტი ამას პირდაპირ არ ამტკიცებს';
            }

            if (isset($preferences['budget_max_gel'])) {
                $preferenceParts[] = 'თუ ბიუჯეტში მოთავსებული მოდელი არსებობს, პირველად ის ახსენე; ბიუჯეტს ზემოთ მყოფი ვარიანტი მხოლოდ მეორად ალტერნატივად შესთავაზე';
            }

            if ($preferenceParts !== []) {
                $systemPrompt .= "\n\nUSER PREFERENCES (მომხმარებლის პრეფერენციები):\n" . implode("\n", array_map(fn ($part) => '• ' . $part, $preferenceParts));
            }
        }

        $summaryLines = [
            'standalone_query: ' . (trim($intentResult->standaloneQuery()) !== '' ? $intentResult->standaloneQuery() : '-'),
            'intent: ' . $intentResult->intent(),
            'brand: ' . ($intentResult->brand() ?? '-'),
            'model: ' . ($intentResult->model() ?? '-'),
            'confidence: ' . $intentResult->confidence(),
        ];

        return $systemPrompt . "\n\nINTENT SUMMARY:\n" . implode("\n", array_map(fn ($line) => '- ' . $line, $summaryLines));
    }

    /**
     * Build user context with search results, products, and contact info
     *
     * @param Collection<int, Product> $products
     */
    public function buildUserContext(
        string $normalizedMessage,
        IntentResult $intentResult,
        SearchContext $searchContext,
        array $contactSettings,
        Collection $products,
        string $effectiveRagContextText
    ): string {
        $sections = [
            'საიტის ბმულები:',
            '- მთავარი: ' . route('home'),
            '- კატალოგი: ' . route('products.index'),
            '- კონტაქტი: ' . route('contact'),
            'საკონტაქტო ინფორმაცია (ადმინისტრატორის live პარამეტრები):',
            '- ტელეფონი: ' . ($contactSettings['phone_display'] ?? ''),
            '- WhatsApp: ' . ($contactSettings['whatsapp_url'] ?? ''),
            '- Messenger: ' . ($contactSettings['messenger_url'] ?? ''),
            '- ელფოსტა: ' . ($contactSettings['email'] ?? ''),
            '- მისამართი: ' . ($contactSettings['location'] ?? ''),
            '- სამუშაო საათები: ' . ($contactSettings['hours'] ?? ''),
            '- როცა მომხმარებელი საკონტაქტო გზას ითხოვს, უპირატესობა მიანიჭე WhatsApp ან Messenger ბმულს და არა ტელეფონს ან ელფოსტას.',
            'Intent analysis:',
            '- standalone_query: ' . (trim($intentResult->standaloneQuery()) !== '' ? $intentResult->standaloneQuery() : '-'),
            '- intent: ' . $intentResult->intent(),
            '- confidence: ' . $intentResult->confidence(),
        ];

        if ($effectiveRagContextText !== '') {
            $sections[] = 'ცოდნის ბაზა:';
            $sections[] = $effectiveRagContextText;
        }

        $warrantyTopic = preg_match('/(გარანტ|warranty|guarantee)/iu', $normalizedMessage) === 1;
        if (!$warrantyTopic) {
            foreach ($intentResult->searchKeywords() as $keyword) {
                if (is_string($keyword) && preg_match('/(გარანტ|warranty|guarantee)/iu', $keyword) === 1) {
                    $warrantyTopic = true;
                    break;
                }
            }
        }

        if ($warrantyTopic) {
            $sections[] = 'გარანტიის პოლიტიკა:';

            foreach (UnifiedAiPolicyService::canonicalWarrantyPolicyLines() as $line) {
                $sections[] = $line;
            }
        }

        $newModelsTopic = preg_match('/(ახალ\p{L}*\s+მოდელ\p{L}*|new\s+models?)/iu', $normalizedMessage) === 1;
        $sliderTopic = preg_match('/(სლაიდერ\p{L}*|slider)/iu', $normalizedMessage) === 1;
        $availabilityAssumptionTopic = preg_match('/(პროდუქცი\p{L}*\s+არ\s+გაქვთ|არაფერ\p{L}*\s+არ\s+არის|საერთოდ\s+გაქვთ\s+რამე)/iu', $normalizedMessage) === 1;
        $budgetTopic = preg_match('/(\d+\s*(?:₾|ლარ(?:ი|ამდე|ის)?))/iu', $normalizedMessage) === 1
            || preg_match('/(ბიუჯეტ|იაფ\p{L}*|cheap|budget|cheapest)/iu', $normalizedMessage) === 1;

        if ($intentResult->hasCatalogFacet()) {
            $sections[] = 'კატალოგის ჯგუფის მითითება:';
            $sections[] = '- ქვემოთ მოცემული პროდუქტები გამოიყენე როგორც ამ შეკითხვის სრული შესაბამისი სია.';
            $sections[] = '- თუ მომხმარებელი 2G, 4G ან ფასდაკლებულ მოდელებს ეკითხება, აღნიშნე სიაში არსებული ყველა შესაბამისი ვარიანტი.';
        }

        if ($newModelsTopic) {
            $sections[] = 'ახალი მოდელების ინტერპრეტაცია:';
            $sections[] = '- თუ ზუსტი release-date ინფორმაცია არ ჩანს, "ახალი მოდელები" განმარტე როგორც ამჟამად აქტიური და ხელმისაწვდომი მოდელები.';
        }

        if ($sliderTopic) {
            $sections[] = 'სლაიდერის ახსნის ტონი:';
            $sections[] = '- მოკლედ და ბუნებრივად აუხსენი, რომ სლაიდერში მსგავსი ალტერნატივებიც გამოვიტანეთ, რათა შედარება გაუმარტივდეს.';
            $sections[] = '- გამოიყენე ფორმულირება "გამოვიტანეთ" და არა "გამოიტანეთ".';
        }

        if ($searchContext->productNotFoundMessage()) {
            $sections[] = 'მნიშვნელოვანი კონტექსტი:';
            $sections[] = $searchContext->productNotFoundMessage();
        }

        $sections[] = 'მომხმარებლის შეტყობინება:';
        $sections[] = '- ' . $normalizedMessage;

        if ($searchContext->requestedProduct() !== null) {
            $requestedProduct = $searchContext->requestedProduct();
            $sections[] = 'Requested product (exact match from live database):';
            $sections[] = $this->formatRequestedProduct($requestedProduct);

            $requestedVariant = $this->resolveRequestedVariant($requestedProduct, $normalizedMessage);
            if ($requestedVariant !== null) {
                $sections[] = 'Requested variant: ' . ($requestedVariant->name ?: $requestedVariant->color_name ?: 'Variant');
                $sections[] = 'Variant stock: ' . $this->formatVariantStock($requestedVariant);
            }
        }

        $productLines = $products
            ->map(function (Product $product): string {
                $price = $product->sale_price
                    ? $product->sale_price . ' ₾ (ფასდაკლება, ძველი ფასი ' . $product->price . ' ₾)'
                    : $product->price . ' ₾';

                $stockTotal = max(0, (int) ($product->total_stock ?? 0));
                $stockStatus = $stockTotal > 0 ? 'მარაგშია' : 'ამოწურულია';

                return '- ' . $product->name
                    . ' | ბმული იდენტიფიკატორი: ' . $product->slug
                    . ' | ფასი: ' . $price
                    . ' | მარაგი: ' . $stockStatus;
            })
            ->implode("\n");

        $sections[] = 'პროდუქტები (live მარაგი ბაზიდან):';
        $sections[] = $productLines !== '' ? $productLines : 'პროდუქტები ვერ მოიძებნა.';

        if ($productLines !== '') {
            $sections[] = 'live კატალოგის პასუხის წესი:';
            $sections[] = '- რადგან შესაბამისი live პროდუქტები უკვე ნაპოვნია, არ თქვა "არ გვაქვს", "არ არის", "ვერ მოვიძიე" ან "დაგვიკავშირდით ფასისთვის".';
            $sections[] = '- ჯერ დაასახელე 2-4 კონკრეტული მოდელი ამ სიიდან და მხოლოდ შემდეგ დაამატე მოკლე განმარტება ან follow-up.';

            if ($budgetTopic) {
                $sections[] = '- თუ შეკითხვა ბიუჯეტს ეხება, ჯერ ბიუჯეტში მოქცეული ვარიანტები დაასახელე; თუ ზუსტად არ ეტევა, ახსენე უახლოესი ალტერნატივა.';
            }

            if ($availabilityAssumptionTopic) {
                $sections[] = '- თუ მომხმარებელი ფიქრობს, რომ პროდუქცია არ გვაქვს, რბილად შეასწორე: "კი, გვაქვს" და დაასახელე რამდენიმე მაგალითი.';
            }
        }

        return implode("\n", $sections);
    }

    private function formatRequestedProduct(Product $product): string
    {
        $price = $product->sale_price
            ? $product->sale_price . ' ₾ (discounted, regular ' . $product->price . ' ₾)'
            : $product->price . ' ₾';

        $stockQuantity = max(0, (int) $product->stock_quantity);
        $stockStatus = $stockQuantity > 0 ? 'მარაგშია' : 'ამოწურულია';

        return '- Product: ' . $product->name
            . ' | slug: ' . $product->slug
            . ' | price: ' . $price
            . ' | stock: ' . $stockStatus . ' (' . $stockQuantity . ' ცალი)';
    }

    private function formatVariantStock(ProductVariant $variant): string
    {
        $quantity = max(0, (int) $variant->available_quantity);
        $status = $quantity > 0 ? 'მარაგშია' : 'ამოწურულია';

        return $status . ' (' . $quantity . ' ცალი)';
    }

    private function resolveRequestedVariant(Product $product, string $message): ?ProductVariant
    {
        $variants = $product->relationLoaded('variants')
            ? $product->variants
            : $product->variants()->get();

        if ($variants->isEmpty()) {
            return null;
        }

        $normalizedMessage = mb_strtolower($this->normalizePromptText($message));

        foreach ($variants as $variant) {
            if ($this->variantMatchesMessage($variant, $normalizedMessage)) {
                return $variant;
            }
        }

        return $variants->sortByDesc(fn (ProductVariant $variant): int => (int) $variant->available_quantity)->first();
    }

    private function variantMatchesMessage(ProductVariant $variant, string $message): bool
    {
        $variantText = mb_strtolower($this->normalizePromptText((string) $variant->name . ' ' . (string) $variant->color_name));

        if ($variantText !== '' && str_contains($message, $variantText)) {
            return true;
        }

        foreach ($this->variantColorAliases($variantText) as $alias) {
            if ($alias !== '' && str_contains($message, $alias)) {
                return true;
            }
        }

        $variantTokens = collect(preg_split('/[^\p{L}\p{N}]+/u', $variantText) ?: [])
            ->filter(fn ($token): bool => is_string($token) && trim($token) !== '')
            ->values()
            ->all();

        foreach ($variantTokens as $token) {
            if (mb_strlen($token) >= 3 && str_contains($message, $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function variantColorAliases(string $variantText): array
    {
        $aliases = [];

        $colorMap = [
            'blue' => ['blue', 'ლურჯ', 'ცისფ'],
            'black' => ['black', 'შავ'],
            'white' => ['white', 'თეთრ'],
            'red' => ['red', 'წითელ'],
            'green' => ['green', 'მწვანე'],
            'yellow' => ['yellow', 'ყვითელ'],
            'pink' => ['pink', 'ვარდისფერ'],
            'gray' => ['gray', 'grey', 'ნაცრისფერ'],
        ];

        foreach ($colorMap as $canonical => $synonyms) {
            if (str_contains($variantText, $canonical)) {
                $aliases = array_merge($aliases, $synonyms);
            }
        }

        return array_values(array_unique(array_filter($aliases)));
    }

    private function normalizePromptText(string $text): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower(trim($text))) ?? $text;
    }

    /**
     * Build regeneration instruction for validation failures
     */
    public function buildRegenerationInstruction(array $violations): string
    {
        $violationLines = collect($violations)
            ->map(function (array $violation): string {
                $type = (string) ($violation['type'] ?? 'unknown');
                $details = collect($violation)
                    ->reject(fn ($value, $key): bool => $key === 'type' || $value === null || $value === '')
                    ->map(fn ($value, $key): string => $key . '=' . (is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)))
                    ->implode(', ');

                return $details !== '' ? '- ' . $type . ' (' . $details . ')' : '- ' . $type;
            })
            ->implode("\n");

        $instructions = [
            'Re-answer the same user request in Georgian.',
            'Your previous reply violated response integrity checks. Fix the answer and keep it concise.',
            'Do not invent prices, stock claims, or URLs that are not supported by the provided context.',
            'Validation issues to fix:',
            $violationLines !== '' ? $violationLines : '- unknown',
        ];

        if (collect($violations)->contains(fn (array $violation): bool => ($violation['type'] ?? null) === 'offer_tone_mismatch')) {
            $instructions[] = 'როცა ვარიანტებს სთავაზობ, გამოიყენე „შემოგთავაზოთ" და არა „გთავაზოთ".';
        }

        return implode("\n", $instructions);
    }
}

