<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiKnowledgeController extends Controller
{
    /**
     * Get knowledge base content for AI
     */
    public function index(Request $request): JsonResponse
    {
        $locale = $request->get('lang', 'ka');
        $topic = $request->get('topic', 'all');

        app()->setLocale($locale);

        $knowledge = $this->getKnowledgeBase($topic, $locale);

        return response()->json([
            'source' => 'MyTechnic.ge',
            'language' => $locale,
            'topic' => $topic,
            'updated_at' => now()->toIso8601String(),
            'knowledge' => $knowledge,
        ]);
    }

    /**
     * Get knowledge base content
     */
    private function getKnowledgeBase(string $topic, string $locale): array
    {
        $knowledge = [];

        if ($topic === 'all' || $topic === 'faq') {
            $knowledge['faq'] = $this->getFaqContent($locale);
        }

        if ($topic === 'all' || $topic === 'features') {
            $knowledge['features'] = $this->getFeaturesGuide($locale);
        }

        if ($topic === 'all' || $topic === 'buying_guide') {
            $knowledge['buying_guide'] = $this->getBuyingGuide($locale);
        }

        if ($topic === 'all' || $topic === 'setup') {
            $knowledge['setup'] = $this->getSetupGuide($locale);
        }

        if ($topic === 'all' || $topic === 'comparison') {
            $knowledge['comparison'] = $this->getComparisonGuide($locale);
        }

        if ($topic === 'all' || $topic === 'company') {
            $knowledge['company'] = $this->getCompanyProfile($locale);
        }

        if ($topic === 'all' || $topic === 'contact') {
            $knowledge['contact'] = $this->getContactInfo();
        }

        return $knowledge;
    }

    /**
     * Get FAQ content
     */
    private function getFaqContent(string $locale): array
    {
        return [
            [
                'question' => $locale === 'ka' ? 'რა არის სმარტ საათი?' : 'What is a smart watch?',
                'answer' => $locale === 'ka' 
                    ? 'სმარტ საათი არის ელექტრონული მოწყობილობა რომელიც ატარებენ მაჯაზე და აქვს სხვადასხვა ფუნქციები როგორიცაა GPS ტრეკინგი, ზარები, მესიჯები და სხვა.'
                    : 'A smart watch is an electronic device worn on the wrist with various functions like GPS tracking, calls, messages, and more.',
            ],
            [
                'question' => $locale === 'ka' ? 'რატომ სჭირდება ბავშვს სმარტ საათი?' : 'Why does a child need a smart watch?',
                'answer' => $locale === 'ka'
                    ? 'ბავშვის სმარტ საათი უზრუნველყოფს უსაფრთხოებას GPS ტრეკინგით, საშუალებას აძლევს მშობლებს დაუკავშირდნენ ბავშვს და აკონტროლონ მისი ადგილმდებარეობა.'
                    : 'A kids smart watch provides safety with GPS tracking, allows parents to contact the child and monitor their location.',
            ],
            [
                'question' => $locale === 'ka' ? 'რა არის SIM ბარათიანი საათი?' : 'What is a SIM card watch?',
                'answer' => $locale === 'ka'
                    ? 'SIM ბარათიანი საათი საშუალებას აძლევს ბავშვს დარეკოს და მიიღოს ზარები, გაგზავნოს მესიჯები და გამოიყენოს მობილური ინტერნეტი.'
                    : 'A SIM card watch allows the child to make and receive calls, send messages and use mobile internet.',
            ],
            [
                'question' => $locale === 'ka' ? 'როგორ მუშაობს GPS ტრეკინგი?' : 'How does GPS tracking work?',
                'answer' => $locale === 'ka'
                    ? 'GPS ტრეკინგი საშუალებას აძლევს მშობლებს რეალურ დროში იხილონ ბავშვის ადგილმდებარეობა სპეციალურ აპლიკაციაში.'
                    : 'GPS tracking allows parents to see the child\'s location in real-time through a special app.',
            ],
            [
                'question' => $locale === 'ka' ? 'რა ასაკისთვის არის შესაფერისი?' : 'What age is it suitable for?',
                'answer' => $locale === 'ka'
                    ? 'ჩვენი სმარტ საათები შესაფერისია 4-12 წლის ბავშვებისთვის, სხვადასხვა მოდელები სხვადასხვა ასაკობრივი ჯგუფებისთვის.'
                    : 'Our smart watches are suitable for children aged 4-12, with different models for different age groups.',
            ],
        ];
    }

    /**
     * Get features guide
     */
    private function getFeaturesGuide(string $locale): array
    {
        return [
            'gps_tracking' => [
                'name' => $locale === 'ka' ? 'GPS ტრეკინგი' : 'GPS Tracking',
                'description' => $locale === 'ka'
                    ? 'რეალურ დროში ბავშვის ადგილმდებარეობის თვალყურის დევნება'
                    : 'Real-time child location monitoring',
                'benefits' => $locale === 'ka'
                    ? ['უსაფრთხოება', 'მშობლის სიმშვიდე', 'დაკარგვის პრევენცია']
                    : ['Safety', 'Parent peace of mind', 'Loss prevention'],
            ],
            'sim_support' => [
                'name' => $locale === 'ka' ? 'SIM ბარათის მხარდაჭერა' : 'SIM Card Support',
                'description' => $locale === 'ka'
                    ? 'ზარები, მესიჯები და მობილური ინტერნეტი'
                    : 'Calls, messages and mobile internet',
                'benefits' => $locale === 'ka'
                    ? ['კომუნიკაცია', 'დამოუკიდებლობა', 'საგანგებო სიტუაციებში დახმარება']
                    : ['Communication', 'Independence', 'Emergency assistance'],
            ],
            'video_calls' => [
                'name' => $locale === 'ka' ? 'ვიდეო ზარები' : 'Video Calls',
                'description' => $locale === 'ka'
                    ? 'ვიზუალური კომუნიკაცია მშობლებთან'
                    : 'Visual communication with parents',
                'benefits' => $locale === 'ka'
                    ? ['პირისპირ კომუნიკაცია', 'ემოციური კავშირი', 'ვიზუალური კონტროლი']
                    : ['Face-to-face communication', 'Emotional connection', 'Visual control'],
            ],
            'waterproof' => [
                'name' => $locale === 'ka' ? 'წყალგამძლეობა' : 'Waterproof',
                'description' => $locale === 'ka'
                    ? 'დაცვა წყლისგან და ტენისგან'
                    : 'Protection from water and moisture',
                'benefits' => $locale === 'ka'
                    ? ['გამძლეობა', 'ხანგრძლივობა', 'ყოველდღიური გამოყენება']
                    : ['Durability', 'Longevity', 'Everyday use'],
            ],
        ];
    }

    /**
     * Get buying guide
     */
    private function getBuyingGuide(string $locale): array
    {
        return [
            'by_age' => [
                '4-6_years' => [
                    'title' => $locale === 'ka' ? '4-6 წელი' : '4-6 years',
                    'recommendations' => $locale === 'ka'
                        ? 'მარტივი ინტერფეისი, ძირითადი ფუნქციები, გამძლე კორპუსი'
                        : 'Simple interface, basic functions, durable body',
                    'price_range' => '149₾ - 249₾',
                ],
                '7-9_years' => [
                    'title' => $locale === 'ka' ? '7-9 წელი' : '7-9 years',
                    'recommendations' => $locale === 'ka'
                        ? 'GPS, SIM მხარდაჭერა, თამაშები, სასწავლო ფუნქციები'
                        : 'GPS, SIM support, games, educational features',
                    'price_range' => '199₾ - 349₾',
                ],
                '10-12_years' => [
                    'title' => $locale === 'ka' ? '10-12 წელი' : '10-12 years',
                    'recommendations' => $locale === 'ka'
                        ? '4G LTE, მოწინავე ფუნქციები, სტილური დიზაინი'
                        : '4G LTE, advanced features, stylish design',
                    'price_range' => '249₾ - 499₾',
                ],
            ],
            'by_budget' => [
                'budget' => [
                    'range' => 'up to 200₾',
                    'description' => $locale === 'ka'
                        ? 'საწყისი დონის GPS საათები'
                        : 'Entry-level GPS watches',
                ],
                'mid_range' => [
                    'range' => '200₾ - 350₾',
                    'description' => $locale === 'ka'
                        ? 'სრული ფუნქციონალის SIM საათები'
                        : 'Full-featured SIM watches',
                ],
                'premium' => [
                    'range' => '350₾+',
                    'description' => $locale === 'ka'
                        ? 'მოწინავე 4G LTE მოდელები'
                        : 'Advanced 4G LTE models',
                ],
            ],
        ];
    }

    /**
     * Get setup guide
     */
    private function getSetupGuide(string $locale): array
    {
        return [
            'steps' => [
                [
                    'step' => 1,
                    'title' => $locale === 'ka' ? 'SIM ბარათის ჩადება' : 'Insert SIM card',
                    'description' => $locale === 'ka'
                        ? 'ჩადეთ აქტიური SIM ბარათი საათში'
                        : 'Insert an active SIM card into the watch',
                ],
                [
                    'step' => 2,
                    'title' => $locale === 'ka' ? 'აპლიკაციის ჩამოტვირთვა' : 'Download app',
                    'description' => $locale === 'ka'
                        ? 'ჩამოტვირთეთ შესაბამისი აპლიკაცია სმარტფონზე'
                        : 'Download the corresponding app on your smartphone',
                ],
                [
                    'step' => 3,
                    'title' => $locale === 'ka' ? 'დაწყვილება' : 'Pairing',
                    'description' => $locale === 'ka'
                        ? 'დაასკანერეთ QR კოდი და დაწყვილეთ საათი'
                        : 'Scan the QR code and pair the watch',
                ],
                [
                    'step' => 4,
                    'title' => $locale === 'ka' ? 'პარამეტრების კონფიგურაცია' : 'Configure settings',
                    'description' => $locale === 'ka'
                        ? 'დააყენეთ კონტაქტები, უსაფრთხო ზონები და სხვა'
                        : 'Set up contacts, safe zones and more',
                ],
            ],
        ];
    }

    /**
     * Get comparison guide
     */
    private function getComparisonGuide(string $locale): array
    {
        return [
            'features_comparison' => [
                'gps_vs_gps_sim' => [
                    'title' => $locale === 'ka' ? 'GPS vs GPS+SIM' : 'GPS vs GPS+SIM',
                    'gps_only' => $locale === 'ka'
                        ? 'მხოლოდ ადგილმდებარეობის ტრეკინგი, უნდა იყოს სმარტფონთან ახლოს'
                        : 'Location tracking only, must be near smartphone',
                    'gps_sim' => $locale === 'ka'
                        ? 'დამოუკიდებელი კომუნიკაცია, ზარები, მესიჯები, ინტერნეტი'
                        : 'Independent communication, calls, messages, internet',
                ],
                'price_difference' => [
                    'gps_only' => '149₾ - 199₾',
                    'gps_sim' => '199₾ - 499₾',
                    'recommendation' => $locale === 'ka'
                        ? 'SIM მხარდაჭერა რეკომენდებულია 6+ წლის ბავშვებისთვის'
                        : 'SIM support recommended for children 6+ years',
                ],
            ],
        ];
    }

    /**
     * Get company profile content
     */
    private function getCompanyProfile(string $locale): array
    {
        $privacySummary = $locale === 'ka'
            ? 'ვაგროვებთ მხოლოდ საჭირო მონაცემებს შეკვეთებისთვის, მიწოდებისთვის, მხარდაჭერისთვის და საიტის გაუმჯობესებისთვის.'
            : 'We collect only the data needed for orders, delivery, support, and site improvement.';

        $termsSummary = $locale === 'ka'
            ? 'ვმუშაობთ საქართველოს კანონმდებლობის მიხედვით, ვთავაზობთ უფასო მიწოდებას და ვფარავთ 14-დღიან დაბრუნების წესს.'
            : 'We operate under Georgian law, offer free delivery, and follow a 14-day return policy.';

        return [
            'brand' => 'MyTechnic',
            'about' => [
                'summary' => __('ui.about_intro'),
                'mission' => __('ui.about_mission_body'),
                'highlights' => [
                    __('ui.trust_shipping') . ' - ' . __('ui.trust_shipping_text'),
                    __('ui.trust_warranty') . ' - ' . __('ui.trust_warranty_text'),
                    __('ui.trust_support') . ' - ' . __('ui.trust_support_text'),
                ],
            ],
            'operations' => [
                'delivery' => $locale === 'ka' ? 'უფასო მიწოდება მთელი საქართველოს მასშტაბით' : 'Free delivery across Georgia',
                'warranty' => $locale === 'ka'
                    ? '2G მოდელები - 3 თვე, 4G მოდელები - 6 თვე'
                    : '2G models - 3 months, 4G models - 6 months',
                'support_channels' => $locale === 'ka'
                    ? ['ტელეფონი', 'WhatsApp', 'Messenger']
                    : ['Phone', 'WhatsApp', 'Messenger'],
            ],
            'privacy' => [
                'summary' => $privacySummary,
                'key_points' => $locale === 'ka'
                    ? [
                        'ვაგროვებთ სახელს, ტელეფონს, ელფოსტას, მისამართს, შეკვეთის დეტალებს და ჩატბოტის/ვებსაიტის აქტივობას.',
                        'ვიყენებთ მონაცემებს შეკვეთების დამუშავებისთვის, მიწოდებისთვის, კომუნიკაციისთვის, გაუმჯობესებისთვის და თაღლითობის პრევენციისთვის.',
                        'ჩატბოტის საუბრები შეიძლება გამოყენდეს მხარდაჭერის გასაუმჯობესებლად; AI პასუხები ავტომატურია და არ არის იურიდიული ან სამედიცინო რჩევა.',
                        'ვიცავთ მონაცემებს SSL/TLS-ით, უსაფრთხო სერვერებით, წვდომის კონტროლით და რეგულარული მონიტორინგით.',
                    ]
                    : [
                        'We collect name, phone, email, address, order details, and site/chatbot activity.',
                        'We use data for order processing, delivery, communication, improvement, and fraud prevention.',
                        'Chatbot conversations may be used to improve support; AI replies are automatic and not legal or medical advice.',
                        'We protect data with SSL/TLS, secure servers, access control, and regular monitoring.',
                    ],
            ],
            'terms' => [
                'summary' => $termsSummary,
                'key_points' => $locale === 'ka'
                    ? [
                        'ვებსაიტის მასალები განკუთვნილია მხოლოდ პირადი, არაკომერციული გამოყენებისთვის.',
                        'გადახდა შესაძლებელია საქართველოს ბანკის ონლაინ სისტემით ან კურიერთან ნაღდი ანგარიშსწორებით თბილისში.',
                        'გარანტია: 2G - 3 თვე, 4G - 6 თვე; არ ფარავს მექანიკურ დაზიანებას, წყალში გამოყენებას ან არაავტორიზებულ შეკეთებას.',
                        'დაბრუნება/გაცვლა შესაძლებელია 14 კალენდარული დღის განმავლობაში, თუ პროდუქტი არ არის გამოყენებული და აქვს ორიგინალური შეფუთვა.',
                    ]
                    : [
                        'Site materials are for personal, non-commercial use only.',
                        'Payment is available via BOG online or cash on delivery in Tbilisi.',
                        'Warranty: 2G - 3 months, 4G - 6 months; excludes mechanical damage, water use, and unauthorized repairs.',
                        'Returns/exchanges are available within 14 calendar days if unused and in original packaging.',
                    ],
            ],
        ];
    }

    /**
     * Get current contact info
     */
    private function getContactInfo(): array
    {
        $settings = ContactSetting::allKeyed();

        return [
            'phone' => $settings['phone_display'] ?? null,
            'whatsapp_url' => $settings['whatsapp_url'] ?? null,
            'email' => $settings['email'] ?? null,
            'location' => $settings['location'] ?? null,
            'hours' => $settings['hours'] ?? null,
            'social' => array_filter([
                'instagram' => $settings['instagram_url'] ?? null,
                'facebook' => $settings['facebook_url'] ?? null,
                'messenger' => $settings['messenger_url'] ?? null,
            ]),
        ];
    }
}
