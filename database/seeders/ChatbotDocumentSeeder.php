<?php

namespace Database\Seeders;

use App\Models\ChatbotDocument;
use App\Models\ContactSetting;
use App\Models\Faq;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ChatbotDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedProductDocuments();
        $this->seedFaqDocuments();
        $this->seedContactSettings();
    }

    private function seedProductDocuments(): void
    {
        $products = Product::with('primaryImage')->get();

        foreach ($products as $product) {
            $name = $product->name_ka ?: $product->name_en;
            $price = $product->sale_price
                ? $product->sale_price . ' ლარი (ძველი ფასი ' . $product->price . ' ლარი)'
                : $product->price . ' ლარი';

            $lines = [
                'პროდუქტი: ' . $name,
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

            ChatbotDocument::updateOrCreate(
                ['key' => 'product-' . $product->id],
                [
                    'type' => 'product',
                    'title' => $name,
                    'content_ka' => implode("\n", $lines),
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
                    ],
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedFaqDocuments(): void
    {
        $documents = [
            [
                'key' => 'faq-shipping',
                'type' => 'faq',
                'title' => 'მიწოდება თბილისში',
                'content_ka' => "თბილისში მიწოდება ხდება შეკვეთის გაფორმებიდან მომდევნო სამუშაო დღეს და არის სრულიად უფასო.\n\nკურიერი წინასწარ დაგიკავშირდებათ და დაგიზუსტებთ მისამართს და მისვლის სავარაუდო დროს, რათა ზედმეტად არ მოგიწიოთ ლოდინი.",
                'metadata' => ['category' => 'მიწოდება'],
            ],
            [
                'key' => 'faq-warranty',
                'type' => 'faq',
                'title' => 'გარანტია',
                'content_ka' => "ყველა ჩვენს სმარტსაათზე ვრცელდება ოფიციალური გარანტია, რომელიც მოიცავს წარმოების დეფექტებს და დანადგარის გაუმართაობას.\n\nგარანტიის ვადა და დეტალური პირობები მითითებულია თითოეული პროდუქტის აღწერაში. თუ საათი ნორმალური გამოყენებისას გაფუჭდა, დაუკავშირდით ჩვენს მომსახურების გუნდს და ერთად ვიპოვით საუკეთესო გადაწყვეტილებას.",
                'metadata' => ['category' => 'გარანტია და დაბრუნება'],
            ],
            [
                'key' => 'faq-returns',
                'type' => 'faq',
                'title' => 'დაბრუნება და შეცვლა',
                'content_ka' => "ნივთის დაბრუნება შესაძლებელია მიღებიდან 7 კალენდარული დღის განმავლობაში, თუ მას შენარჩუნებული აქვს სასაქონლო იერსახე და სრული შეფუთვა.\n\nდაბრუნების პროცესის დასაწყებად დაგვიკავშირდით ერთ-ერთ საკონტაქტო არხზე და ოპერატორი დეტალურად აგიხსნით შემდეგ ნაბიჯებს.",
                'metadata' => ['category' => 'გარანტია და დაბრუნება'],
            ],
            [
                'key' => 'faq-gps',
                'type' => 'faq',
                'title' => 'GPS ფუნქციები',
                'content_ka' => "GPS ფუნქცია საშუალებას გაძლევთ რეალურ დროში იხილოთ თქვენი ბავშვის მდებარეობა მობილურ აპლიკაციაში.\n\nაპლიკაციიდან შეგიძლიათ ნახოთ როგორც მიმდინარე ლოკაცია, ასევე გადაადგილების ისტორია. ზოგიერთი მოდელი მხარს უჭერს უსაფრთხო ზონების (გეოფენსის) დაყენებასაც — თუ ბავშვი ამ ზონას გასცდება, მიიღებთ შეტყობინებას.",
                'metadata' => ['category' => 'ბავშვის უსაფრთხოება'],
            ],
            [
                'key' => 'faq-sim',
                'type' => 'faq',
                'title' => 'SIM ბარათის მხარდაჭერა',
                'content_ka' => "სიმ ბარათი საჭიროა იმისთვის, რომ საათმა შეძლოს ზარების, შეტყობინებების და მონაცემების გაგზავნა/მიღება. რეკომენდებულია აქტიური SIM ბარათი ინტერნეტის პაკეტით, რათა GPS და ონლაინ ფუნქციები სტაბილურად იმუშაოს.\n\nSIM ბარათის ჩასმის ინსტრუქცია მითითებულია საათის სახელმძღვანელოში და საჭიროების შემთხვევაში შეგვიძლია ონლაინაც დაგეხმაროთ.",
                'metadata' => ['category' => 'პროდუქტის გამოყენება'],
            ],
            [
                'key' => 'faq-battery',
                'type' => 'faq',
                'title' => 'ბატარეის დრო',
                'content_ka' => "ბატარეის ხანგრძლივობა დამოკიდებულია გამოყენების ინტენსივობაზე. ჩვეულებრივ, ნორმალური დატვირთვისას საათი ერთ დამუხტვაზე 1-3 დღეს მუშაობს.\n\nთუ GPS მუდმივად ჩართულია და ბევრი ზარი მიმდინარეობს, ბატარეა უფრო სწრაფად დაიხარჯება – ასეთ შემთხვევაში რეკომენდებულია ყოველდღიური დამუხტვა.",
                'metadata' => ['category' => 'პროდუქტის გამოყენება'],
            ],
            [
                'key' => 'faq-water',
                'type' => 'faq',
                'title' => 'წყალგამძლეობა',
                'content_ka' => "ზოგი მოდელი წყლისადმი გამძლეა ყოველდღიური გამოყენებისთვის – ხელის დაბანისას ან მსუბუქი წვიმის დროს საათის მოხსნა საჭირო არ არის.\n\nპროდუქტის გვერდზე მითითებულია ზუსტი IP მაჩვენებელი, რომელიც განსაზღვრავს, რა დონემდეა საათი დაცული წყლისგან. გთხოვთ, არ გამოიყენოთ საათი ცურვის, ზღვის ან აუზის დროს, თუ ეს პირდაპირ არ არის ნებადართული მოდელის მახასიათებლებში.",
                'metadata' => ['category' => 'პროდუქტის გამოყენება'],
            ],
            [
                'key' => 'faq-app',
                'type' => 'faq',
                'title' => 'აპლიკაციის ინსტალაცია',
                'content_ka' => "საათის სრულფასოვანი გამოყენებისთვის საჭიროა მობილური აპლიკაციის დაყენება მშობლის ტელეფონზე.\n\nშეკვეთასთან ერთად მიიღებთ ინსტრუქციას ჩამოტვირთვის ბმულებით. აპლიკაციაში ბავშვის საათის მიბმა მარტივია და ნაბიჯ-ნაბიჯ ინსტრუქცია ხელმისაწვდომია ქართულ ენაზე.",
                'metadata' => ['category' => 'აპლიკაცია და მართვა'],
            ],
            [
                'key' => 'faq-payments',
                'type' => 'faq',
                'title' => 'გადახდის მეთოდები',
                'content_ka' => "გადახდა შესაძლებელია როგორც ნაღდი ანგარიშსწორებით კურიერთან, ასევე საბანკო გადარიცხვით.\n\nშეკვეთის გაფორმების შემდეგ ოპერატორი გამოგიგზავნით დეტალურ ინფორმაციას ანგარიშის ნომრისა და გადახდის დადასტურების წესის შესახებ.",
                'metadata' => ['category' => 'შეკვეთა და გადახდა'],
            ],
            [
                'key' => 'faq-contact',
                'type' => 'faq',
                'title' => 'დახმარება და კონტაქტი',
                'content_ka' => "თუ კითხვები დაგრჩათ, ყოველთვის შეგიძლიათ მოგვწეროთ Facebook-ზე, WhatsApp-ზე, Instagram-ზე, ელფოსტით ან დაგვირეკოთ.\n\nკონტაქტის გვერდზე ნახავთ ყველა საკონტაქტო არხს და სამუშაო საათებს. ვეცდებით, რომ შეტყობინებებს რაც შეიძლება სწრაფად ვუპასუხოთ.",
                'metadata' => ['category' => 'კონტაქტი'],
            ],
        ];

        foreach ($documents as $index => $document) {
            Faq::updateOrCreate(
                ['question' => $document['title']],
                [
                    'answer' => $document['content_ka'],
                    'category' => $document['metadata']['category'] ?? 'სხვა',
                    'sort_order' => $index,
                    'is_active' => true,
                ]
            );

            ChatbotDocument::updateOrCreate(
                ['key' => $document['key']],
                [
                    'type' => $document['type'],
                    'title' => $document['title'],
                    'content_ka' => $document['content_ka'],
                    'metadata' => $document['metadata'] ?? ['key' => $document['key']],
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedContactSettings(): void
    {
        foreach (ContactSetting::DEFAULTS as $key => $value) {
            ContactSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }
}
