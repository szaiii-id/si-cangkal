# AGENTS.md

SI-CANGKAL is a Laravel 13 + Inertia v3 + Svelte 5 (runes) + Tailwind v4 travel-booking app on PostgreSQL. Roles are `admin`, `mitra`, and `client`. User-facing copy and most comments are Indonesian; match that style.

## Setup

```bash
composer install
bun install
cp .env.example .env
php artisan key:generate
docker compose up -d pgsql      # PostgreSQL 16; first run creates sicangkal_db + sicangkal_db_testing
php artisan migrate
php artisan wayfinder:generate --with-form
```

- Use **Bun** (`bun.lock`; CI runs `bun install --frozen-lockfile`). `pnpm-workspace.yaml` is unused, and `composer setup` still calls npm — prefer the commands above.
- Frontend tooling is **Vite+** (`vp`), not plain Vite. Use the `bun run` scripts.
- PostgreSQL is mandatory: migrations use `jsonb`, and tests/E2E use `.env.testing`. Do not switch to SQLite.
- `composer dev` runs `php artisan dev`: serve + queue:listen + pail + Vite. Keep a queue worker running when working on E-Ticket or notification flows.
- `php artisan db:seed` only creates the production admin; its password is random and written to the `audit` log channel (set `ADMIN_PROD_PASSWORD` to control it).

## Commands

| Task                     | Command                                                                                                                |
| ------------------------ | ---------------------------------------------------------------------------------------------------------------------- |
| Dev (all processes)      | `composer dev`                                                                                                         |
| Build frontend           | `bun run build`                                                                                                        |
| Frontend format + lint   | `bun run check` / `bun run check:fix`                                                                                  |
| Svelte/TS typecheck      | `bun run types:check`                                                                                                  |
| PHP format (fix / check) | `composer lint` / `composer lint:check`                                                                                |
| PHP static analysis      | `composer types:check` (PHPStan level 7)                                                                               |
| Pest (all)               | `php artisan test`                                                                                                     |
| Pest (single)            | `php artisan test --filter=BookingControllerTest` or `php artisan test tests/Feature/Client/BookingControllerTest.php` |
| Backend gate             | `composer test` (config:clear -> Pint check -> PHPStan -> Pest)                                                        |
| Vitest                   | `bun run test`; single file: `bun run test resources/js/utils/__tests__/formatCurrency.test.ts`                        |
| E2E                      | `bun run test:e2e`; single spec: `bun run test:e2e e2e/auth/login.spec.ts --project=chromium`                          |
| E2E sharded              | `bun run test:e2e:shard:4`                                                                                             |
| E2E rate limit           | `bun run test:e2e:rate-limit`                                                                                          |

- `bun run check` is Vite+ format + lint (type-aware) but **not** `svelte-check`; run `bun run types:check` separately. Lint warnings fail the check.
- `bun run types:check` and `composer types:check` are different: Svelte vs PHPStan.
- `resources/js/components/ui/*` is excluded from Vite+ fmt/lint; don't reformat it to satisfy a check.

## Architecture

- Request flow: `routes/web.php` -> controller (`app/Http/Controllers/<Role>`) -> FormRequest -> Policy -> Service (`app/Services/<Domain>`) -> repository (`app/Repositories/Contracts` + `Eloquent`) -> model. Bind new repository/service interfaces in `app/Providers/AppServiceProvider.php`.
- Role guards are middleware aliases in `bootstrap/app.php`: `role:admin|mitra|client`, `password.changed`, `mitra.complete`. Post-login redirects are also defined there.
- `EventServiceProvider` disables auto-discovery. `BookingConfirmed` explicitly maps to `CreateTripForBooking` (sync; creates `Trip`, then dispatches `GenerateETicketJob`) and `NotifyClientListener` (queued; currently a no-op). Keep that order.
- Timezone: `config/app.php` hardcodes the app timezone to `UTC` (the `APP_TIMEZONE` env var is ignored). DB stores UTC; use `App\Support\Timezone` for all "today"/"now" business logic, never raw `now()`. User/trip/package timezones are IANA; `User::DEFAULT_TIMEZONE` is `Asia/Makassar`.
- Status enums live in `app/Enums` (labels, badge classes, transition helpers). Frontend mirrors labels and types in `resources/js/utils/enums` and `resources/js/types`; update both when adding statuses.
- Security helpers: use `App\Support\LikeEscaper` for user-supplied `LIKE` searches (Postgres needs an explicit `ESCAPE` clause), and `App\Support\AuditLogger` for security-relevant actions (writes to the `audit` log channel).
- PDFs (E-Ticket, reports) are Blade views in `resources/views/pdf` and `resources/views/reports`, rendered by DomPDF. They are not Svelte pages.

