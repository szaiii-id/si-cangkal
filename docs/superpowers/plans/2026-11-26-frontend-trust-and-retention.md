# Frontend Trust & Retention Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the client experience clearer and warmer — branded bottom nav, package ratings, visible mitra logo, a two-step payment choice with online as the primary path, no technical jargon, fewer decorative arrows, and honest re-engagement.

**Architecture:** Backend exposes bulk review statistics; Inertia props drive Svelte 5 rune components. Payment method selection is lifted to the booking detail page so the bank-account and proof-upload blocks render only after transfer is chosen. Copy changes are concentrated in client-facing strings; code comments keep technical terms.

**Tech Stack:** Laravel 13, Inertia v3, Svelte 5 runes, Tailwind v4, PostgreSQL, Pest, Vitest, Playwright.

**Spec:** `docs/superpowers/specs/2026-11-26-frontend-trust-and-retention-design.md`

## Global Constraints

- User-facing copy is Indonesian and contains **no technology names** (`Xendit`, `webhook`, `gateway`, `E-Ticket`, `Inertia`, `token`, `URL`).
- Svelte 5 runes only (`$props`, `$state`, `$derived`, `$effect`); no Svelte 4 `export let`.
- Tailwind v4 `@theme` tokens; on white use `text-<color>-text`, on solid fills use `bg-<color>-dark` + white text.
- WCAG AA contrast must hold; axe scans run in `e2e/accessibility/axe-scan.spec.ts`.
- No dark patterns: no false urgency, no hidden options, no guilt copy.
- Keep the labels "Lihat Detail" and "Bayar Sekarang" — E2E selectors depend on them; remove only arrow SVGs.
- Gates after every task: `bun run test`, `bun run check`, `bun run types:check`; backend changes also `composer test`.

## Review Focus

1. Package with **zero reviews** → no stars or "0 ulasan" shown on catalog card; pinned in Task 2's Vitest test.
2. Mitra with **no logo** → initial circle, no broken `<img>`; pinned in Task 3's Vitest test.
3. Pending booking where client **already uploaded proof / proof rejected** → transfer block stays visible so status can be seen; pinned in Task 4's Vitest test.
4. Selecting **Bayar Online** → no bank accounts and no upload form rendered; pinned in Task 4's Vitest test.
5. Rating like **4.5** and long package names → rounded display and no overflow at 390px; pinned in Task 2's Vitest test.

---

### Task 1: Bottom bar warna brand

**Files:**

- Modify: `resources/js/components/layout/ClientLayout.svelte:24-30,182-221`
- Test: `resources/js/components/layout/__test__/ClientLayout.test.ts`

**Interfaces:**

- Consumes: `clientMenu` from `@/utils/menus` with labels `Dashboard`, `Katalog Paket`, `Booking Saya`, `Profile Saya`.
- Produces: active pill `bg-pink-dark text-white`, inactive `text-gray-600` with `hover:bg-pink/10`, unchanged `aria-current`.

- [ ] **Step 1: Write the failing test**

Update the mocked `clientMenu` labels to the real ones, then add:

```ts
it('memakai warna brand pada item aktif dan warna netral pada item nonaktif', () => {
    // render with page props pointing to /client/dashboard
    expect(screen.getByLabelText('Dashboard').className).toContain(
        'bg-pink-dark',
    );
    expect(screen.getByLabelText('Katalog Paket').className).toContain(
        'text-gray-600',
    );
    expect(screen.getByLabelText('Dashboard')).toHaveAttribute(
        'aria-current',
        'page',
    );
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `bun run test resources/js/components/layout/__test__/ClientLayout.test.ts`
Expected: FAIL (label mock mismatch / class assertion fails).

- [ ] **Step 3: Implement the color map**

Replace the `colors` keys with the real menu labels; set active `bg-pink-dark text-white` and inactive `text-gray-600` plus hover `bg-pink/10` on the pill and icon classes at L182–221. Keep the fallback for unknown labels.

- [ ] **Step 4: Run test and gates**

Run: `bun run test resources/js/components/layout/__test__/ClientLayout.test.ts && bun run check && bun run types:check`
Expected: PASS, 0 lint errors, 0 type errors.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/layout/ClientLayout.svelte resources/js/components/layout/__test__/ClientLayout.test.ts
git commit -m "feat: warna brand pada navigasi bawah client"
```

---

### Task 2: Rating pada kartu katalog

**Files:**

- Modify: `app/Repositories/Contracts/ReviewRepositoryInterface.php`
- Modify: `app/Repositories/Eloquent/ReviewRepository.php:39-53`
- Modify: `app/Http/Controllers/Client/CatalogController.php:24-41`
- Modify: `resources/js/types/props/client.ts:138`
- Modify: `resources/js/pages/Client/Catalog/Index.svelte:160-224`
- Test: `tests/Feature/Client/CatalogRatingTest.php` (new), `resources/js/pages/Client/Catalog/__test__/Index.test.ts`

