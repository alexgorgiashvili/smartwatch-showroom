# MyMarket Goal Agent Prompt

ქვემოთ არის Goal-ზე გასაშვები მზად prompt MyMarket-ზე პროდუქტების ატვირთვისთვის. ტექსტი აერთიანებს:

- მიმდინარე 5-მოდელიან პარტიას
- უსაფრთხო ოპერაციულ წესებს
- განმეორებად template-ს შემდეგი პარტიებისთვის

## Copy-Ready Prompt

```text
Objective:
MyMarket-ზე განათავსე ჩემი მაღაზიის 5 პროდუქტი ამ თანმიმდევრობით: Q19 → X01 → CT23 → T53 → KT34. იმუშავე Chrome-ის არსებულ user session-ზე, თუ browser automation დაგჭირდება. გამოიყენე production site/admin როგორც primary source of truth; local workspace შეიძლება ნაწილობრივ მოძველებული იყოს. ამ დავალების მიზანია უსაფრთხოდ, თანმიმდევრულად და კონტროლირებულად შევქმნათ პირველი რეალური batch.

Success criteria:
1. შეამოწმე live/product/admin source-იდან თითოეული SKU-ის მიმდინარე ფასი, ფასდაკლება, ფერები, რეალური მარაგი, ფოტოები და ძირითადი მახასიათებლები.
2. MyMarket-ზე შექმენი თითო მოდელისთვის ერთი listing.
3. Listing-ში გააერთიანე ფერები ერთ განცხადებაში; არ შექმნა ფერების მიხედვით ცალკე duplicate listing-ები.
4. Listing-ში შეიტანე მხოლოდ ის ფერები, რომლებიც რეალურად in stock არის.
5. Q19 და CT23 გამოაქვეყნე როგორც discounted products, თუ live source-შიც აქტიური ფასდაკლება დადასტურდა.
6. T53 პოზიციონირდეს როგორც gift/gift box bundle.
7. KT34 პოზიციონირდეს როგორც Android-based model და გამოკვეთილად გაუსვი ხაზი მის მახასიათებლებს.
8. ყველა განცხადება იყოს Georgian language-ში.
9. არ ჩართო ონლაინ გაყიდვა, installment, paid promo, shop opening, balance top-up, bank/account ცვლილება, თუ ეს ცალსახად არ არის საჭირო და დამტკიცებული.
10. გამოქვეყნებამდე მაჩვენე 1 ზუსტი preview batch summary და მხოლოდ ჩემი დასტურის შემდეგ გამოაქვეყნე ყველა 5.
11. გამოქვეყნების შემდეგ გადაამოწმე, რომ განცხადებები შექმნილია/აქტიურია ან moderation pending სტატუსშია.

Operating rules:
- გამოიყენე Chrome existing profile/session. თუ საჭიროა login/CAPTCHA/OTP, გააჩერე პროცესი და სთხოვე user-ს ჩაერთოს ბრაუზერში; არ მოითხოვო credentials ჩატში.
- არ გახსნა და არ გამოიტანო cookies, saved passwords, private profile data, phone verification codes, bank info ან სხვა sensitive ინფორმაცია.
- თუ MyMarket-ის ფორმა ან ველი ბუნდოვანია, უპირატესობა მიანიჭე conservative/default ვარიანტს და დააფიქსირე ეს preview-ში.
- არ გამოიგონო ფუნქცია, აქსესუარი, გარანტია, ფერი, მარაგი ან feature. რაც ვერ დასტურდება live source-ით, ან გამოტოვე, ან მონიშნე preview-ში როგორც unclear.
- თუ discount % ველში მხოლოდ integer მიიღება, სცადე integer value, მაგრამ არ გამოაქვეყნო, თუ საბოლოო ფასი target price-ს 0.50 ₾-ზე მეტით ასცდება.
- არ გამოიყენო MyTechnic-ის trust claims, support claims, ოფიციალური იმპორტიორი, nationwide free delivery, installment, support hours ან სხვა ბრენდული დაპირებები, თუ ეს კონკრეტული MyMarket listing-ის description-ში მიზანმიმართულად არ გვინდა.
- აღწერა იყოს product-first, მოკლე, გაყიდვადი და სპეციფიკაციებზე დაფუძნებული.

MyMarket listing defaults:
- Category: ტექნიკა → მობილურები და აქსესუარები → სმარტ საათი
- Condition: ახალი
- SIM support: კი, თუ მოდელს აქვს
- Listing style: basic standard listing
- Delivery note: მიუთითე სწრაფი საკურიერო მიწოდება 2–3 საათში, თუ MyMarket-ის ველები ამას იძლევა და ეს წესს არ არღვევს
- Online sales/installment: გამორთული/არაქტიური
- Paid promotion: არ გამოიყენო

Preview gate:
გამოქვეყნებამდე მაჩვენე ერთი compact summary ამ სტრუქტურით:
- SKU / სათაური
- live verified price / sale price
- discount handling
- ფერები და მარაგი
- გამოსაყენებელი ფოტოების რაოდენობა
- short title
- 2-3 sentence description
- filled key attributes
- ნებისმიერი ambiguity ან risk

Only after explicit approval publish all 5.

Known batch intent and positioning:

1. Q19
- Priority: პირველი
- Core angle: ბიუჯეტური 2G საბავშვო სმარტ საათი კამერით, SOS-ით და GPS/LBS safety ფუნქციებით
- Discount: yes, if live source still shows 79 ₾ → 59 ₾
- Known workspace fallback:
  - price intent: 79 → 59
  - short description confirms: SIM support, camera, SOS, GPS tracker, IP67, 1.44" OLED
  - colors previously noted: მწვანე 10, წითელი 5, იასამნისფერი 6
  - workspace photo evidence: 6 base product images + 2 extra gallery assets in quick-review bundle
- Listing focus: დაბალი ფასი + ძირითადი უსაფრთხოების ფუნქციები + მარტივი არჩევანი პატარა ბავშვისთვის

2. X01
- Priority: მეორე
- Core angle: 4G ვიდეოზარიანი მოდელი კარგი ყოველდღიური ფასით
- Known workspace fallback:
  - price previously noted: 109 ₾
  - short description confirms: 4G, video call/camera, GPS + WiFi + LBS, SOS, IP67, 650mAh
  - colors previously noted: pink 5, white 0, black 5, blue 3
  - workspace image count: 5
- Listing focus: ვიდეო ზარი, 4G კავშირი, GPS/WiFi/LBS მდებარეობა
- Important: white/out-of-stock variant არ შეიტანო თუ live source-შიც 0 stock დადასტურდა

3. CT23
- Priority: მესამე
- Brand display: Wonlex CT23
- Core angle: ხელმისაწვდომი 4G Wonlex მოდელი მშობლისთვის მნიშვნელოვანი ძირითადი ფუნქციებით
- Discount: yes, if live source still shows discount
- Known previous note:
  - public production note: 179 ₾ → 149 ₾
  - user-side older note mentioned stock 25, but current local DB does not confirm that; MUST recheck live variant stock before publish
- Known workspace fallback:
  - short description confirms: 4G, GPS, SOS, video call, IP67, 1.83" screen, Nano-SIM
  - prior note also mentions: RTOS, 240x280, 650mAh, 2–4 day battery
  - current local DB fallback: regular price 169 ₾, no active sale price stored locally
  - current local DB fallback colors: შავი 1, ლურჯი 0, ვარდისფერი 0
  - workspace image count: 6
- Listing focus: Wonlex brand + 4G + GPS + SOS + video call + balanced price

4. T53
- Priority: მეოთხე
- Core angle: სასაჩუქრე 4G gift box bundle
- Must emphasize:
  - ეს არის სასაჩუქრე ბოქსი
  - კომპლექტაციაში შედის დამატებითი აქსესუარები
- Known previous note:
  - price: 179 ₾
  - includes 2 replacement straps + pendant
  - battery: 750mAh
  - colors previously noted: pink 5, blue 5
- Known workspace fallback:
  - short description confirms: gift 4G watch, video call, GPS, SOS
  - current local DB fallback image count: 7
- Listing focus: საჩუქრისთვის გამზადებული ნაკრები + ვიდეო ზარი + GPS + ვიზუალური მიმზიდველობა

5. KT34
- Priority: მეხუთე
- Brand display: Wonlex KT34
- Core angle: Android-based უფრო ძლიერი მოდელი უფროსი ასაკის ბავშვისთვის
- Must emphasize:
  - Android operating system
  - richer feature set
  - stronger specs than budget models
- Known previous note:
  - price: 229 ₾
  - 1GB RAM + 8GB storage
  - 1.85" IPS display
  - 800mAh battery
  - 3–5 day battery life
  - GPS + WiFi + LBS
  - video/voice calls, SOS, heart rate, sleep tracking
  - colors previously noted: black 0, blue 3, pink 3
- Known workspace fallback:
  - short description confirms: Android, GPS, SOS, calls, camera, IP67
  - current local DB fallback image count: 6
- Listing focus: Android სისტემა + მეტი ფუნქცია + GPS/WiFi/LBS + ჯანმრთელობის/დამატებითი smart features
- Important: black/out-of-stock variant არ შეიტანო თუ live source-შიც 0 stock დადასტურდა

Discount handling rules:
- For Q19 target final price is 59 ₾ if discount is active.
- For CT23 target final price is 149 ₾ if discount is active.
- If MyMarket accepts decimal discount rate, use the exact rate needed to reach target price.
- If only integer % is allowed, try nearest integer and inspect resulting final price.
- Do not publish discounted listing if the resulting final price differs from target by more than 0.50 ₾ without approval.

Attribute normalization rules:
- If camera resolution is shown vaguely as "<3MP", map conservatively to 2MP only if a camera field is mandatory.
- If camera resolution is shown as "3–7MP", map conservatively to 3.2MP only if mandatory.
- If memory card support is not explicitly confirmed, choose "არა" when the field is mandatory.
- If warranty is not clearly stated in live product/admin source, do not invent it in listing copy.

Photo rules:
- Use product-specific photos only.
- Put the strongest hero image first.
- For T53, prefer the image that best shows the gift-box nature.
- For KT34, prefer the image that best communicates advanced features / Android / premium feel.
- If image upload count is limited, prioritize clear front angle, side angle, UI/features image, packaging/accessory image, color representation.

Title/copy rules:
- Titles must be concise, searchable, and Georgian-first.
- Include model code in title.
- Mention 2-4 strongest differentiators only.
- Avoid keyword stuffing.
- Example style:
  - `Q19 საბავშვო სმარტ საათი 2G კამერით და SOS ფუნქციით`
  - `Wonlex CT23 4G საბავშვო სმარტ საათი GPS-ით და ვიდეო ზარით`
  - `T53 სასაჩუქრე 4G საბავშვო სმარტ საათი GPS-ით`
  - `Wonlex KT34 Android 4G საბავშვო სმარტ საათი GPS-ით`

Description rules:
- 2-3 მოკლე აბზაცი ან კომპაქტური bullet-style ტექსტი, თუ ფორმა იძლევა.
- პირველი წინადადება: რა ტიპის საათია და ვისთვის არის.
- მეორე ნაწილი: 3-6 ძირითადი ფუნქცია.
- მესამე ნაწილი საჭიროების მიხედვით: ფერები/კომპლექტაცია/ფასდაკლება.
- არ გამოიყენო ზედმეტი მარკეტინგული დაპირებები.

Execution order:
1. Open MyMarket create-listing flow.
2. Build preview for all five before publishing anything.
3. Stop and present preview.
4. After approval, publish all five in the requested order.
5. Re-open each created listing and verify visible title, price, discount, photos, and stock-related text.
6. Return a final report with:
   - created listing URLs
   - publish/moderation status
   - final title used
   - final price used
   - included colors
   - any fields left conservative due to ambiguity

Completion condition:
Task is complete only when all 5 listings are either successfully created and visible, or clearly submitted and marked pending moderation, with a final report returned.
```

