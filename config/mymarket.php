<?php

return [
    'listing_defaults' => [
        'category_path_ka' => [
            'ტექნიკა',
            'მობილურები და აქსესუარები',
            'სმარტ საათი',
        ],
        'condition' => 'ახალი',
        'language' => 'ka',
        'one_listing_per_model' => true,
        'combine_colors_in_one_listing' => true,
        'include_only_in_stock_variants' => true,
        'online_sales_enabled' => false,
        'installment_enabled' => false,
        'paid_promotion_enabled' => false,
    ],

    'attribute_defaults' => [
        'memory_card_if_mandatory' => 'არა',
    ],

    'presets' => [
        'initial-5-models' => [
            'batch_id' => 'mymarket-initial-5-models',
            'models' => [
                [
                    'sequence' => 1,
                    'model_code' => 'Q19',
                    'slug' => '2g-network-kids-smart-watch-anti-lost-sos-gps-video-call',
                    'positioning_angle_ka' => 'ბიუჯეტური 2G საბავშვო საათი კამერით, SOS-ით და ძირითადი უსაფრთხოების ფუნქციებით',
                    'discount_expected' => true,
                    'discount_target_price_gel' => 59,
                    'camera_if_mandatory' => '3.2MP',
                    'must_emphasize_ka' => ['ფასდაკლება', 'SOS', 'GPS/LBS უსაფრთხოება', 'კამერა'],
                    'must_avoid_ka' => ['ზედმეტი ბრენდული დაპირებები', 'გამოუდასტურებელი აქსესუარები'],
                ],
                [
                    'sequence' => 2,
                    'model_code' => 'X01',
                    'slug' => 'kids-smartwatches-camera-video-phone-call-sos-wifi-lbs-locator-alarm-clock-voice-childrens-boys-girls-smart-watch',
                    'positioning_angle_ka' => 'ხელმისაწვდომი 4G ვიდეოზარიანი მოდელი ყოველდღიური გამოყენებისთვის',
                    'discount_expected' => false,
                    'camera_if_mandatory' => '2MP',
                    'must_emphasize_ka' => ['4G', 'ვიდეო ზარი', 'GPS + WiFi + LBS', 'SOS'],
                    'must_avoid_ka' => ['თეთრი ფერის ჩართვა stock=0 შემთხვევაში'],
                ],
                [
                    'sequence' => 3,
                    'model_code' => 'CT23',
                    'slug' => 'wonlex-cheaper-kids-gps-smart-watch-4g-ct23',
                    'positioning_angle_ka' => 'ხელმისაწვდომი 4G Wonlex მოდელი მშობლისთვის მნიშვნელოვანი ძირითადი ფუნქციებით',
                    'discount_expected' => false,
                    'camera_if_mandatory' => '2MP',
                    'user_note_possible_live_discount' => 'Older user note said 179 → 149; verify live before publish.',
                    'must_emphasize_ka' => ['Wonlex', '4G', 'GPS', 'ვიდეო ზარი', 'SOS'],
                    'must_avoid_ka' => ['ფასდაკლების მითითება live დადასტურების გარეშე', 'stock 25-ის გამეორება live დადასტურების გარეშე'],
                ],
                [
                    'sequence' => 4,
                    'model_code' => 'T53',
                    'slug' => 'yqt-4g-sos-smart-watch-for-kids-video-call-1-83-camera-oem-factory-gps-smartwatch-for-children',
                    'positioning_angle_ka' => 'სასაჩუქრე 4G bundle დამატებითი კომპლექტაციით',
                    'discount_expected' => false,
                    'camera_if_mandatory' => '2MP',
                    'must_emphasize_ka' => ['სასაჩუქრე ბოქსი', '2 ცვლადი სამაჯური', 'დეკორატიული აქსესუარი', 'ვიდეო ზარი', 'GPS'],
                    'must_avoid_ka' => ['ჩვეულებრივ საათად წარმოდგენა gift-angle-ის გარეშე'],
                ],
                [
                    'sequence' => 5,
                    'model_code' => 'KT34',
                    'slug' => 'newest-style-4g-smart-watch-sos-call-wifi-lbs-gps-tracker-android-smart-watch-kid-sos-camera-alarm-clock-kt34',
                    'positioning_angle_ka' => 'Android სისტემაზე მომუშავე უფრო ძლიერი მოდელი უფროსი ასაკის ბავშვისთვის',
                    'discount_expected' => false,
                    'camera_if_mandatory' => '2MP',
                    'must_emphasize_ka' => ['Android სისტემა', 'GPS + WiFi + LBS', 'ვიდეო და ხმოვანი ზარი', 'გულისცემის მონიტორი', 'ძილის მონიტორინგი'],
                    'must_avoid_ka' => ['შავი ფერის ჩართვა stock=0 შემთხვევაში', 'მხოლოდ საბაზისო მოდელად წარმოდგენა'],
                ],
            ],
        ],
    ],
];
