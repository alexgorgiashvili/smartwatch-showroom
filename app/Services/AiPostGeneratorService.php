<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiPostGeneratorService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.openai.key');
        $this->model = config('services.openai.model', 'gpt-4.1-mini');
        $this->baseUrl = config('services.openai.base_url', 'https://api.openai.com/v1');
    }

    /**
     * Generate a Facebook marketing post for a product.
     */
    public function generateProductPost(Product $product, string $language = 'ka', string $tone = 'professional'): array
    {
        $productInfo = $this->buildProductContext($product);
        $prompt = $this->buildPrompt($productInfo, $language, $tone);

        return $this->callOpenAi($prompt);
    }

    /**
     * Generate a custom Facebook post from free-text description.
     */
    public function generateCustomPost(string $description, string $language = 'ka', string $tone = 'professional'): array
    {
        $langLabel = $language === 'ka' ? 'ქართულ' : 'ინგლისურ';

        $prompt = "შექმენი Facebook-ის მარკეტინგული პოსტი {$langLabel} ენაზე.\n\n"
            . "თემა/აღწერა: {$description}\n\n"
            . "ტონი: {$tone}\n\n"
            . "მოთხოვნები:\n"
            . "- პოსტი უნდა იყოს მიმზიდველი და engagement-ზე ორიენტირებული\n"
            . "- დაამატე შესაბამისი emoji-ები\n"
            . "- დაამატე 3-5 ჰეშთეგი\n"
            . "- მაქსიმუმ 300 სიტყვა\n"
            . "- დაამატე call-to-action\n\n"
            . "დააბრუნე მხოლოდ პოსტის ტექსტი, არანაირი დამატებითი ახსნა.";

        return $this->callOpenAi($prompt);
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
        if ($product->battery_life_hours) {
            $info .= "ბატარეა: {$product->battery_life_hours} საათი\n";
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

    private function callOpenAi(string $prompt): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(30)->post("{$this->baseUrl}/chat/completions", [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'შენ ხარ პროფესიონალი სოციალური მედიის მარკეტოლოგი, რომელიც სპეციალიზდება სმარტ საათების და ტექნოლოგიური პროდუქტების პრომოციაში ქართულ ბაზარზე.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 1000,
                'temperature' => 0.8,
            ]);

            if ($response->failed()) {
                Log::error('OpenAI post generation failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

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
