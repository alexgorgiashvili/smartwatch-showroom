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
        private PineconeService $pinecone,
        private DocumentChunker $chunker
    ) {
    }

    public function syncFaq(Faq $faq, bool $syncEmbedding = true): bool
    {
        $document = ChatbotDocument::updateOrCreate(
            ['key' => 'faq-' . $faq->id],
            [
                'type' => 'faq',
                'title' => $faq->question,
                'title_en' => $faq->question_en,
                'content_ka' => "კითხვა: {$faq->question}\n\nპასუხი: {$faq->answer}",
                'content_en' => filled($faq->question_en) && filled($faq->answer_en)
                    ? "Question: {$faq->question_en}\n\nAnswer: {$faq->answer_en}"
                    : null,
                'metadata' => [
                    'category' => $faq->category,
                    'source' => 'faq',
                    'faq_id' => $faq->id,
                ],
                'is_active' => $faq->is_active,
            ]
        );

        $synced = ! $syncEmbedding || $this->syncDocumentEmbedding($document);
        $this->bumpProductContextVersion();

        return $synced;
    }

    public function syncLegacyFaqDocuments(bool $syncEmbedding = true): bool
    {
        $faqs = Faq::query()
            ->get()
            ->keyBy(fn (Faq $faq): string => trim((string) $faq->question));
        $synced = true;

        ChatbotDocument::query()
            ->active()
            ->where('type', 'faq')
            ->where(function ($query): void {
                $query->whereNull('title_en')
                    ->orWhere('title_en', '')
                    ->orWhereNull('content_en')
                    ->orWhere('content_en', '');
            })
            ->chunkById(100, function ($documents) use ($faqs, $syncEmbedding, &$synced): void {
                foreach ($documents as $document) {
                    $faq = $faqs->get(trim((string) $document->title));

                    if (! $faq || blank($faq->question_en) || blank($faq->answer_en)) {
                        $synced = false;
                        continue;
                    }

                    $metadata = is_array($document->metadata) ? $document->metadata : [];
                    $document->update([
                        'title_en' => $faq->question_en,
                        'content_en' => "Question: {$faq->question_en}\n\nAnswer: {$faq->answer_en}",
                        'metadata' => array_merge($metadata, [
                            'category_en' => $faq->category_en,
                            'faq_id' => $faq->id,
                        ]),
                    ]);

                    $synced = (! $syncEmbedding || $this->syncDocumentEmbedding($document)) && $synced;
                }
            });

        $this->bumpProductContextVersion();

        return $synced;
    }

    public function deactivateFaq(Faq $faq): bool
    {
        ChatbotDocument::query()
            ->where('key', 'faq-' . $faq->id)
            ->update(['is_active' => false]);

        $this->bumpProductContextVersion();

        return true;
    }

    public function syncContacts(?array $settings = null, bool $syncEmbedding = true): bool
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
        ]));

        $contentEn = implode("\n", array_filter([
            'Phone: ' . ($settings['phone_display'] ?? ''),
            'WhatsApp: ' . ($settings['whatsapp_url'] ?? ''),
            'Email: ' . ($settings['email'] ?? ''),
            'Location: ' . ($settings['location_en'] ?? $settings['location'] ?? ''),
            'Working hours: ' . ($settings['hours_en'] ?? ''),
            'Instagram: ' . ($settings['instagram_url'] ?? ''),
            'Facebook: ' . ($settings['facebook_url'] ?? ''),
            'Messenger: ' . ($settings['messenger_url'] ?? ''),
        ]));

        $document = ChatbotDocument::updateOrCreate(
            ['key' => 'contact-main'],
            [
                'type' => 'support',
                'title' => 'კონტაქტი',
                'title_en' => 'Contact',
                'content_ka' => $content,
                'content_en' => $contentEn,
                'metadata' => [
                    'source' => 'contact_settings',
                ],
                'is_active' => true,
            ]
        );

        $synced = ! $syncEmbedding || $this->syncDocumentEmbedding($document);
        $this->bumpProductContextVersion();

        return $synced;
    }

    public function syncStaticPages(bool $syncEmbedding = true): bool
    {
        $synced = true;

        foreach ($this->staticPageDocuments() as $documentData) {
            $document = ChatbotDocument::updateOrCreate(
                ['key' => $documentData['key']],
                [
                    'type' => $documentData['type'],
                    'title' => $documentData['title'],
                    'title_en' => $documentData['title_en'],
                    'content_ka' => $documentData['content_ka'],
                    'content_en' => $documentData['content_en'],
                    'metadata' => $documentData['metadata'],
                    'is_active' => true,
                ]
            );

            $synced = (! $syncEmbedding || $this->syncDocumentEmbedding($document)) && $synced;
        }

        $this->bumpProductContextVersion();

        return $synced;
    }

    public function syncProduct(Product $product, bool $syncEmbedding = true): bool
    {
        $product->loadMissing(['variants', 'primaryImage']);

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

        if ($batteryLife = $product->batteryLifeLabel('ka')) {
            $lines[] = 'ბატარეა: ' . $batteryLife;
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

                return $variant->name . ': ' . $status;
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
        $category = $this->resolveProductCategory($product);
        $isInStock = max(0, (int) $totalStock) > 0;
        $stockStatus = $isInStock ? 'მარაგშია' : 'ამოწურულია';
        $lines[] = 'საერთო მარაგი: ' . $stockStatus;

        $content = implode("\n", $lines);
        $contentEn = $this->buildEnglishProductContent($product, $isInStock);

        $document = ChatbotDocument::updateOrCreate(
            ['key' => 'product-' . $product->id],
            [
                'type' => 'product',
                'title' => $name,
                'title_en' => $product->name_en,
                'content_ka' => $content,
                'content_en' => $contentEn,
                'product_id' => $product->id,
                'metadata' => [
                    'key' => 'product-' . $product->id,
                    'slug' => $product->slug,
                    'image_url' => $product->primaryImage?->url,
                    'price' => (float) $product->price,
                    'sale_price' => $product->sale_price !== null ? (float) $product->sale_price : null,
                    'is_in_stock' => $isInStock,
                    'category' => $category,
                    'brand' => $product->brand,
                    'last_updated' => $product->updated_at?->timestamp ?? now()->timestamp,
                    'sim_support' => (bool) $product->sim_support,
                    'gps_features' => (bool) $product->gps_features,
                    'water_resistant' => $product->water_resistant,
                    'battery_life_hours' => $product->battery_life_hours,
                    'battery_life_range' => $product->battery_life_range,
                    'battery_life_label' => $product->batteryLifeLabel('ka'),
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
                    'text_en' => $contentEn,
                    'content_en' => $contentEn,
                ],
                'is_active' => true,
            ]
        );

        $synced = ! $syncEmbedding || $this->syncDocumentEmbedding($document);
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
                $chunkCount = max(1, (int) data_get($document->metadata, 'chunk_count', 1));

                if ($chunkCount > 1) {
                    $ids = [];
                    for ($index = 0; $index < $chunkCount; $index++) {
                        $ids[] = $document->pinecone_id . '#chunk-' . $index;
                    }
                    $this->pinecone->deleteByIds($ids);
                } else {
                    $this->pinecone->deleteByIds([$document->pinecone_id]);
                }
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

    private function buildEnglishProductContent(Product $product, bool $isInStock): string
    {
        $price = $product->sale_price
            ? $product->sale_price . ' GEL (regular price ' . $product->price . ' GEL)'
            : $product->price . ' GEL';

        $lines = [
            'Product: ' . $product->name_en,
            'Slug: ' . $product->slug,
            'Price: ' . $price,
        ];

        if ($product->short_description_en) {
            $lines[] = 'Short description: ' . $product->short_description_en;
        }
        if ($product->description_en) {
            $lines[] = 'Description: ' . $product->description_en;
        }

        $lines[] = 'SIM support: ' . ($product->sim_support ? 'Yes' : 'No');
        $lines[] = 'GPS features: ' . ($product->gps_features ? 'Yes' : 'No');

        $specifications = [
            'Water resistance' => $product->water_resistant,
            'Battery life' => $product->batteryLifeLabel('en'),
            'Warranty' => $product->warranty_months ? $product->warranty_months . ' months' : null,
            'Operating system' => $product->operating_system,
            'Screen size' => $product->screen_size,
            'Display type' => $product->display_type,
            'Resolution' => $product->screen_resolution,
            'Battery capacity' => $product->battery_capacity_mah ? $product->battery_capacity_mah . ' mAh' : null,
            'Charging time' => $product->charging_time_hours ? $product->charging_time_hours . ' hours' : null,
            'Case material' => $product->case_material,
            'Band material' => $product->band_material,
            'Camera' => $product->camera,
        ];

        foreach ($specifications as $label => $value) {
            if (filled($value)) {
                $lines[] = $label . ': ' . $value;
            }
        }

        if (is_array($product->functions) && $product->functions !== []) {
            $englishFunctions = array_values(array_filter(
                $product->functions,
                fn ($function): bool => preg_match('/\p{Georgian}/u', (string) $function) !== 1
            ));
            if ($englishFunctions !== []) {
                $lines[] = 'Functions: ' . implode(', ', $englishFunctions);
            }
        }

        if ($product->variants->isNotEmpty()) {
            $lines[] = 'Variants:';
            foreach ($product->variants as $variant) {
                $lines[] = '- ' . $variant->localizedName('en') . ': ' . ((int) $variant->quantity > 0 ? 'In stock' : 'Out of stock');
            }
        }

        $lines[] = 'Overall stock: ' . ($isInStock ? 'In stock' : 'Out of stock');

        return implode("\n", $lines);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function staticPageDocuments(): array
    {
        return [
            [
                'key' => 'page-about',
                'type' => 'company',
                'title' => 'MyTechnic-ის შესახებ',
                'title_en' => 'About MyTechnic',
                'content_ka' => implode("\n\n", [
                    'MyTechnic არის საქართველოში SIM-იანი ბავშვთა სმარტ საათების ოფიციალური იმპორტიორი და თბილისში დაფუძნებული გუნდი.',
                    'ჩვენ ვამახვილებთ ყურადღებას ზარზე, GPS-ზე, შეტყობინებებზე, ვიდეო ზარზე, კამერაზე, ბატარეაზე და ყოველდღიურ გამოყენებადობაზე.',
                    'გვინდა, რომ მშობელს ჰქონდეს სიმშვიდე, ბავშვს კი უსაფრთხო და მარტივი კავშირი.',
                    'მნიშვნელოვანი დეტალები: უფასო მიწოდება მთელი საქართველოს მასშტაბით, ' . UnifiedAiPolicyService::canonicalWarrantySummary('ka') . '.',
                    'დაგვიკავშირდით ტელეფონით, WhatsApp-ით ან Messenger-ით — სწრაფად და პირდაპირ.',
                ]),
                'content_en' => implode("\n\n", [
                    'MyTechnic is the official importer of SIM-enabled kids smartwatches in Georgia and a Tbilisi-based team.',
                    'We focus on calling, GPS, messaging, video calls, cameras, battery life, and practical everyday use.',
                    'Our goal is to give parents peace of mind and children a safe, simple way to stay connected.',
                    'Key details: free delivery across Georgia and ' . UnifiedAiPolicyService::canonicalWarrantySummary('en') . '.',
                    'Contact us by phone, WhatsApp, or Messenger for fast, direct support.',
                ]),
                'metadata' => [
                    'source' => 'site_page',
                    'page' => 'about',
                ],
            ],
            [
                'key' => 'page-privacy',
                'type' => 'policy',
                'title' => 'კონფიდენციალობის პოლიტიკა',
                'title_en' => 'Privacy Policy',
                'content_ka' => implode("\n\n", [
                    'ვაგროვებთ მხოლოდ იმ ინფორმაციას, რომელიც საჭიროა შეკვეთისა და მხარდაჭერისთვის: სახელი, ტელეფონი, ელფოსტა, მისამართი, შეკვეთის დეტალები და ვებსაიტთან ან ჩატბოტთან დაკავშირებული აქტივობა.',
                    'ვიყენებთ მონაცემებს შეკვეთების დამუშავებისთვის, მიწოდებისთვის, მომხმარებელთან კომუნიკაციისთვის, ვებსაიტის გაუმჯობესებისთვის და თაღლითობის პრევენციისთვის.',
                    'ჩატბოტთან მიმოწერა შეიძლება გამოყენდეს მხარდაჭერის გაუმჯობესებისთვის. AI პასუხები ავტომატურად გენერირდება და არ წარმოადგენს იურიდიულ ან სამედიცინო რჩევას.',
                    'ვიცავთ მონაცემებს SSL/TLS, უსაფრთხო სერვერებით, წვდომის კონტროლით და რეგულარული მონიტორინგით. პერსონალურ ინფორმაციას მესამე მხარეს არ ვუზიარებთ თქვენი თანხმობის გარეშე.',
                ]),
                'content_en' => implode("\n\n", [
                    'We collect only the information needed to process orders and provide support, such as your name, phone number, email, delivery address, order details, and website or chatbot activity.',
                    'We use this data to process and deliver orders, communicate with customers, improve the website, and prevent fraud.',
                    'Chatbot conversations may be used to improve support. AI responses are generated automatically and are not legal or medical advice.',
                    'We protect data with SSL/TLS, secure servers, access controls, and regular monitoring. We do not share personal information with third parties without consent except when required to deliver the service or comply with law.',
                ]),
                'metadata' => [
                    'source' => 'site_page',
                    'page' => 'privacy',
                ],
            ],
            [
                'key' => 'page-terms',
                'type' => 'policy',
                'title' => 'მომსახურების პირობები',
                'title_en' => 'Terms of Service',
                'content_ka' => implode("\n\n", [
                    'MyTechnic-ის ვებსაიტზე შესვლით და გამოყენებით თქვენ ეთანხმებით ამ პირობებს და საქართველოს მოქმედ კანონმდებლობას.',
                    'ვებსაიტის მასალები განკუთვნილია მხოლოდ პირადი, არაკომერციული გამოყენებისთვის. აკრძალულია კოპირება, გავრცელება, რევერსული ინჟინერია ან სამართლებრივი აღნიშვნების შეცვლა.',
                    'გადახდა შესაძლებელია საქართველოს ბანკის უსაფრთხო ონლაინ სისტემით ან კურიერთან ნაღდი ანგარიშსწორებით თბილისში. მიწოდება უფასოა მთელი საქართველოს მასშტაბით.',
                    'გარანტია: ' . UnifiedAiPolicyService::canonicalWarrantySummary('ka') . '. გარანტია არ ფარავს მექანიკურ დაზიანებას, წყალში გამოყენებას ან არაავტორიზებულ შეკეთებას.',
                    'დაბრუნება/გაცვლა შესაძლებელია 14 კალენდარული დღის განმავლობაში, თუ პროდუქტი არ არის გამოყენებული, აქვს ორიგინალური შეფუთვა და თან ახლავს ყიდვის დამადასტურებელი დოკუმენტი.',
                    'ფასები, მარაგი და მახასიათებლები შეიძლება შეიცვალოს წინასწარი შეტყობინების გარეშე.',
                ]),
                'content_en' => implode("\n\n", [
                    'By accessing and using the MyTechnic website, you agree to these terms and applicable Georgian law.',
                    'Website materials are provided for personal, non-commercial use. Copying, distribution, reverse engineering, or removal of legal notices is prohibited.',
                    'Payment is available through Bank of Georgia’s secure online system and, for eligible Tbilisi orders, by cash on delivery. Delivery is free across Georgia.',
                    'Warranty: ' . UnifiedAiPolicyService::canonicalWarrantySummary('en') . '. The warranty does not cover mechanical damage, water damage caused by misuse, or unauthorized repairs.',
                    'Returns or exchanges may be requested within 14 calendar days if the product is unused, includes the original packaging, and is accompanied by proof of purchase.',
                    'Prices, stock, and specifications may change without prior notice.',
                ]),
                'metadata' => [
                    'source' => 'site_page',
                    'page' => 'terms',
                ],
            ],
        ];
    }

    private function syncDocumentEmbedding(ChatbotDocument $document): bool
    {
        if (!$this->embedding->isConfigured() || !$this->pinecone->isConfigured()) {
            return false;
        }

        try {
            $pineconeId = $document->pinecone_id ?: $this->resolvePineconeId($document);

            if (!$document->pinecone_id) {
                $document->update(['pinecone_id' => $pineconeId]);
            }

            if ($document->type === 'product') {
                $combinedContent = implode("\n\n", array_filter([
                    $document->content_ka,
                    $document->content_en,
                ]));
                $chunks = $this->chunker->chunk($combinedContent, 'product');

                if ($chunks === []) {
                    return false;
                }

                $inputs = array_map(static function (array $chunk): string {
                    return $chunk['section'] . "\n" . $chunk['text'];
                }, $chunks);

                $vectors = $this->embedding->embedMany($inputs);

                $oldChunkCount = max(1, (int) data_get($document->metadata, 'chunk_count', 1));
                $newChunkCount = count($chunks);

                if ($oldChunkCount > $newChunkCount) {
                    $staleIds = [];
                    for ($index = $newChunkCount; $index < $oldChunkCount; $index++) {
                        $staleIds[] = $pineconeId . '#chunk-' . $index;
                    }

                    if ($staleIds !== []) {
                        $this->pinecone->deleteByIds($staleIds);
                    }
                }

                $documentMetadata = is_array($document->metadata) ? $document->metadata : [];
                $documentMetadata['chunk_count'] = $newChunkCount;

                if ((int) data_get($document->metadata, 'chunk_count', 1) !== $newChunkCount) {
                    $document->update(['metadata' => $documentMetadata]);
                }

                $metadataBase = [
                    'key' => $document->key,
                    'type' => $document->type,
                    'title' => $document->title,
                    'product_id' => $document->product_id,
                    'chunk_count' => $newChunkCount,
                ];

                $upsertVectors = [];

                foreach ($chunks as $index => $chunk) {
                    $values = $vectors[$index] ?? [];

                    if ($values === []) {
                        continue;
                    }

                    $upsertVectors[] = [
                        'id' => $pineconeId . '#chunk-' . $index,
                        'values' => $values,
                        'metadata' => array_filter(array_merge($documentMetadata, $metadataBase, [
                            'chunk_index' => $index,
                            'section' => (string) ($chunk['section'] ?? 'product'),
                            'text' => (string) ($chunk['text'] ?? ''),
                        ]), static fn ($value) => $value !== null),
                    ];
                }

                if ($upsertVectors === []) {
                    return false;
                }

                $this->pinecone->upsert($upsertVectors);

                return true;
            }

            $embeddingInput = implode("\n\n", array_filter([
                $document->title,
                $document->content_ka,
                $document->title_en,
                $document->content_en,
            ]));
            $vector = $this->embedding->embed($embeddingInput);

            if ($vector === []) {
                return false;
            }

            $metadata = [
                'key' => $document->key,
                'type' => $document->type,
                'title' => $document->title,
                'title_en' => $document->title_en,
                'content_en' => $document->content_en,
                'product_id' => $document->product_id,
            ];

            $documentMetadata = is_array($document->metadata) ? $document->metadata : [];

            $this->pinecone->upsert([
                [
                    'id' => $pineconeId,
                    'values' => $vector,
                    'metadata' => array_filter(array_merge($documentMetadata, $metadata), static fn ($value) => $value !== null),
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

    private function resolvePineconeId(ChatbotDocument $document): string
    {
        if ($document->type === 'product') {
            $metadata = is_array($document->metadata) ? $document->metadata : [];
            $category = (string) ($metadata['category'] ?? 'smartwatch');
            $category = trim(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $category) ?? 'smartwatch'), '-');

            if ($category === '') {
                $category = 'smartwatch';
            }

            return 'product#' . $category . '#' . $document->product_id;
        }

        return 'doc_' . $document->id;
    }

    private function resolveProductCategory(Product $product): string
    {
        if ($product->brand && str_contains(strtolower($product->brand), 'smart')) {
            return 'smartwatch';
        }

        return 'smartwatch';
    }
}
