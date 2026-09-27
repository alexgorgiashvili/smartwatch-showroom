<?php

namespace App\Services\Chatbot;

use App\Models\Conversation;
use App\Models\Customer;
use App\Services\Chatbot\Agents\SupervisorAgent;

class ChatPipelineService
{
    public function process(
        string $safeIncomingMessage,
        Conversation $conversation,
        Customer $customer,
        ?string $traceId,
        BifurcatedMemoryService $memory,
        IntentAnalyzerService $intentAnalyzer,
        SupervisorAgent $supervisor,
        UnifiedAiPolicyService $policy,
        ChatbotFallbackStrategyService $fallbackStrategy
    ): PipelineResult {
        $trace = array_filter([
            'trace_id' => $traceId,
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'channel' => 'widget',
        ], fn ($value) => $value !== null);

        if ($policy->isGreetingOnly($safeIncomingMessage)) {
            $greetingReply = $fallbackStrategy->resolveGreetingOutcome()->reply();

            return new PipelineResult(
                $greetingReply,
                $conversation->id,
                '',
                IntentResult::fallback($safeIncomingMessage),
                ['products' => []],
                true,
                null,
                true,
                [],
                true,
                0,
                ChatbotOutcomeReason::GREETING_ONLY,
                false,
                false
            );
        }

        $directFallbackIntent = $this->directFallbackIntentForMessage($safeIncomingMessage);
        if ($directFallbackIntent instanceof IntentResult) {
            $memory->appendMessage($conversation->id, 'user', $safeIncomingMessage);
            $validationContext = $this->directFallbackValidationContext();

            $directReply = $fallbackStrategy->resolveProviderFailureOutcome(
                $directFallbackIntent,
                $validationContext,
                [],
                []
            )->reply();

            $memory->appendMessage($conversation->id, 'assistant', $directReply);

            return new PipelineResult(
                $directReply,
                $conversation->id,
                '',
                $directFallbackIntent,
                $validationContext,
                true,
                null,
                true,
                [],
                true,
                0,
                null,
                false,
                true
            );
        }

        $sessionContext = $memory->getSessionContext($conversation->id);
        $history = $sessionContext['recent'] ?? [];
        if ($history === [] && empty($sessionContext['summary'])) {
            $clarification = $this->firstTurnClarification($safeIncomingMessage);
            if ($clarification !== null) {
                $memory->appendMessage($conversation->id, 'user', $safeIncomingMessage);
                $memory->appendMessage($conversation->id, 'assistant', $clarification);

                return new PipelineResult(
                    $clarification,
                    $conversation->id,
                    '',
                    IntentResult::fromArray([
                        'standalone_query' => $safeIncomingMessage,
                        'intent' => 'general',
                        'entities' => [],
                        'needs_product_data' => false,
                    ], 0),
                    ['products' => []],
                    true,
                    null,
                    true,
                    [],
                    true,
                    0,
                    null,
                    false,
                    true
                );
            }
        }
        $preferences = $memory->getUserPreferences($customer->id);
        $scopedPreferences = $memory->scopePreferencesForMessage($preferences, $safeIncomingMessage);

        $intentResult = $intentAnalyzer->analyze(
            $safeIncomingMessage,
            $history,
            $scopedPreferences,
            $trace
        )->normalizedForWidget();

        $memory->appendMessage($conversation->id, 'user', $safeIncomingMessage);

        $supervisorResult = $supervisor->orchestrate(
            $safeIncomingMessage,
            $conversation->id,
            $customer->id,
            $intentResult,
            $scopedPreferences,
            $trace
        );

        $extractedPreferences = is_array($supervisorResult['extracted_preferences'] ?? null)
            ? $supervisorResult['extracted_preferences']
            : [];

        if ($extractedPreferences !== []) {
            $memory->updateUserPreferences($customer->id, $extractedPreferences);
        }

        $validationPassed = (bool) ($supervisorResult['validation_passed'] ?? false);
        $agentResponse = (string) ($supervisorResult['response'] ?? '');
        $agentReason = $supervisorResult['reason'] ?? null;

        if ($agentReason === null && !$validationPassed) {
            $agentReason = ChatbotOutcomeReason::VALIDATOR_RETRY_FAILED;
            $agentResponse = $fallbackStrategy->resolveStaticReason($agentReason)->reply();
        }

        if ($agentResponse === '' && $agentReason !== null) {
            $agentResponse = $fallbackStrategy->resolveStaticReason((string) $agentReason)->reply();
        }

        // Normalize the two Armenian punctuation marks Luna occasionally emits
        // inside otherwise Georgian text, then reject any remaining Armenian script.
        $agentResponse = str_replace(["\u{055D}", "\u{0589}"], [':', '.'], $agentResponse);
        $hasForeignScript = preg_match('/[\x{0530}-\x{058F}]/u', $agentResponse) === 1;
        $localePassed = $agentResponse === '' || (
            !$hasForeignScript && (app()->getLocale() === 'en'
                ? $policy->passesLocaleQa($agentResponse, 'en')
                : $policy->passesStrictGeorgianQa($agentResponse))
        );
        $validationContext = $supervisorResult['validation_context'] ?? ['products' => []];
        if (!$localePassed) {
            $isMediaRequest = preg_match('/ფოტო|ვიდეო/iu', $safeIncomingMessage) === 1
                && preg_match('/გამომიგზავ|გამოგზავ|მომაწოდ|აჩვენ/iu', $safeIncomingMessage) === 1;
            $waterFallback = app()->getLocale() !== 'en'
                && preg_match('/წყალგამძლ|წყალგაუმტ|waterproof/iu', $safeIncomingMessage) === 1
                    ? $this->verifiedWaterResistanceFallback($validationContext)
                    : null;
            $cheapFallback = app()->getLocale() !== 'en'
                && preg_match('/ყველაზე\s+იაფ|იაფიანი|ბიუჯეტურ/iu', $safeIncomingMessage) === 1
                    ? $this->verifiedAffordableProductsFallback($validationContext)
                    : null;
            if (app()->getLocale() !== 'en' && $isMediaRequest) {
                $agentResponse = 'ამ ტექსტურ ჩატში რეალურ ფოტოს ან ვიდეოს ვერ გამოგიგზავნით. მომწერეთ სასურველი მოდელის სახელი, რომ შესაბამისი პროდუქტის გვერდის ბმული მოგაწოდოთ.';
            } elseif ($waterFallback !== null) {
                $agentResponse = $waterFallback;
            } elseif ($cheapFallback !== null) {
                $agentResponse = $cheapFallback;
            } elseif (app()->getLocale() !== 'en' && $intentResult->hasCatalogFacet() && !empty($validationContext['products'])) {
                $agentResponse = $fallbackStrategy->resolveProviderFailureOutcome(
                    $intentResult,
                    $validationContext,
                    $history,
                    $scopedPreferences
                )->reply();
            } else {
                $agentResponse = app()->getLocale() !== 'en'
                    && $intentResult->intent() === 'price_query'
                    && empty($validationContext['products'])
                        ? 'ამჟამად ფასს ვერ ვადასტურებ. მომწერეთ კონკრეტული მოდელი ან პროდუქტის ბმული, რომ ინფორმაცია გადავამოწმო.'
                        : $policy->localeFallback();
            }
            $agentReason = ChatbotOutcomeReason::STRICT_GEORGIAN;
        }

        $violations = $supervisorResult['violations'] ?? [];
        if (($validationContext['require_live_catalog_evidence'] ?? false) && $agentReason === null) {
            $validator = app(ResponseValidatorService::class);
            $priceCheck = $validator->validatePriceIntegrity($agentResponse, $validationContext);
            $stockCheck = $validator->validateStockClaims($agentResponse, $validationContext);
            if (!$priceCheck->isValid() || !$stockCheck->isValid()) {
                $violations = array_merge($violations, $priceCheck->violations(), $stockCheck->violations());
                $agentResponse = $validator->integrityFallback();
                $agentReason = ChatbotOutcomeReason::VALIDATOR_FAILED;
                $validationPassed = false;
            }
        }

        if (in_array($agentReason, [ChatbotOutcomeReason::VALIDATOR_FAILED, ChatbotOutcomeReason::VALIDATOR_RETRY_FAILED], true)
            && preg_match('/რეკლამ|აქცი/iu', $safeIncomingMessage) === 1
            && preg_match('/\d+\s*(?:₾|ლარ)|ფას/iu', $safeIncomingMessage) === 1) {
            $agentResponse = 'რეკლამაში ნაჩვენები შეთავაზების პირობებს ამ შეტყობინებით ვერ ვადასტურებ. მომწერეთ რეკლამის ბმული და მოდელის სახელი, რომ მოქმედი ფასი კატალოგში გადავამოწმო.';
        }

        if ($supervisorResult['success'] ?? false) {
            $memory->appendMessage($conversation->id, 'assistant', $agentResponse);
        }

        return new PipelineResult(
            $agentResponse,
            $conversation->id,
            '',
            $intentResult,
            $validationContext,
            true,
            null,
            $validationPassed,
            $violations,
            $localePassed,
            0,
            $agentReason,
            (bool) (($supervisorResult['reflection_attempts'] ?? 0) > 0),
            (bool) ($supervisorResult['success'] ?? false)
        );
    }

