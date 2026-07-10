# MyMarket Live Form Mapping

თარიღი: `2026-07-06`
წყარო: არსებული Chrome session-ში გახსნილი `https://mymarket.ge/ka/pr-form/`
სტატუსი: `dry-run inspection only`, გამოქვეყნება არ მომხდარა

## დადასტურებული მდგომარეობა

- მომხმარებელი შესულია ანგარიშში.
- MyMarket create-listing form უკვე გახსნილია.
- კატეგორია უკვე დაყენებულია:
  - `ტექნიკა -> მობილურები და აქსესუარები -> სმარტ საათი`
- გვერდზე ჩანს publish ღილაკი, მაგრამ არ დაჭერილა.

## Live Field Mapping

### ძირითადი ბლოკი

- `განცხადების ტიპი`
  - radio options:
    - `გაყიდვა`
    - `შეძენა`
    - `გაქირავება`
    - `მომსახურება`

- `აირჩიე/ჩაწერე კატეგორია *`
  - category selector არსებობს
  - მიმდინარე მნიშვნელობა: `ტექნიკა -> მობილურები და აქსესუარები -> სმარტ საათი`

- `ნივთის მდგომარეობა`
  - radio options:
    - `მეორადი`
    - `ახალი`
    - `ახალივით`
    - `ნაწილებად`

- `ფოტოს ატვირთვა`
  - მაქსიმუმ `12 ფოტო`
  - ცალკე `ატვირთვა` button ჩანს

- `Youtube ვიდეო`
  - optional textbox

### ფასი და გაყიდვის პარამეტრები

- `მიუთითე ნივთის ფასი*`
  - numeric textbox
  - ინსპექციის დროს მიმდინარე მნიშვნელობა იყო `59`
  - currency label: `ლარი`

- `ფასდაკლება *`
  - separate textbox
  - UI suffix: `%`
  - ეს ადასტურებს, რომ discount percent პირდაპირ field-ად არის შეყვანილი

- `ფასი შეთანხმებით`
  - ცალკე toggle/checkbox-like option ჩანს

- `მინდა გაყიდვა საიტზე`
  - online-sales section ჩანს
  - შიგნით გამოჩნდა:
    - `TBC განვადება`
    - `BOG განვადება`
    - `Credo განვადება`
    - `საზღვარგარეთ გაგზავნა`
    - `უპროცენტო გადანაწილება`
    - `გადანაწილების საკომისიო`
  - ამ ბლოკში თანხის ავტომატური გამოთვლებიც ჩანს
  - ჩვენი გეგმისთვის ეს ნაწილი უნდა დარჩეს გამორთული

### მიწოდება

- `მიწოდების ფორმა`
  - checkbox/button options:
    - `მე გადავცემ მყიდველს ნივთს`
    - `2-3 საათში გაგზავნა`
  - მეორე ვარიანტის აღწერა პირდაპირ ემთხვევა ჩვენს დაგეგმილ delivery messaging-ს

### ძირითადი მახასიათებლები

- `ბრენდი`
  - combobox
  - new brand entry შესაძლებელი ჩანს

- `სმარტ საათის ტიპი *`
  - combobox

- `სიმ ბარათი *`
  - radio:
    - `დიახ`
    - `არა`

- `კამერა *`
  - combobox

- `მეხსიერების ბარათი *`
  - radio:
    - `დიახ`
    - `არა`

- `სენსორული ეკრანი *`
  - radio:
    - `დიახ`
    - `არა`

### სათაური და აღწერა

- `დასახელება *`
  - textbox
  - ინსპექციის დროს prefilled value იყო: `სმარტ საათი`
  - ეს ნიშნავს, რომ automation-მა აუცილებლად უნდა ჩაანაცვლოს ეს generic მნიშვნელობა კონკრეტული model title-ით

- `აღწერა`
  - rich text editor-like textbox
  - limit counter ჩანს: `4000 / 4000`

- დამატებითი ბლოკები:
  - `Add Product Description in English`
  - `Добавить описание продукта на русском`
  - ეს optional ჩანს და ჩვენი პირველი batch-ისთვის საჭირო არ არის

### საკონტაქტო ინფორმაცია

- `აირჩიე მდებარეობა *`
  - combobox

- `სახელი *`
  - textbox
  - მიმდინარე მნიშვნელობა: `alex gorgiashvili`

- `მობილურის ნომერი *`
  - textbox
  - phone value არ დაფიქსირებულა დოკუმენტში უსაფრთხოების მიზნით

