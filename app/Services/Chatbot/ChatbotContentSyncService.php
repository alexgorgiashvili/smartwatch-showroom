<?php

namespace App\Services\Chatbot;

use App\Models\ChatbotDocument;
use App\Models\ContactSetting;
use App\Models\Faq;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ChatbotContentSyncService
{
    public function __construct(
        private EmbeddingService $embedding,
        private PineconeService $pinecone
    ) {
    }

    public function syncFaq(Faq $faq): bool
    {
        $document = ChatbotDocument::updateOrCreate(
            ['key' => 'faq-' . $faq->id],
            [
                'type' => 'faq',
                'title' => $faq->question,
                'content_ka' => "კითხვა: {$faq->question}\n\nპასუხი: {$faq->answer}",
                'metadata' => [
                    'category' => $faq->category,
                    'source' => 'faq',
                    'faq_id' => $faq->id,
                ],
                'is_active' => $faq->is_active,
            ]
        );

        return $this->syncDocumentEmbedding($document);
    }

    public function deactivateFaq(Faq $faq): bool
    {
        ChatbotDocument::query()
            ->where('key', 'faq-' . $faq->id)
            ->update(['is_active' => false]);

        return true;
    }

    public function syncContacts(?array $settings = null): bool
    {
        $settings ??= ContactSetting::allKeyed();

        $content = implode("\n", array_filter([
            'ტელეფონი: ' . ($settings['phone_display'] ?? ''),
            'WhatsApp: ' . ($settings['whatsapp_url'] ?? ''),
            'ელფოსტა: ' . ($settings['email'] ?? ''),
            'ლოკაცია: ' . ($settings['location'] ?? ''),
            'სამუშაო საათები: ' . ($settings['hours'] ?? ''),
            'Instagram: ' . ($settings['instagram_url'] ?? ''),
            'Facebook: ' . ($settings['facebook_url'] ?? ''),
            'Messenger: ' . ($settings['messenger_url'] ?? ''),
            'Telegram: ' . ($settings['telegram_url'] ?? ''),
        ]));

        $document = ChatbotDocument::updateOrCreate(
            ['key' => 'contact-main'],
            [
                'type' => 'support',
                'title' => 'კონტაქტი',
                'content_ka' => $content,
                'metadata' => [
                    'source' => 'contact_settings',
                ],
                'is_active' => true,
            ]
        );

        return $this->syncDocumentEmbedding($document);
    }

    public function syncProduct(Product $product): bool
    {
        $product->loadMissing('variants');

        if (!$product->is_active) {
            return $this->deactivateProduct($product);
        }

        $name = $product->name_ka ?: $product->name_en;
        $price = $product->sale_price
            ? $product->sale_price . ' ლარი (ძველი ფასი ' . $product->price . ' ლარი)'
            : $product->price . ' ლარი';

        $lines = [
            'პროდუქტი: ' . $name,
            'slug: ' . $product->slug,
            'ფასი: ' . $price,
        ];

        $shortDescription = $product->short_description_ka ?: $product->short_description_en;
        $description = $product->description_ka ?: $product->description_en;

        if ($shortDescription) {
            $lines[] = 'მოკლე აღწერა: ' . $shortDescription;
        }

        if ($description) {
            $lines[] = 'აღწერა: ' . $description;
        }

        $lines[] = 'SIM მხარდაჭერა: ' . ($product->sim_support ? 'კი' : 'არა');
        $lines[] = 'GPS ფუნქციები: ' . ($product->gps_features ? 'კი' : 'არა');

        if ($product->water_resistant) {
            $lines[] = 'წყალგამძლეობა: ' . $product->water_resistant;
        }

        if ($product->battery_life_hours) {
            $lines[] = 'ბატარეა: ' . $product->battery_life_hours . ' სთ';
        }

        if ($product->warranty_months) {
            $lines[] = 'გარანტია: ' . $product->warranty_months . ' თვე';
        }

        if ($product->operating_system) {
            $lines[] = 'ოპერაციული სისტემა: ' . $product->operating_system;
        }

        if ($product->screen_size) {
            $lines[] = 'ეკრანის ზომა: ' . $product->screen_size;
        }

        if ($product->display_type) {
            $lines[] = 'დისპლეის ტიპი: ' . $product->display_type;
        }

        if ($product->screen_resolution) {
            $lines[] = 'გაფართოება: ' . $product->screen_resolution;
        }

        if ($product->battery_capacity_mah) {
            $lines[] = 'ბატარეის ტევადობა: ' . $product->battery_capacity_mah . ' mAh';
        }

        if ($product->charging_time_hours) {
            $lines[] = 'დამუხტვის დრო: ' . $product->charging_time_hours . ' საათი';
        }

        if ($product->case_material) {
            $lines[] = 'კორპუსის მასალა: ' . $product->case_material;
        }

        if ($product->band_material) {
            $lines[] = 'სამაჯურის მასალა: ' . $product->band_material;
        }

        if ($product->camera) {
            $lines[] = 'კამერა: ' . $product->camera;
        }

        if (is_array($product->functions) && $product->functions !== []) {
            $lines[] = 'ფუნქციები: ' . implode(', ', $product->functions);
        }

        $variantLines = $product->variants
            ->map(function (ProductVariant $variant): string {
                $qty = max(0, (int) $variant->quantity);
                $status = $qty > 0 ? 'მარაგშია' : 'ამოწურულია';

                return $variant->name . ': ' . $status . ' (' . $qty . ' ცალი)';
            })
            ->values()
            ->all();

        if ($variantLines !== []) {
            $lines[] = 'ვარიანტები:';
            foreach ($variantLines as $variantLine) {
                $lines[] = '- ' . $variantLine;
            }
        }

        $totalStock = $product->variants->sum('quantity');
        $lines[] = 'საერთო მარაგი: ' . max(0, (int) $totalStock) . ' ცალი';

        $content = implode("\n", $lines);

        $document = ChatbotDocument::updateOrCreate(
            ['key' => 'product-' . $product->id],
            [
                'type' => 'product',
                'title' => $name,
                'content_ka' => $content,
                'product_id' => $product->id,
                'metadata' => [
                    'key' => 'product-' . $product->id,
                    'slug' => $product->slug,
                    'price' => (string) $product->price,
                    'sale_price' => $product->sale_price ? (string) $product->sale_price : null,
                    'sim_support' => (bool) $product->sim_support,
                    'gps_features' => (bool) $product->gps_features,
                    'water_resistant' => $product->water_resistant,
                    'battery_life_hours' => $product->battery_life_hours,
                    'warranty_months' => $product->warranty_months,
                    'operating_system' => $product->operating_system,
                    'screen_size' => $product->screen_size,
                    'display_type' => $product->display_type,
                    'screen_resolution' => $product->screen_resolution,
                    'battery_capacity_mah' => $product->battery_capacity_mah,
                    'charging_time_hours' => $product->charging_time_hours,
                    'case_material' => $product->case_material,
                    'band_material' => $product->band_material,
                    'camera' => $product->camera,
                    'functions' => $product->functions,
                    'total_stock' => max(0, (int) $totalStock),
                    'text' => $content,
                    'content' => $content,
                ],
                'is_active' => true,
            ]
        );

        $synced = $this->syncDocumentEmbedding($document);
        $this->bumpProductContextVersion();

        return $synced;
    }

    public function deactivateProduct(Product $product): bool
    {
        $document = ChatbotDocument::query()
            ->where('key', 'product-' . $product->id)
            ->first();

        if (!$document) {
            $this->bumpProductContextVersion();
            return true;
        }

        $document->update(['is_active' => false]);

        if ($document->pinecone_id && $this->pinecone->isConfigured()) {
            try {
                $this->pinecone->deleteByIds([$document->pinecone_id]);
            } catch (\Throwable $exception) {
                Log::warning('Failed to delete product vector from Pinecone', [
                    'key' => $document->key,
                    'pinecone_id' => $document->pinecone_id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->bumpProductContextVersion();

        return true;
    }

    private function bumpProductContextVersion(): void
    {
        if (!Cache::has('product_context_version')) {
            Cache::forever('product_context_version', 1);
        }

        Cache::increment('product_context_version');
    }

    private function syncDocumentEmbedding(ChatbotDocument $document): bool
    {
        if (!$this->embedding->isConfigured() || !$this->pinecone->isConfigured()) {
            return false;
        }

        try {
            $vector = $this->embedding->embed($document->title . "\n" . $document->content_ka);

            if ($vector === []) {
                return false;
            }

            $pineconeId = $document->pinecone_id ?: 'doc_' . $document->id;

            if (!$document->pinecone_id) {
                $document->update(['pinecone_id' => $pineconeId]);
            }

            $metadata = [
                'key' => $document->key,
                'type' => $document->type,
                'title' => $document->title,
                'product_id' => $document->product_id,
            ];

            $this->pinecone->upsert([
                [
                    'id' => $pineconeId,
                    'values' => $vector,
                    'metadata' => array_filter($metadata, static fn ($value) => $value !== null),
                ],
            ]);

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Chatbot content sync failed', [
                'key' => $document->key,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
