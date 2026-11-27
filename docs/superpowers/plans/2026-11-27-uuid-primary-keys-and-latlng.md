# UUID Primary Keys & Package Coordinates Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace `users.id` and `bookings.id` with UUID primary keys across schema, backend, frontend, and E2E; add package coordinates used by Google Maps links and precise `.ics` files.

**Architecture:** Migrations are rewritten in place and the local/testing databases are recreated with `migrate:fresh`. Eloquent models use `HasUuids` with string key type. All foreign keys to users/bookings become UUID columns. Other entities keep their existing primary keys. Coordinates flow from mitra forms through requests, service, resource, snapshot, and public pages into the Maps helper and ICS builder.

**Tech Stack:** Laravel 13, PostgreSQL, Inertia v3, Svelte 5 runes, Tailwind v4, Pest, Vitest, Playwright.

**Spec:** `docs/superpowers/specs/2026-11-27-uuid-primary-keys-and-latlng-design.md`

## Global Constraints

- `users.id` and `bookings.id` are UUID v4 strings; `$keyType='string'`, `$incrementing=false`.
- Foreign keys to users/bookings are UUID with `constrained()` and indexes kept.
- Package coordinates: `latitude decimal(10,7)`, `longitude decimal(11,7)`, nullable.
- ICS: RFC 5545, CRLF, 75-octet line folding, all-day `VALUE=DATE`, exclusive `DTEND`, stable UID from booking UUID, `GEO`, `LOCATION`, `URL`.
- No paid Google Maps API and no SDK — Maps URLs only.
- `migrate:fresh` + reseed before running any gate.

## Review Focus

1. A UUID string reaching a method still typed `int $id` → PHPStan must find zero errors. Test: `composer types:check`.
2. E2E helper extracting booking ID with `(\d+)`/`Number()` from a UUID URL → must use UUID regex. Test: run `bun run test:e2e e2e/e2e-flows/booking-confirmation-flow.spec.ts --project=chromium`.
3. `Trip::hasMitra()` using `mitra_id > 0` after UUID → always false. Test: `php artisan test tests/Feature/Services/Trip` + PHPStan.
4. Email verification comparing `(int) route('id')` to a UUID key → must compare strings. Test: `php artisan test tests/Feature/Auth/EmailVerificationTest.php`.
5. Package without coordinates → Maps helper and ICS must fall back to text query / omit `GEO` without crashing. Test: `bun run test resources/js/utils/__tests__/externalLinks.test.ts resources/js/utils/__tests__/calendar.test.ts`.

---

### Task 1: Schema & model UUID conversion

**Files:**

- Modify: all migrations listed in spec section 1
- Modify: `app/Models/User.php`, `app/Models/Booking.php`, and FK-bearing models listed in spec section 2
- Test: `php artisan test`

**Interfaces:**

- Produces: `User::getKey(): string`, `Booking::getKey(): string` (UUID); all FK columns UUID.

- [ ] **Step 1: Rewrite migrations**

Change `users.id`, `bookings.id`, and all FK columns listed in the spec to `uuid()`; change `personal_access_tokens` to `uuidMorphs('tokenable')`; add `latitude`/`longitude` to `packages`.

- [ ] **Step 2: Update models**

Add `HasUuids`, `$keyType='string'`, `$incrementing=false` to `User` and `Booking`; update docblocks and FK docblocks; change `Trip::hasMitra()` to a string-safe check.

- [ ] **Step 3: Recreate database and run backend tests**

Run: `php artisan migrate:fresh --seed && php artisan test`
Expected: green or only failures attributable to remaining int type hints (fixed in Task 2).

- [ ] **Step 4: Commit**

`git commit -m "feat: uuid primary key untuk users dan bookings"`

---

### Task 2: Backend integer-ID assumptions

**Files:**

- Modify: repositories, services, jobs, events, requests, policies listed in spec section 4
- Modify: `routes/web-testing.php`
- Test: `composer test`

**Interfaces:**

- Consumes: UUID keys from Task 1.
- Produces: all user/booking ID signatures typed `string`.

- [ ] **Step 1: Replace `int` type hints carrying user/booking IDs with `string`**

Use the exact file list from spec section 4. Do not touch package/trip/day IDs.

- [ ] **Step 2: Fix explicit casts and validation**

`EmailVerificationRequest` string comparison; `routes/web-testing.php` `booking_id` string validation and `findOrFail` without `(int)`; `XenditNotificationProcessor` mitra_id cast removal.