    private function directFallbackIntentForMessage(string $message): ?IntentResult
    {
        $normalized = mb_strtolower(trim($message));

        if ($normalized === '') {
            return null;
        }

        if ($this->containsAny($normalized, ['საკონტაქტო', 'კონტაქტ', 'whatsapp', 'messenger', 'ვაცაპ', 'მესენჯერ'])
            && !$this->containsAny($normalized, ['მიწოდ', 'მიტან', 'ფას', 'ღირს', 'მარაგ', 'გარანტ', 'გაცვლ', 'გადახდ'])) {
            return IntentResult::fromArray([
                'standalone_query' => $message,
                'intent' => 'general',
                'entities' => [],
                'needs_product_data' => false,
                'search_keywords' => ['contact', 'whatsapp', 'messenger'],
                'is_out_of_domain' => false,
                'confidence' => 1.0,
            ], 0);
        }

        if ($this->containsAny($normalized, ['რა მოდელები გაქვთ', 'რა საათები გაქვთ', 'რომელი მოდელები გაქვთ'])) {
            return IntentResult::fromArray([
                'standalone_query' => $message,
                'intent' => 'recommendation',
                'entities' => [],
                'needs_product_data' => true,
                'search_keywords' => ['მოდელები', 'catalog'],
                'is_out_of_domain' => false,
                'confidence' => 1.0,
            ], 0);
        }

        if ($this->containsAny($normalized, ['რა ფასები გაქვთ', 'ფასები გაქვთ', 'ფასები მაჩვენე'])) {
            return IntentResult::fromArray([
                'standalone_query' => $message,
                'intent' => 'price_query',
                'entities' => [],
                'needs_product_data' => true,
                'search_keywords' => ['ფასი', 'catalog'],
                'is_out_of_domain' => false,
                'confidence' => 1.0,
            ], 0);
        }

        return null;
    }

