# Admin Inbox Omnichannel-ის კოდის ბაზის ანალიზი

წინამდებარე ანგარიშში წარმოდგენილია `smartwatch-showroom` პროექტის Admin Inbox-ის დეტალური ანალიზი Omnichannel (მრავალარხიანი) მოთხოვნების ჭრილში. ანალიზი მოიცავს მომხმარებლის ინტერფეისს (UI/UX), ბექენდის ლოგიკას (API/Services) და შეტყობინებების (Notifications) სისტემებს.

## 1. UI/UX (Blade, JS, CSS)

**1.1. Blade შაბლონები (`resources/views/admin/inbox/index.blade.php`)**
*   **სტრუქტურა**: გამოყენებულია სამსვეტიანი დიზაინი (საუბრების სია, ჩატის ველი და მომხმარებლის დეტალები). ეს უზრუნველყოფს SPA (Single Page Application) გამოცდილებას.
*   **ფილტრაცია**: ინტეგრირებულია პლატფორმების მიხედვით გაფილტვრის შესაძლებლობა (Facebook, Instagram, WhatsApp, Messenger, Web/Home), რაც Omnichannel მოთხოვნებს სრულად პასუხობს.
*   **ფუნქციონალი**: შესაძლებელია სტატუსების (Active, Archived, Closed) და პრიორიტეტების (Low, Normal, High, Urgent) მართვა, ასევე AI რეჟიმის (Bot) ჩართვა/გამორთვა.

**1.2. JavaScript (`resources/js/admin-inbox.js`, `public/js/inbox-pwa.js`)**
*   **მონაცემთა მართვა**: იყენებს `axios`-ს API-სთან საკომუნიკაციოდ (`loadConversations`, `openConversation`, `sendMessage`).
*   **რეალურ დროში განახლება (Polling)**: განხორციელებულია 15-წამიანი "polling" მექანიზმი (`startPolling()`), რომელიც ამოწმებს ახალ შეტყობინებებს და ანახლებს UI-ს რეფრეშის გარეშე.
*   **PWA და შეტყობინებები**: `inbox-pwa.js` უზრუნველყოფს Service Worker-ის რეგისტრაციას და WebPush გამოწერების (subscriptions) მართვას. ასევე უსმენს ლოკალურ ბრაუზერის მოვლენებს (`inbox-browser-notification`).

**1.3. CSS / სტილები (`resources/css/inbox-nobleui.css`, `resources/css/admin.css`)**
*   **დიზაინი**: გამოყენებულია NobleUI-ს ჩატის სტილები. უზრუნველყოფილია "Typing indicators" (ბეჭდვის ანიმაცია), მორგებული scrollbar-ები და სხვადასხვა ფერის "ბუშტები" (Bubbles) გამომგზავნის ტიპის მიხედვით (მომხმარებელი, ბოტი, ადმინი).

---

## 2. Backend / API (Controllers, Services, Models)

**2.1. Controllers (`InboxController`, `WebhookController`)**
*   **InboxController**: ემსახურება SPA-ს. აბრუნებს მონაცემებს JSON ფორმატში (`conversations`, `messages`). ამუშავებს შეტყობინების გაგზავნასა და სტატუსის/პრიორიტეტის ცვლილებებს.
*   **WebhookController**: იღებს შემომავალ მოთხოვნებს სხვადასხვა პლატფორმიდან (Meta, WhatsApp) და ამუშავებს მათ.

**2.2. Services (`MessageDispatcher`, `ConversationManager`)**
*   **Omnichannel ინტეგრაცია**: `MessageDispatcher` ცენტრალური სერვისია, რომელიც მართავს შემომავალ შეტყობინებებს. ის იყენებს `InstagramApiService`, `MessengerApiService` და `WhatsAppService` კლასებს თითოეული პლატფორმიდან მომხმარებლის პროფილის ამოსაღებად (Profile Data Fetching) და შესაბამისი Customer-ის შესაქმნელად.
*   **მედია და მეტამონაცემები**: ამოიცნობს მედიის ტიპებს (სურათი, ვიდეო, აუდიო, ფაილი) და ინახავს `platform_message_id`-ს, რათა თავიდან აიცილოს დუბლირებული შეტყობინებები.
*   **ტრანზაქციები**: ბაზაში ჩაწერა ხდება `DB::transaction`-ის გამოყენებით უსაფრთხოების უზრუნველსაყოფად.

**2.3. Repositories (`ConversationRepository`, `MessageRepository`)**
*   **მონაცემთა ბაზა**: გამოყოფილია ლოგიკა Controller-ებიდან. `ConversationRepository` მართავს საუბრების მოძიებას და უზრუნველყოფს სწორ Caching-ს წაუკითხავი შეტყობინებებისთვის (`inbox:unread-count:v1`).

---

## 3. შეტყობინებები და შეტყობინებების სისტემები (Notifications)

**3.1. რეალური დრო (Broadcasting / WebSockets)**
*   **მოვლენები (Events)**: `MessageReceived` კლასი აკეთებს Event-ების ბროუდქასთინგს (`PrivateChannel('inbox')`). თუმცა, მიმდინარე UI ძირითადად ეყრდნობა JS Polling-ს ყოველ 15 წამში. კოდში არსებული კომენტარის მიხედვით ("Broadcasting will be handled by events in Phase 1.5"), სრული WebSocket ინტეგრაცია შესაძლოა განახლების პროცესშია.

**3.2. Push Notifications (WebPush)**
*   **სერვისი**: `PushNotificationService` იყენებს `Minishlink\WebPush` ბიბლიოთეკას, რათა გააგზავნოს VAPID-ზე დაფუძნებული Push შეტყობინებები.
*   **მიმღებები**: შეტყობინებები იგზავნება ადმინებთან (`sendToAdmins`), როდესაც ახალი შეტყობინება შემოდის ნებისმიერი პლატფორმიდან.
*   **ლოგიკა**: სისტემა ამოწმებს, არის თუ არა ადმინი უკვე Inbox-ის გვერდზე (`cache()->get('user_..._on_inbox_page')`) და თუ არის, Push შეტყობინებას აღარ უგზავნის, რაც UX-ს საგრძნობლად აუმჯობესებს.

**3.3. Email შეტყობინებები**
*   ჩატის ახალი მესიჯებისთვის სპეციფიკური Email გაგზავნის ლოგიკა არ არის აღმოჩენილი, რაც ჩატის სისტემებისთვის სტანდარტული მიდგომაა (სპამის თავიდან ასაცილებლად). სისტემა ეყრდნობა მყისიერ (Push/Real-time) არხებს.

---

## 📝 შეჯამება და რეკომენდაციები
1.  **Omnichannel მზადყოფნა**: სისტემა მზად არის მრავალარხიანი (Facebook, IG, WA) მუშაობისთვის. API და Database სტრუქტურა კარგად არის მორგებული პლატფორმების გამიჯვნაზე.
2.  **Performance (წარმადობა)**: 15-წამიანი Polling კარგია დროებით გადაწყვეტად, თუმცა უმჯობესი იქნება `MessageReceived` Event-ების სრული გადაყვანა რეალურ დროში (Laravel Echo + Pusher/Reverb) კლიენტის მხარეს.
3.  **მომხმარებლის გამოცდილება (UX)**: შეტყობინებების მართვა ლოკალურად და Push Notification-ების ჭკვიანური გაფილტვრა (როცა ადმინი უკვე ჩატშია) მაღალი დონის მომხმარებლის გამოცდილებას ქმნის.