## Reusable Template For Next Batches

```text
Objective:
MyMarket-ზე განათავსე შემდეგი batch: [MODEL_1] → [MODEL_2] → [MODEL_3]

Rules:
1. Use production site/admin as source of truth.
2. One listing per model; combine colors in one listing.
3. Include only in-stock variants.
4. Verify price, sale price, images, stock, specs before publish.
5. Show one preview summary before publishing anything.
6. Publish only after explicit approval.
7. After publish, verify live status and return URLs.

Per-model input format:
- Model:
- Positioning angle:
- Target regular price:
- Target sale price:
- Discount active: yes/no
- Key features to emphasize:
- Must mention:
- Must avoid:
- Known colors/stock:
- Known image count:
- Notes about ambiguity:

Preview format:
- SKU / title
- verified price
- discount handling
- colors
- image count
- description
- key attributes
- risks / unclear fields
```

## Notes For Us

- ამ ფაილში live source-ზე მიბმული წესებია მთავარი; workspace facts არის fallback.
- თუ მოგვინდება, შემდეგ ეტაპზე შეგვიძლია ამავე სტრუქტურით მეორე ფაილი გავაკეთოთ: `MyMarket listing master sheet`.
- თუ გინდა, შემდეგ ტურში ამასვე გადავაქცევ `checklist + spreadsheet schema + short operator SOP` დოკუმენტადაც.
