# გეგმა — Facebook/Instagram პოსტინგი, კომენტარების მართვა, real‑time შეტყობინებები, UI/UX

## 1) შეჯამება

ამ გეგმის მიზანია:

* არსებული Facebook Posts ფუნქციონალის გადამოწმება/გაძლიერება (FB/IG), მათ შორის ფოტო/ვიდეო პოსტინგი, ფორმატის კონტროლი და სტატუსის მონიტორინგი.

* ადმინ პანელში Social Comments-ის მართვის სრულფასოვანი სისტემა (ფილტრები, reply, spam, მომხმარებლის ბლოკი, quick replies, bulk, export, audit log).

* ახალი კომენტარების real‑time შეტყობინებები WebSocket-ით (არსებული Laravel Echo + Pusher ინფრასტრუქტურაზე).

* არსებული NobleUI (demo1) სტილში თანამედროვე, responsive UI/UX რედიზაინი და WCAG 2.1 AA ხელმისაწვდომობის გაუმჯობესება.

* ცვლილებების შემდეგ ინტეგრაციული ტესტები და cross‑browser ვალიდაცია.

## 2) მიმდინარე მდგომარეობის ანალიზი (Repo ფაქტები)

### 2.1 Facebook/Instagram პოსტინგი

<br />

* UI/კონტროლი: [FacebookPostController.php](file:///c:/laragon/www/smartwatch-showroom/app/Http/Controllers/Admin/FacebookPostController.php), ხედები: [facebook-posts](file:///c:/laragon/www/smartwatch-showroom/resources/views/admin/facebook-posts/)

* FB API: [FacebookPageService.php](file:///c:/laragon/www/smartwatch-showroom/app/Services/FacebookPageService.php) — `/feed` ტექსტი, `/photos` სურათი URL-ით. Timeout/retry არ აქვს.

* IG API: [InstagramPageService.php](file:///c:/laragon/www/smartwatch-showroom/app/Services/InstagramPageService.php) — media container + publish; მხოლოდ ფოტო (`image_url`). Timeout/retry არ აქვს; container readiness polling არ აქვს.

* ვალიდაციები: IG publish-ს სჭირდება `image_url` ([FacebookPostController.php](file:///c:/laragon/www/smartwatch-showroom/app/Http/Controllers/Admin/FacebookPostController.php#L55-L135)).

* მედია მენეჯერი: cropper + upload endpoint `/admin/images/upload-standalone` ([admin-facebook-posts.js](file:///c:/laragon/www/smartwatch-showroom/resources/js/admin-facebook-posts.js#L450-L515), [ProductImageController.php](file:///c:/laragon/www/smartwatch-showroom/app/Http/Controllers/Admin/ProductImageController.php#L108-L142)) — მხოლოდ სურათები.

### 2.2 Social Comments (უკვე ნაწილობრივ გაკეთებულია)

* UI: [social-comments/index.blade.php](file:///c:/laragon/www/smartwatch-showroom/resources/views/admin/social-comments/index.blade.php) + JS: [admin-social-comments.js](file:///c:/laragon/www/smartwatch-showroom/resources/js/admin-social-comments.js)

* Backend: [SocialCommentController.php](file:///c:/laragon/www/smartwatch-showroom/app/Http/Controllers/Admin/SocialCommentController.php), Service: [SocialCommentService.php](file:///c:/laragon/www/smartwatch-showroom/app/Services/SocialCommentService.php)

* ფუნქციები: სია/ფილტრები (status/platform/sentiment/search), reply, hide, bulk status, fetch.

* მოდელი/ცხრილი: [SocialComment.php](file:///c:/laragon/www/smartwatch-showroom/app/Models/SocialComment.php), [create\_social\_comments\_table](file:///c:/laragon/www/smartwatch-showroom/database/migrations/2026_03_17_032530_create_social_comments_table.php)

* AI reply გენერაცია: [AiSuggestionService::generateCommentReply](file:///c:/laragon/www/smartwatch-showroom/app/Services/AiSuggestionService.php#L513-L631) — აბრუნებს detected\_tone, მაგრამ sentiment ველში არ ინახება.

### 2.3 Real‑time ინფრასტრუქტურა

* Laravel broadcasting: [config/broadcasting.php](file:///c:/laragon/www/smartwatch-showroom/config/broadcasting.php) (Pusher მხარდაჭერა), JS: [bootstrap.js](file:///c:/laragon/www/smartwatch-showroom/resources/js/bootstrap.js) (Echo + pusher-js).

* Channels: [routes/channels.php](file:///c:/laragon/www/smartwatch-showroom/routes/channels.php) — `inbox` და `inbox.conversation.{id}`.

* Default broadcaster ახლა `BROADCAST_DRIVER`-ზეა დამოკიდებული (ხშირად `null`).

### 2.4 Audit Log უკვე არსებობს

* Trait: [AuditTrait.php](file:///c:/laragon/www/smartwatch-showroom/app/Traits/AuditTrait.php)

* ცხრილი: [create\_admin\_audit\_logs\_table](file:///c:/laragon/www/smartwatch-showroom/database/migrations/2026_02_19_100007_create_admin_audit_logs_table.php)

## 3) მიღებული გადაწყვეტილებები (თქვენი პასუხების მიხედვით)

* “ბლოკი” მომხმარებელზე: **Facebook-ზე რეალური ბლოკი Graph API-ით + fallback** (თუ API/ID/permissions ვერ შესრულდება, ლოკალური blocked-list + auto-hide/auto-spam).

* “Sentiment” აღარ გვჭირდება; ამის ნაცვლად: **Auto‑response სისტემა** — კონკრეტულ პოსტზე “მსგავს” კომენტარებზე ავტომატური პასუხი იმ წესებით/ინსტრუქციით, რასაც ადმინი მიუთითებს.

* ვიდეო პოსტინგი: **FB + IG ორივეზე**.

## 4) შეთავაზებული ცვლილებები (ფაილები + რა/რატომ/როგორ)

### 4.1 Facebook/Instagram პოსტინგი — სტაბილურობა, ვიდეოები, მონიტორინგი

**ფაილები:**

* [FacebookPostController.php](file:///c:/laragon/www/smartwatch-showroom/app/Http/Controllers/Admin/FacebookPostController.php)

* [FacebookPageService.php](file:///c:/laragon/www/smartwatch-showroom/app/Services/FacebookPageService.php)

* [InstagramPageService.php](file:///c:/laragon/www/smartwatch-showroom/app/Services/InstagramPageService.php)

* [FacebookPost.php](file:///c:/laragon/www/smartwatch-showroom/app/Models/FacebookPost.php) + ახალი migration

* UI/JS: [facebook-posts/\_form.blade.php](file:///c:/laragon/www/smartwatch-showroom/resources/views/admin/facebook-posts/_form.blade.php), [admin-facebook-posts.js](file:///c:/laragon/www/smartwatch-showroom/resources/js/admin-facebook-posts.js)

**ცვლილებები:**

1. **Media ტიპის მხარდაჭერა (image/video/none)**

   * DB: `facebook_posts`-ში დავამატებთ ველებს: `media_type` (enum: none,image,video), `video_url` (nullable), პლატფორმის-ცალკე IDs/სტატუსები (ქვემოთ).

   * Form: “Image URL”-ს გვერდით დავამატებთ “Video URL / Upload” არჩევანს (NobleUI სტილში), და ვალიდაციას:

     * IG: photo/video აუცილებელია (text-only არა).

     * FB: text-only დასაშვებია, image/video optional.
2. **ვიდეო upload & public URL გენერაცია**

   * ახალი endpoint (Admin): ვიდეო ფაილის ატვირთვა `storage/app/public/videos/...` და დაბრუნება public URL-ით.

   * ფრონტში: ვიდეოსთვის ცალკე upload control (max size/allowed mimetype: `video/mp4` + limit).

   * უსაფრთხოება: MIME/size validation; არასოდეს ვლოგავთ token-ს.
3. **Meta Graph API რობუსტობა**

   * `Http::timeout()` + `retry()` + უკეთესი error context (status, error\_code, endpoint) FB/IG სერვისებში.

   * IG publish flow:

     * container create

     * container readiness polling (`status_code`) exponential backoff-ით

     * publish
4. **სტატუსის მონიტორინგი (per platform)**

   * DB: დავამატებთ `facebook_publish_status`, `instagram_publish_status` (queued|publishing|published|failed), `facebook_error`, `instagram_error`, `last_publish_check_at`.

   * Publish პროცესში და/ან scheduler/command-ით (cron): “publishing” პოსტებისთვის ვამოწმებთ IG container/publish მდგომარეობას და ვაახლებთ სტატუსებს.
5. **ვალიდაციები და “partial success”**

   * `FacebookPost.status` აღარ იქნება მხოლოდ “published/failed”; დავამატებთ `partial` ან ცალკე პლატფორმის სტატუსებს, რათა FB წარმატება + IG შეცდომა სწორად აისახოს.

### 4.2 Social Comments — სრულფასოვანი მართვა + Auto‑reply + Block + Export + Audit

**ფაილები:**

* [SocialCommentController.php](file:///c:/laragon/www/smartwatch-showroom/app/Http/Controllers/Admin/SocialCommentController.php)

* [SocialCommentService.php](file:///c:/laragon/www/smartwatch-showroom/app/Services/SocialCommentService.php)

* [SocialComment.php](file:///c:/laragon/www/smartwatch-showroom/app/Models/SocialComment.php) + ახალი მოდელები/migrations

* UI/JS: [social-comments/index.blade.php](file:///c:/laragon/www/smartwatch-showroom/resources/views/admin/social-comments/index.blade.php), [admin-social-comments.js](file:///c:/laragon/www/smartwatch-showroom/resources/js/admin-social-comments.js)

* Routes: [routes/web.php](file:///c:/laragon/www/smartwatch-showroom/routes/web.php), [routes/channels.php](file:///c:/laragon/www/smartwatch-showroom/routes/channels.php)

**ცვლილებები:**

1. **ფილტრები (თარიღით/სტატუსით) + სრული სია**

   * Controller list endpoint-ში დავამატებთ `date_from/date_to` ფილტრებს `commented_at`-ზე.

   * UI-ში დავამატებთ date inputs (accessibility-friendly).
2. **Reply UX გაუმჯობესება + Quick Replies**

   * DB: `social_quick_replies` (title, body, optional platform/scope).

   * UI: reply modal-ში dropdown “Quick replies” + “Insert” ღილაკი.

   * Audit: reply/send/hide/status ცვლილებები ჩაიწერება `admin_audit_logs`-ში.
3. **Bulk მოქმედებები**

   * დამატება: bulk delete, bulk status update (არსებულის გაფართოება).
4. **Export CSV/XLSX**

   * Endpoint: `/admin/social-comments/export?format=csv|xlsx` + იგივე ფილტრები რაც list-ში.

   * Implementation: `openspout/openspout` (უკვე პროექტშია) — დიდი მოცულობისთვის memory-safe stream.
5. **User Block (FB რეალური + fallback)**

   * DB: `social_blocked_users` (platform, author\_id, author\_name, reason, blocked\_at, blocked\_by).

   * Action:

     * Facebook: Graph API `/page-id/blocked` edge-ზე “block” request (თუ permissions/ID ვალიდურია).

     * Fallback: blocked-list-ში დამატება და მომავალ fetch-ზე ამ ავტორის კომენტარების auto-hide/auto-spam.

   * UI: თითო კომენტარზე “Block user” მოქმედება + bulk “Block selected”.
6. **Auto‑Reply Rules (თქვენი მოთხოვნა “მსგავს კომენტარებზე ავტომატური პასუხი”)**

   * DB: `social_auto_reply_rules`

     * `facebook_post_id` (scope: კონკრეტული პოსტი)

     * `match_type` (keywords|contains|regex)

     * `match_value` (string)

     * `reply_template` (admin-ის მითითებული ტექსტი ან AI ინსტრუქცია)

     * `use_ai` (true/false) — თუ true, AI-ს ვაძლევთ ინსტრუქციას + კომენტარის ტექსტს და ვაგენერირებთ პასუხს

     * throttling: `max_replies_per_author_per_day`, `enabled`

   * Flow:

     * ახალი comment import-ისას (ან webhook-ისას მომავალში) ვამოწმებთ წესებს.

     * თუ match + throttling OK → `replyToComment()` ავტომატურად იძახება.

     * შედეგი ინახება `SocialComment.actual_reply`, `status=replied`, + audit log entry.

   * UI: Social Comments გვერდზე “Auto‑reply rules” drawer/modal: წესების CRUD + test input (preview match).

### 4.3 Real‑time Notifications WebSocket-ით (ახალი კომენტარები)

**ფაილები:**

* ახალი Event: `App\Events\SocialCommentCreated`

* [routes/channels.php](file:///c:/laragon/www/smartwatch-showroom/routes/channels.php)

* [admin-social-comments.js](file:///c:/laragon/www/smartwatch-showroom/resources/js/admin-social-comments.js)

**ცვლილებები:**

1. ახალი private channel: `social.comments` (auth: ნებისმიერი ლოგინიანი admin).
2. Comment import-ისას (SocialCommentService-ში) “wasRecentlyCreated” შემთხვევაში ვაბროდქასტებთ `SocialCommentCreated`-ს.
3. UI: Echo-ზე listener → toast + unread badge update + სურვილისამებრ list reload (დაბალი დარტყმით: debounce).
4. Fallback: თუ BROADCAST\_DRIVER != pusher → polling (არსებული “Fetch from Meta” + optional interval).

### 4.4 UI/UX რედიზაინი + WCAG 2.1 AA

**ფაილები:**

* [layout.blade.php](file:///c:/laragon/www/smartwatch-showroom/resources/views/admin/layout.blade.php) (არსებული demo1)

* [facebook-posts/\_form.blade.php](file:///c:/laragon/www/smartwatch-showroom/resources/views/admin/facebook-posts/_form.blade.php)

* [social-comments/index.blade.php](file:///c:/laragon/www/smartwatch-showroom/resources/views/admin/social-comments/index.blade.php)

* შესაბამისი JS მოდულები

**ცვლილებები:**

* Responsive: table → mobile-friendly cards/toggleable columns (საჭიროების მიხედვით).

* Accessibility:

  * ფორმებში სწორი label/aria-describedby/error rendering

  * კონტრასტის შემოწმება (badge/button), focus state consistency

  * modal focus trapping/inert სწორად (ახლაც `inert` ატრიბუტი გამოიყენება image manager-ში)

* ერთიანი ვიზუალური ენა: demo1 კომპონენტების კლასები/spacing ერთნაირად.

## 5) რისკები და შეზღუდვები

* Meta Graph API-ის “block user” შეიძლება მოითხოვდეს დამატებით permissions-ს (მაგ. pages\_manage\_metadata) და user ID-ის ტიპის შეზღუდვებს. ამიტომ გვაქვს fallback (ლოკალური block + auto-hide).

* IG ვიდეო პოსტინგისთვის საჭიროა რომ `video_url` იყოს **publicly reachable HTTPS URL**; ლოკალური `127.0.0.1` URL Meta-სგან ვერ გაიხსნება.

* WebSocket real‑time საჭიროებს სწორ Pusher ENV კონფიგურაციას (`BROADCAST_DRIVER=pusher`, VITE\_PUSHER\_\*).

## 6) ვერიფიკაცია (შესრულების შემდეგ)

### 6.1 ავტომატური ტესტები (PHPUnit)

* Feature tests:

  * FacebookPost publish ვალიდაციები (IG media required, platform selection required)

  * FB/IG publish services Http::fake-ით (text/photo/video flows, error paths)

  * SocialComment list ფილტრები (status/platform/date/search)

  * Export endpoints (csv/xlsx response headers + row count)

  * Auto-reply rule matching + throttling + audit log insertion

* Event broadcasting: unit/feature დონეზე `Event::fake()` / `Broadcast::fake()`-ით.

### 6.2 Manual / Integration

* Admin UI: შექმნა/რედაქტი/პუბლიკაცია FB/IG-ზე (ფოტო/ვიდეო), სტატუსები სწორად აისახოს.

* Social Comments: filter/date/bulk/reply/quick replies/block/export მუშაობდეს.

* Real-time: მეორე ბრაუზერში ახალი კომენტარის შემოტანა → პირველში toast და badge update.

* Cross‑browser smoke: Chrome, Firefox, Edge (responsive + modals + file upload).

