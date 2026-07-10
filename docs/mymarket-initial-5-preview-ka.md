# MyMarket Initial 5 Preview

ეს დოკი არის `preview-first` სამუშაო ფაილი პირველი 5 მოდელისთვის.

მიზანი:
- თითოეულ მოდელზე გვქონდეს წინასწარ შეთანხმებული listing ტექსტი
- Chrome-ით draft/preview შევსებისას ტექსტი პირდაპირ ამ ფაილიდან ავიღოთ
- publish-მდე ხელახლა გადავამოწმოთ მხოლოდ live ფასები, stock, ფერები და ფოტოები

გლობალური წესები:
- Category: ტექნიკა → მობილურები და აქსესუარები → სმარტ საათი
- Listing type: გაყიდვა
- Condition: ახალი
- ერთი listing თითო მოდელზე
- ფერები ერთ listing-ში ერთიანდება
- მხოლოდ in-stock ფერები
- Online sales / installment / paid promo: გამორთული
- Max photos: 12
- Memory card თუ ველი სავალდებულოა და ზუსტად არ დასტურდება: არა
- Touch screen: დიახ
- Camera mapping:
  - `<3MP` → `2MP`
  - `3 - 7MP` → `3.2MP`
- Location candidate: თბილისი
- Publish-მდე გადასამოწმებელი ველები:
  - ფასი
  - ფასდაკლება
  - ფერები
  - stock
  - ფოტოები

## 1. Q19

Model:
- `Q19`

Proposed title:
- `Q19 საბავშვო სმარტ საათი 2G SIM SOS კამერით`

Price plan:
- Base price: `79 GEL`
- Discount plan: `59 GEL` მხოლოდ თუ live source ადასტურებს აქტიურ ფასდაკლებას
- Discount handling: integer discount field გამოიყენე მხოლოდ live დადასტურების შემდეგ

Included colors:
- `მწვანე`
- `წითელი`
- `იასამნისფერი`

Excluded colors:
- none

Image plan:
- Planned image count: `8`
- მთავარი ფოტო: ის, სადაც საათი ყველაზე მკაფიოდ ჩანს და ბავშვის უსაფრთხოების device-სავით იკითხება

Short description:
- `Q19 არის ბიუჯეტური საბავშვო სმარტ საათი SIM ბარათის მხარდაჭერით, SOS ღილაკით, კამერით და ლოკაციის კონტროლის ფუნქციებით. მშობლისთვის მოსახერხებელი ვარიანტია ყოველდღიური კავშირის და უსაფრთხოების საბაზისო საჭიროებებისთვის.`

Key features to mention:
- `2G`
- `SIM ბარათი`
- `SOS ღილაკი`
- `კამერა`
- `GPS/LBS კონტროლი`
- `1.44" OLED ეკრანი`
- `IP67`

Suggested attributes:
- Brand: `Generic` თუ `Q19` როგორც brand არ არსებობს dropdown-ში
- Smart watch type: საბავშვო / kids category-სთან ყველაზე ახლო ვარიანტი
- SIM: `დიახ`
- Camera: `3.2MP`
- Memory card: `არა`
- Touch screen: `დიახ`

Commercial angle:
- მთავარი აქცენტი ფასდაკლებაზე თუ live-ში აქტიურია
- მეორე აქცენტი უსაფრთხოების ფუნქციებზე

Risk / ambiguity:
- ფასდაკლება live-ით უნდა დადასტურდეს
- Brand dropdown-ში ზუსტი მოდელი შეიძლება არ არსებობდეს

## 2. X01

Model:
- `X01`

Proposed title:
- `X01 საბავშვო სმარტ საათი 4G ვიდეო ზარით და GPS-ით`

Price plan:
- Final price target: `109 GEL`
- Discount: `არა`, თუ live source-ში ახალი ფასდაკლება არ გამოჩნდა

Included colors:
- `ვარდისფერი`
- `შავი`
- `ლურჯი`

