# MVP Implementation Plan

This file is an execution document for the next agent. It is intentionally written as a concrete implementation checklist, not as an audit summary.

## Current Status

Status: `Not ready` for marketing launch.

Launch goal:
- Close all `P0` and `P1` items.
- Run one full QA pass for mobile, checkout, SEO, and analytics.

## Working Rules

- Do not print secrets, `.env` values, tokens, or credentials.
- Do not run DB writes, migrations, deploys, or cache clears without explicit user approval.
- If a task requires production-adjacent validation, mark it as `Needs approval`.
- The git worktree is already dirty. Do not revert unrelated user changes.

## P0 - Fix Immediately

### 1. Remove or lock down the public test route

Problem:
The public route `/test/send-message` writes data and broadcasts an event.

Where:
- `routes/web.php:421`

Required change:
- Prefer removing the route entirely.
- If it must exist, allow it only in `local` environment.
- If it must exist outside local, protect it with auth/admin access.

Why this blocks launch:
Public traffic can trigger test side effects. This is both a security and data integrity issue.

Done when:
- The route is no longer publicly reachable in a production-safe environment.
- There is a test or protection rule proving that behavior.

Effort:
`S`

### 2. Make the BOG payment callback trustworthy

Problem:
The BOG callback is CSRF-exempt and appears to mark orders as paid from callback payload data, without clear callback verification.

Where:
- `routes/web.php:117`
- `app/Http/Middleware/VerifyCsrfToken.php:16`
- `app/Http/Controllers/Site/GeoPaymentController.php:237`

Required change:
- Add official BOG callback verification.
- If BOG does not provide a signature for this flow, verify payment status server-to-server before marking an order as paid.
- Make callback handling idempotent.
- Handle invalid, duplicate, and unknown-status callbacks safely.

Why this blocks launch:
Paid traffic cannot rely on the checkout flow if payment completion can be spoofed or trusted without verification.

Done when:
- Only verified callbacks or verified remote payment status can move an order to `completed`.
- Duplicate callbacks do not create duplicate side effects.
- Failed and unknown callback states are handled safely.

Effort:
`M`

## P1 - Must Finish Before Marketing

### 3. Fix stock reservation for abandoned card payments

Problem:
Stock is reduced before payment is fully confirmed, and audit evidence only showed stock restoration on rejected callback.

Where:
- `app/Http/Controllers/Site/GeoPaymentController.php:133`

Required change:
- Choose one approach and implement it clearly:
- Either decrement stock only after confirmed payment.
- Or keep pending reservations but add expiry/reconciliation that releases abandoned reservations.
- Keep COD behavior correct.

Why this blocks launch:
Ad traffic can create abandoned checkouts that freeze inventory and hide real stock from later customers.

Done when:
- Pending card payments cannot hold stock indefinitely.
- Card and COD behavior is explicit and stable.

Effort:
`M`

### 4. Fix MySQL-safe catalog sorting

Problem:
Catalog sorting uses `NULLS LAST`, which is likely invalid on MySQL/MariaDB.

Where:
- `app/Http/Controllers/ProductController.php:38`
- `config/database.php:18`

Required change:
- Rewrite sorting in a MySQL-safe way.
- Sort by effective price: `sale_price` if present, otherwise `price`.
- Keep null handling deterministic for both asc and desc.

Why this blocks launch:
Broken sort affects a core shopping flow and may produce a 500 or wrong ordering.

Done when:
- Price ascending and descending both work correctly.
- Behavior is covered by a feature test.

Effort:
`S`

### 5. Remove placeholder contact and social fallback values

Problem:
Fallback phone, WhatsApp, or Messenger values look like placeholders and may leak to production UI if settings are missing.

Where:
- `app/Models/ContactSetting.php:16`
- `resources/views/layouts/app.blade.php:196`
- `resources/views/contact/index.blade.php:55`
- `resources/views/components/footer.blade.php`

Required change:
- Remove fake fallback values.
- Replace them with approved real values, or hide the element when config is missing.
- Do not render fake contact targets to end users.

Why this blocks launch:
Marketing traffic must not reach wrong phone numbers or generic social links.

Done when:
- No placeholder contact or social values appear anywhere in storefront UI.
- Missing config has a safe fallback behavior.

Effort:
`S`

### 6. Align warranty, delivery, and trust copy

Problem:
Customer-facing trust/legal copy is inconsistent.

Where:
- `resources/lang/ka/ui.php:66`
- `resources/views/pages/terms.blade.php:146`
- Also verify Home, Catalog, FAQ, and Cart

Required change:
- Define one correct policy for:
- warranty
- delivery
- return/exchange
- free shipping claims
- Update all customer-facing views to match that policy.

Why this blocks launch:
Marketing traffic should not hit conflicting legal or trust claims.

Done when:
- All visible warranty, delivery, return, and trust statements are consistent.
- The current 1-month vs 12-month conflict is removed.

Effort:
`S`

### 7. Fix OG image and favicon references

Problem:
Some views reference missing public assets for OG image and favicon files.

Where:
- `resources/views/home.blade.php:10`
- `resources/views/products/index.blade.php:8`
- `resources/views/products/show.blade.php:10`
- `resources/views/layouts/app.blade.php:45`