### პრომო სერვისები

- `VIP`
- `VIP+`
- `SUPER VIP`
- `ფერი`
- `ავტომატური განახლება`

ეს ბლოკი ფორმის ქვედა ნაწილში ჩანს, მაგრამ ჩვენი workflow-ისთვის არ უნდა ჩაირთოს.

## Automation Implications

- exporter/prompt-ში უკვე უნდა გვქონდეს:
  - price
  - discount percent or target price logic
  - condition=`ახალი`
  - category path
  - delivery choice
  - brand
  - watch type
  - sim yes/no
  - camera field mapping
  - memory card yes/no
  - touchscreen yes/no
  - title
  - description

- live form confirms, რომ `discount` პროცენტში ივსება და არა პირდაპირ discounted price-ით.
- live form confirms, რომ `memory card` ველი mandatory-ია და conservative default `არა` სწორია, თუ live source explicit confirm არ იძლევა.
- live form confirms, რომ `2-3 საათში გაგზავნა` ზუსტად როგორც ოპერატიული არჩევანი არსებობს.
- live form confirms, რომ online-sales/installment block ცალკეა და შეგვიძლია მიზანმიმართულად არ ჩავრთოთ.

## Known Gaps After Dry-Run

- არ დაგვიდასტურებია combobox option lists შიგნით:
  - `ბრენდი`
  - `სმარტ საათის ტიპი`
  - `კამერა`
  - `მდებარეობა`
- არ დაგვიდასტურებია discount textbox იღებს თუ არა მხოლოდ integer value-ს ან decimal-ს.
- არ გაგვიტარებია image upload dry-run.
- არ დაგვიფიქსირებია form validation errors publish ღილაკის დაჭერის გარეშე.

## დამატებითი live findings — მეორე dry-run

- `ბრენდი` dropdown-ის გახსნისას დაფიქსირდა დიდი სია და მასში ნამდვილად არსებობს:
  - `Wonlex`
  - `Generic`
- ეს მნიშვნელოვანია, რადგან:
  - `CT23` და `KT34` შესაძლებელია `Wonlex` ბრენდით წავიდეს
  - `Q19`, `X01`, `T53` თუ ზუსტი ბრენდი ცალკე არ არსებობს, conservative fallback შეიძლება იყოს `Generic`

- `ფასდაკლება` ველზე დამატებითი ტესტი:
  - `25.5` შეყვანის მცდელობის შემდეგ value არ დამიჯდა და დარჩა `0`
  - `25` integer value წარმატებით ჩაიწერა
- აქედან პრაქტიკული დასკვნა:
  - discount field რეალურად integer-only ქცევას ავლენს
  - Goal agent-მა percent უნდა ითვალოს integer-ად
  - თუ target final price integer პროცენტით ზუსტად არ ჯდება, preview-ში უნდა მონიშნოს აცდენა

- `აირჩიე მდებარეობა` dropdown გახსნისას ჩანს დიდი location grid.
  - snapshot-ში დადასტურებული მაგალითები:
    - `თბილისი`
    - `ბათუმი`
    - `გორი`
    - `გურჯაანი`
    - `რუსთავი` არ ჩანდა ამ კონკრეტულ visible block-ში, მაგრამ სია აშკარად მრავალქალაქიანია
- პრაქტიკული default:
  - თუ ოპერატორს სხვა მითითება არ აქვს, პირველ არჩევანად `თბილისი` არის უსაფრთხო candidate, მაგრამ publish-მდე სასურველია account-side რეალურ ოპერაციულ მდებარეობასთან გადამოწმება

- reload-ის შემდეგ form state არასტაბილურად შეიცვალა:
  - ადრე snapshot-ში ჩანდა `ბრენდი / სმარტ საათის ტიპი / კამერა / სიმ ბარათი / მეხსიერების ბარათი / სენსორული ეკრანი`
  - reload-ის შემდეგ იგივე form state-ში `ძირითადი მახასიათებლები` ბლოკში ეს ველები აღარ ჩანდა და დარჩა მხოლოდ:
    - სათაური
    - აღწერა
    - შშმპ checkbox
  - ასევე category/path snapshot-ში დაბრუნდა უფრო generic მდგომარეობაში
- აქედან მნიშვნელოვანი ოპერატორული დასკვნა:
  - MyMarket create form-ს აქვს არასტაბილური ან state-dependent rendering
  - Goal agent-მა publish-მდე უნდა გადაამოწმოს, რომ საჭირო ატრიბუტის ველები რეალურად ჩანს მიმდინარე UI state-ში
  - თუ ეს ველები არ ჩანს, აგენტმა არ უნდა გამოიგონოს შევსება; უნდა:
    1. გადაამოწმოს category state
    2. სცადოს form state-ის აღდგენა
    3. ambiguity მონიშნოს preview-ში

