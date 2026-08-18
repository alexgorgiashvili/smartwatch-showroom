<?php

/*
|--------------------------------------------------------------------------
| One-time Gift Box V1 import map
|--------------------------------------------------------------------------
|
| This file is read only by gift-boxes:backfill and gift-boxes:audit. The
| storefront never uses it; ready_gift_boxes is the runtime source of truth.
|
*/

return [
    'smart_start' => [
        'title_ka' => 'Smart Start ყუთი',
        'title_en' => 'Smart Start Box',
        'description_ka' => 'საბავშვო სმარტ საათი და კომპაქტური უსადენო დინამიკი.',
        'description_en' => 'A kids smartwatch paired with a compact wireless speaker.',
        'main_product' => '2g-smart-watch-children-android-sos-lbs-tracking',
        'addon_products' => ['test-a9-mini-wireless-speaker'],
        'packaging_slug' => 'standard',
        'theme_key' => 'grape',
        'sort_order' => 10,
    ],
    'camera_fun' => [
        'title_ka' => 'Camera Fun ყუთი',
        'title_en' => 'Camera Fun Box',
        'description_ka' => 'სმარტ საათი და საბავშვო მინი კამერა მხიარული საჩუქრისთვის.',
        'description_en' => 'A smartwatch and a kids mini camera for a playful gift.',
        'main_product' => 'q21-children-smartwatch',
        'addon_products' => ['test-kids-x2-mini-camera'],
        'packaging_slug' => 'standard',
        'theme_key' => 'coral',
        'sort_order' => 20,
    ],
    'premium_music' => [
        'title_ka' => 'Premium Music ყუთი',
        'title_en' => 'Premium Music Box',
        'description_ka' => '4G სმარტ საათი, კატის ყურებიანი ყურსასმენი და პრემიუმ შეფუთვა.',
        'description_en' => 'A 4G smartwatch, cat-ear headphones, and premium packaging.',
        'main_product' => 'ct27-ultra-small-4g-rtos-gps-watch',
        'addon_products' => ['test-p47-cat-ear-headphones'],
        'packaging_slug' => 'premium',
        'theme_key' => 'grape',
        'sort_order' => 30,
    ],
];
