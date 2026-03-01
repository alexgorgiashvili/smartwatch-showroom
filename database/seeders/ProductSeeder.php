<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'name_en' => 'MyTechnic Pro GPS',
                'name_ka' => 'MyTechnic სმარტ საათი Pro GPS',
                'slug' => 'mytechnic-pro-gps',
                'short_description_en' => 'Advanced GPS tracking smartwatch for kids with SOS button and geofencing',
                'short_description_ka' => 'პროფესიონალური GPS საათი ბავშვებისთვის SOS ღილაკითა და გეოფენსინგით',
                'description_en' => 'The MyTechnic Pro GPS is the ultimate safety device for your child. Features include real-time GPS tracking, SOS emergency button, two-way calling, geofencing alerts, and 3-day battery life. Water-resistant IP67 design perfect for active kids.',
                'description_ka' => 'MyTechnic Pro GPS არის საუკეთესო უსაფრთხოების მოწყობილობა თქვენი ბავშვისთვის. ფუნქციები მოიცავს: რეალურ დროში GPS თრექინგს, SOS საგანგებო ღილაკს, ორმხრივ ზარებს, გეოფენსინგ შეტყობინებებს და 3-დღიან ბატარეას. წყლისადმი გამძლე IP67 დიზაინი იდეალურია აქტიური ბავშვებისთვის.',
                'price' => 249.00,
                'sale_price' => 199.00,
                'sim_support' => true,
                'gps_features' => true,
                'water_resistant' => 'IP67',
                'battery_life_hours' => 72,
                'warranty_months' => 24,
                'featured' => true,
                'images' => [
                    'images/products/IKsoyOMX0dMyaaYmI2RoVlXkiqdXXONHWE9NQ1HX.jpg',
                    'images/products/GBBJimLHg1STT9YVI0Eg0LMUmPchFHEnMqr84kE5.jpg',
                    'images/products/3smzRL6DGLa5ZJSwn9WF0C4xSPtVRh3iTDhDlfHM.jpg',
                ],
            ],
            [
                'name_en' => 'SafeKid GPS Tracker Watch',
                'name_ka' => 'SafeKid GPS ტრეკერი საათი',
                'slug' => 'safekid-gps-tracker-watch',
                'short_description_en' => 'Reliable GPS smartwatch with video calling and activity tracking',
                'short_description_ka' => 'საიმედო GPS სმარტსაათი ვიდეო ზარებითა და აქტივობის თრექინგით',
                'description_en' => 'SafeKid GPS Tracker Watch keeps your child connected and safe. Includes HD video calling, accurate GPS positioning, step counter, and Class Mode for school hours. Long-lasting 2-day battery with quick charge support.',
                'description_ka' => 'SafeKid GPS ტრეკერი საათი ინარჩუნებს თქვენს ბავშვს დაკავშირებულ და უსაფრთხოდ. მოიცავს HD ვიდეო ზარებს, ზუსტ GPS პოზიციონირებას, ნაბიჯების მრიცხველს და სკოლის რეჟიმს. გრძელვადიანი 2-დღიანი ბატარეა სწრაფი დამუხტვის მხარდაჭერით.',
                'price' => 189.00,
                'sale_price' => null,
                'sim_support' => true,
                'gps_features' => true,
                'water_resistant' => 'IP65',
                'battery_life_hours' => 48,
                'warranty_months' => 12,
                'featured' => true,
                'images' => [
                    'images/products/DiU5EZC1cf3wIYfnB8MizVZL0QOe1ioEuOwH7FRj.jpg',
                    'images/products/CtkhuS8AHRdQObKCcxGfWueQc0n55vhG9j5U4hf3.jpg',
                    'images/products/E31FB6T4URu93UU3xFUh8hQzavrTmnSZdjZljxLu.jpg',
                ],
            ],
            [
                'name_en' => 'Junior Smart Watch 4G',
                'name_ka' => 'Junior სმარტ საათი 4G',
                'slug' => 'junior-smart-watch-4g',
                'short_description_en' => '4G-enabled kids smartwatch with camera and voice messages',
                'short_description_ka' => '4G სმარტსაათი ბავშვებისთვის კამერითა და ხმოვანი შეტყობინებებით',
                'description_en' => 'Junior Smart Watch 4G brings cutting-edge connectivity to your child\'s wrist. Features 4G LTE support, front camera for photos, voice messaging, location history, and parental controls through dedicated app. Perfect for independent kids.',
                'description_ka' => 'Junior სმარტ საათი 4G მოაქვს უახლესი დაკავშირება თქვენი ბავშვის მაჯაზე. მოიცავს 4G LTE მხარდაჭერას, წინა კამერას ფოტოებისთვის, ხმოვან შეტყობინებებს, მდებარეობის ისტორიას და მშობლის კონტროლს სპეციალური აპლიკაციის საშუალებით. იდეალურია დამოუკიდებელი ბავშვებისთვის.',
                'price' => 299.00,
                'sale_price' => 259.00,
                'sim_support' => true,
                'gps_features' => true,
                'water_resistant' => 'IP68',
                'battery_life_hours' => 60,
                'warranty_months' => 24,
                'featured' => false,
                'images' => [
                    'images/products/egFSQKgK1oxDVd5ywFSLfO0bOPa2pD5ZteNKfrVp.jpg',
                    'images/products/extltgaCD5fWIT1WztmJZT36OrYNl8IXZnet21Sy.jpg',
                    'images/products/gjB9NwcbwXuHSpisp21mi2ildBB8pMGDi74tink7.jpg',
                ],
            ],
            [
                'name_en' => 'MiniGuard GPS Watch',
                'name_ka' => 'MiniGuard GPS საათი',
                'slug' => 'miniguard-gps-watch',
                'short_description_en' => 'Compact and durable GPS watch for young children',
                'short_description_ka' => 'კომპაქტური და გამძლე GPS საათი პატარა ბავშვებისთვის',
                'description_en' => 'MiniGuard GPS Watch is designed specifically for younger kids aged 4-8. Simple interface, one-touch SOS, GPS tracking, and bright colorful design. Lightweight and comfortable for small wrists.',
                'description_ka' => 'MiniGuard GPS საათი სპეციალურად შექმნილია 4-8 წლის ბავშვებისთვის. მარტივი ინტერფეისი, ერთ შეხებაზე SOS, GPS თრექინგი და ნათელი ფერადი დიზაინი. მსუბუქი და კომფორტული პატარა მაჯებისთვის.',
                'price' => 139.00,
                'sale_price' => 119.00,
                'sim_support' => true,
                'gps_features' => true,
                'water_resistant' => 'IP65',
                'battery_life_hours' => 36,
                'warranty_months' => 12,
                'featured' => false,
                'images' => [
                    'images/products/k9NUS7abFZyxs9nK6MEA3yrS062rDA4IGJp7zEPj.jpg',
                    'images/products/Lop9tiRbDkHVhP9m9wJvRNbPz2SBDHUcoKLTUHts.jpg',
                    'images/products/NQm0zdALb3rbIMrPQBr0UCl8TkV2uCO3o0d46i3V.jpg',
                ],
            ],
            [
                'name_en' => 'ActiveKid Fitness Tracker Watch',
                'name_ka' => 'ActiveKid ფიტნეს ტრეკერი საათი',
                'slug' => 'activekid-fitness-tracker-watch',
                'short_description_en' => 'Sports-focused smartwatch with GPS and activity monitoring',
                'short_description_ka' => 'სპორტზე ორიენტირებული სმარტსაათი GPS-ითა და აქტივობის მონიტორინგით',
                'description_en' => 'ActiveKid Fitness Tracker Watch encourages healthy habits. Track steps, distance, calories, and active time. GPS for outdoor activities, heart rate monitor, and sleep tracking. Perfect for sporty children.',
                'description_ka' => 'ActiveKid ფიტნეს ტრეკერი საათი ხელს უწყობს ჯანსაღ ჩვევებს. თვალყური ადევნეთ ნაბიჯებს, მანძილს, კალორიებს და აქტიური დროს. GPS გარე აქტივობებისთვის, პულსის მონიტორი და ძილის თრექინგი. იდეალურია სპორტული ბავშვებისთვის.',
                'price' => 169.00,
                'sale_price' => null,
                'sim_support' => false,
                'gps_features' => true,
                'water_resistant' => 'IP68',
                'battery_life_hours' => 96,
                'warranty_months' => 18,
                'featured' => false,
                'images' => [
                    'images/products/R0ay9KwWmN5uODrA3JOOWIkS92Ux9ZtFZY2Uo1n5.jpg',
                    'images/products/RHBqyWZapzihKJomgiRn4UAmrBrCJKuCxzNY4LNP.jpg',
                    'images/products/RpmZkh5nUYKiN4IJgYwT5j7fJD1d8qNywXSvbUWV.jpg',
                ],
            ],
            [
                'name_en' => 'SmartGuardian Watch Pro',
                'name_ka' => 'SmartGuardian საათი Pro',
                'slug' => 'smartguardian-watch-pro',
                'short_description_en' => 'Premium all-in-one safety smartwatch for kids',
                'short_description_ka' => 'პრემიუმ ყველა-ერთში უსაფრთხოების სმარ‌ტსაათი ბავშვებისთვის',
                'description_en' => 'SmartGuardian Watch Pro is the complete safety solution. Advanced AI-powered location tracking, real-time video calls, fall detection, health monitoring, and comprehensive parental app. Built with German engineering standards.',
                'description_ka' => 'SmartGuardian საათი Pro არის სრული უსაფრთხოების გადაწყვეტა. გაწინაურებული AI-ით მართული ლოკაციის თრექინგი, რეალურ დროში ვიდეო ზარები, დაცემის გამოვლენა, ჯანმრთელობის მონიტორინგი და ყოვლისმომცველი მშობლის აპლიკაცია. აშენებულია გერმანული საინჟინრო სტანდარტებით.',
                'price' => 349.00,
                'sale_price' => 299.00,
                'sim_support' => true,
                'gps_features' => true,
                'water_resistant' => 'IP68',
                'battery_life_hours' => 84,
                'warranty_months' => 36,
                'featured' => true,
                'images' => [
                    'images/products/rTv9oV8lE1s8MHXEzC1cEB4xUQCVnGfEZXTOM7NB.jpg',
                    'images/products/vR7kMA0EwiF9HQE7eGLWEKKJPNWujJj9NHOnj5uY.jpg',
                    'images/products/wdlssAS7fCPtQN87aXpKCvXPKrSYj1ludPgR3yDK.jpg',
                ],
            ],
            [
                'name_en' => 'EcoWatch Kids Edition',
                'name_ka' => 'EcoWatch ბავშვის ედიცია',
                'slug' => 'ecowatch-kids-edition',
                'short_description_en' => 'Eco-friendly GPS watch made from recycled materials',
                'short_description_ka' => 'ეკოლოგიურად სუფთა GPS საათი გადამუშავებული მასალებისგან',
                'description_en' => 'EcoWatch Kids Edition combines safety with sustainability. Made from 80% recycled materials, solar-assisted charging, GPS tracking, and two-way calling. Teach your child about technology and environment together.',
                'description_ka' => 'EcoWatch ბავშვის ედიცია აერთიანებს უსაფრთხოებას მდგრადობასთან. დამზადებულია 80% გადამუშავებული მასალებისგან, მზის დახმარებული დამუხტვა, GPS თრექინგი და ორმხრივი ზარები. ასწავლეთ თქვენს ბავშვს ტექნოლოგიასა და გარემოს შესახებ ერთად.',
                'price' => 179.00,
                'sale_price' => null,
                'sim_support' => true,
                'gps_features' => true,
                'water_resistant' => 'IP66',
                'battery_life_hours' => 120,
                'warranty_months' => 24,
                'featured' => false,
                'images' => [
                    'images/products/zticakZiSjhXr12sg6VZeNcm6YKqnx19W6Wi4qq4.jpg',
                    'images/products/56VHTm09oyKvLGNTk66BXNcfWYgyUMWEOziIJks5.webp',
                    'images/products/dXHyqP0pqhiXQLwFyDSG9FgnpBlGufxRaBoiL6yL.webp',
                ],
            ],
            [
                'name_en' => 'BubbleWatch Mini',
                'name_ka' => 'BubbleWatch მინი',
                'slug' => 'bubblewatch-mini',
                'short_description_en' => 'Fun and colorful GPS watch for preschoolers',
                'short_description_ka' => 'სახალისო და ფერადი GPS საათი სკოლამდელი ბავშვებისთვის',
                'description_en' => 'BubbleWatch Mini makes safety fun for little ones. Large SOS button, simple GPS tracking, voice messages, and cute bubble design. Available in 5 vibrant colors. Perfect first smartwatch.',
                'description_ka' => 'BubbleWatch მინი უსაფრთხოებას სახალისოს ხდის პატარებისთვის. დიდი SOS ღილაკი, მარტივი GPS თრექინგი, ხმოვანი შეტყობინებები და საყვარელი ბუშტის დიზაინი. ხელმისაწვდომია 5 ნათელ ფერში. იდეალური პირველი სმარტსაათი.',
                'price' => 99.00,
                'sale_price' => 79.00,
                'sim_support' => true,
                'gps_features' => true,
                'water_resistant' => 'IP64',
                'battery_life_hours' => 24,
                'warranty_months' => 12,
                'featured' => false,
                'images' => [
                    'images/products/r1KXVukmiO9M3VY6u1tydgG3Efb0NEpaRneHVczy.webp',
                    'images/products/tuZLYnxASEutWIGeHAnO5REKuKnp2yWZwaTy7LIp.webp',
                    'images/products/fhqgZljvsMVq50XPbv3G02dEzfVECy4NIEsG3Qyv.png',
                ],
            ],
        ];

        foreach ($products as $productData) {
            $images = $productData['images'];
            unset($productData['images']);

            $product = Product::updateOrCreate([
                'slug' => $productData['slug'],
            ], array_merge($productData, [
                'is_active' => true,
                'currency' => 'GEL',
            ]));

            $product->images()->delete();

            foreach ($images as $index => $imagePath) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'path' => $imagePath,
                    'alt_en' => $product->name_en,
                    'alt_ka' => $product->name_ka,
                    'sort_order' => $index,
                    'is_primary' => $index === 0,
                ]);
            }

            $product->variants()->delete();

            $variants = [
                ['name' => 'ფერი: შავი', 'quantity' => 12],
                ['name' => 'ფერი: ლურჯი', 'quantity' => 8],
                ['name' => 'ფერი: ვარდისფერი', 'quantity' => 5],
            ];

            if ($product->slug === 'bubblewatch-mini') {
                $variants = [
                    ['name' => 'ფერი: შავი', 'quantity' => 6],
                    ['name' => 'ფერი: ლურჯი', 'quantity' => 0],
                    ['name' => 'ფერი: ვარდისფერი', 'quantity' => 4],
                ];
            }

            foreach ($variants as $variant) {
                ProductVariant::create([
                    'product_id' => $product->id,
                    'name' => $variant['name'],
                    'quantity' => $variant['quantity'],
                    'low_stock_threshold' => 3,
                ]);
            }
        }

        $this->command->info('Created ' . count($products) . ' products with images.');
    }
}
