# Phase 4 — Invoices & Billing (Plan)

**Date:** 2026-06-24
**Phase:** 4 (per `docs/idea.md`)
**Builds on:** Phase 1 (tenant foundation + RBAC), Phase 2 (Clients), Phase 3 (Products & Services)

## Goal

Deliver the **Invoices & Billing** module: manual invoice creation/editing with per-line-item tax,
payment recording, a per-company tax-rate catalog, and downloadable invoice PDFs — all tenant-isolated
and gated on the already-seeded `invoices.*` permission set.

## Clarified Decisions (from user)

1. **PDF:** Install `barryvdh/laravel-dompdf` now and ship a downloadable invoice PDF this phase.
2. **Tax:** **Per-line-item** tax rate. Each invoice item may carry its own tax rate; the rate percent is
   snapshotted onto the item so later catalog edits never rewrite invoice history (same snapshot pattern as
   `ClientService.price`).
3. **Auto-generation:** **Manual only** this phase. Generating invoices from `client_services.next_due_date`
   is deferred (overlaps Phase 7 reminders/scheduler).

## Affected Items

### New files

**Enums**
- `app/Enums/InvoiceStatus.php` — Draft, Unpaid, Paid, Overdue, Cancelled (`label()` + `color()`).
- `app/Enums/PaymentMethod.php` — Cash, BankTransfer, Card, Online, Cheque, Other (`label()`).

**Migrations** (FK order: tax_rates → invoices → invoice_items → transactions)
- `*_create_tax_rates_table.php`
- `*_create_invoices_table.php`
- `*_create_invoice_items_table.php`
- `*_create_transactions_table.php`

**Models** (all `BelongsToCompany` + `LogsActivity` + `SoftDeletes` where sensible)
- `app/Models/TaxRate.php`
- `app/Models/Invoice.php`
- `app/Models/InvoiceItem.php`
- `app/Models/Transaction.php`

**Policies** (gate on `invoices.*`; tax rates + payments share the invoices permission set — see Risks)
- `app/Policies/InvoicePolicy.php`
- `app/Policies/TaxRatePolicy.php`
- `app/Policies/TransactionPolicy.php`

**Livewire**
- `app/Livewire/Admin/Invoices/Index.php` + view — list (search, status filter, sort, paginate) + create
  modal (client, issue/due dates, currency, notes) + delete modal.
- `app/Livewire/Admin/Invoices/Show.php` + view — the invoice builder: header (status/date edit), line-item
  management (add/edit/remove: description, qty, unit price, tax rate), live totals, payments section
  (record payment modal + transaction list + delete), "Download PDF".
- `app/Livewire/Admin/TaxRates/Index.php` + view — Flux-table CRUD (name, rate %, active) in modals.

**PDF**
- `app/Http/Controllers/Admin/InvoicePdfController.php` — single-action; authorizes `invoices.view`,
  renders the invoice via DomPDF, returns a streamed download.
- `resources/views/pdf/invoice.blade.php` — print template (plain Blade/Tailwind-free inline styles).

**Factories & Seeder**
- `database/factories/TaxRateFactory.php`, `InvoiceFactory.php` (states: `unpaid`, `paid`, `overdue`,
  `draft`), `InvoiceItemFactory.php`, `TransactionFactory.php`.
- `database/seeders/InvoiceSeeder.php` — demo tax rates + ~20 invoices (mix of paid/unpaid/overdue) with
  items and some transactions, scoped to the demo company.

**Tests**
- `tests/Feature/InvoicesTest.php`
- `tests/Feature/TaxRatesTest.php`

### Changed files
- `composer.json` — add `barryvdh/laravel-dompdf` (approved).
- `routes/web.php` — `admin.invoices`, `admin.invoices.show`, `admin.invoices.pdf`, `admin.tax-rates`.
- `resources/views/layouts/app/sidebar.blade.php` — enable **Invoices** (Management, gated `invoices.view`)
  and add **Tax Rates** (System, gated `invoices.view`).
- `app/Models/Client.php` — add `invoices()` HasMany.
- `app/Models/Company.php` — add `invoices()`, `taxRates()`, `transactions()` HasMany.
- `app/Livewire/Admin/Clients/Show.php` — add `invoices()` computed.
- `resources/views/livewire/admin/clients/show.blade.php` — real Invoices count + invoices table (replace
  the placeholder zero).
- `database/seeders/DatabaseSeeder.php` — call `InvoiceSeeder` after `ProductSeeder`.

## Data Model

**tax_rates**: `id, company_id, name, rate (decimal 5,2 percent), is_active, timestamps, softDeletes`.

