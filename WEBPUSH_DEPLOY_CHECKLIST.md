# Web Push Deploy Checklist

Use this checklist when rolling out inbox phone notifications to production.

## 1) Git + Deploy

1. Push changes to GitHub.
2. On server: pull latest branch.
3. Install/update PHP deps:
   - `composer install --no-dev --optimize-autoloader`

## 2) Environment (`.env`)

Set these values on the **server** `.env` (do not commit secrets):

- `WEBPUSH_VAPID_PUBLIC_KEY=...`
- `WEBPUSH_VAPID_PRIVATE_KEY=...`
- `WEBPUSH_VAPID_SUBJECT=mailto:support@mytechnic.ge`

Recommended:
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://your-domain.com`

## 3) Database

Run migrations:

- `php artisan migrate --force`

Expected table:
- `push_subscriptions`

## 4) Laravel cache refresh

After `.env`/config changes:

- `php artisan config:clear`
- `php artisan cache:clear`
- `php artisan route:clear`
- `php artisan view:clear`

(If you use config cache in production, run `php artisan config:cache` after validation.)

## 5) Frontend assets

If deploying built assets from server:

- `npm ci`
- `npm run build`

Or ensure fresh `public/build` is deployed from CI.

## 6) HTTPS + browser requirements

Web Push requires secure context:

- Production must be `https://...`
- Service worker file is served at `/admin-sw.js`
- Inbox opens under `/admin/...` and requests notification permission

## 7) Functional smoke test

1. Login as admin on phone.
2. Open admin inbox page once and allow notifications.
3. Verify subscription row exists in `push_subscriptions`.
4. Trigger test push:
   - `POST /admin/push-subscriptions/test`
5. Send real customer message from widget/webhook and confirm phone notification is delivered.
6. Tap notification and verify it opens admin inbox.

## 8) Known platform notes

- Android Chrome: works directly with browser permission.
- iOS Safari: requires HTTPS and may require adding the site to Home Screen for reliable push behavior.

## 9) Rollback

If needed:

1. Revert code release.
2. Keep `push_subscriptions` table (safe to keep).
3. If rotating VAPID keys, force re-subscription by clearing old rows:
   - `TRUNCATE TABLE push_subscriptions;`

## 10) Security reminders

- Never commit `.env` with real keys.
- Rotate VAPID keys only when necessary.
- Rotating keys invalidates old browser subscriptions.
