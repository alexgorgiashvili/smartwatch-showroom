<?php

return [
    'enabled' => env('GIFT_BUILDER_ENABLED', false),
    'public_enabled' => env('GIFT_BUILDER_PUBLIC_ENABLED'),
    'preview_key' => env('GIFT_BUILDER_PREVIEW_KEY'),
    'max_items' => 4,
    'message_max_length' => 300,

    'recipients' => [
        'child_5_7' => [
            'label_ka' => 'ბავშვი 5-7',
            'label_en' => 'Child 5-7',
        ],
        'child_8_12' => [
            'label_ka' => 'ბავშვი 8-12',
            'label_en' => 'Child 8-12',
        ],
        'teen' => [
            'label_ka' => 'თინეიჯერი',
            'label_en' => 'Teen',
        ],
        'other' => [
            'label_ka' => 'სხვა',
            'label_en' => 'Other',
        ],
    ],

    'occasions' => [
        'birthday' => [
            'label_ka' => 'დაბადების დღე',
            'label_en' => 'Birthday',
        ],
        'new_year' => [
            'label_ka' => 'ახალი წელი',
            'label_en' => 'New Year',
        ],
        'school' => [
            'label_ka' => 'სკოლისთვის',
            'label_en' => 'School',
        ],
        'just_because' => [
            'label_ka' => 'უბრალოდ საჩუქრად',
            'label_en' => 'Just because',
        ],
    ],

    'budget_bands' => [
        'under_50' => [
            'label_ka' => '50₾-მდე',
            'label_en' => 'Up to 50₾',
            'min' => null,
            'max' => 50,
        ],
        'under_100' => [
            'label_ka' => '100₾-მდე',
            'label_en' => 'Up to 100₾',
            'min' => null,
            'max' => 100,
        ],
        'under_250' => [
            'label_ka' => '250₾-მდე',
            'label_en' => 'Up to 250₾',
            'min' => null,
            'max' => 250,
        ],
    ],

    'packaging' => [
        'standard' => [
            'label_ka' => 'სტანდარტული სასაჩუქრე ყუთი',
            'label_en' => 'Standard gift box',
            'price' => 0,
            'capacity_units' => 4,
        ],
        'premium' => [
            'label_ka' => 'პრემიუმ ყუთი',
            'label_en' => 'Premium box',
            'price' => 12,
            'capacity_units' => 6,
        ],
    ],

    'discount' => [
        'type' => 'fixed',
        'amount' => 0,
        'min_items' => 2,
    ],

    'presets' => [
        'safe_start' => [
            'label_ka' => 'Safe Start Box',
            'label_en' => 'Safe Start Box',
            'description_ka' => 'სმარტ საათი, სტანდარტული ყუთი და მისალოცი ბარათი.',
            'description_en' => 'A smartwatch, standard box, and greeting card.',
            'recipient_type' => 'child_5_7',
            'occasion' => 'just_because',
            'budget_band' => 'under_100',
        ],
        'birthday_ready' => [
            'label_ka' => 'Birthday Ready Box',
            'label_en' => 'Birthday Ready Box',
            'description_ka' => 'დაბადების დღისთვის გამზადებული საჩუქარი.',
            'description_en' => 'A birthday-ready gift setup.',
            'recipient_type' => 'child_8_12',
            'occasion' => 'birthday',
            'budget_band' => 'under_250',
        ],
        'premium_care' => [
            'label_ka' => 'Premium Care Box',
            'label_en' => 'Premium Care Box',
            'description_ka' => 'უფრო სრულყოფილი საჩუქარი პრემიუმ შეფუთვით.',
            'description_en' => 'A more complete gift with premium packaging.',
            'recipient_type' => 'teen',
            'occasion' => 'new_year',
            'budget_band' => 'under_250',
            'packaging_slug' => 'premium',
        ],
    ],

    'ready_boxes' => [
        'smart_start' => [
            'label_ka' => 'Smart Start ყუთი',
            'label_en' => 'Smart Start Box',
            'description_ka' => 'საბავშვო სმარტ საათი და კომპაქტური უსადენო დინამიკი.',
            'description_en' => 'A kids smartwatch paired with a compact wireless speaker.',
            'main_product' => '2g-smart-watch-children-android-sos-lbs-tracking',
            'addon_products' => ['test-a9-mini-wireless-speaker'],
            'budget_band' => 'under_100',
            'packaging_slug' => 'standard',
        ],
        'camera_fun' => [
            'label_ka' => 'Camera Fun ყუთი',
            'label_en' => 'Camera Fun Box',
            'description_ka' => 'სმარტ საათი და საბავშვო მინი კამერა მხიარული საჩუქრისთვის.',
            'description_en' => 'A smartwatch and a kids mini camera for a playful gift.',
            'main_product' => 'q21-children-smartwatch',
            'addon_products' => ['test-kids-x2-mini-camera'],
            'budget_band' => 'under_100',
            'packaging_slug' => 'standard',
        ],
        'premium_music' => [
            'label_ka' => 'Premium Music ყუთი',
            'label_en' => 'Premium Music Box',
            'description_ka' => '4G სმარტ საათი, კატის ყურებიანი ყურსასმენი და პრემიუმ შეფუთვა.',
            'description_en' => 'A 4G smartwatch, cat-ear headphones, and premium packaging.',
            'main_product' => 'ct27-ultra-small-4g-rtos-gps-watch',
            'addon_products' => ['test-p47-cat-ear-headphones'],
            'budget_band' => 'under_250',
            'packaging_slug' => 'premium',
        ],
    ],
];
