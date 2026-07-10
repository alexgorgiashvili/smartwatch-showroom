# MyMarket UI Recovery Flow

ეს დოკი საჭიროა იმიტომ, რომ MyMarket create-listing form ზოგჯერ state-dependent ან არასტაბილურად იცვლება.

მთავარი მაგალითი უკვე დადასტურდა:

- ერთ dry-run-ში ჩანდა `ბრენდი / სმარტ საათის ტიპი / კამერა / SIM / memory card / touch screen`
- reload-ის შემდეგ იგივე page flow-ში ეს ველები გაქრა

ამიტომ Goal აგენტმა publish-მდე უნდა იმუშაოს ამ recovery წესებით.

## 1. Normal state — რა უნდა ჩანდეს კარგ მდგომარეობაში

სანამ listing-ის შევსებას გააგრძელებ, დარწმუნდი, რომ ჩანს:

- Category path
- Price
- Discount
- Delivery section
- Brand
- Smart watch type
- SIM
- Camera
- Memory card
- Touch screen
- Title
- Description
- Location

თუ ეს ბლოკებიდან რომელიმე კრიტიკული ნაწილი არ ჩანს, არ გააგრძელო ბრმად.

## 2. Recovery trigger — როდის ჩაითვლება UI პრობლემურად

Recovery რეჟიმში გადადი თუ:

- reload-ის შემდეგ გაქრა attribute ველები
- category path დაბრუნდა generic ან ცარიელ მდგომარეობაში
- form-ის შუა ნაწილი უცებ შემცირდა და მხოლოდ title/description დარჩა
- combobox აღარ შეესაბამება მანამდე აღმოჩენილ id/state-ს
- preview-სთვის საჭირო ველები აღარ იკითხება

## 3. Recovery steps — ზუსტი რიგითობა

1. შეაჩერე field filling

- აღარ გააგრძელო სხვა ველების შევსება
- არაფერი გამოაქვეყნო

2. გადაამოწმე category state

- დარწმუნდი, რომ category რეალურად ისევ არის:
  - ტექნიკა → მობილურები და აქსესუარები → სმარტ საათი

3. გადაამოწმე listing basics

- listing type = გაყიდვა
- condition = ახალი

4. გადაამოწმე, დაბრუნდა თუ არა attribute block

- თუ `ბრენდი / ტიპი / კამერა / SIM / memory card / touch screen` ისევ ჩანს, workflow გააგრძელე
- თუ ისევ არ ჩანს, ჩათვალე UI unstable

5. გამოიყენე conservative continuation rule

- თუ პროდუქტის გამოსაქვეყნებლად ეს ველები აუცილებელია და არ ჩანს, publish არ გააგრძელო
- preview-ში მონიშნე:
  - `UI instability`
  - `required attributes not visible`
  - `publish paused`

6. მხოლოდ ამის შემდეგ მიიღე გადაწყვეტილება

- ან state აღდგა და workflow გრძელდება
- ან პროცესი ჩერდება preview ეტაპზე და ელოდება operator approval / manual check-ს

## 4. რა არ უნდა ქნას აგენტმა

- არ უნდა გამოიგონოს field selection
- არ უნდა ივარაუდოს, რომ დამალული ველები მაინც სწორად ჩაიწერა
- არ უნდა გამოაქვეყნოს listing იმ იმედით, რომ missing fields optional იყო
- არ უნდა შეცვალოს category შემთხვევითად მხოლოდ იმისთვის, რომ ველები “გამოაჩინოს”

## 5. Safe fallback rules

- brand fallback:
  - `Wonlex` როცა live dropdown-ში ზუსტად არსებობს და მოდელი Wonlex-ია
  - `Generic` როცა ზუსტი ბრენდი არ ჩანს და conservative fallback საჭიროა

- discount fallback:
  - მხოლოდ integer პროცენტი
  - თუ target price ვერ ჯდება, preview-ში აცდენა მონიშნე

- location fallback:
  - candidate: `თბილისი`
  - მაგრამ publish-მდე გადაამოწმე რეალურ ოპერაციულ მდებარეობასთან

## 6. Preview note template

თუ UI instability მოხდა, preview-ში გამოიყენე დაახლოებით ასეთი ჩანაწერი:

```text
Risk note:
Reload/state change-ის შემდეგ MyMarket form-ში attribute ველები ნაწილობრივ გაქრა.
Category/form state თავიდან გადავამოწმე.
Publish შეჩერებულია, სანამ required fields კვლავ ცხადად არ გამოჩნდება ან ოპერატორი არ დაადასტურებს გაგრძელებას.
```

## 7. Publish gate

Publish შეიძლება მხოლოდ მაშინ, როცა:

- required fields კვლავ ჩანს
- critical values live-ით გადამოწმებულია
- preview დამტკიცებულია

სხვა შემთხვევაში სწორი ქცევა არის pause, არა guesswork.
