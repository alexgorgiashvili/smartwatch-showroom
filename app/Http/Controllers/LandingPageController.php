<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\View\View;

class LandingPageController extends Controller
{
    /**
     * Age-targeted landing pages.
     * $config keys: title_ka, title_en, desc_ka, desc_en, meta_ka, meta_en,
     *               keyword_ka, keyword_en, slug, products_filter
     */
    public function age(string $range): View
    {
        $configs = [
            '4-6' => [
                'title_ka'    => 'სმარტ საათი 4-6 წლის ბავშვისთვის',
                'title_en'    => 'Smartwatch for 4-6 Year Old Kids',
                'intro_ka'    => '4-დან 6 წლამდე ასაკის ბავშვისთვის საუკეთესო SIM-იანი სმარტ საათი. მშობელი ყოველთვის კავშირში, ბავშვი — თავისუფლად.',
                'intro_en'    => 'The best SIM smartwatch for children aged 4-6. Stay connected without giving your child a phone.',
                'meta_ka'     => 'სმარტ საათი 4-6 წლის ბავშვისთვის — MyTechnic. SIM, GPS ტრეკინგი, SOS ღილაკი. უფასო მიტანა საქართველოში.',
                'meta_en'     => 'Smartwatch for 4-6 year old kids — MyTechnic. SIM card, GPS tracking, SOS button. Free delivery in Georgia.',
                'bullet_ka'   => ['SIM ბარათი — ზარები & შეტყობინებები', 'GPS + უსაფრთხო ზონები', 'SOS ღილაკი — სასწრაფო კავშირი', 'მყარი, მსუბუქი, ფერადი დიზაინი'],
                'bullet_en'   => ['SIM card — calls & messages', 'GPS + safe zones', 'SOS button — emergency contact', 'Durable, lightweight, colorful design'],
                'schema_name' => 'Smartwatch for Kids 4-6',
                'q1_ka' => 'შეიძლება 4 წლის ბავშვს SIM-იანი საათი ჰქონდეს?',
                'a1_ka' => 'კი — ეს ყველაზე მარტივი გზაა ბავშვის კავშირში შესანარჩუნებლად. ყველა MyTechnic მოდელი ადვილია გამოსაყენებლად, ნათელი ღილაკებითა და დიდი ეკრანით.',
                'q2_ka' => 'რომელი სიმ ჩადო?',
                'a2_ka' => 'საქართველოში — Magti, Silknet ან Beeline. ვიყენებთ ნანო-SIM ბარათებს. დეტალური ინსტრუქცია ყოველ შეძენასთან ერთად.',
            ],
            '7-10' => [
                'title_ka'    => 'სმარტ საათი 7-10 წლის ბავშვისთვის (სკოლის ასაკი)',
                'title_en'    => 'Smartwatch for 7-10 Year Old Kids (School Age)',
                'intro_ka'    => '7-10 წლის სკოლის ასაკის ბავშვისთვის — SIM-იანი სმარტ საათი, რომელიც სკოლის რეჟიმს მხარს უჭერს. GPS, ზარები, WhatsApp.',
                'intro_en'    => 'For school-age kids 7-10 — a SIM smartwatch with school mode support. GPS, calls, WhatsApp.',
                'meta_ka'     => 'სმარტ საათი 7-10 წლის ბავშვისთვის — MyTechnic. SIM, GPS, სკოლის რეჟიმი. ოფ. იმპორტიორი საქართველოში.',
                'meta_en'     => 'Smartwatch for 7-10 year old kids — MyTechnic. SIM, GPS, school mode. Official importer in Georgia.',
                'bullet_ka'   => ['სკოლის რეჟიმი — გაკვეთილების დროს ეკრანი ჩაიკეტება', 'GPS — იცი სად არის ბავშვი', '4G LTE + WhatsApp + ზარები', 'IP67 წყლისგან დაცვა'],
                'bullet_en'   => ['School mode — screen locks during lessons', 'GPS — always know where your child is', '4G LTE + WhatsApp + calls', 'IP67 water resistance'],
                'schema_name' => 'Smartwatch for Kids 7-10',
                'q1_ka' => 'სკოლის რეჟიმი რა არის?',
                'a1_ka' => 'სკოლის რეჟიმი ხურავს ეკრანს გაკვეთილების დასახელებულ საათებში. მშობელი თვითონ ადგენს გრაფიკს. ბავშვი ვერ გახსნის სოც. ქსელებს ან სხვა აპებს.',
                'q2_ka' => 'WhatsApp მუშაობს საათზე?',
                'a2_ka' => 'WhatsApp მუშაობს Android-ის საათებზე. iOS-ის ზოგიერთ მოდელზე მხოლოდ ჩამონტაჟებულ messenger-ს ვიყენებთ. პროდუქტის გვერდზე ზუსტი ინფო მოხვდება.',
            ],
            '11-14' => [
                'title_ka'    => 'სმარტ საათი 11-14 წლის მოზარდისთვის',
                'title_en'    => 'Smartwatch for 11-14 Year Old Teens',
                'intro_ka'    => '11-14 წლის მოზარდისთვის — სტილური, 4G LTE სმარტ საათი. GPS, ზარები, სოც. ქსელები — ტელეფონის გარეშე.',
                'intro_en'    => 'For teens 11-14 — stylish 4G LTE smartwatch. GPS, calls, social apps — without a full phone.',
                'meta_ka'     => 'სმარტ საათი 11-14 წლის მოზარდისთვის — MyTechnic. 4G LTE, GPS, სტილური. ყიდვა საქართველოში.',
                'meta_en'     => 'Smartwatch for teens 11-14 — MyTechnic. 4G LTE, GPS, stylish. Buy in Georgia.',
                'bullet_ka'   => ['4G LTE — სრული ინტერნეტი მაჯაზე', 'GPS — მშობელი ყოველთვის ინფორმირებული', 'SIM ბარათი — ცალკე ნომერი', 'სტილური, მოდური დიზაინი'],
                'bullet_en'   => ['4G LTE — full internet on the wrist', 'GPS — parents always informed', 'SIM card — separate number', 'Stylish, trendy design'],
                'schema_name' => 'Smartwatch for Teens 11-14',
                'q1_ka' => 'უფრო კარგია ტელეფონი თუ SIM-იანი საათი მოზარდისთვის?',
                'a1_ka' => 'SIM-იანი საათი კომპრომისია — ბავშვს აქვს კავშირი (ზარები, GPS), მაგრამ ნაკლები distraction სოც. ქსელებისგან. ბევრი მშობელი ირჩევს საათს 11-13 წლამდე.',
                'q2_ka' => 'ტელეფონის ნომერი სჭირდება?',
                'a2_ka' => 'კი — ნანო-SIM ბარათი. Magti, Silknet ან Beeline ბარათი მუშაობს. ჩვენ ვეხმარებით setup-ში.',
            ],
        ];

        if (! isset($configs[$range])) {
            abort(404);
        }

        $config = $configs[$range];

        $products = Product::active()
            ->where('sim_support', true)
            ->with(['primaryImage', 'variants'])
            ->orderByDesc('featured')
            ->orderByRaw('COALESCE(sale_price, price) ASC')
            ->get();

        return view('landing.age', compact('config', 'products', 'range'));
    }

    /**
     * SIM card compatibility guide.
     */
    public function simGuide(): View
    {
        return view('landing.sim-guide');
    }

    /**
     * Gift guide page — products sorted by price tier.
     */
    public function giftGuide(): View
    {
        $products = Product::active()
            ->where('sim_support', true)
            ->with(['primaryImage', 'variants'])
            ->orderByRaw('COALESCE(sale_price, price) ASC')
            ->get();

        $budget    = $products->filter(fn ($p) => ($p->sale_price ?? $p->price) <= 150);
        $mid       = $products->filter(fn ($p) => ($p->sale_price ?? $p->price) > 150 && ($p->sale_price ?? $p->price) <= 250);
        $premium   = $products->filter(fn ($p) => ($p->sale_price ?? $p->price) > 250);

        return view('landing.gift-guide', compact('budget', 'mid', 'premium'));
    }
}