**Interfaces:**

- Consumes: existing `statistikForPackage(Package $package)`.
- Produces: `ReviewRepositoryInterface::statistikForPackages(array $packageIds): array` returning `[packageId => ['rata_rata' => float, 'jumlah' => int]]`; Inertia prop `ratings` on `Client/Catalog/Index`.

- [ ] **Step 1: Write the failing backend test**

```php
it('mengirim statistik rating tiap paket ke halaman katalog', function () {
    // buat 2 paket aktif; review paket A dengan rating 4 dan 5
    $response = $this->actingAs($client)->get(route('client.catalog.index'));
    $response->assertInertia(fn (Assert $page) => $page
        ->has('ratings.'.$packageA->id)
        ->where('ratings.'.$packageA->id.'.jumlah', 2)
        ->where('ratings.'.$packageA->id.'.rata_rata', 4.5));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Client/CatalogRatingTest.php`
Expected: FAIL — prop `ratings` belum ada.

- [ ] **Step 3: Implement bulk statistics**

Add `statistikForPackages(array $packageIds): array` to `ReviewRepositoryInterface` and `ReviewRepository` using one `whereIn(...)->groupBy('package_id')` query with `AVG(rating)` and `COUNT(*)`. In `CatalogController::index`, build a `ratings` map keyed by package id and add it to the Inertia props.

- [ ] **Step 4: Run backend test and gates**

Run: `php artisan test tests/Feature/Client/CatalogRatingTest.php && composer test`
Expected: PASS, Pint and PHPStan clean.

- [ ] **Step 5: Write the failing Vitest test**

Add to `resources/js/pages/Client/Catalog/__test__/Index.test.ts`:

```ts
it('menampilkan bintang hanya untuk paket yang punya ulasan', () => {
    // props: paket A ratings 4.5/2, paket B tanpa entri ratings
    expect(screen.getByText(/4,5/)).toBeInTheDocument();
    expect(screen.getByText(/\(2 ulasan\)/)).toBeInTheDocument();
    expect(screen.queryByLabelText(/rating paket B/i)).not.toBeInTheDocument();
});
```

- [ ] **Step 6: Run Vitest to verify it fails**

Run: `bun run test resources/js/pages/Client/Catalog/__test__/Index.test.ts`
Expected: FAIL — bintang belum dirender.

- [ ] **Step 7: Render stars on each card**

In `Catalog/Index.svelte`, render `<StarRating value={Math.round(rating.rata_rata)} readonly size="sm" />`, formatted average, and `(n ulasan)` only when an entry exists. Update `client.ts` with the `ratings` prop type.

- [ ] **Step 8: Run Vitest and gates**

