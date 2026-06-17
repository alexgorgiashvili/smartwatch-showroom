<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiPostGeneratorService
{
    private string $apiKey;
    private string $generationModel;
    private string $reviewModel;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.openai.key');
        // Use a fast model for initial generation
        $this->generationModel = config('services.openai.model', 'gpt-4o-mini');
        // Use a stronger model for linguistic review
        $this->reviewModel = config('services.openai.review_model', 'gpt-4o');
        $this->baseUrl = config('services.openai.base_url', 'https://api.openai.com/v1');
    }

    /**
     * Generate a single Facebook marketing post for a product.
     */
    public function generateProductPost(Product $product, string $language = 'ka', string $tone = 'professional'): array
    {
        $productInfo = $this->buildProductContext($product);
        $prompt = $this->buildPrompt($productInfo, $language, $tone);

        $result = $this->callOpenAi($prompt, $this->generationModel);
        if ($result['success']) {
            $result['content'] = $this->reviewAndCorrect($result['content'], $language);
        }
        return $result;
    }

    /**
     * Generate a custom Facebook post from free-text description.
     */
    public function generateCustomPost(string $description, string $language = 'ka', string $tone = 'professional'): array
    {
        $langLabel = $language === 'ka' ? 'ქართულ' : 'ინგლისურ';

        $prompt = "შექმენი Facebook-ის მარკეტინგული პოსტი {$langLabel} ენაზე.\n\n"
            . "თემა/აღწერა/ინსტრუქცია: {$description}\n\n"
            . "ტონი: {$tone}\n\n"
            . "მოთხოვნები:\n"
            . "- პოსტი უნდა იყოს მიმზიდველი, ლოგიკურად თანმიმდევრული და მიზნობრივი\n"
            . "- დაამატე შესაბამისი emoji-ები\n"
            . "- დაამატე 3-5 ჰეშთეგი\n"
            . "- მაქსიმუმ 300 სიტყვა\n"
            . "- დაამატე call-to-action\n\n"
            . "დააბრუნე მხოლოდ პოსტის ტექსტი, არანაირი დამატებითი ახსნა.";

        $result = $this->callOpenAi($prompt, $this->generationModel);
        if ($result['success']) {
            $result['content'] = $this->reviewAndCorrect($result['content'], $language);
        }
        return $result;
    }

    /**
     * Generate 3 post variants (Custom or Autonomous).
     * Quality control is applied to each variant.
     */
    public function generateThreeVariants(?Product $product, string $language = 'ka', ?string $description = null, string $mode = 'custom', string $tone = 'professional'): array
    {
        $langLabel = $language === 'ka' ? 'ქართულ' : 'ინგლისურ';
        $context = '';

        if ($product) {
            $context = $this->buildProductContext($product);
        }
        if ($description) {
            $context .= "\nდამატებითი ინსტრუქცია/აღწერა: {$description}";
        }
        if (empty($context)) {
            $context = 'სმარტ საათების მაღაზია MyTechnic.ge';
        }

        if ($mode === 'autonomous') {
            // In autonomous mode, we let the AI decide the best 3 angles based on the product features
            $variantsInstruction = <<<INST
შექმენი 3 ვარიანტი, რომლებიც საუკეთესოდ მოერგება ამ პროდუქტის სპეციფიკას (ავტომატური რეჟიმი).
მაგალითად, თუ პროდუქტს აქვს ძლიერი ბატარეა და წყალგამძლეობა, ერთი ვარიანტი იყოს აქტიური ცხოვრების სტილზე.
ვარიანტების სახელები (JSON გასაღებები) შენ თავად მოიფიქრე ინგლისურად (მაგ: "active_lifestyle", "elegant", "tech_focused").
INST;
        } else {
            // Custom mode
            $variantsInstruction = <<<INST
შექმენი ზუსტად 3 ვარიანტი, რომლებიც მიჰყვება მომხმარებლის მითითებულ ტონს ({$tone}):
1. მთავარი (primary) — ზუსტად მითითებული ტონით და სტილით.
2. მოკლე (short) — იგივე სათქმელი, მაგრამ უფრო მოკლე და კონკრეტული.
3. ემოციური (emotional) — უფრო მეტი ემოციით და storytelling-ით.
JSON გასაღებები უნდა იყოს: "primary", "short", "emotional".
INST;
        }

        $prompt = <<<PROMPT
შექმენი Facebook/Instagram-ის 3 სხვადასხვა მარკეტინგული პოსტი {$langLabel} ენაზე.

კონტექსტი:
{$context}

ინსტრუქცია:
{$variantsInstruction}

თითოეული პოსტი:
- მაქსიმუმ 250 სიტყვა
- შესაბამისი emoji-ები
- 3-5 ჰეშთეგი
- call-to-action ლინკით: https://mytechnic.ge
- ლოგიკურად თანმიმდევრული და პროფესიონალური

დააბრუნე მხოლოდ ვალიდური JSON ფორმატით, არანაირი დამატებითი ტექსტი (არც markdown ტიკები).
მაგალითი:
{"variant1_name": "text...", "variant2_name": "text...", "variant3_name": "text..."}
PROMPT;

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(60)->post("{$this->baseUrl}/chat/completions", [
                'model' => $this->generationModel,
                'messages' => [
                    ['role' => 'system', 'content' => 'შენ ხარ პროფესიონალი სოციალური მედიის მარკეტოლოგი. დააბრუნე მხოლოდ JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 2500,
                'temperature' => 0.8,
            ]);

            if ($response->failed()) {
                Log::error('OpenAI 3-variant generation failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);
                return ['success' => false, 'error' => 'AI სერვისი დროებით მიუწვდომელია'];
            }

            $content = trim($response->json('choices.0.message.content', ''));
            $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
            $content = preg_replace('/\s*```$/', '', $content);

            $variants = json_decode($content, true);

            if (!is_array($variants) || empty($variants)) {
                Log::warning('OpenAI 3-variant: unexpected format', ['content' => $content]);
                return ['success' => false, 'error' => 'AI-მ არასწორი ფორმატი დააბრუნა'];
            }

            // Quality Control Loop (Review & Correct each variant)
            foreach ($variants as $key => $text) {
                $variants[$key] = $this->reviewAndCorrect($text, $language);
            }

            return [
                'success' => true,
                'variants' => $variants,
                'prompt' => $prompt,
                'mode' => $mode
            ];
        } catch (\Exception $e) {
            Log::error('OpenAI 3-variant exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'AI სერვისთან კავშირი ვერ მოხერხდა'];
        }
    }

    /**
     * NLP Quality Control Mechanism.
     * Reviews the generated text for 100% grammar accuracy, spelling, and punctuation.
     */
    private function reviewAndCorrect(string $text, string $language): string
    {
        // If not Georgian, skip complex NLP correction for now, or apply general grammar check
        if ($language !== 'ka') {
            return $text;
        }

        $systemPrompt = <<<PROMPT
შენ ხარ ქართული ენის უმაღლესი დონის რედაქტორი (NLP ხარისხის კონტროლი).
შენი მიზანია შეამოწმო მოწოდებული მარკეტინგული ტექსტი და უზრუნველყო:
1. გრამატიკული სისწორე (100% სიზუსტე).
2. პუნქტუაცია და სინტაქსი.
3. სტილისტური დახვეწილობა (ამოიღე ბარბარიზმები, უხეში კალკები).
4. ტექსტის ლოგიკური ბმულები.

წესები:
- შეინარჩუნე ორიგინალური ტექსტის იდეა, ემოციები და emoji-ები.
- შეასწორე მხოლოდ შეცდომები და სტილისტური ხარვეზები.
- დააბრუნე მხოლოდ შესწორებული ტექსტი, არანაირი დამატებითი კომენტარი.
PROMPT;

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(30)->post("{$this->baseUrl}/chat/completions", [
                'model' => $this->reviewModel, // Use a stronger model for review
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $text],
                ],
                'temperature' => 0.2, // Low temperature for factual corrections
                'max_tokens' => 1500,
            ]);

            if ($response->successful()) {
                $corrected = trim($response->json('choices.0.message.content', ''));
                if (!empty($corrected)) {
                    return $corrected;
                }
            }
        } catch (\Exception $e) {
            Log::warning('AI Review failed, returning original text', ['error' => $e->getMessage()]);
        }

        return $text;
    }

    private function buildProductContext(Product $product): string
    {
        $locale = app()->getLocale();
        $name = $product->{"name_{$locale}"} ?? $product->name_ka ?? $product->name_en;
        $desc = $product->{"short_description_{$locale}"} ?? $product->short_description_ka ?? $product->short_description_en;
        $price = $product->sale_price ?? $product->price;

        $info = "პროდუქტი: {$name}\n";
        $info .= "ფასი: {$price} ₾\n";

        if ($desc) {
            $info .= "აღწერა: {$desc}\n";
        }
        if ($product->brand) {
            $info .= "ბრენდი: {$product->brand}\n";
        }
        if ($product->sim_support) {
            $info .= "SIM ბარათის მხარდაჭერა: დიახ\n";
        }
        if ($product->gps_features) {
            $info .= "GPS: დიახ\n";
        }
        if ($product->water_resistant) {
            $info .= "წყალგამძლე: {$product->water_resistant}\n";
        }
        if ($batteryLife = $product->batteryLifeLabel()) {
            $info .= "ბატარეა: {$batteryLife}\n";
        }
        if ($product->camera) {
            $info .= "კამერა: {$product->camera}\n";
        }

        return $info;
    }

    private function buildPrompt(string $productInfo, string $language, string $tone): string
    {
        $langLabel = $language === 'ka' ? 'ქართულ' : 'ინგლისურ';

        return "შექმენი Facebook-ის მარკეტინგული პოსტი {$langLabel} ენაზე ამ პროდუქტისთვის.\n\n"
            . "პროდუქტის ინფორმაცია:\n{$productInfo}\n"
            . "ტონი: {$tone}\n\n"
            . "მოთხოვნები:\n"
            . "- პოსტი უნდა იყოს მიმზიდველი და გაყიდვაზე ორიენტირებული\n"
            . "- დაამატე შესაბამისი emoji-ები\n"
            . "- დაამატე 3-5 ჰეშთეგი (მაგ: #smartwatch #საათი)\n"
            . "- მაქსიმუმ 250 სიტყვა\n"
            . "- დაამატე call-to-action ლინკით: https://mytechnic.ge\n"
            . "- ხაზი გაუსვი ფასს და მთავარ ფუნქციებს\n\n"
            . "დააბრუნე მხოლოდ პოსტის ტექსტი, არანაირი დამატებითი ახსნა.";
    }

    private function callOpenAi(string $prompt, string $model): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(30)->post("{$this->baseUrl}/chat/completions", [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => 'შენ ხარ პროფესიონალი სოციალური მედიის მარკეტოლოგი, რომელიც სპეციალიზდება სმარტ საათების და ტექნოლოგიური პროდუქტების პრომოციაში ქართულ ბაზარზე.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 1000,
                'temperature' => 0.8,
            ]);

            if ($response->failed()) {
                return [
                    'success' => false,
                    'error' => $response->json('error.message', 'AI სერვისი დროებით მიუწვდომელია'),
                ];
            }

            $content = $response->json('choices.0.message.content', '');

            return [
                'success' => true,
                'content' => trim($content),
                'prompt' => $prompt,
            ];
        } catch (\Exception $e) {
            Log::error('OpenAI request exception', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => 'AI სერვისთან კავშირი ვერ მოხერხდა',
            ];
        }
    }
}