**invoices**: `id, company_id, client_id (cascade), number (string), status, issue_date, due_date,
currency(3), subtotal(12,2), tax_total(12,2), total(12,2), paid_at(nullable), notes(text), timestamps,
softDeletes`. Indexes: `status`, `due_date`, `client_id`; unique `(company_id, number)`.
Number generated per company: `INV-` + zero-padded next sequence (`max existing for company + 1`,
counting trashed to avoid reuse).

**invoice_items**: `id, company_id, invoice_id (cascade), description, quantity(8,2), unit_price(12,2),
tax_rate_id (nullable, nullOnDelete), tax_rate (decimal 5,2 snapshot), line_subtotal(12,2),
tax_amount(12,2), line_total(12,2), timestamps`.

**transactions**: `id, company_id, invoice_id (cascade), amount(12,2), method, reference(nullable),
paid_at(date), notes(nullable), timestamps`.

### Totals & status logic (on `Invoice`)
- `recalculateTotals()` — `subtotal = Σ line_subtotal`, `tax_total = Σ tax_amount`, `total = subtotal +
  tax_total`; persisted after any item add/edit/delete.
- `amountPaid()` = Σ transaction amounts; `balance()` = `total − amountPaid()`.
- `isPaid()` = `total > 0 && balance() <= 0`; `isOverdue()` = status Unpaid && `due_date` past.
- Recording a payment that clears the balance flips status → Paid and sets `paid_at`. Status is also
  directly admin-editable (Draft/Unpaid/Paid/Overdue/Cancelled).
- Per-item math: `line_subtotal = quantity × unit_price`, `tax_amount = line_subtotal × tax_rate/100`,
  `line_total = line_subtotal + tax_amount`.

## Implementation Steps

1. `composer require barryvdh/laravel-dompdf`; confirm package discovery.
2. Enums (`InvoiceStatus`, `PaymentMethod`).
3. Migrations (4) in FK order; `php artisan migrate`.
4. Models + relationships + activity log + totals/status helpers; add HasMany on `Client`/`Company`.
5. Policies (3) gating on `invoices.*`.
6. Livewire: `TaxRates/Index`, then `Invoices/Index`, then `Invoices/Show` + views.
7. PDF controller + Blade template.
8. Routes + sidebar + client-profile integration.
9. Factories + `InvoiceSeeder` + wire into `DatabaseSeeder`.
10. Feature tests (`InvoicesTest`, `TaxRatesTest`).
11. `vendor/bin/pint --dirty --format agent`; full `php artisan test --compact`.

## Verification / Testing Plan

- **Automated** (`php artisan test --compact`): tenant isolation (cross-company invisibility + `company_id`
  auto-stamp) for invoices/items/transactions/tax rates; permission gates for every action; invoice create
  with generated number; line-item add/edit/remove with per-line tax recalculation; payment recording →
  balance + auto-Paid; overdue detection; tax-rate CRUD; PDF route authorized vs forbidden (200 vs 403).
- **`migrate:fresh --seed`** runs clean and produces demo invoices in mixed states.
- **Manual (halt for user):** create an invoice, add taxed + untaxed lines, watch totals; record a partial
  then full payment (status flips to Paid); download the PDF; tax-rate CRUD; client-profile invoices card.

## Risks & Assumptions

- **Permission catalog unchanged.** No `taxes.*` or `transactions.*` permissions exist in the seeded
  catalog, and idea.md groups tax + payments under the invoices module. So tax-rate and payment actions gate
  on `invoices.*` (view→`invoices.view`, create/update/delete→matching `invoices.*`). Avoids re-seeding
  roles. If tax config later needs its own role, split it out then.
- **Invoice number race.** Sequence is computed at create time; the unique `(company_id, number)` index is
  the backstop. Concurrent creation in one tenant is unlikely in this admin panel; revisit if it bites.
- **DomPDF + Tailwind v4.** DomPDF doesn't run the Tailwind pipeline, so the PDF template uses inline/basic
  CSS, not Flux/Tailwind utilities.
- **Soft-deleted clients.** `invoices.client_id` is `cascadeOnDelete` at the DB level, but clients use
  soft deletes, so invoices survive a client soft-delete (history preserved), consistent with Phase 3.
- **Money formatting** stays raw (`CUR 0.00`, `number_format`) as in Phase 3; no locale/money layer yet.

## Out of Scope (this phase)
- Auto-generation from `next_due_date`, recurring invoices (Phase 7 overlap).
- Credit notes / refunds, payment-gateway integration (Stripe/PayPal — Phase 8 settings).
- Per-country automatic tax resolution; emailing invoices to clients.