## Frontend

- Svelte 5 runes only (`$props`, `$state`, `$derived`, `$effect`); do not use Svelte 4 `export let` or store patterns.
- Inertia pages live in `resources/js/pages/<Role>/...`; page names passed to `Inertia::render` are path-based (e.g. `Client/Bookings/Show`). Shared props come from `app/Http/Middleware/HandleInertiaRequests.php` (`auth.user`, `flash`, `unread_notifications`, `viewer_timezone`).
- Use Inertia `router` / `use:inertia` and the wrappers in `resources/js/api/*.ts`; don't use raw `fetch` for page navigation. API wrapper URLs are hand-written, so update them when routes change.
- Tailwind v4 is configured in `resources/css/app.css` (`@theme`), not `tailwind.config.js`. For WCAG AA, use `text-<color>-text` on white/transparent backgrounds and `bg-<color>-dark` + white text for solid fills. Axe scans run in `e2e/accessibility/axe-scan.spec.ts`.
- Wayfinder generates `resources/js/{actions,routes,wayfinder}` (gitignored; never edit). Regenerate after route/controller changes with `php artisan wayfinder:generate --with-form`; Vite does it on dev/build, but `bun run types:check` does not.
- `bootstrap/app.php` only renders the Inertia `Error` page outside `local`; locally Laravel shows its default error page.

## Testing notes

- Pest Feature tests use `RefreshDatabase`; `tests/TestCase` mocks `FirebaseNotificationServiceInterface` by default. The User/Package/Trip helpers in `tests/Helpers` are globally loaded from `tests/Pest.php`.
- Vitest runs in jsdom and only picks up `resources/js/**/*.{test,spec}.{ts,js}`. `vitest-setup.ts` mocks `window.matchMedia` for FlareCharts.
- E2E serves compiled assets from `public/build`; run `bun run build` after frontend changes (CI does this before Playwright).
- Playwright starts its own `php -S` on `http://localhost:8000` and refuses to reuse an existing server. It runs `migrate:fresh` against the E2E DB and seeds `TestSeeder` (users `admin@test.com`, `mitra@test.com`, `client@test.com`, password `password`). It needs PostgreSQL plus the `psql` client; the DB user needs `CREATEDB`.
- Use the E2E fixtures (`e2e/fixtures/base.ts`): `test`, `expect`, `asAdmin`, `asMitra`, `asClient`, `seed`, `resetAll`. Do not log in through the UI in E2E.
- `routes/web-testing.php` and all `/_test/*` endpoints exist only when `APP_ENV=testing`; never rely on them in production. Use `seed.runCommand(page, 'bookings:expire'|'trips:activate')` instead of waiting for the hourly scheduler.
- E2E forces `QUEUE_CONNECTION=sync` so E-Ticket generation is deterministic; don't change it to async for E2E.
- E2E host must be `localhost`, not `127.0.0.1` (the FCM support check requires it). Shards use ports `8000 + index`, DBs `sicangkal_db_testing_wN`, and storage `storage/shard-N`; `bun run test:e2e:shard:*` handles setup and stale-port cleanup.
- `rate-limit.spec.ts` is excluded from normal browser projects; run it with `bun run test:e2e:rate-limit` or the `rate-limit-*` projects. Auth limits live in `config/auth-throttle.php`; tests use `AUTH_THROTTLE_*_TEST_OVERRIDE` to stay deterministic.
- WebKit runs only when `CI=true`; locally use the Chromium/Firefox projects.
- `.env.testing` is committed and used by Pest/E2E. E2E also expects a Firebase service-account JSON under `storage/app/firebase/` (gitignored; CI writes a dummy). Don't commit credentials.

## Repo quirks

- `AGENTS.md` and `opencode.json` are gitignored as per-developer files; don't try to commit them.
- CI order is Pint -> PHPStan -> Svelte check, then Pest/Vitest in parallel jobs; E2E is a separate workflow. Before finishing backend/frontend work, run `bun run check`, `bun run types:check`, `bun run test`, and `composer test`.
