# Phase 4 — Invoices & Billing (Completion Report)

**Date:** 2026-06-24
**Plan:** `docs/plans/2026-06-24-1015-phase-4-invoices-billing.md`
**Status:** ✅ Completed & user-verified

## Summary

Delivered the **Invoices & Billing** module on the Phase 1–3 tenant-isolation foundation:

- **Invoices** — `Invoice` headers with a per-company sequential `number` (`INV-000001`…), status, issue/due
  dates, currency and cached money totals. Created as a draft from `/admin/invoices`, then built out on a
  dedicated detail page.
- **Line items with per-line tax** (the agreed Phase 4 decision) — each `InvoiceItem` carries description,
  quantity, unit price and an optional tax rate whose **percent is snapshotted** onto the line, so editing
  or deleting a catalog rate never rewrites a historical invoice. Derived money columns (`line_subtotal`,
  `tax_amount`, `line_total`) are computed once on save; the header totals recalculate after every mutation.
- **Payments** — `Transaction` rows recorded manually (amount, method, reference, date). The sum drives the
  invoice balance; a payment that clears the balance auto-flips the invoice to **Paid** with a `paid_at`
  stamp, and removing the settling payment re-opens it.
- **Tax-rate catalog** (`/admin/tax-rates`) — per-company reusable rates (name + percent + active flag),
  picked from on the line-item form.
- **Invoice PDF** — downloadable via `barryvdh/laravel-dompdf` (approved install) using an inline-styled
  print template independent of the Tailwind/Flux pipeline.
- **Enums** — `InvoiceStatus` (Draft/Unpaid/Paid/Overdue/Cancelled; `label()` + `color()`) and
  `PaymentMethod` (Cash/BankTransfer/Card/Online/Cheque/Other; `label()`).
- **RBAC** — all actions gate on the already-seeded `invoices.*` permission set via `InvoicePolicy` /
  `TaxRatePolicy`; payments authorize through the parent invoice's `update` policy.
- **Client profile + sidebar** — the Invoices placeholder card now shows the real count plus an invoices
  table; sidebar enables **Invoices** (Management) and **Tax Rates** (System), gated on `invoices.view`.
- **Scope:** invoices are **manual-only** this phase (no auto-generation from `next_due_date`).

## Changelog

### Added
- `app/Enums/InvoiceStatus.php`, `app/Enums/PaymentMethod.php`
- `app/Models/TaxRate.php`, `app/Models/Invoice.php`, `app/Models/InvoiceItem.php`, `app/Models/Transaction.php`
- `database/migrations/2026_06_24_101501_create_tax_rates_table.php`
- `database/migrations/2026_06_24_101502_create_invoices_table.php`
- `database/migrations/2026_06_24_101503_create_invoice_items_table.php`
- `database/migrations/2026_06_24_101504_create_transactions_table.php`
- `app/Policies/InvoicePolicy.php`, `app/Policies/TaxRatePolicy.php`
- `app/Livewire/Admin/Invoices/Index.php`, `resources/views/livewire/admin/invoices/index.blade.php`
- `app/Livewire/Admin/Invoices/Show.php`, `resources/views/livewire/admin/invoices/show.blade.php`
- `app/Livewire/Admin/TaxRates/Index.php`, `resources/views/livewire/admin/tax-rates/index.blade.php`
- `app/Http/Controllers/Admin/InvoicePdfController.php`, `resources/views/pdf/invoice.blade.php`
- `database/factories/TaxRateFactory.php`, `InvoiceFactory.php`, `InvoiceItemFactory.php`, `TransactionFactory.php`
- `database/seeders/InvoiceSeeder.php`
- `tests/Feature/InvoicesTest.php`, `tests/Feature/TaxRatesTest.php`
- `docs/plans/2026-06-24-1015-phase-4-invoices-billing.md` (plan)

### Changed
- `composer.json` / `composer.lock` — added `barryvdh/laravel-dompdf` (v3.1).
- `app/Models/Client.php` — added `invoices()` HasMany.
- `app/Models/Company.php` — added `invoices()`, `taxRates()`, `transactions()` HasMany.
- `app/Livewire/Admin/Clients/Show.php` — added `invoices()` computed.
- `resources/views/livewire/admin/clients/show.blade.php` — real Invoices count + invoices table (replaced the placeholder zero).
- `routes/web.php` — `admin.invoices`, `admin.invoices.show`, `admin.invoices.pdf`, `admin.tax-rates`.
- `resources/views/layouts/app/sidebar.blade.php` — enabled Invoices + Tax Rates nav items, gated on `invoices.view`.
- `database/seeders/DatabaseSeeder.php` — calls `InvoiceSeeder` after `ProductSeeder`.

