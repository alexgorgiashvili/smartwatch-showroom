<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Response;

class AiContentController extends Controller
{
    /**
     * Get product content in markdown format
     */
    public function showMarkdown(Product $product): Response
    {
        $locale = request()->get('lang', 'ka');
        app()->setLocale($locale);

        $markdown = $this->generateMarkdown($product, $locale);

        return response($markdown, 200)
            ->header('Content-Type', 'text/markdown; charset=UTF-8');
    }

    /**
     * Generate markdown content for product
     */
    private function generateMarkdown(Product $product, string $locale): string
    {
        $name = $locale === 'ka' ? ($product->name_ka ?? $product->name) : ($product->name_en ?? $product->name);
        $description = $locale === 'ka' ? ($product->description_ka ?? $product->description) : ($product->description_en ?? $product->description);
        
        $price = $product->sale_price ?? $product->price;
        $originalPrice = $product->sale_price ? $product->price : null;
        $discount = $originalPrice ? round((($originalPrice - $price) / $originalPrice) * 100) : 0;

        $markdown = "# {$name} - MyTechnic.ge\n\n";

        // Overview
        $markdown .= "## " . ($locale === 'ka' ? 'მიმოხილვა' : 'Overview') . "\n";
        $markdown .= "{$description}\n\n";

        // Key Features
        $markdown .= "## " . ($locale === 'ka' ? 'ძირითადი მახასიათებლები' : 'Key Features') . "\n";
        
        if ($product->sim_support) {
            $markdown .= "- ✅ " . ($locale === 'ka' ? 'SIM ბარათის მხარდაჭერა' : 'SIM card support') . "\n";
        }
        if ($product->gps) {
            $markdown .= "- ✅ " . ($locale === 'ka' ? 'GPS რეალურ დროში ტრეკინგი' : 'GPS real-time tracking') . "\n";
        }
        if ($product->video_call) {
            $markdown .= "- ✅ " . ($locale === 'ka' ? 'ვიდეო ზარები' : 'Video calls') . "\n";
        }
        if ($product->camera) {
            $markdown .= "- ✅ " . ($locale === 'ka' ? 'კამერა' : 'Camera') . "\n";
        }
        if ($product->waterproof) {
            $markdown .= "- ✅ " . ($locale === 'ka' ? 'წყალგამძლე' : 'Waterproof') . " ({$product->waterproof})\n";
        }
        if ($product->battery_life) {
            $markdown .= "- ✅ " . ($locale === 'ka' ? 'ბატარეის ხანგრძლივობა' : 'Battery life') . ": {$product->battery_life}\n";
        }
        $markdown .= "\n";

        // Price
        $markdown .= "## " . ($locale === 'ka' ? 'ფასი' : 'Price') . "\n";
        if ($originalPrice) {
            $markdown .= "- " . ($locale === 'ka' ? 'რეგულარული' : 'Regular') . ": " . number_format($originalPrice, 0) . " ₾\n";
            $markdown .= "- " . ($locale === 'ka' ? 'ფასდაკლება' : 'Sale') . ": **" . number_format($price, 0) . " ₾** ({$discount}% " . ($locale === 'ka' ? 'ფასდაკლება' : 'off') . ")\n";
        } else {
            $markdown .= "- **" . number_format($price, 0) . " ₾**\n";
        }
        $markdown .= "- " . ($locale === 'ka' ? 'უფასო მიწოდება თბილისში' : 'Free delivery in Tbilisi') . "\n\n";

        // Suitable For
        if ($product->age_min && $product->age_max) {
            $markdown .= "## " . ($locale === 'ka' ? 'შესაფერისია' : 'Suitable For') . "\n";
            $markdown .= "- " . ($locale === 'ka' ? 'ასაკი' : 'Age') . ": {$product->age_min}-{$product->age_max} " . ($locale === 'ka' ? 'წელი' : 'years') . "\n";
            $markdown .= "- " . ($locale === 'ka' ? 'გამოყენება' : 'Use case') . ": " . ($locale === 'ka' ? 'სკოლა, გარე აქტივობები, უსაფრთხოება' : 'School, outdoor activities, safety') . "\n\n";
        }

        // Reviews
        if ($product->reviews_avg_rating) {
            $markdown .= "## " . ($locale === 'ka' ? 'შეფასებები' : 'Reviews') . "\n";
            $markdown .= "- " . ($locale === 'ka' ? 'რეიტინგი' : 'Rating') . ": " . round($product->reviews_avg_rating, 1) . "/5 ⭐\n";
            $markdown .= "- " . ($locale === 'ka' ? 'მიმოხილვების რაოდენობა' : 'Reviews count') . ": " . ($product->reviews_count ?? 0) . "\n\n";
        }

        // Where to Buy
        $markdown .= "## " . ($locale === 'ka' ? 'სად შევიძინოთ' : 'Where to Buy') . "\n";
        $markdown .= ($locale === 'ka' ? 'ხელმისაწვდომია MyTechnic.ge-ზე - ოფიციალური იმპორტიორი საქართველოში' : 'Available at MyTechnic.ge - Official importer in Georgia') . "\n";
        $markdown .= "URL: " . route('products.show', $product) . "\n\n";

        // Additional Info
        $markdown .= "---\n\n";
        $markdown .= ($locale === 'ka' ? '**შენიშვნა:** ფასები შეიძლება შეიცვალოს. გთხოვთ დაადასტუროთ ვებსაიტზე.' : '**Note:** Prices subject to change. Please verify on website.') . "\n";
        $markdown .= ($locale === 'ka' ? '**გარანტია:** ყველა პროდუქტი მოდის გარანტიით' : '**Warranty:** All products come with warranty') . "\n";
        $markdown .= ($locale === 'ka' ? '**მხარდაჭერა:** ქართულ და ინგლისურ ენებზე' : '**Support:** Available in Georgian and English') . "\n";

        return $markdown;
    }
}
