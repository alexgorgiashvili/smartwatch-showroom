<?php

namespace App\Services\Chatbot;

use App\Models\Product;
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

        $learningLessons = $this->adaptiveLearning->buildLessonsText();
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
            'საკონტაქტო ინფორმაცია (ადმინისტრატორის ლაივ პარამეტრები):',
            '- ტელეფონი: ' . ($contactSettings['phone_display'] ?? ''),
            '- WhatsApp: ' . ($contactSettings['whatsapp_url'] ?? ''),
            '- ელფოსტა: ' . ($contactSettings['email'] ?? ''),
            '- მისამართი: ' . ($contactSettings['location'] ?? ''),
            '- სამუშაო საათები: ' . ($contactSettings['hours'] ?? ''),
            'Intent analysis:',
            '- standalone_query: ' . (trim($intentResult->standaloneQuery()) !== '' ? $intentResult->standaloneQuery() : '-'),
            '- intent: ' . $intentResult->intent(),
            '- confidence: ' . $intentResult->confidence(),
        ];

        if ($effectiveRagContextText !== '') {
            $sections[] = 'ცოდნის ბაზა:';
            $sections[] = $effectiveRagContextText;
        }

        if ($searchContext->productNotFoundMessage()) {
            $sections[] = 'მნიშვნელოვანი კონტექსტი:';
            $sections[] = $searchContext->productNotFoundMessage();
        }

        $sections[] = 'მომხმარებლის შეტყობინება:';
        $sections[] = '- ' . $normalizedMessage;

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

        $sections[] = 'პროდუქტები (ლაივ მარაგი ბაზიდან):';
        $sections[] = $productLines !== '' ? $productLines : 'პროდუქტები ვერ მოიძებნა.';

        return implode("\n", $sections);
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

        return implode("\n", [
            'Re-answer the same user request in Georgian.',
            'Your previous reply violated response integrity checks. Fix the answer and keep it concise.',
            'Do not invent prices, stock claims, or URLs that are not supported by the provided context.',
            'Validation issues to fix:',
            $violationLines !== '' ? $violationLines : '- unknown',
        ]);
    }
}