    private function firstTurnClarification(string $message): ?string
    {
        $question = mb_strtolower(trim($message));
        if (preg_match('/^რომელს\s+მირჩევ(?:დი)?\s*[?!.]*$/iu', $question) === 1) {
            return 'ვისთვის ეძებთ საათს, რა ბიუჯეტი გაქვთ და რომელი ფუნქციაა თქვენთვის მთავარი — ზარები, მდებარეობა თუ ვიდეოზარი? ამით შესაბამის მოდელებს შეგირჩევთ.';
        }

        if (preg_match('/^\d+\s*(?:₾|ლარ)(?:იან[ი]?)?(?:შიც|ში)\s*[?!.]*$/iu', $question) === 1) {
            return 'რომელ ფუნქციას ან მოდელს გულისხმობთ ამ ფასის საათში? მომწერეთ მოდელის სახელი ან პროდუქტის ბმული, რომ ზუსტად შეგიმოწმოთ.';
        }

        if (preg_match('/^\d+\s*(?:₾|ლარიან[ი]?)\s+მხოლოდ\s+[\p{L}]+\s*[?!.]*$/iu', $question) === 1) {
            return 'რომელი მოდელის ფერი გაინტერესებთ? ერთსა და იმავე ფასად რამდენიმე საათი გვაქვს; მომწერეთ მოდელის სახელი ან პროდუქტის ბმული, რომ ხელმისაწვდომი ფერები ზუსტად შეგიმოწმოთ.';
        }

        if (preg_match('/^(?:და\s+)?(?:რ?ამე\s+სხვა\s+)?სიმ\s*ბარათ[ი]?\s*(?:უნდა|იდება|რომელიმე)/iu', $question) === 1
            || preg_match('/^ყველა\s+ქსელის\s+სიმ\s*ბარათ/iu', $question) === 1) {
            return 'რომელი საათის მოდელზე კითხულობთ? SIM-ის ტიპი და ოპერატორთან თავსებადობა კონკრეტული მოდელის მიხედვით უნდა გადავამოწმო; მომწერეთ მოდელის სახელი ან ბმული.';
        }

        if (preg_match('/^ოპერატორი\s+ხომა?რ\s+გჭირდებათ/iu', $question) === 1) {
            return 'მობილურ ოპერატორს გულისხმობთ SIM ბარათისთვის თუ მაღაზიის კონსულტანტთან დაკავშირებას?';
        }

        if (preg_match('/ლოკაციის\s+ჩართვა\s+საიდან/iu', $question) === 1) {
            return 'რომელი მოდელის ლოკაციის ჩართვა გსურთ? მომწერეთ საათის მოდელი და, თუ იცით, აპლიკაციის სახელი — ზუსტი ნაბიჯები ამაზეა დამოკიდებული.';
        }

        return null;
    }

