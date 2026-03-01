<?php

namespace Database\Seeders;

use App\Models\Article;
use Illuminate\Database\Seeder;

class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        $articles = [

            /* ─── 1. Buying Guide ─── */
            [
                'slug'               => 'rogori-saati-virchiot-bavshvistvis',
                'title_ka'           => 'რომელი სმარტ საათი ავირჩიოთ ბავშვისთვის — 2026 სრული სახელმძღვანელო',
                'title_en'           => 'Which Smartwatch to Choose for Your Child — Complete 2026 Guide',
                'excerpt_ka'         => 'GPS ტრეკინგი, SIM ბარათი, კამერა, ბატარეის ხანგრძლივობა, წყალგამძლეობა — ყველაფერი, რაც უნდა იცოდეთ ბავშვის სმარტ საათის არჩევის წინ. ასაკობრივი რჩევები და მოდელების შედარება.',
                'excerpt_en'         => 'GPS tracking, SIM card, camera, battery life, water resistance — everything you need to know before buying a kids smartwatch. Age-based tips and model comparison.',
                'body_ka'            => '<h2>რატომ სჭირდება ბავშვს სმარტ საათი?</h2><p>2026 წელს ბავშვის სმარტ საათი უბრალო აქსესუარი კი არა, <strong>უსაფრთხოების მოწყობილობაა</strong>. GPS ტრეკერი მშობელს საშუალებას აძლევს რეალურ დროში აკონტროლოს ბავშვის მდებარეობა, ხოლო SOS ღილაკი საგანგებო სიტუაციაში ერთი დაჭერით უკავშირდება მშობელს.</p><p>ტელეფონისგან განსხვავებით, სმარტ საათი <strong>არ აქვს ინტერნეტ ბრაუზერი და სოციალური ქსელები</strong> — ბავშვი იყენებს მხოლოდ ზარს, SOS-ს და ხმოვან შეტყობინებას. ეს მშობელს სიმშვიდეს ანიჭებს.</p><h2>მთავარი კრიტერიუმები არჩევისას</h2><h3>1. GPS ტრეკინგი</h3><p>ყველა ხარისხიანი ბავშვის საათი იყენებს <strong>GPS + LBS + Wi-Fi</strong> სამეულს ზუსტი ადგილმდებარეობისთვის. GPS სატელიტური სიგნალი ღია ცის ქვეშ 5-15 მეტრი სიზუსტით მუშაობს, LBS შენობებში ავსებს GPS-ს, ხოლო Wi-Fi კიდევ უფრო აზუსტებს პოზიციას.</p><h3>2. SIM ბარათი და ქსელი</h3><p>საქართველოში სმარტ საათი მუშაობს <strong>Magti</strong>, <strong>Silknet</strong> ან <strong>Cellfie</strong> Nano-SIM ბარათით. 4G მოდელი სტაბილურ კავშირს უზრუნველყოფს და ვიდეო ზარსაც მხარს უჭერს.</p><p><strong>მნიშვნელოვანი:</strong> SIM ბარათის ჩასმის წინ გამორთეთ PIN კოდი ტელეფონიდან.</p><h3>3. ბატარეის ხანგრძლივობა</h3><p>მინიმუმ <strong>680 mAh</strong> ბატარეა 2-3 დღე ძლებს ნორმალური გამოყენებისას.</p><h3>4. წყალგამძლეობა</h3><p><strong>IP67</strong> რეიტინგი ნიშნავს, რომ საათი ხელის დაბანას, წვიმას და მსუბუქ შხეფებს უძლებს.</p><h3>5. კამერა</h3><p>წინა კამერა ვიდეო ზარისთვის გამოიყენება — მშობელი რეალურ დროში ხედავს ბავშვს.</p><h2>ასაკობრივი რეკომენდაციები</h2><h3>4-6 წელი</h3><p>მარტივი ინტერფეისი, SOS ღილაკი და GPS. რეკომენდირებული: Q12 ან Q21.</p><h3>7-10 წელი</h3><p>კამერა, სკოლის რეჟიმი და geo-fence. რეკომენდირებული: Wonlex CT23 ან CT27.</p><h3>11+ წელი</h3><p>ვიდეო ზარი და Setracker2 სრული ფუნქციონალი. რეკომენდირებული: Wonlex KT34 ან KT20.</p><h2>დასკვნა</h2><p>ბავშვის სმარტ საათის არჩევისას მთავარია GPS სიზუსტე, SIM თავსებადობა საქართველოს ოპერატორებთან და ასაკისთვის შესაფერისი ფუნქციები.</p>',
                'body_en'            => '<h2>Why Does Your Child Need a Smartwatch?</h2><p>In 2026, a kids smartwatch is not just an accessory — it is a <strong>safety device</strong>. The GPS tracker lets parents monitor their child\'s location in real time, while the SOS button connects to parents with a single press.</p><p>Unlike a phone, a smartwatch <strong>has no internet browser or social media</strong> — the child can only make calls, use SOS, and send voice messages.</p><h2>Key Criteria for Choosing</h2><h3>1. GPS Tracking</h3><p>Every quality kids watch uses <strong>GPS + LBS + Wi-Fi</strong> for accurate positioning.</p><h3>2. SIM Card and Network</h3><p>In Georgia, smartwatches work with <strong>Magti</strong>, <strong>Silknet</strong>, or <strong>Cellfie</strong> Nano-SIM cards.</p><p><strong>Important:</strong> Disable the PIN code before inserting the SIM into the watch.</p><h3>3. Battery Life</h3><p>A minimum <strong>680 mAh</strong> battery lasts 2-3 days with normal use.</p><h3>4. Water Resistance</h3><p><strong>IP67</strong> means the watch handles handwashing, rain, and light splashes.</p><h3>5. Camera</h3><p>A front camera is used for video calls — parents see their child in real time.</p><h2>Age-Based Recommendations</h2><h3>Ages 4-6</h3><p>Simple interface, SOS, GPS. Recommended: Q12 or Q21.</p><h3>Ages 7-10</h3><p>Camera, school mode, geo-fence. Recommended: Wonlex CT23 or CT27.</p><h3>Ages 11+</h3><p>Video calls, full Setracker2. Recommended: Wonlex KT34 or KT20.</p><h2>Conclusion</h2><p>Focus on GPS accuracy, SIM compatibility with Georgian carriers, and age-appropriate features.</p>',
                'meta_title_ka'      => 'რომელი სმარტ საათი ავირჩიოთ ბავშვისთვის 2026 — სრული სახელმძღვანელო | MyTechnic',
                'meta_title_en'      => 'Which Smartwatch to Choose for Kids 2026 — Complete Guide | MyTechnic',
                'meta_description_ka'=> 'GPS, SIM, კამერა, ბატარეა — ბავშვის სმარტ საათის არჩევის სრული სახელმძღვანელო 2026. ასაკობრივი რეკომენდაციები და მოდელების შედარება.',
                'meta_description_en'=> 'GPS, SIM, camera, battery — complete kids smartwatch buying guide for 2026. Age recommendations and model comparisons.',
                'schema_type'        => 'Article',
                'is_published'       => true,
                'published_at'       => now()->subDays(18),
            ],

            /* ─── 2. SIM Card Comparison ─── */
            [
                'slug'               => 'sim-barat-bavshvis-saatshi-magti-silknet',
                'title_ka'           => 'SIM ბარათი ბავშვის სმარტ საათში — Magti, Silknet, Cellfie შედარება 2026',
                'title_en'           => 'SIM Card in Kids Smartwatch — Magti, Silknet, Cellfie Comparison 2026',
                'excerpt_ka'         => 'რომელი ოპერატორის SIM ბარათი ჯობია ბავშვის GPS საათში? Magti, Silknet და Cellfie-ს შედარება, PIN კოდის გამორთვა და VoLTE-ს შეზღუდვა.',
                'excerpt_en'         => 'Which carrier SIM is best for a kids GPS watch? Magti, Silknet, and Cellfie comparison, PIN code disable guide, and VoLTE limitation.',
                'body_ka'            => '<h2>რომელი SIM ბარათი შეიძენოთ?</h2><p>ბავშვის GPS სმარტ საათი მუშაობს <strong>Nano-SIM</strong> ბარათით. საქართველოში სამი ძირითადი ოპერატორია: <strong>Magti</strong>, <strong>Silknet</strong> და <strong>Cellfie</strong>.</p><h2>Magti</h2><p>ყველაზე ფართო 4G/LTE გაშვება საქართველოში. GPS საათებთან საუკეთესო თავსებადობა.</p><h2>Silknet</h2><p>სტაბილური 4G ქალაქებში. ფიბერ-ოპტიკის მომხმარებლებისთვის კომბინირებული ფასდაკლება.</p><h2>Cellfie</h2><p>Cellfie (ყოფილი Selfie) ბიუჯეტურ პაკეტებს სთავაზობს. იაფი ვარიანტი თბილისისთვის.</p><h2>⚠️ PIN კოდის გამორთვა</h2><p>SIM-ის ჩასმის <strong>წინ აუცილებლად გამორთეთ PIN კოდი</strong> ტელეფონიდან. PIN კოდიანი SIM საათში ვერ ამუშავდება.</p><h2>⚠️ VoLTE შეზღუდვა</h2><p>ზოგიერთი ოპერატორის SIM-ს ნაგულისხმევად ჩართული აქვს VoLTE. ბავშვის საათის უმრავლესობას VoLTE არ აქვს მხარდაჭერილი — ოპერატორს დაურეკეთ და VoLTE გამორთეთ.</p>',
                'body_en'            => '<h2>Which SIM Card Should You Buy?</h2><p>Kids GPS smartwatches use <strong>Nano-SIM</strong> cards. In Georgia, there are three main carriers: <strong>Magti</strong>, <strong>Silknet</strong>, and <strong>Cellfie</strong>.</p><h2>Magti</h2><p>Widest 4G/LTE coverage in Georgia. Best compatibility with GPS watches.</p><h2>Silknet</h2><p>Stable 4G in cities. Combined discounts for fiber customers.</p><h2>Cellfie</h2><p>Cellfie (formerly Selfie) offers budget plans. Affordable option for Tbilisi.</p><h2>⚠️ PIN Code</h2><p><strong>Before inserting the SIM, disable the PIN code</strong> from a phone. A PIN-locked SIM will not work in the watch.</p><h2>⚠️ VoLTE Limitation</h2><p>Some carrier SIMs have VoLTE enabled by default. Most kids watches do not support VoLTE — contact your carrier to disable it.</p>',
                'meta_title_ka'      => 'SIM ბარათი ბავშვის საათში — Magti, Silknet, Cellfie შედარება 2026 | MyTechnic',
                'meta_title_en'      => 'SIM Card for Kids Smartwatch — Magti, Silknet, Cellfie 2026 | MyTechnic',
                'meta_description_ka'=> 'Magti, Silknet თუ Cellfie? SIM ბარათის შედარება ბავშვის GPS სმარტ საათისთვის. PIN კოდის გამორთვა, VoLTE შეზღუდვა.',
                'meta_description_en'=> 'Magti, Silknet or Cellfie? SIM card comparison for kids GPS smartwatch. PIN disable guide, VoLTE limitation.',
                'schema_type'        => 'HowTo',
                'is_published'       => true,
                'published_at'       => now()->subDays(16),
            ],

            /* ─── 3. GPS in Georgia ─── */
            [
                'slug'               => 'gps-saati-bavshvistvis-sakartveloshi',
                'title_ka'           => 'GPS სმარტ საათი ბავშვისთვის საქართველოში — ადგილმდებარეობა, SOS და უსაფრთხოება',
                'title_en'           => 'GPS Smartwatch for Kids in Georgia — Location, SOS, and Safety',
                'excerpt_ka'         => 'რეალურ დროში GPS ადგილმდებარეობა, SOS საგანგებო ღილაკი, Geo-fence ზონა და ზარები მშობლებთან — საქართველოში მუშაობს Magti-სა და Silknet-ის ქსელში.',
                'excerpt_en'         => 'Real-time GPS location, SOS emergency button, geo-fence zone, and calls to parents — works on Magti and Silknet networks in Georgia.',
                'body_ka'            => '<h2>როგორ მუშაობს GPS ტრეკინგი?</h2><p>GPS სმარტ საათი სატელიტურ სიგნალს იყენებს ადგილმდებარეობის დასადგენად. მშობელი <strong>Setracker2</strong> აპლიკაციის საშუალებით რუკაზე ხედავს ბავშვის ზუსტ პოზიციას.</p><h2>GPS + LBS + Wi-Fi</h2><ul><li><strong>GPS</strong> — სატელიტი, 5-15 მეტრი სიზუსტე</li><li><strong>LBS</strong> — საბაზო სადგური, მუშაობს შენობებში</li><li><strong>Wi-Fi</strong> — ახლომდებარე ქსელების სკანირება</li></ul><h2>SOS საგანგებო ღილაკი</h2><p>ბავშვი 3 წამი აჭერს SOS-ს → ავტომატური ზარი მშობელს + მდებარეობის კოორდინატები.</p><h2>Geo-fence — უსაფრთხო ზონა</h2><p>Setracker2-ში მშობელი ადგენს უსაფრთხო ზონებს. ბავშვი რომ ტოვებს ზონას — Push-შეტყობინება მოდის.</p><h2>საქართველოში გაშვება</h2><p>GPS საათი მუშაობს Magti, Silknet და Cellfie ქსელებში. 4G საათები ყველა მთავარ ქალაქში სტაბილურად მუშაობს.</p>',
                'body_en'            => '<h2>How Does GPS Tracking Work?</h2><p>A GPS smartwatch uses satellite signals to determine location. Parents see the exact position on a map through <strong>Setracker2</strong>.</p><h2>GPS + LBS + Wi-Fi</h2><ul><li><strong>GPS</strong> — satellite, 5-15m accuracy</li><li><strong>LBS</strong> — cell tower, works indoors</li><li><strong>Wi-Fi</strong> — nearby network scanning</li></ul><h2>SOS Emergency Button</h2><p>Child holds SOS for 3 seconds → auto-call to parent + location coordinates.</p><h2>Geo-fence — Safe Zone</h2><p>Parents set safe zones in Setracker2. Push notification when child leaves the zone.</p><h2>Coverage in Georgia</h2><p>GPS watches work on Magti, Silknet, and Cellfie networks. 4G watches work reliably in all major cities.</p>',
                'meta_title_ka'      => 'GPS სმარტ საათი ბავშვისთვის საქართველოში — ტრეკინგი, SOS | MyTechnic',
                'meta_title_en'      => 'GPS Smartwatch for Kids in Georgia — Tracking, SOS | MyTechnic',
                'meta_description_ka'=> 'GPS სმარტ საათი ბავშვისთვის საქართველოში — რეალურ დროში ადგილმდებარეობა, SOS ღილაკი, Geo-fence. Magti და Silknet ქსელი.',
                'meta_description_en'=> 'GPS smartwatch for kids in Georgia — real-time location, SOS button, geo-fence. Works on Magti and Silknet networks.',
                'schema_type'        => 'Article',
                'is_published'       => true,
                'published_at'       => now()->subDays(13),
            ],

            /* ─── 4. School Mode ─── */
            [
                'slug'               => 'bavshvis-saati-skolistvis',
                'title_ka'           => 'ბავშვის სმარტ საათი სკოლისთვის — სკოლის რეჟიმი, Geo-fence და GPS ტრეკინგი',
                'title_en'           => 'Kids Smartwatch for School — School Mode, Geo-fence, and GPS Tracking',
                'excerpt_ka'         => 'სკოლის რეჟიმი ბლოკავს საათის ფუნქციებს გაკვეთილების დროს. Geo-fence სკოლის პერიმეტრს აკონტროლებს.',
                'excerpt_en'         => 'School mode blocks watch functions during lessons. Geo-fence monitors the school perimeter.',
                'body_ka'            => '<h2>რა არის სკოლის რეჟიმი?</h2><p>სკოლის რეჟიმი <strong>გაკვეთილების დროს ბლოკავს საათის ეკრანს</strong>. საათი მხოლოდ დროს აჩვენებს. SOS და მშობლის ზარი ხელმისაწვდომი რჩება.</p><h2>როგორ ჩავრთოთ?</h2><ol><li>Setracker2 → Settings → Do Not Disturb</li><li>დაამატეთ დროის ინტერვალი (09:00-14:00)</li><li>აირჩიეთ კვირის დღეები</li><li>შეინახეთ</li></ol><h2>Geo-fence — სკოლის პერიმეტრი</h2><p>რუკაზე ადგენთ უსაფრთხო ზონას სკოლის ირგვლივ. თუ ბავშვი ტოვებს ზონას — მყისიერი შეტყობინება.</p><h2>GPS ისტორია</h2><p>Setracker2-ის Footprint ფუნქცია აჩვენებს ბავშვის მოძრაობის ისტორიას თარიღის მიხედვით.</p>',
                'body_en'            => '<h2>What Is School Mode?</h2><p>School mode <strong>blocks the watch screen during lesson hours</strong>. The watch only shows the time. SOS and parent calls remain available.</p><h2>How to Enable?</h2><ol><li>Setracker2 → Settings → Do Not Disturb</li><li>Add time interval (09:00-14:00)</li><li>Select weekdays</li><li>Save</li></ol><h2>Geo-fence — School Perimeter</h2><p>Set a safe zone around the school on the map. Instant notification if the child leaves the zone.</p><h2>GPS History</h2><p>Setracker2\'s Footprint feature shows the child\'s movement history by date.</p>',
                'meta_title_ka'      => 'ბავშვის სმარტ საათი სკოლისთვის — სკოლის რეჟიმი, Geo-fence | MyTechnic',
                'meta_title_en'      => 'Kids Smartwatch for School — School Mode, Geo-fence | MyTechnic',
                'meta_description_ka'=> 'სკოლის რეჟიმი + Geo-fence + GPS ტრეკინგი — ბავშვი სკოლაში, მშობელი მშვიდად. MyTechnic-ის 4G სმარტ საათები.',
                'meta_description_en'=> 'School mode + geo-fence + GPS tracking — child at school, parent at ease. MyTechnic 4G smartwatches.',
                'schema_type'        => 'Article',
                'is_published'       => true,
                'published_at'       => now()->subDays(10),
            ],

            /* ─── 5. Budget Under 200 GEL ─── */
            [
                'slug'               => 'iafi-bavshvis-gps-saati-200-lars',
                'title_ka'           => 'იაფი ბავშვის GPS სმარტ საათი 200 ლარამდე — 2026 საუკეთესო მოდელები',
                'title_en'           => 'Budget Kids GPS Smartwatch Under 200 GEL — Best 2026 Models',
                'excerpt_ka'         => '200 ლარამდე ბავშვის GPS სმარტ საათი — ხელმისაწვდომი მოდელები GPS ტრეკერით, SOS ღილაკით და SIM ბარათის მხარდაჭერით.',
                'excerpt_en'         => 'Kids GPS smartwatch under 200 GEL — affordable models with GPS tracker, SOS button, and SIM card support.',
                'body_ka'            => '<h2>ხელმისაწვდომი GPS საათი</h2><p>200 ₾-ის ფარგლებში შეგიძლიათ შეიძინოთ საათი GPS ტრეკინგით, SOS ღილაკით და ზარებით.</p><h2>რა ფუნქციები აქვს?</h2><ul><li>GPS + LBS ტრეკინგი</li><li>SOS საგანგებო ღილაკი</li><li>SIM ბარათი, კამერა</li><li>სკოლის რეჟიმი, IP67</li><li>ბატარეა 400-680 mAh</li></ul><h2>საუკეთესო მოდელები</h2><p><strong>Q12</strong> — ყველაზე პოპულარული ბიუჯეტური მოდელი. IP67, GPS + LBS, SOS, კამერა.</p><p><strong>Q21</strong> — წყალგამძლე, SOS, GPS. მინიმალისტური დიზაინი.</p><p><strong>Q19</strong> — კამერა, GPS, SOS, ხმოვანი შეტყობინება.</p><p><strong>Q15</strong> — GPS + LBS, SOS, კამერა, LED ფანარი.</p><h2>2G თუ 4G?</h2><p>200 ₾-მდე ძირითადად 2G მოდელებია. ხმოვანი ზარი, SMS და GPS — ბავშვის უსაფრთხოებისთვის საკმარისი.</p>',
                'body_en'            => '<h2>Affordable GPS Watch</h2><p>Within 200 ₾ you can get a watch with GPS tracking, SOS button, and calls.</p><h2>Features</h2><ul><li>GPS + LBS tracking</li><li>SOS emergency button</li><li>SIM card, camera</li><li>School mode, IP67</li><li>Battery 400-680 mAh</li></ul><h2>Best Models</h2><p><strong>Q12</strong> — most popular budget model. IP67, GPS + LBS, SOS, camera.</p><p><strong>Q21</strong> — waterproof, SOS, GPS. Minimalist design.</p><p><strong>Q19</strong> — camera, GPS, SOS, voice message.</p><p><strong>Q15</strong> — GPS + LBS, SOS, camera, LED flashlight.</p><h2>2G or 4G?</h2><p>Under 200 ₾ models are mostly 2G. Voice calls, SMS, and GPS — sufficient for child safety.</p>',
                'meta_title_ka'      => 'იაფი ბავშვის GPS სმარტ საათი 200 ლარამდე 2026 | MyTechnic',
                'meta_title_en'      => 'Budget Kids GPS Smartwatch Under 200 GEL 2026 | MyTechnic',
                'meta_description_ka'=> '200 ₾-მდე ბავშვის GPS სმარტ საათი — Q12, Q21, Q19, Q15. SOS, GPS, კამერა. მიწოდება მთელ საქართველოში.',
                'meta_description_en'=> 'Kids GPS smartwatch under 200 GEL — Q12, Q21, Q19, Q15. SOS, GPS, camera. Delivery across Georgia.',
                'schema_type'        => 'ItemList',
                'is_published'       => true,
                'published_at'       => now()->subDays(8),
            ],

            /* ─── 6. Setracker2 Setup Guide ─── */
            [
                'slug'               => 'setracker2-aplikacia-bavshvis-saatistvis',
                'title_ka'           => 'Setracker2 აპლიკაცია — ბავშვის სმარტ საათის დაყენების სრული სახელმძღვანელო',
                'title_en'           => 'Setracker2 App — Complete Kids Smartwatch Setup Guide',
                'excerpt_ka'         => 'ნაბიჯ-ნაბიჯ სახელმძღვანელო Setracker2 აპლიკაციის ჩამოტვირთვა, რეგისტრაცია, საათის დაკავშირება, SOS ნომრების და Geo-fence-ის დაყენება.',
                'excerpt_en'         => 'Step-by-step guide to download Setracker2, register, connect the watch, set up SOS numbers and geo-fence.',
                'body_ka'            => '<h2>რა არის Setracker2?</h2><p><strong>Setracker2</strong> — უფასო აპლიკაცია Android-ისა და iOS-ისთვის ბავშვის GPS სმარტ საათის მართვისთვის.</p><h2>ნაბიჯი 1 — ჩამოტვირთვა</h2><p>Google Play Store ან App Store-ში მოძებნეთ Setracker2 (JEEBR inc.).</p><h2>ნაბიჯი 2 — რეგისტრაცია</h2><p>Language: English, Area: Europe and Africa, Account: ელ-ფოსტა ან ტელეფონი.</p><h2>ნაბიჯი 3 — საათის დაკავშირება</h2><p>საათის უკანა მხრიდან დაასკანერეთ QR კოდი ან შეიყვანეთ Reg Code (15 ციფრი).</p><h2>ნაბიჯი 4 — SOS ნომრები</h2><p>შეიყვანეთ 3 ნომერი პრიორიტეტის მიხედვით.</p><h2>ნაბიჯი 5 — Geo-fence</h2><p>რუკაზე მონიშნეთ უსაფრთხო ზონა (სკოლა, სახლი), რადიუსი 200-1000 მეტრი.</p><h2>ნაბიჯი 6 — დამატებითი პარამეტრები</h2><ul><li>Do Not Disturb — სკოლის რეჟიმი</li><li>Remote monitoring</li><li>Alarm, Phone book, Find Watch</li></ul>',
                'body_en'            => '<h2>What Is Setracker2?</h2><p><strong>Setracker2</strong> is a free app for Android and iOS to manage your child\'s GPS smartwatch.</p><h2>Step 1 — Download</h2><p>Search Setracker2 in Google Play Store or App Store (JEEBR inc.).</p><h2>Step 2 — Registration</h2><p>Language: English, Area: Europe and Africa, Account: email or phone.</p><h2>Step 3 — Connect Watch</h2><p>Scan the QR code on the back of the watch or enter the 15-digit Reg Code.</p><h2>Step 4 — SOS Numbers</h2><p>Enter 3 numbers in priority order.</p><h2>Step 5 — Geo-fence</h2><p>Mark a safe zone on the map (school, home), radius 200-1000 meters.</p><h2>Step 6 — Additional Settings</h2><ul><li>Do Not Disturb — school mode</li><li>Remote monitoring</li><li>Alarm, Phone book, Find Watch</li></ul>',
                'meta_title_ka'      => 'Setracker2 აპლიკაცია — ბავშვის საათის დაყენების სახელმძღვანელო | MyTechnic',
                'meta_title_en'      => 'Setracker2 App — Kids Smartwatch Setup Guide | MyTechnic',
                'meta_description_ka'=> 'Setracker2 აპლიკაციის ჩამოტვირთვა, რეგისტრაცია, საათის დაკავშირება, SOS ნომრები, Geo-fence. სრული სახელმძღვანელო.',
                'meta_description_en'=> 'Download Setracker2, register, connect the watch, set SOS numbers, configure geo-fence. Complete setup guide.',
                'schema_type'        => 'HowTo',
                'is_published'       => true,
                'published_at'       => now()->subDays(4),
            ],

            /* ─── 7. 4G vs 2G ─── */
            [
                'slug'               => '4g-vs-2g-bavshvis-saati',
                'title_ka'           => '4G vs 2G ბავშვის სმარტ საათი — რომელი აჯობებს და რატომ?',
                'title_en'           => '4G vs 2G Kids Smartwatch — Which Is Better and Why?',
                'excerpt_ka'         => '4G და 2G ბავშვის სმარტ საათის დეტალური შედარება: ქსელი, ვიდეო ზარი, GPS სიზუსტე, ბატარეა, ფასი.',
                'excerpt_en'         => 'Detailed comparison of 4G and 2G kids smartwatches: network, video calls, GPS accuracy, battery, price.',
                'body_ka'            => '<h2>4G და 2G — რა განსხვავებაა?</h2><p>ბავშვის სმარტ საათი ორ ძირითად კატეგორიად იყოფა: <strong>4G (LTE)</strong> და <strong>2G (GSM)</strong>.</p><h2>4G უპირატესობები</h2><ul><li>ვიდეო ზარი</li><li>სწრაფი GPS განახლება</li><li>Wi-Fi პოზიციონირება</li><li>მომავლის გარანტია</li></ul><h2>2G უპირატესობები</h2><ul><li>დაბალი ფასი</li><li>ბატარეის ეკონომია</li><li>მარტივი ინტერფეისი</li></ul><h2>რომელი აირჩიოთ?</h2><p><strong>4G</strong> — ვიდეო ზარი, 7+ წელი, მაქსიმალური GPS. <strong>2G</strong> — ბიუჯეტი, 4-6 წელი, GPS + SOS საკმარისია.</p>',
                'body_en'            => '<h2>4G and 2G — What\'s the Difference?</h2><p>Kids smartwatches come in two categories: <strong>4G (LTE)</strong> and <strong>2G (GSM)</strong>.</p><h2>4G Advantages</h2><ul><li>Video calls</li><li>Faster GPS updates</li><li>Wi-Fi positioning</li><li>Future-proof</li></ul><h2>2G Advantages</h2><ul><li>Low price</li><li>Battery economy</li><li>Simple interface</li></ul><h2>Which to Choose?</h2><p><strong>4G</strong> — video calls, ages 7+, max GPS. <strong>2G</strong> — budget, ages 4-6, GPS + SOS is enough.</p>',
                'meta_title_ka'      => '4G vs 2G ბავშვის სმარტ საათი — შედარება 2026 | MyTechnic',
                'meta_title_en'      => '4G vs 2G Kids Smartwatch — Comparison 2026 | MyTechnic',
                'meta_description_ka'=> '4G და 2G ბავშვის სმარტ საათის შედარება: ვიდეო ზარი, GPS სიზუსტე, ბატარეა, ფასი. რომელი აირჩიოთ.',
                'meta_description_en'=> '4G vs 2G kids smartwatch comparison: video calls, GPS accuracy, battery, price. Which to choose.',
                'schema_type'        => 'Article',
                'is_published'       => true,
                'published_at'       => now()->subDays(2),
            ],
        ];

        foreach ($articles as $data) {
            Article::updateOrCreate(['slug' => $data['slug']], $data);
        }
    }
}
