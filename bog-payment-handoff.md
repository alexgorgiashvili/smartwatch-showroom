# BOG_PAYMENT (საქართველოს ბანკის ონლაინ გადახდა) — ჰენდოფი

ეს დოკუმენტი აჯამებს პროექტში არსებულ BOG გადახდის ინტეგრაციას, რათა მარტივად გადაიტანო სხვა პროექტში. ყველა კონფიგურაცია მოცემულია key-ების დონეზე; საიდუმლო მნიშვნელობები არ არის ჩადებული.

## 1) კონფიგურაცია (ENV key-ები)

ნუ შეიტან ნამდვილ მნიშვნელობებს აქ; დააყენე სამიზნე პროექტის .env-ში.

- BOG_PAYMENT_CLIENT_ID=YOUR_CLIENT_ID
- BOG_SECRET_KEY=YOUR_SECRET_KEY
- BOG_PAYMENT_LANGUAGE=ka
- BOG_PAYMENT_CURRENCY=GEL
- BOG_PAYMENT_URL=https://api.bog.ge

დამხმარე კონფიგი:
- [config/bog.php](config/bog.php)

## 2) Route-ები და CSRF

- POST /order/validate
  - Controller: GeoPaymentController@validatePaymentOrder
- GET /bog/payment/redirect
  - Controller: GeoPaymentController@bogPayRedirect
- POST /bog/payment/callback
  - Controller: GeoPaymentController@bogPaymentCallback

CSRF გამონაკლისი:
- [app/Http/Middleware/VerifyCsrfToken.php](app/Http/Middleware/VerifyCsrfToken.php)
  - bog/payment/callback

## 3) Backend Flow (მოკლე)

### 3.1 validatePaymentOrder
- შემოსული `payment_type` განსაზღვრავს გადახდის ტიპს.
- BOG ბარათი: `payment_type = 1`
- BOG განვადება: `payment_type = 3` (ამ ფაილში პირდაპირ არ არის რეალიზებული)

### 3.2 bogPayRedirect (BOG order შექმნა)
- ქმნის `generatedOrderId` ფორმატით `IPAY-xxxxxx`.
- იძახებს `BogPayService::create`.
- ინახავს `CharicxvaLog` ჩანაწერს სტატუსით `CREATED` და `chveni_statusi = dawyeba`.
- ქმნის `OrderInstallment` ჩანაწერს ძირითადი მონაცემებით.
- აბრუნებს BOG redirect URL-ს (front-end გადამისამართებისთვის).

### 3.3 bogPaymentCallback (Callback/Webhook)
- ელოდება request body-ში:
  - `body.order_id`
  - `body.external_order_id`
  - `body.order_status.key`
  - `body.payment_detail.*`
- სტატუსების რუკა:
  - `completed` -> `CharicxvaLog` (`PERFORMED`, `warmatebuli gadaxda`)
  - `rejected` -> `CharicxvaLog` (`REJECTED`, `gadaxda ver moxerxda`)

ფაილი:
- [app/Http/Controllers/Site/GeoPaymentController.php](app/Http/Controllers/Site/GeoPaymentController.php)

## 4) BOG API ინტეგრაცია (Service)

ფაილი:
- [app/Services/BogPayService.php](app/Services/BogPayService.php)

### 4.1 OAuth Token
- Endpoint:
  - https://oauth2.bog.ge/auth/realms/bog/protocol/openid-connect/token
- Auth:
  - client credentials (Basic Auth)

### 4.2 Order Creation
- Endpoint:
  - {BOG_PAYMENT_URL}/payments/v1/ecommerce/orders
- Request payload (მოკლე):
  - callback_url
  - external_order_id
  - purchase_units.currency
  - purchase_units.total_amount
  - purchase_units.basket[]:
    - product_id
    - quantity
    - unit_price
  - redirect_urls.success
  - redirect_urls.fail

- Response გამოიყენება:
  - `id`
  - `_links.redirect.href` (redirect URL)

## 5) Frontend Integrations

ფაილი:
- [resources/js/components/frontend/pages/checkout.vue](resources/js/components/frontend/pages/checkout.vue)

- `payment_type` მნიშვნელობები:
  - 1 = BOG (ბარათი)
  - 3 = BOG (განვადება)
  - 2 = Credo
  - 4 = TBC

- Checkout აკეთებს POST `/order/validate` და შემდეგ redirect URL-ზე გადადის.

## 6) ლოგირება

- მოდელი: [app/Models/CharicxvaLog.php](app/Models/CharicxvaLog.php)
- ლოგ არხი: `mylog`
- ლოგ ფაილები ჩვეულებრივ:
  - storage/logs/mylog.log

## 7) Assets (BOG ლოგოები)

- public/images/payment-method/bog_geo_vertical.png
- public/images/payment-method/bog_geo_horizontal.png
- public/images/payment-method/bog1.png

## 8) ცნობილი საკითხები / რისკები

1) Callback URL mismatch
- Service-ში hardcoded callback:
  - https://mytechnic.ge/bogpay/payment/callback
- Route/config-ში:
  - /bog/payment/callback
შესაძლოა callback არ მოხვდეს სწორ endpoint-ზე.

2) Callback payload assumptions
- Controller ელოდება `body.*` სტრუქტურას. თუ BOG განსხვავებულ ფორმატს გამოაგზავნის, handler-მა შესაძლოა 400 დააბრუნოს.

3) Basket მონაცემები
- `purchase_units.basket` იყენებს fixed product_id=1 და quantity=1.
  რეალური კალათი არ იგზავნება.

---

თუ გინდა, შემიძლია ეს დოკი გავაღრმავო (status mapping, DB ველები, ინვოისის ლოგიკა, ან BOG განვადების flow).