    /**
     * @param array<int, string> $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (trim($needle) !== '' && str_contains($haystack, mb_strtolower(trim($needle)))) {
                return true;
            }
        }

        return false;
    }

    private function directFallbackValidationContext(): array
    {
        $contactSettings = \App\Models\ContactSetting::allKeyed();
        $allowedUrls = array_values(array_unique(array_filter([
            rtrim(route('home'), '/'),
            rtrim(route('products.index'), '/'),
            rtrim(route('contact'), '/'),
            !empty($contactSettings['whatsapp_url']) ? rtrim((string) $contactSettings['whatsapp_url'], '/') : null,
            !empty($contactSettings['messenger_url']) ? rtrim((string) $contactSettings['messenger_url'], '/') : null,
        ])));

        return [
            'products' => [],
            'allowed_urls' => $allowedUrls,
        ];
    }

    private function verifiedWaterResistanceFallback(array $validationContext): ?string
    {
        $products = $validationContext['products'] ?? [];
        if (!is_array($products)) {
            return null;
        }

        $lines = collect($products)
            ->filter(fn ($product): bool => is_array($product)
                && trim((string) ($product['name'] ?? '')) !== ''
                && trim((string) ($product['water_resistant'] ?? '')) !== '')
            ->take(4)
            ->map(fn (array $product): string => '**' . trim((string) $product['name']) . '** — '
                . trim((string) $product['water_resistant']))
            ->all();

        return $lines === []
            ? null
            : 'კატალოგის აღწერაში წყალგამძლეობად მითითებულია: ' . implode('; ', $lines)
                . '. წყალში გამოყენების ზუსტი პირობები პროდუქტის აღწერაში გადაამოწმეთ.';
    }

    private function verifiedAffordableProductsFallback(array $validationContext): ?string
    {
        $products = $validationContext['products'] ?? [];
        if (!is_array($products)) {
            return null;
        }

        $lines = collect($products)
            ->filter(fn ($product): bool => is_array($product)
                && !empty($product['is_in_stock'])
                && trim((string) ($product['name'] ?? '')) !== ''
                && is_numeric(($product['sale_price'] ?? null) ?: ($product['price'] ?? null)))
            ->sortBy(fn (array $product): float => (float) (($product['sale_price'] ?? null) ?: $product['price']))
            ->take(2)
            ->map(fn (array $product): string => '**' . trim((string) $product['name']) . '** — '
                . (string) (float) (($product['sale_price'] ?? null) ?: $product['price']) . ' ₾')
            ->all();

        return $lines === []
            ? null
            : 'შემოწმებული კატალოგის მარაგში არსებული იაფი ვარიანტებია: ' . implode('; ', $lines)
                . '. ფასი შეკვეთამდე პროდუქტის გვერდზე გადაამოწმეთ.';
    }
}
