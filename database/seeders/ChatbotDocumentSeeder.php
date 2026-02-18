<?php

namespace Database\Seeders;

use App\Models\ChatbotDocument;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ChatbotDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedProductDocuments();
        $this->seedFaqDocuments();
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
                'type' => 'policy',
                'title' => 'მიწოდება და გადაზიდვა',
                'content_ka' => "მიწოდება ხდება საქართველოს მასშტაბით. შეკვეთის დადასტურების შემდეგ ოპერატორი დაგიკავშირდებათ მიწოდების დეტალებზე.\nსტანდარტული მიწოდების ვადა: 1-3 სამუშაო დღე.",
            ],
            [
                'key' => 'faq-warranty',
                'type' => 'policy',
                'title' => 'გარანტია',
                'content_ka' => "ყველა საათზე ვრცელდება ოფიციალური გარანტია.\nგარანტიის ვადა დამოკიდებულია მოდელზე და მითითებულია პროდუქტის აღწერაში.",
            ],
            [
                'key' => 'faq-returns',
                'type' => 'policy',
                'title' => 'დაბრუნება და შეცვლა',
                'content_ka' => "დაბრუნება შესაძლებელია პროდუქტის მიღებიდან 7 დღის განმავლობაში, თუ პროდუქტი გაუხსნელი და დაუზიანებელია.\nდეტალებისთვის მოგვმართეთ კონტაქტის გვერდზე.",
            ],
            [
                'key' => 'faq-gps',
                'type' => 'support',
                'title' => 'GPS ფუნქციები',
                'content_ka' => "GPS საშუალებას გაძლევთ აკონტროლოთ ბავშვის მდებარეობა რეალურ დროში.\nზოგ მოდელში შესაძლებელია უსაფრთხო ზონების დაყენება და შეტყობინებების მიღება.",
            ],
            [
                'key' => 'faq-sim',
                'type' => 'support',
                'title' => 'SIM ბარათის მხარდაჭერა',
                'content_ka' => "SIM ბარათი საჭიროა ზარებისა და მონაცემების ფუნქციონირებისთვის.\nგთხოვთ გამოიყენოთ აქტიური SIM ბარათი მონაცემთა პაკეტით.",
            ],
            [
                'key' => 'faq-battery',
                'type' => 'support',
                'title' => 'ბატარეის დრო',
                'content_ka' => "ბატარეის ხანგრძლივობა დამოკიდებულია გამოყენებაზე.\nსაშუალოდ 24-48 საათი, აქტიური GPS და ზარების დროს ნაკლები.",
            ],
            [
                'key' => 'faq-water',
                'type' => 'support',
                'title' => 'წყალგამძლეობა',
                'content_ka' => "ზოგი მოდელი დაცულია სველ გარემოში გამოყენებისთვის.\nIP მაჩვენებელი მითითებულია პროდუქტის აღწერაში.",
            ],
            [
                'key' => 'faq-app',
                'type' => 'support',
                'title' => 'აპლიკაციის ინსტალაცია',
                'content_ka' => "საათის მართვისთვის საჭიროა მობილური აპლიკაცია.\nინსტრუქციას მიიღებთ შეკვეთასთან ერთად ან მოგვწერეთ დეტალებისთვის.",
            ],
            [
                'key' => 'faq-payments',
                'type' => 'policy',
                'title' => 'გადახდის მეთოდები',
                'content_ka' => "გადახდა შესაძლებელია ნაღდი ანგარიშსწორებით ან საბანკო გადარიცხვით.\nდეტალებზე ოპერატორი მოგაწვდით ინფორმაციას დადასტურების შემდეგ.",
            ],
            [
                'key' => 'faq-contact',
                'type' => 'faq',
                'title' => 'დახმარება და კონტაქტი',
                'content_ka' => "თუ დამატებითი კითხვები გაქვთ, დაგვიკავშირდით კონტაქტის გვერდიდან.\nჩვენი გუნდი სწრაფად გიპასუხებთ.",
            ],
        ];

        foreach ($documents as $document) {
            ChatbotDocument::updateOrCreate(
                ['key' => $document['key']],
                [
                    'type' => $document['type'],
                    'title' => $document['title'],
                    'content_ka' => $document['content_ka'],
                    'metadata' => ['key' => $document['key']],
                    'is_active' => true,
                ]
            );
        }
    }
}
