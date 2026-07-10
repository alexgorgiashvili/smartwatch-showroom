# MyMarket Goal Prompt — საბოლოო copy-paste ვერსია

```text
გამოიყენე Chrome-ის არსებული MyMarket session და იმუშავე მხოლოდ უკვე ავტორიზებულ პროფილში.

Objective:
MyMarket-ზე დაამატე 5 პროდუქტი ამ ზუსტი რიგითობით:
Q19 → X01 → CT23 → T53 → KT34

Source of truth:
- primary: live site/admin
- fallback: local workspace მხოლოდ მაშინ, როცა live მნიშვნელობა დროებით მიუწვდომელია

Global rules:
- თითო მოდელზე შექმენი მხოლოდ 1 listing
- ფერები გააერთიანე ერთ listing-ში
- listing-ში მოხვდეს მხოლოდ in-stock ფერები
- ყველა ტექსტი იყოს ქართულად
- არ ჩართო online sales, installment, paid promo, shop opening, balance top-up ან სხვა დამატებითი ფასიანი სერვისი
- არ გამოიგონო specs, stock, colors, discount, accessories, warranty ან brand claim
- თუ login / CAPTCHA / OTP / phone verification დაგხვდა, გაჩერდი და დაელოდე user-ის browser interaction-ს
- თუ ველი სავალდებულოა, მაგრამ მნიშვნელობა ზუსტად ვერ დასტურდება, გამოიყენე conservative ვარიანტი და ეს მონიშნე preview-ში
- discount პროცენტი ჩაწერე მხოლოდ integer-ად
- თუ reload/state change-ის შემდეგ ზოგი attribute field გაქრა, ჯერ გადაამოწმე category/form state; არ გააგრძელო ბრმად
- თუ UI instability დაფიქსირდა, მიჰყევი `docs/mymarket-ui-recovery-flow-ka.md`-ში აღწერილ recovery წესს

MyMarket defaults:
- Category: ტექნიკა → მობილურები და აქსესუარები → სმარტ საათი
- Listing type: გაყიდვა
- Condition: ახალი
- Language: ქართული
- memory card თუ ზუსტად არ დასტურდება: არა
- touch screen: დიახ
- camera mapping:
  - <3MP → 2MP
  - 3-7MP → 3.2MP
- location:
  - თუ ოპერატორისგან სხვა მითითება არ გაქვს, უსაფრთხო default candidate არის `თბილისი`
  - მაგრამ publish-მდე გადაამოწმე, რომ ეს შეესაბამება რეალურ ოპერაციულ მდებარეობას

Per-model instructions:

1) Q19
- positioning: ბიუჯეტური 2G საბავშვო საათი SOS-ით, GPS/LBS-ით და კამერით
- თუ live source ადასტურებს ფასდაკლებას, გამოიყენე 79 → 59 ლარი
- თუ live source discount-ს აღარ ადასტურებს, განათავსე ჩვეულებრივ ფასად და preview-ში მკაფიოდ მონიშნე
- გაამახვილე ყურადღება: SOS, GPS/LBS, კამერა, ბიუჯეტური ფასი

2) X01
- positioning: 4G ვიდეოზარიანი მოდელი ყოველდღიური გამოყენებისთვის
- გაამახვილე ყურადღება: 4G, ვიდეო ზარი, GPS + WiFi + LBS, SOS
- თუ თეთრი ფერი ისევ out of stock არის, არ ჩასვა listing-ში

3) CT23
- brand display: Wonlex CT23
- positioning: ხელმისაწვდომი 4G Wonlex მოდელი მშობლისთვის საჭირო ძირითადი ფუნქციებით
- discount გამოიყენე მხოლოდ თუ live source-ში ნამდვილად ჩანს აქტიური ფასდაკლება
- თუ discount არ ჩანს, განათავსე ჩვეულებრივ ფასად
- ძველი ჩანაწერი არ გამოიყენო discount-ის დასამტკიცებლად
- გაამახვილე ყურადღება: Wonlex, 4G, GPS, ვიდეო ზარი, SOS

4) T53
- positioning: სასაჩუქრე 4G საბავშვო საათი / gift bundle
- “სასაჩუქრე ბოქსი” ჩასვი სათაურშიც და აღწერაშიც
- თუ live source ადასტურებს, ახსენე დამატებითი აქსესუარები
- მთავარ ფოტოდ აირჩიე ის, რომელიც ყველაზე კარგად აჩენს gift-box ბუნებას
- გაამახვილე ყურადღება: gift angle, ვიდეო ზარი, GPS

5) KT34
- brand display: Wonlex KT34
- positioning: Android სისტემაზე მომუშავე უფრო ძლიერი მოდელი
- “Android” ჩასვი სათაურში
- გაამახვილე ყურადღება: Android, GPS + WiFi + LBS, ვიდეო/ხმოვანი ზარი, ძლიერი features
- თუ შავი ფერი ისევ out of stock არის, არ ჩასვა listing-ში

Title rules:
- სათაურში ჩანდეს model code
- სათაური იყოს მოკლე, searchable და ბუნებრივი
- ჩასვი მხოლოდ 2-4 ყველაზე ძლიერი განმასხვავებელი ნიშანი
- არ გააკეთო keyword stuffing

Description rules:
- აღწერა იყოს მოკლე, product-first და გაყიდვადი
- სტრუქტურა:
  1. რა არის პროდუქტი და ვისთვისაა
  2. 3-6 მთავარი ფუნქცია
  3. ფერები / კომპლექტაცია / ფასდაკლება

Preview-first gate:
გამოქვეყნებამდე მოამზადე preview ყველა 5 პროდუქტზე და მაჩვენე:
- model
- final title
- verified price
- discount handling
- included colors
- image count
- short description
- key attributes
- ambiguity / risk

ამ ეტაპზე გაჩერდი და დაელოდე approval-ს.

Approval-ის შემდეგ:
- გამოაქვეყნე ყველა 5 listing ზუსტად ამ რიგითობით: Q19 → X01 → CT23 → T53 → KT34
- თითოეულის შემდეგ გადაამოწმე სტატუსი
- ბოლოს დააბრუნე:
  - model
  - final title
  - final price
  - discount used or not
  - included colors
  - listing URL
  - status
  - conservative field notes

Completion condition:
ამოცანა დასრულებულია მხოლოდ მაშინ, როცა 5-ვე listing ან წარმატებით შექმნილია, ან დადასტურებულია pending moderation სტატუსში, და დაბრუნებულია საბოლოო ანგარიში.
```
