# MyMarket Goal Prompt — მოკლე ოპერატორული ვერსია

```text
გამოიყენე Chrome-ის არსებული MyMarket session და იმუშავე მხოლოდ უკვე ავტორიზებულ პროფილში.

ამატებ 5 პროდუქტს ამ რიგითობით:
Q19 → X01 → CT23 → T53 → KT34

Source of truth:
- primary: live site/admin
- fallback: local workspace მხოლოდ მაშინ, როცა live მნიშვნელობა დროებით მიუწვდომელია

მუშაობის წესები:
- თითო მოდელზე შექმენი მხოლოდ 1 listing
- ფერები გააერთიანე ერთ listing-ში
- ჩასვი მხოლოდ in-stock ფერები
- ყველა ტექსტი იყოს ქართულად
- არ ჩართო online sales, installment, paid promo, shop opening, balance top-up
- არ გამოიგონო specs, stock, colors, discount, accessories ან warranty
- თუ login / OTP / CAPTCHA დაგხვდა, გაჩერდი და დაელოდე user-ის browser interaction-ს

სპეციფიკური აქცენტები:
- Q19: თუ live source ადასტურებს discount-ს, გამოიყენე 79 → 59 ლარი
- CT23: discount გამოიყენე მხოლოდ live დადასტურების შემთხვევაში
- T53: წარმოაჩინე როგორც სასაჩუქრე ბოქსი / gift bundle
- KT34: გაუსვი ხაზი Android სისტემას და მის უფრო ძლიერ მახასიათებლებს

Preview-first რეჟიმი:
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

გამოქვეყნებამდე გაჩერდი და დაელოდე approval-ს.

Approval-ის შემდეგ:
- გამოაქვეყნე ყველა 5 listing ზუსტად მითითებული რიგითობით
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
```