Excluded colors:
- `თეთრი` because current stock is `0`

Image plan:
- Planned image count: `5`
- მთავარი ფოტო: საათის წინა მხარე, სადაც ეკრანი და ფორმა მკაფიოდ ჩანს

Short description:
- `X01 არის 4G საბავშვო სმარტ საათი ვიდეო ზარით, კამერით და ლოკაციის მონიტორინგით. მოდელი გათვლილია ყოველდღიურ კომუნიკაციაზე და მშობლისთვის კომფორტულ კონტროლზე GPS, WiFi და LBS ფუნქციების დახმარებით.`

Key features to mention:
- `4G`
- `ვიდეო ზარი`
- `GPS + WiFi + LBS`
- `SOS`
- `კამერა`
- `IP67`
- `650mAh`

Suggested attributes:
- Brand: `Generic`
- Smart watch type: საბავშვო / kids-თან ყველაზე ახლო ვარიანტი
- SIM: `დიახ`
- Camera: `2MP`
- Memory card: `არა`
- Touch screen: `დიახ`

Commercial angle:
- აქცენტი კომუნიკაციაზე: ვიდეო ზარი + 4G
- მეორე აქცენტი მდებარეობის კონტროლზე

Risk / ambiguity:
- თეთრი ფერი live-ში ხელახლა უნდა შემოწმდეს

## 3. CT23

Model:
- `CT23`

Proposed title:
- `Wonlex CT23 საბავშვო სმარტ საათი 4G GPS ვიდეო ზარით`

Price plan:
- Current price target: `169 GEL`
- Discount: მხოლოდ თუ live source-ში აქტიური ფასდაკლება რეალურად ჩანს
- ძველი ჩანაწერი `179 → 149` არ გამოიყენება დადასტურების გარეშე

Included colors:
- `შავი`

Excluded colors:
- `ლურჯი` because current stock is `0`
- `ვარდისფერი` because current stock is `0`

Image plan:
- Planned image count: `6`
- მთავარი ფოტო: ის, სადაც `Wonlex` ვიზუალურად ან premium look ყველაზე კარგად ჩანს

Short description:
- `Wonlex CT23 არის 4G საბავშვო სმარტ საათი GPS ტრეკერით, ვიდეო ზარით და SOS ფუნქციით. მოდელი გამოდგება მშობლისთვის, ვისაც სჭირდება სტაბილური კავშირი, ლოკაციის კონტროლი და ბრენდირებული არჩევანი ხელმისაწვდომ ფასში.`

Key features to mention:
- `Wonlex`
- `4G`
- `GPS`
- `ვიდეო ზარი`
- `SOS`
- `Nano-SIM`
- `1.83" IPS ეკრანი`

Suggested attributes:
- Brand: `Wonlex`
- Smart watch type: საბავშვო / kids-თან ყველაზე ახლო ვარიანტი
- SIM: `დიახ`
- Camera: `2MP`
- Memory card: `არა`
- Touch screen: `დიახ`

Commercial angle:
- ბრენდის ხაზი: `Wonlex`
- ფასის ხაზი: ხელმისაწვდომი 4G მოდელი

Risk / ambiguity:
- live discount შეიძლება იყოს შეცვლილი
- stock ძალიან დაბალია, ამიტომ publish-მდე live stock აუცილებლად გადასამოწმებელია

## 4. T53

Model:
- `T53`

Proposed title:
- `T53 სასაჩუქრე ბოქსი საბავშვო სმარტ საათი 4G ვიდეო ზარით`

Price plan:
- Final price target: `179 GEL`
- Discount: `არა`, თუ live-ში სხვა რამ არ დადასტურდა

Included colors:
- `ვარდისფერი`
- `ლურჯი`

Excluded colors:
- none

Image plan:
- Planned image count: `7`
- მთავარი ფოტო: ის, რომელიც ყველაზე კარგად აჩენს gift-box კომპლექტაციას

