# MyMarket Goal Prompt — პროდუქტის ატვირთვის reusable შაბლონი

ქვემოთ არის Goal-ზე გასაშვები დეტალური prompt, რომელიც გამოდგება როგორც ამ 5 მოდელისთვის, ისე შემდეგი batch-ებისთვისაც.

## Copy-ready prompt ამ პარტიისთვის

```text
Objective:
MyMarket-ზე განათავსე ჩემი მაღაზიის 5 პროდუქტი ამ თანმიმდევრობით: Q19 → X01 → CT23 → T53 → KT34.
იმუშავე მხოლოდ ჩემი არსებული Chrome session-ით და მიმდინარე ავტორიზებული MyMarket პროფილით.
პროდუქტის ინფორმაციის primary source არის ჩემი მიმდინარე live საიტი/admin; local workspace გამოიყენე მხოლოდ fallback-ად, როცა live მნიშვნელობა დროებით ვერ იკითხება.

მთავარი წესი:
არ გამოაქვეყნო არაფერი ავტომატურად. ჯერ მოამზადე preview ყველა 5 პროდუქტისთვის, მაჩვენე summary, დაელოდე ჩემს დასტურს და მხოლოდ ამის შემდეგ გამოაქვეყნე.

Success criteria:
1. თითოეულ მოდელზე შეიქმნას 1 listing.
2. ფერები გაერთიანდეს ერთ listing-ში; ფერზე ცალკე duplicate listing არ შეიქმნას.
3. listing-ში მოხვდეს მხოლოდ ის ფერები, რომლებიც რეალურად in stock არის.
4. სათაური, ფასი, შესაძლო ფასდაკლება, ფოტოები და ძირითადი მახასიათებლები გადაამოწმე live source-ით.
5. ყველა ტექსტი იყოს ქართულად, მოკლედ და გაყიდვად.
6. გამოქვეყნების შემდეგ დამიბრუნე საბოლოო ანგარიში: URL-ები, ფინალური სათაურები, ფასები, ჩასმული ფერები, სტატუსი.

Hard rules:
- გამოიყენე მხოლოდ არსებული Chrome profile/session.
- თუ დაგხვდა login / CAPTCHA / OTP / phone verification, გაჩერდი და მომთხოვე browser-ში ხელით ჩარევა.
- არ მოითხოვო credentials ჩატში.
- არ ჩართო ონლაინ გაყიდვა, installment, ფასიანი promotion, shop opening, balance top-up ან სხვა დამატებითი სერვისი.
- არ გამოიგონო ფუნქცია, მახასიათებელი, გარანტია, ფერი, მარაგი ან აქსესუარი.
- თუ ველი სავალდებულოა, მაგრამ მნიშვნელობა ზუსტად არ დასტურდება, გამოიყენე ყველაზე conservative ვარიანტი და ეს მონიშნე preview-ში.
- თუ ფასდაკლების ველი მხოლოდ integer %-ს იღებს, სცადე უახლოესი სწორი მნიშვნელობა, მაგრამ არ გამოაქვეყნო, თუ საბოლოო ფასი target ფასს 0.50 ლარზე მეტად აცდენილია.

MyMarket defaults:
- Category: ტექნიკა → მობილურები და აქსესუარები → სმარტ საათი
- Condition: ახალი
- Listing type: გაყიდვა
- Language: ქართული
- Paid promo: გამორთული
- Online sales/installment: გამორთული
- Delivery: მხოლოდ ის მონიშნე, რაც უკვე სტანდარტულად და უსაფრთხოდ გვაქვს; არაფერი ახალი არ ჩართო ჩემი დადასტურების გარეშე

Live form mapping rules:
- სავალდებულო ველები შეავსე conservative mapping-ით.
- Brand ველში აირჩიე შესაბამისი ბრენდი თუ ზუსტად არსებობს; თუ არა, გამოიყენე ყველაზე ახლო ნეიტრალური ვარიანტი და მონიშნე preview-ში.
- Smart watch type, camera, SIM, memory card, touch screen და სხვა ველები შეავსე მხოლოდ დადასტურებული ან conservative მნიშვნელობით.

Discount rules:
- Q19-ზე target discount price: 59 ლარი, მხოლოდ თუ live source-ში კვლავ აქტიური ფასდაკლება დადასტურდა.
- CT23-ზე discount გამოიყენე მხოლოდ თუ live source-ში ნამდვილად ჩანს აქტიური ფასდაკლება. თუ discount აღარ არის, განათავსე ჩვეულებრივ ფასად.
- discount-ის არსებობა არასოდეს ივარაუდო ძველი ჩანაწერის მიხედვით.

Per-model instructions:

1) Q19
- Priority: პირველი
- Positioning: ბიუჯეტური საბავშვო 2G საათი კამერით, SOS-ით და GPS/LBS უსაფრთხოების ფუნქციებით
- Regular price intent: 79 ლარი
- Sale price intent: 59 ლარი
- Must emphasize: ფასდაკლება, SOS, GPS/LBS, კამერა
- Known colors to verify live: მწვანე, წითელი, იასამნისფერი
- Listing angle: დაბალი ფასი + ბავშვის უსაფრთხოების საბაზისო ფუნქციები

2) X01
- Priority: მეორე
- Positioning: 4G ვიდეოზარიანი მოდელი ყოველდღიური გამოყენებისთვის
- Regular price intent: 109 ლარი
- Must emphasize: 4G, ვიდეო ზარი, GPS + WiFi + LBS, SOS
- Known colors to verify live: ვარდისფერი, შავი, ლურჯი
- Must avoid if still out of stock: თეთრი

3) CT23
- Priority: მესამე
- Brand display: Wonlex CT23
- Positioning: ხელმისაწვდომი 4G Wonlex მოდელი მშობლისთვის საჭირო ძირითადი ფუნქციებით
- Regular price intent: live-ით გადაამოწმე
- Possible old discount note exists, but do not trust it without live confirmation
- Must emphasize: Wonlex, 4G, GPS, ვიდეო ზარი, SOS
- Include only colors that are live in stock

4) T53
- Priority: მეოთხე
- Positioning: სასაჩუქრე 4G bundle
- Regular price intent: 179 ლარი
- Must emphasize strongly:
  - ეს არის სასაჩუქრე ბოქსი
  - კომპლექტაციაში შედის დამატებითი აქსესუარები
  - თუ live source ადასტურებს: 2 ცვლადი სამაჯური / დეკორატიული აქსესუარი
- Listing angle: საჩუქრისთვის გამზადებული მოდელი + ვიდეო ზარი + GPS
- მთავარ ფოტოდ აირჩიე ის, რომელიც ყველაზე კარგად აჩენს gift-box ბუნებას

5) KT34
- Priority: მეხუთე
- Brand display: Wonlex KT34
- Positioning: Android სისტემაზე მომუშავე უფრო ძლიერი მოდელი
- Regular price intent: 229 ლარი
- Must emphasize strongly:
  - Android operating system
  - უფრო ძლიერი მახასიათებლები ვიდრე ბიუჯეტურ მოდელებში
  - GPS + WiFi + LBS
  - ვიდეო/ხმოვანი ზარი
  - თუ live source ადასტურებს: health/smart features
- Include only live in-stock colors
- Must avoid if still out of stock: შავი

Title rules:
- სათაური იყოს მოკლე, searchable და ქართულად ბუნებრივი
- სათაურში ყოველთვის ჩანდეს model code
- ჩასვი მხოლოდ 2-4 ყველაზე ძლიერი განმასხვავებელი ნიშანი
- არ გააკეთო keyword stuffing

Description rules:
- აღწერა იყოს მოკლე, სუფთა და product-first
- 2-3 პატარა აბზაცი ან კომპაქტური ჩამონათვალი
- სტრუქტურა:
  1. რა არის პროდუქტი და ვისთვისაა
  2. 3-6 მთავარი ფუნქცია
  3. ფერები / კომპლექტაცია / ფასდაკლება
- არ გამოიყენო გადაჭარბებული მარკეტინგული დაპირებები

Attribute normalization rules:
- თუ camera ველი სავალდებულოა და წყაროში წერია "<3MP", გამოიყენე conservative ვარიანტი 2MP
- თუ camera ველი სავალდებულოა და წყაროში წერია "3-7MP", გამოიყენე conservative ვარიანტი 3.2MP
- თუ memory card მხარდაჭერა ცხადად არ ჩანს, mandatory ველში მიუთითე "არა"
- warranty არ ჩაწერო description-ში, თუ live source-ში მკაფიოდ არ ჩანს

Photo rules:
- გამოიყენე მხოლოდ ამ პროდუქტის ფოტოები
- პირველი ფოტო იყოს ყველაზე ძლიერი hero image
- T53-ზე პრიორიტეტი მიეცი gift-box visuals-ს
- KT34-ზე პრიორიტეტი მიეცი advanced / Android / premium feel ვიზუალს
- თუ ფოტოებზე ლიმიტია, პრიორიტეტი: front view, side view, UI/features, packaging/accessories, color representation

Preview gate:
გამოქვეყნებამდე მაჩვენე ერთიანი summary ამ ფორმატით თითოეულ მოდელზე:
- SKU / მოდელი
- საბოლოოდ გამოყენებადი სათაური
- live verified price
- sale / discount handling
- ჩასმული ფერები და stock note
- ფოტოების რაოდენობა
- მოკლე აღწერა
- შევსებული key attributes
- რაიმე ambiguity / risk

ამის შემდეგ გაჩერდი და დაელოდე ჩემს დასტურს.

Publish stage:
ჩემი დასტურის შემდეგ:
1. გამოაქვეყნე ყველა 5 listing ზუსტად ამ რიგითობით: Q19 → X01 → CT23 → T53 → KT34
2. თითოეულის შემდეგ გადაამოწმე status
3. ბოლოს გახსენი თითოეული listing და გადაამოწმე:
   - სათაური
   - ფასი
   - ფასდაკლება
   - ფოტოები
   - ფერების ტექსტი / აღწერა

Final output format:
- Model
- Final title
- Final price
- Discount used or not
- Included colors
- Listing URL
- Status: active / pending moderation / failed
- Notes about conservative field choices

Completion condition:
ამოცანა დასრულებულია მხოლოდ მაშინ, როცა 5-ვე listing ან წარმატებით შექმნილია, ან მკაფიოდ დადასტურებულია pending moderation სტატუსში, და დაბრუნებულია საბოლოო ანგარიში.
```

## Reusable mini-template შემდეგი პარტიებისთვის

```text
Objective:
MyMarket-ზე განათავსე შემდეგი batch ამ თანმიმდევრობით: [MODEL_1] → [MODEL_2] → [MODEL_3]

Global rules:
- source of truth: live site/admin
- 1 listing თითო მოდელზე
- ფერები ერთ listing-ში
- მხოლოდ in-stock ფერები
- ჯერ preview, მერე approval, მერე publish
- არ ჩართო paid promo / online sales / installment

Per model:
- Model:
- Brand display:
- Positioning angle:
- Regular price:
- Sale price:
- Discount active:
- Must emphasize:
- Must mention:
- Must avoid:
- Verified colors/stock:
- Image priority:
- Notes / ambiguity:
```

## ოპერატორული შენიშვნა

თუ გინდა, შემდეგ ეტაპზე ამას გადავაქცევ:

- checklist-ად
- spreadsheet schema-დ
- და მოკლე SOP-ად Goal აგენტისთვის / ოპერატორისთვის
