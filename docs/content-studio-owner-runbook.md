# Content Studio owner runbook

No migration, deploy, credentials, cache clear, social post, or Codex automation was run by this change.

After staging review, an owner may run the following from the deployed release directory:

```powershell
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan queue:restart
```

Set `GOOGLE_SEARCH_CONSOLE_CREDENTIALS`, `GOOGLE_SEARCH_CONSOLE_SITE_URL`, `GOOGLE_ANALYTICS_CREDENTIALS`, and `GOOGLE_ANALYTICS_PROPERTY_ID` only in the deployment secret store. The Google service account must be read-only (`webmasters.readonly`, `analytics.readonly`) and must not be placed in `.env.example`, source control, prompts, or logs.

Create a restricted Sanctum token manually for each agent using only the `content-studio:submit` ability. The token may submit campaigns, items, revisions, assets, and status reads; the web-only owner routes perform approval, schedule, rejection, and publication.

For a staging smoke test, use `php artisan content-studio:collect-analytics --limit=1` only after read-only credentials are configured. Scheduled publishing is performed by the normal scheduler through `content-studio:publish-scheduled`; do not manually run it against production until the owner has approved an item.