Short description:
- `T53 არის სასაჩუქრე ბოქსის ფორმატში მომზადებული 4G საბავშვო სმარტ საათი ვიდეო ზარით, GPS კონტროლით და SOS ფუნქციით. კარგი არჩევანია საჩუქრადაც, რადგან კომპლექტაციაში აქცენტი გაკეთებულია ვიზუალზე და დამატებით ელემენტებზე.`

Key features to mention:
- `სასაჩუქრე ბოქსი`
- `4G`
- `ვიდეო ზარი`
- `GPS`
- `SOS`
- `2 ცვლადი სამაჯური`
- `დეკორატიული აქსესუარი`

Suggested attributes:
- Brand: `Generic` თუ dropdown-ში `T53` ან ზუსტი ბრენდი არ არსებობს
- Smart watch type: საბავშვო / kids-თან ყველაზე ახლო ვარიანტი
- SIM: `დიახ`
- Camera: `2MP`
- Memory card: `არა`
- Touch screen: `დიახ`

Commercial angle:
- მთავარი გაყიდვადი ხაზი არის `gift positioning`
- სათაურშიც და აღწერაშიც უნდა ჩანდეს `სასაჩუქრე ბოქსი`

Risk / ambiguity:
- დამატებითი აქსესუარები publish-მდე live/visual confirmation-ით უნდა დადასტურდეს

## 5. KT34

Model:
- `KT34`

Proposed title:
- `Wonlex KT34 Android საბავშვო სმარტ საათი 4G GPS-ით`

Price plan:
- Final price target: `229 GEL`
- Discount: `არა`, თუ live-ში სხვა რამ არ დადასტურდა

Included colors:
- `ლურჯი`
- `ვარდისფერი`

Excluded colors:
- `შავი` because current stock is `0`

Image plan:
- Planned image count: `6`
- მთავარი ფოტო: ის, სადაც ეკრანის ზომა და model premium feel ყველაზე კარგად ჩანს

Short description:
- `Wonlex KT34 არის Android სისტემაზე მომუშავე 4G საბავშვო სმარტ საათი GPS, WiFi და LBS მხარდაჭერით. ეს მოდელი უფრო ძლიერი არჩევანია მათთვის, ვისაც უნდა ვიდეო ზარი, ხმოვანი კავშირი და გამორჩეული ფუნქციები ერთი მოწყობილობით.`

Key features to mention:
- `Android`
- `4G`
- `GPS + WiFi + LBS`
- `ვიდეო და ხმოვანი ზარი`
- `გულისცემის მონიტორი`
- `ძილის მონიტორინგი`
- `800mAh`

Suggested attributes:
- Brand: `Wonlex`
- Smart watch type: საბავშვო / kids-თან ყველაზე ახლო ვარიანტი
- SIM: `დიახ`
- Camera: `2MP`
- Memory card: `არა`
- Touch screen: `დიახ`

Commercial angle:
- მთავარი აქცენტი არის `Android`
- მეორე აქცენტი არის უფრო ძლიერი ფუნქციების ნაკრები

Risk / ambiguity:
- შავი ფერი live-ში ხელახლა უნდა შემოწმდეს
- Smart watch type dropdown-ის ზუსტი არჩევანი Chrome draft შევსებისას უნდა დავადასტუროთ

## Draft Fill Order

1. `Q19`
2. `X01`
3. `CT23`
4. `T53`
5. `KT34`

## Before Draft Fill

- live admin/source-ში გადავამოწმოთ ფასი, ფასდაკლება, ფერები, stock, ფოტოები
- Chrome-ში გადავამოწმოთ, რომ attribute block სრულად ჩანს
- თუ category/form state აირია, ვიხელმძღვანელოთ `docs/mymarket-ui-recovery-flow-ka.md`-ით

## Ready For Next Step

ეს preview პაკეტი მზად არის draft/preview შევსების ეტაპისთვის, მაგრამ publish-ready არ ჩაითვლება live recheck-ის გარეშე.
