---
name: verify
description: Build, launch, and drive this Laravel + Vue admin app to observe a change working end-to-end (server boot, seeding gotchas, Playwright drive recipe).
---

# Verifying changes in this app

## Build & boot

On the dev machine the app is already served by **Laravel Herd** at
`http://kheedma-academy.test` — do NOT run `php artisan serve`; just build and
drive that URL directly:

```bash
composer install && npm install
npm run build                       # required: tests + pages need public/build/manifest.json
# .env already exists on the dev machine — never overwrite it
php artisan migrate --no-interaction
php artisan permission:cache-reset   # see gotcha below
```

Fallback for sandboxes/CI without Herd (fresh checkout, no .env):

```bash
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --no-interaction
php artisan permission:cache-reset && php artisan cache:clear
php artisan db:seed --no-interaction   # admin@kheedma.id / password (ADMIN_* env overrides)
php artisan serve --host=127.0.0.1 --port=8000 --no-reload
```

## Gotchas

- **Sanctum stateful domains**: the admin SPA login only works from the host
  APP_URL names — `kheedma-academy.test` via Herd on the dev machine, or
  `127.0.0.1:8000` / `localhost` in the serve fallback. Serving on a
  random port makes `/api/login` fail with "Session store not set on request".
- **Spatie permission cache** lives in the `database` cache store. After
  `migrate:fresh`, a stale cached (empty) permission map makes
  `PermissionSeeder` crash on a unique violation. Always
  `php artisan permission:cache-reset` before reseeding.
- **Sandboxed/offline builds**: `vite build` fetches brand fonts from
  fonts.bunny.net. If that host is blocked, pre-seed
  `node_modules/.cache/laravel-vite-plugin/fonts/` with stub CSS/woff2 keyed by
  `sha256(url[+":text"]).slice(0,16)` for the two css2 URLs (Syncopate 400;700,
  Montserrat 400;500;600;700).

## Driving the surfaces

- **Admin SPA** (`/admin`): Vue app; drive with playwright-core +
  `executablePath: '/opt/pw-browsers/chromium'`. Login form: `input[type="email"]`,
  `input[type="password"]`, `button[type="submit"]`. Sidebar is `nav a:has-text(...)`.
  Dialogs render inside `.fixed.z-50` with an overlay — scope clicks (e.g.
  `button[type="submit"]`) or the overlay intercepts them.
- **Public funnel**: `/komunitas` and `/program/{slug}/daftar` Blade forms with
  `#name #phone #email #password #referral_source` ids; success redirects to `/akun`
  and logs the member in (use a fresh browser context per persona).
- **API**: cookie+CSRF (`/sanctum/csrf-cookie`, then `X-XSRF-TOKEN` header,
  `Accept: application/json`, `X-Requested-With: XMLHttpRequest`).
