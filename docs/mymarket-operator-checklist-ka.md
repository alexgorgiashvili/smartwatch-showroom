# MyMarket Operator Checklist

ეს checklist გამოიყენე სანამ Goal აგენტს გაუშვებ და შემდეგ მისი შედეგი გადაამოწმო.

## 1. დაწყებამდე

- MyMarket გახსნილია იმავე Chrome პროფილში
- პროფილი ავტორიზებულია
- category სწორია: ტექნიკა → მობილურები და აქსესუარები → სმარტ საათი
- listing type = გაყიდვა
- condition = ახალი
- paid promo/offline დამატებითი სერვისები გამორთულია

## 2. თითო მოდელზე სავალდებულო შემოწმება

- live source-ით დადასტურებულია ფასი
- live source-ით დადასტურებულია discount თუ არსებობს
- live source-ით დადასტურებულია stock
- out-of-stock ფერები გამორიცხულია
- ფოტოები ეკუთვნის სწორ პროდუქტს
- სათაურში ჩანს model code
- აღწერა ქართულადაა და არ არის ზედმეტად გაბერილი

## 3. ამ batch-ის სპეციალური წესები

- Q19:
  - discount მხოლოდ live დადასტურებით
  - აქცენტი: SOS / GPS / კამერა / ბიუჯეტური ფასი

- X01:
  - აქცენტი: 4G / ვიდეო ზარი / GPS + WiFi + LBS
  - თეთრი ფერი არ ჩასვა თუ ისევ out of stock არის

- CT23:
  - brand display: Wonlex CT23
  - discount მხოლოდ live დადასტურებით
  - ძველი ჩანაწერი არ ჩათვალო საკმარის მტკიცებულებად

- T53:
  - აუცილებლად წარმოაჩინე როგორც სასაჩუქრე ბოქსი
  - თუ live source ადასტურებს, ახსენე დამატებითი აქსესუარები
  - hero image იყოს gift-box ტიპის ვიზუალი

- KT34:
  - აუცილებლად გაუსვი ხაზი Android სისტემას
  - გაუსვი ხაზი მის უფრო ძლიერ specs/features-ს
  - შავი ფერი არ ჩასვა თუ ისევ out of stock არის

## 4. MyMarket field defaults

- SIM card = დიახ, თუ მოდელი რეალურად SIM-იანია
- camera field mandatory হলে:
  - `<3MP` → 2MP
  - `3-7MP` → 3.2MP
- memory card თუ მკაფიოდ არ დასტურდება → არა
- touch screen → დიახ
- discount percent შეიყვანე მხოლოდ integer-ად
- online sales / installment → გამორთული

## 4.1 UI instability check

- reload-ის ან state change-ის შემდეგ გადაამოწმე, რომ `ბრენდი / ტიპი / კამერა / SIM / memory card / touch screen` ველები ისევ რეალურად ჩანს
- თუ ეს ველები უცებ გაქრა:
  - category state თავიდან გადაამოწმე
  - form state აღადგინე
  - publish არ გააგრძელო ბრმად
  - ambiguity მონიშნე preview-ში
  - მიჰყევი [mymarket-ui-recovery-flow-ka.md](C:/laragon/www/smartwatch-showroom/docs/mymarket-ui-recovery-flow-ka.md)

## 5. Preview approval gate

გამოქვეყნებამდე preview-ში უნდა ჩანდეს:

- Model
- Title
- Price
- Discount handling
- Included colors
- Image count
- Short description
- Key attributes
- Risks / unclear fields

თუ რომელიმე ამ პუნქტიდან აკლია, publish არ უნდა მოხდეს.

## 6. Publish-ის შემდეგ შემოწმება

- listing URL გაიხსნა
- სათაური სწორია
- ფასი სწორია
- discount სწორია
- ფოტოები სწორია
- ფერების/მარაგის ტექსტი შეცდომაში არ შეიყვანს მომხმარებელს
- სტატუსი არის active ან pending moderation

## 7. Stop conditions

პროცესი უნდა გაჩერდეს თუ:

- მოითხოვა login / OTP / CAPTCHA
- live source და local data ერთმანეთს ეწინააღმდეგება კრიტიკულ ველში
- discount target ზუსტად ვერ ჯდება
- ფოტოები ვერ დადასტურდა სწორ მოდელზე
- listing form მოითხოვს ბუნდოვან ველს, რომლის შევსებაც რისკიანია