## Recommended Next Browser Step

შემდეგ dry-run-ში, publish-ის გარეშე, შეგვიძლია დავადასტუროთ:

- `ბრენდი` combobox values
- `სმარტ საათის ტიპი` შესაბამისი არჩევანი
- `კამერა` field-ის რეალური option format
- `მეხსიერების ბარათი=არა` და `სენსორული ეკრანი=დიახ` flow
- `მდებარეობა` default/selectable values
- discount textbox behavior

## დამატებითი live findings — boolean ატრიბუტების ქცევა

- `სიმ ბარათი`, `მეხსიერების ბარათი`, `სენსორული ეკრანი` ველები ვიზუალურად radio-control-ებია, მაგრამ DOM-ში მათი `checked=true` მდგომარეობა სანდო ინდიკატორი არ აღმოჩნდა.
- პრაქტიკაში არჩეული მნიშვნელობა ჩანს `input.btn-check` ელემენტზე დაკიდებული `active` class-ით.
- მაგალითად dry-run-ში დადასტურდა:
  - `radio-Attr-3030-0` = SIM `დიახ`
  - `radio-Attr-3041-1` = Memory card `არა`
  - `radio-Attr-3042-0` = Touch screen `დიახ`
- ამ ველებზე Playwright-ის ჩვეულებრივი `check()`/`setChecked()` შეიძლება შეცდომით ჩავთვალოთ, რომ არ მუშაობს, მიუხედავად იმისა, რომ UI state რეალურად იცვლება.
- უსაფრთხო ოპერატორული წესი:
  - radio input-ის ნაცვლად დააჭირე შესაბამის `label[for="..."]` ელემენტს
  - შემდეგ გადაამოწმე `active` class გადავიდა თუ არა სწორ ვარიანტზე
  - მხოლოდ ამის შემდეგ ჩათვალე boolean ატრიბუტი შევსებულად

## დამატებითი live findings — Q19 dry-run პროგრესი

- `Q19`-ზე dry-run-ით დადასტურდა:
  - Category path სწორად დაჯდა
  - `CatID=978`
  - `Brand=Generic` დაჯდა და `BrandID=5345`
  - `Smart watch type=საათი` დაჯდა და `Attr-7451=7460`
  - `Camera` options რეალურად შეიცავს `3.2` მნიშვნელობას
  - `Q19`-ისთვის `Camera=3.2` დაჯდა და `Attr-3031=3039`
- core fields rehearsal-ით ასევე ჩაიწერა:
  - price
  - integer discount
  - title
  - description
- location commit-იც დადასტურდა:
  - `#react-select-5-input`-ში `თბილისი`
  - შემდეგ `ArrowDown`
  - შემდეგ `Enter`
  - შედეგი: `LocID=320`
- ამ ეტაპზე დაუდასტურებელი დარჩა:
  - image upload dry-run
  - final preview submission without publish

## დამატებითი live findings — image upload limitation

- `Photos` input რეალურად არსებობს:
  - `input[name="Photos"]`
  - `multiple=true`
  - accept:
    - `image/jpeg`
    - `.jpeg`
    - `.jpg`
    - `image/png`
    - `.heic`
    - `.heif`
    - `image/webp`
- local test image მზად იყო:
  - `storage/app/public/images/products/2g-network-kids-smart-watch-anti-lost-sos-gps-video-call/01.jpg`
- chooser launch-ის რამდენიმე გზა გამოიცადა:
  - visible `ატვირთვა` button
  - hidden `input[name="Photos"]` force click
  - `.file_input_inner[role="presentation"]` wrapper click
  - visible DOM node click `ფოტოს ატვირთვა მაქსიმუმ 12 ფოტო`
- ყველა შემთხვევაში Chrome bridge-ში შედეგი იყო ერთი და იგივე:
  - `filechooser` event timeout
- პრაქტიკული დასკვნა:
  - upload flow MyMarket-ის ამ UI state-ში Chrome bridge-იდან სტაბილურად არ იხსნება
  - listing automation-ის დარჩენილი საიმედო ნაწილი არის form filling და attribute mapping
  - image upload-ს ან ცალკე ხელით ჩარევა დასჭირდება, ან სხვა browser/runtime workaround