Run: `bun run test resources/js/pages/Client/Catalog/__test__/Index.test.ts && bun run check && bun run types:check`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add app resources/js docs/superpowers
git commit -m "feat: tampilkan rating pada kartu katalog"
```

---

### Task 3: Logo mitra pada kartu penyelenggara

**Files:**

- Modify: `resources/js/pages/Client/Catalog/Show.svelte:57-59,286-288`
- Test: `resources/js/pages/Client/Catalog/__test__/Show.test.ts`

**Interfaces:**

- Consumes: `pkg.mitra.profile.logo_url` — already serialized because `CatalogController::show` loads `mitra.profile` and `Profile::getLogoUrlAttribute()` is appended. No backend change.
- Produces: `<img>` when `logo_url` is a non-empty string, initial circle otherwise.

- [ ] **Step 1: Write the failing test**

```ts
it('menampilkan logo mitra bila tersedia dan inisial bila tidak', () => {
    // render with pkg.mitra.profile.logo_url = 'https://cdn.test/logo.png'
    expect(screen.getByRole('img', { name: /logo mitra/i })).toHaveAttribute(
        'src',
        'https://cdn.test/logo.png',
    );
    // render ulang tanpa logo_url
    expect(screen.getByText('P')).toBeInTheDocument();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `bun run test resources/js/pages/Client/Catalog/__test__/Show.test.ts`
Expected: FAIL — tidak ada `<img>`.

- [ ] **Step 3: Render the logo with fallback**

Replace the initial-only circle with an `<img alt="Logo mitra" src={mitraProfile?.logo_url}>` guarded by a non-empty check; keep `mitraInitial` as fallback and on image error. Use existing rounded sizing (`w-12 h-12 rounded-full object-cover`).

- [ ] **Step 4: Run test and gates**

Run: `bun run test resources/js/pages/Client/Catalog/__test__/Show.test.ts && bun run check && bun run types:check`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/Client/Catalog/Show.svelte resources/js/pages/Client/Catalog/__test__/Show.test.ts
git commit -m "feat: tampilkan logo mitra pada detail paket"
```

---

### Task 4: Pilihan metode pembayaran dua langkah

**Files:**

- Modify: `resources/js/components/booking/PaymentOptionsCard.svelte:1-80`
- Modify: `resources/js/pages/Client/Bookings/Show.svelte:313-380`
- Test: `resources/js/components/booking/__test__/PaymentOptionsCard.test.ts`, `e2e/payment/xendit-payment.spec.ts:47`

**Interfaces:**

- Consumes: `bookingId`, `canUseGateway`, plus `hasProof: boolean` and `rejectReason: string|null` from the booking detail page.
- Produces: bindable `selectedMethod: 'online' | 'transfer' | null`; initial value is `'transfer'` when `hasProof || rejectReason`, otherwise `null`.

- [ ] **Step 1: Write the failing tests**

In `PaymentOptionsCard.test.ts`:

```ts
it('menampilkan opsi online lebih menonjol dan Transfer sebagai opsi kedua', () => {
    expect(screen.getByText('Bayar Online')).toBeInTheDocument();
    expect(
        screen.getByRole('button', { name: 'Pilih Transfer' }),
    ).toBeInTheDocument();
    expect(screen.queryByText(/Xendit/i)).not.toBeInTheDocument();
});

it('tidak menampilkan rekening maupun form bukti sebelum transfer dipilih', () => {
    // render halaman detail dengan metode belum dipilih
    expect(
        screen.queryByText(/Rekening Tujuan Transfer/i),
    ).not.toBeInTheDocument();
    expect(
        screen.queryByLabelText(/foto bukti transfer/i),
    ).not.toBeInTheDocument();
});

it('menampilkan rekening dan form bukti bila sudah ada bukti atau bukti ditolak', () => {
    // render dengan hasProof = true
    expect(screen.getByText(/Rekening Tujuan Transfer/i)).toBeInTheDocument();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `bun run test resources/js/components/booking/__test__/PaymentOptionsCard.test.ts`
Expected: FAIL.

- [ ] **Step 3: Implement method selection**

Add `let selectedMethod = $state<...>()` with a bindable prop, defaulting to `'transfer'` when `hasProof || rejectReason`. Mark the online card visually primary (brand border/gradient, small honest "Cepat" badge) and the transfer card secondary with a "Pilih Transfer" button. Copy rewrites belong to Task 5.

- [ ] **Step 4: Gate the detail-page blocks**

In `Client/Bookings/Show.svelte`, bind `selectedMethod` and wrap the "Konfirmasi Pembayaran" + `UploadProofForm` block and the "Rekening Tujuan Transfer" card in `{#if selectedMethod === 'transfer'}`. Selecting online renders only the "Bayar Sekarang" button.

- [ ] **Step 5: Update E2E copy expectation**

Change `e2e/payment/xendit-payment.spec.ts:47` to expect `Bayar Online`, and keep the `Bayar Sekarang` click expectation.

- [ ] **Step 6: Run tests and gates**

Run: `bun run test resources/js/components/booking/__test__/PaymentOptionsCard.test.ts && bun run check && bun run types:check`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/booking resources/js/pages/Client/Bookings/Show.svelte e2e/payment/xendit-payment.spec.ts
git commit -m "feat: pilih metode pembayaran sebelum menampilkan rekening dan bukti"
```

---

### Task 5: Bersihkan bahasa teknis dari tampilan client

**Files:**

- Modify: `resources/js/components/booking/PaymentOptionsCard.svelte`, `resources/js/components/booking/UploadProofForm.svelte`, `resources/js/pages/Client/Bookings/Show.svelte`, `resources/js/pages/Client/E-Tickets/**` (if any label), `resources/js/components/layout/ClientLayout.svelte`
- Test: `resources/js/components/booking/__test__/PaymentOptionsCard.test.ts`, `e2e/fixtures/selectors.ts:245`

**Interfaces:**

- Consumes: copy table in the spec.
- Produces: user-facing strings free of technology names; `e2e/fixtures/selectors.ts` uses the new proof label.

- [ ] **Step 1: Write the failing test**

```ts
it('tidak memakai istilah teknis pada teks yang terlihat', () => {
    const text = document.body.textContent ?? '';
    expect(text).not.toMatch(
        /Xendit|QRIS|VA|E-WALLET|E-Ticket|upload|webhook|gateway/i,
    );
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `bun run test resources/js/components/booking/__test__/PaymentOptionsCard.test.ts`
Expected: FAIL.

- [ ] **Step 3: Replace the copy**

Apply the spec's copy table, update `e2e/fixtures/selectors.ts:245` to the new proof label, and sweep the client-facing directories for remaining terms. Code comments remain unchanged.

- [ ] **Step 4: Run test and gates**

Run: `bun run test resources/js/components/booking/__test__/PaymentOptionsCard.test.ts && bun run check && bun run types:check`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js e2e
git commit -m "refactor: ganti bahasa teknis dengan bahasa pelanggan"
```

---

### Task 6: Hapus panah dekoratif

**Files:**

- Modify: `resources/js/pages/Client/Catalog/Index.svelte:213-220`, `resources/js/pages/Client/Catalog/Show.svelte:336-343`, `resources/js/pages/Client/Dashboard.svelte:43-46`, `resources/js/pages/Client/Bookings/Index.svelte:280-299`, `resources/js/pages/Client/Notifications/Index.svelte:202-220`, `resources/js/global/StatCard.svelte:53`, `resources/js/components/admin/dashboard/StatsGrid.svelte:85`, `resources/js/components/admin/dashboard/RecentBookings.svelte:32`, `resources/js/components/mitra/dashboard/RecentBookingsList.svelte:33`
- Test: existing Vitest tests for those components

**Interfaces:**

- Consumes: unchanged button labels.
- Produces: no right-arrow SVG in action buttons; pagination and back arrows untouched.

- [ ] **Step 1: Write the failing test**

In the affected component tests, assert:

```ts
expect(
    container.querySelector('svg path[d="M14 5l7 7m0 0l-7 7m7-7H3"]'),
).toBeNull();
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `bun run test resources/js/pages/Client/__test__/Dashboard.test.ts resources/js/components/admin/dashboard/__test__/StatGrid.test.ts`
Expected: FAIL.

- [ ] **Step 3: Remove the decorative SVGs**

Delete only the right-arrow SVG nodes from the listed action buttons/links. Keep text labels, spacing, hover states, and pagination/back arrows.

- [ ] **Step 4: Run tests and gates**

Run: `bun run test && bun run check && bun run types:check`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js
git commit -m "refactor: hapus panah dekoratif dari tombol aksi"
```

---

### Task 7: Kartu "Lanjutkan pesanan Anda" (retensi jujur)

**Files:**

- Modify: `resources/js/pages/Client/Dashboard.svelte`, `resources/js/types/props/client.ts`
- Test: `resources/js/pages/Client/__test__/Dashboard.test.ts`

**Interfaces:**

- Consumes: existing `bookings` prop on the client dashboard (pending bookings already available).
- Produces: a card shown only when a pending-payment booking exists, linking to `client.bookings.show`.

- [ ] **Step 1: Write the failing test**

```ts
it('menampilkan ajakan melanjutkan pesanan hanya bila ada booking menunggu pembayaran', () => {
    // props with one PENDING_PAYMENT booking
    expect(screen.getByText(/Lanjutkan pesanan Anda/i)).toBeInTheDocument();
    // props with no pending booking
    expect(
        screen.queryByText(/Lanjutkan pesanan Anda/i),
    ).not.toBeInTheDocument();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `bun run test resources/js/pages/Client/__test__/Dashboard.test.ts`
Expected: FAIL.

- [ ] **Step 3: Implement the card**

Use a calm, non-urgent copy ("Lanjutkan pesanan Anda" + booking code), neutral styling, and a text link to the booking detail. Do not add countdown, badges, or guilt language.

- [ ] **Step 4: Run test and gates**

Run: `bun run test resources/js/pages/Client/__test__/Dashboard.test.ts && bun run check && bun run types:check`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/Client/Dashboard.svelte resources/js/types/props/client.ts resources/js/pages/Client/__test__/Dashboard.test.ts
git commit -m "feat: kartu lanjutkan pesanan pada dashboard client"
```

---

### Task 8: Verifikasi akhir

**Files:** none (verification only)

- [ ] **Step 1: Jalankan seluruh gate**

Run: `composer test && bun run test && bun run check && bun run types:check && bun run build`
Expected: all pass.

- [ ] **Step 2: Jalankan E2E terdampak**

Run: `bun run test:e2e e2e/payment/xendit-payment.spec.ts --project=chromium && bun run test:e2e e2e/accessibility/axe-scan.spec.ts --project=chromium`
Expected: PASS; no contrast or selector regressions.

- [ ] **Step 3: Cek manual mobile/tablet**

Render halaman katalog, detail paket, dan detail booking pada 390×844 dan 768×1024; pastikan tidak ada overflow horizontal dan label baru terbaca.

- [ ] **Step 4: Pastikan tidak ada istilah teknis tersisa**

Run: `grep -rniE 'Xendit|E-Ticket|QRIS|VA|E-WALLET|webhook|gateway' resources/js/pages/Client resources/js/components/booking resources/js/components/layout --include='*.svelte'`
Expected: hanya muncul di komentar kode, bukan di string tampilan.