Required change:
- Use the existing `og-default.webp`, or add the missing file that the code expects.
- Update favicon and apple-touch references to files that actually exist.
- Keep `site.webmanifest` consistent with the final asset paths.

Why this matters before launch:
This does not break checkout, but it makes social previews and browser identity look broken during campaigns.

Done when:
- Every referenced OG and favicon asset exists.
- Home, Catalog, and Product pages share a valid fallback image strategy.

Effort:
`S`

### 8. Add analytics and pixel readiness

Problem:
The storefront does not show clear analytics or pixel instrumentation across the customer funnel.

Where:
- `resources/views/layouts/app.blade.php`
- `resources/views/products/show.blade.php`
- `resources/views/products/index.blade.php`
- `resources/views/checkout/index.blade.php`

Required change:
- Add env-gated support for:
- GTM or GA4
- Meta Pixel
- Emit these core events:
- `ViewContent`
- `AddToCart`
- `Lead`
- `InitiateCheckout`
- `Purchase`
- If env config is missing, storefront must still work without errors.

Why this blocks launch:
Marketing attribution and optimization are weak without funnel events.

Done when:
- Core funnel steps emit the expected events.
- Missing analytics config does not break the site.

Effort:
`M`

## P2 - Polish Before or Just After Launch

### 9. Translate remaining English UI

Problem:
Some customer-facing checkout and gift flow text is still in English.

Where:
- `resources/views/checkout/index.blade.php:3`
- `resources/views/checkout/success.blade.php:3`
- `resources/views/checkout/fail.blade.php:3`
- `resources/views/layouts/app.blade.php:88`
- `resources/views/cart/index.blade.php`

Required change:
- Translate visible UI copy to Georgian.
- Keep English only if it is a deliberate brand term.

Done when:
- Checkout, cart, and gift flow UI is consistently localized for the storefront.

Effort:
`S`

### 10. Run responsive QA on all core customer flows

Problem:
Responsive code exists, but it has not been fully validated in this audit.

Pages to verify:
- home
- product listing
- product detail
- cart
- checkout
- contact

Viewports to verify:
- `375px`
- `390px`
- `768px`
- `1440px`

Required change:
- Fix any overlap, broken spacing, hidden CTA, slider, image, or sticky-bar issue discovered during QA.

Done when:
- All critical customer flows are usable on mobile and desktop.
- Primary CTAs remain visible and reachable.

Effort:
`S/M`

## Can Wait Until After Marketing Launch

- Full English locale and hreflang cleanup, if launch is Georgian-only
- Blog/content expansion
- Internal admin/lab cleanup
- PWA or push-notification hardening

## Needs Approval

### 1. Database and migrations

Do not run without approval:
- `php artisan migrate`
- DB-writing tests

Possible approval-based follow-up:
- `php artisan migrate:status`
- focused test run against a safe test DB

### 2. Build output

`npm run build` may modify tracked `public/build` files.

Do not run without approval.

Possible approval-based follow-up:
- run build
- review asset diff

### 3. Payment provider validation

Final BOG confirmation requires:
- credential-backed verification
- callback endpoint validation
- staging or sandbox payment test

Without approval:
- only do code-level hardening

## Recommended Execution Order

### Day 1

1. Remove or lock `/test/send-message`
2. Fix MySQL price sorting
3. Remove placeholder contacts
4. Align warranty, delivery, and trust copy
5. Fix OG and favicon references

### Day 2

1. Harden BOG callback verification
2. Fix pending-payment stock behavior
3. Add or update focused tests where safe

### Day 3

1. Add analytics and pixel hooks
2. Translate remaining English storefront UI
3. Run first browser QA pass

### Day 4-5

Approval-dependent:
- run build
- run approved tests
- run payment smoke checks
- run final responsive QA

## Concrete Tasks For Another Agent

1. Remove or production-guard `/test/send-message` in `routes/web.php`, and add a test proving it is not publicly available.
2. Rewrite catalog sort logic in `ProductController` so price asc/desc works on MySQL/MariaDB using effective price.
3. Audit all storefront contact fallbacks and remove placeholder phone, WhatsApp, and Messenger values.
4. Unify warranty, delivery, return, and free-shipping copy across all customer-facing pages.
5. Fix OG image, favicon, and apple-touch references so every referenced public asset exists.
6. Implement trusted BOG callback handling with verification and idempotency before marking orders paid.
7. Prevent abandoned card payments from holding stock indefinitely.
8. Add env-gated GA4/GTM/Meta Pixel hooks and emit `ViewContent`, `AddToCart`, `Lead`, `InitiateCheckout`, and `Purchase`.
9. Translate remaining English copy in checkout, cart, and gift flows.
10. After approval, run build, tests, and browser QA, then document any remaining launch blockers.

## Launch Gate

The site can be considered ready for marketing when:
- all `P0` and `P1` items are complete
- checkout and payment completion are trustworthy
- no placeholder contact or legal/trust copy remains in storefront UI
- analytics events are wired for the funnel
- responsive QA is complete
- all approval-dependent essentials have been completed or explicitly waived