## Validation

- `php artisan test --compact` — **113 passed, 255 assertions** (Phase 3 baseline was 87; +26 new across
  Invoices and TaxRates). Coverage: tenant isolation (cross-company invisibility, `company_id` auto-stamp)
  for invoices/items/transactions/tax rates; permission gates (`viewAny`/`create`/`update`/`delete`);
  invoice create with generated number + Draft status; create-validation (client required, `due_date >=
  issue_date`); line-item validation; per-line tax recalculation of `line_subtotal`/`tax_amount` and the
  header `subtotal`/`tax_total`/`total`; line removal recalculating down; full payment → auto-Paid + cleared
  balance; partial payment leaving balance; deleting the settling payment re-opening a Paid invoice;
  overdue detection (unpaid past due vs paid); PDF route authorized (200, `application/pdf`) vs forbidden
  (403); invoice soft-delete.
- `php artisan migrate:fresh --seed` — clean; migrations run in FK order (tax_rates → invoices →
  invoice_items → transactions); `InvoiceSeeder` produces 2 tax rates and 20 invoices in mixed
  draft/unpaid/overdue/paid states with line items and payments.
- `vendor/bin/pint --dirty` — clean.
- **Manual verification by user:** create invoice, add taxed + untaxed lines with live totals, record
  partial then full payment (status → Paid), download PDF, tax-rate CRUD, client-profile invoices card —
  confirmed working in the browser.

## Discovery Notes / Deviations

- **No separate `TransactionPolicy`.** Payments only ever exist within an invoice, so recording/deleting a
  payment authorizes through the parent invoice's `update` policy (`invoices.update`) instead of a dedicated
  policy — one fewer file, identical gate. (The plan tentatively listed a `TransactionPolicy`.)
- **Tax + payments reuse `invoices.*`.** The seeded permission catalog has no `taxes.*`/`transactions.*`
  set and idea.md groups tax under the invoices/billing module, so `TaxRatePolicy` and the payment actions
  gate on `invoices.*`. No role re-seed was required.
- **PDF controller uses the `Gate` facade.** The app's base `Controller` doesn't use the
  `AuthorizesRequests` trait, so `$this->authorize()` is unavailable in plain controllers; the PDF
  controller calls `Gate::authorize('view', $invoice)` (surfaced and fixed during the test run).
- **`company_id` not in `$fillable`** on the new models (matching the Phase 2/3 convention). It is stamped
  by the `BelongsToCompany` creating hook under auth, or set directly by factories/seeder. Line items and
  transactions created via the builder set `company_id` explicitly from the parent invoice before saving.
- **Invoice number generation** is computed per tenant (`max existing incl. trashed + 1`), backed by a
  unique `(company_id, number)` index. Sufficient for a single-admin panel; revisit if concurrent creation
  in one tenant ever becomes real.
- **Static analysis:** the remaining Larastan findings (`render()` without return types, factory
  `company_id` closures typed as `Model|Collection`) are pre-existing patterns shared across Phase 1–3
  files; the new code follows the same established conventions rather than diverging. PHPStan also needs
  `--memory-limit=1G` locally to avoid the default 128M crash.
- No sub-agents were used; the codebase was small enough to inspect directly.

## Postponed Items

- **Auto-generation** of invoices from `client_services.next_due_date` and recurring invoices — deferred
  (overlaps Phase 7 reminders/scheduler).
- **Credit notes / refunds** and **payment-gateway** integration (Stripe/PayPal) — Phase 8 settings.
- **Emailing** invoices to clients and **per-country automatic tax** resolution.
- Tickets/Domains counts on the client profile remain placeholder zeros until Phases 5–6.
- Money formatting stays raw (`CUR 0.00`); no locale/money layer yet.

## Follow-up Recommendations

- When Phase 7 (reminders) lands, reuse `Invoice::isOverdue()` and `client_services.expires_at` so overdue
  and renewal thresholds stay in one place; an `overdue` cron could also flip stale Unpaid invoices.
- When the dashboard widgets are wired (Phase 8), source "Revenue this month" from `transactions` and the
  expiring/overdue lists from `invoices` + `client_services`.
- The Phase 1 follow-up (extract per-company role seeding into an action invoked on UI company creation)
  remains open — new tenants created via the UI still get no roles/permissions until seeded, so the
  Invoices/Tax-Rates screens would 403 for them.
- Consider a money/locale formatting layer (and currency symbol) before client-facing PDFs ship externally.
- If tax configuration later needs its own role separate from invoicing, split out a `taxes.*` permission
  set and a dedicated `TaxRatePolicy` gate.