- [ ] **Step 3: Run static analysis and tests**

Run: `composer test`
Expected: Pint pass, PHPStan 0 errors, Pest pass.

- [ ] **Step 4: Commit**

`git commit -m "fix: type hint UUID untuk id user dan booking"`

---

### Task 3: Frontend & E2E types

**Files:**

- Modify: `resources/js/types/**`, `resources/js/api/**`, inline component props from spec section 3
- Modify: `e2e/pages/**`, `e2e/fixtures/seed.ts`, specs with numeric ID extraction
- Test: `bun run types:check && bun run test`

**Interfaces:**

- Consumes: UUID IDs from backend.
- Produces: `bookingId: string` and `id: string` across frontend/E2E.

- [ ] **Step 1: Change TS types from `number` to `string` for user/booking IDs and FKs**

Include `AuthUser.id`, `BookingEloquent.id`, `PaymentEloquent.booking_id`, etc. Keep package/trip ids numeric.

- [ ] **Step 2: Replace E2E numeric ID extraction with UUID regex**

`ClientBookingPage`, `MitraBookingPage`, `booking-confirmation-flow.spec.ts`, `manual-booking.spec.ts`, seed helpers.

- [ ] **Step 3: Run frontend checks**

Run: `bun run types:check && bun run test`
Expected: 0 errors, all Vitest pass.

- [ ] **Step 4: Commit**

`git commit -m "fix: tipe UUID di frontend dan E2E"`

---

### Task 4: Package coordinates end-to-end

**Files:**

- Modify: `app/Http/Requests/Mitra/{StorePackageRequest,UpdatePackageRequest}.php`, `PackageService`, `PackageController`, `PackageResource`, `BookingResource`, `TripService`, `PublicETicketController`, factories/seeders, `routes/web-testing.php`
- Modify: `resources/js/pages/Mitra/Packages/{Create,Edit}.svelte`, `resources/js/types/models/package.ts`, `resources/js/types/props/client.ts`
- Test: `composer test && bun run test`

**Interfaces:**

- Produces: `latitude: string|null`, `longitude: string|null` on package resources and `PackageEloquent`.

- [ ] **Step 1: Add validation and persistence for coordinates**

Nullable `numeric` + `between:lat,-90,90` / `between:lng,-180,180` in both requests; pass through service/controller.

- [ ] **Step 2: Expose coordinates to frontend and public pages**

Add to `PackageResource`, `BookingResource.packagePayload`, `TripService::buildSnapshot`, `PublicETicketController`, TS types, and mitra Create/Edit forms.

- [ ] **Step 3: Add coordinates to factories/seeders/testing endpoint**

- [ ] **Step 4: Run gates and commit**

`git commit -m "feat: koordinat paket untuk Maps dan ICS"`

---

### Task 5: Precise Maps links and ICS

**Files:**

- Modify: `resources/js/utils/externalLinks.ts`, `resources/js/utils/calendar.ts`
- Modify: `Client/Bookings/Show.svelte`, `Client/Catalog/Show.svelte`, `resources/views/e-tickets/verify.blade.php`
- Test: `resources/js/utils/__tests__/{externalLinks,calendar}.test.ts`

**Interfaces:**

- Consumes: coordinates from Task 4, UUID booking from Task 1.
- Produces: `googleMapsUrl(query, coords?)`, `buildIcs(event)` with all-day + stable UID + GEO.

- [ ] **Step 1: Write failing tests for coordinate-based Maps URL and precise ICS**

Assert `query=lat,lng`, `DTSTART;VALUE=DATE`, `DTEND;VALUE=DATE`, `UID:booking-<uuid>@si-cangkal`, `GEO:lat;lng`, `URL:`, CRLF, and 75-octet folding.

- [ ] **Step 2: Implement helpers**

Use existing `escapeIcs`, add `foldIcsLine`, all-day stamps from `YYYY-MM-DD`, stable UID from booking UUID.

- [ ] **Step 3: Wire pages to pass coordinates**

- [ ] **Step 4: Run tests, build, commit**

`git commit -m "feat: Maps berbasis koordinat dan ICS presisi"`

---

### Task 6: Full verification

- [ ] Run `php artisan migrate:fresh --seed`
- [ ] Run `composer test`, `bun run test`, `bun run check`, `bun run types:check`, `bun run build`
- [ ] Run `bun run test:e2e:shard:4` and `bun run test:e2e:rate-limit`
- [ ] Verify a booking UUID URL and an ICS download in browser
- [ ] Create branch, push, open PR
