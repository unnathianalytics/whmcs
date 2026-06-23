# Phase 3 — Products & Services (Completion Report)

**Date:** 2026-06-23
**Plan:** `docs/plans/2026-06-23-1902-phase-3-products-services.md`
**Status:** ✅ Completed & user-verified

## Summary

Delivered the **Products & Services** module on the Phase 2 tenant-isolation foundation:

- **Product catalog** — `ProductGroup` → `Product` → `ProductPricing` (one price row per billing cycle).
  Managed from a single `/admin/products` screen: groups with create/edit/delete, products with create/edit/
  delete, and pricing rows managed inline inside the product modal (add/remove cycles, duplicate-cycle
  validation).
- **Client services** — `ClientService` instances that subscribe a client to a product, with a price/cycle
  snapshot and **manually-entered** `starts_at`, `expires_at`, `next_due_date` (per the agreed Phase 3
  decision). Selecting a product only pre-fills price + cycle, which the admin can override.
- **Services workspace** (`/admin/services`) — Flux table across all clients with search (client/product/
  label), status filter, expiry filter (expiring ≤30d / expired), sortable columns, and **color-coded expiry
  urgency** badges (red ≤7 days or expired, yellow ≤30, green beyond, zinc when no expiry).
- **Client profile integration** — the Phase 2 "Services" placeholder card now shows the real service count
  plus a table of the client's services with status + expiry badges and a "Manage" link.
- **Enums** — `BillingCycle` (Monthly…Triennial, One-Time; `label()` + `months()`) and `ServiceStatus`
  (Pending/Active/Suspended/Cancelled/Expired; `label()` + `color()`).
- **RBAC** — all actions gate on the already-seeded `services.*` permissions. Products use `ProductPolicy`;
  client services use `ClientServicePolicy`; product **groups** (which have no dedicated model policy) gate
  on the permission string directly via an `authorizePermission()` helper.
- **Sidebar** — Products + Services nav items enabled, gated on `services.view`.

## Changelog

### Added
- `app/Enums/BillingCycle.php`, `app/Enums/ServiceStatus.php`
- `app/Models/ProductGroup.php`, `app/Models/Product.php`, `app/Models/ProductPricing.php`, `app/Models/ClientService.php`
- `database/migrations/2026_06_23_190201_create_product_groups_table.php`
- `database/migrations/2026_06_23_190202_create_products_table.php`
- `database/migrations/2026_06_23_190203_create_product_pricings_table.php`
- `database/migrations/2026_06_23_190204_create_client_services_table.php`
- `database/factories/ProductGroupFactory.php`, `database/factories/ProductFactory.php`, `database/factories/ProductPricingFactory.php`, `database/factories/ClientServiceFactory.php`
- `database/seeders/ProductSeeder.php`
- `app/Policies/ProductPolicy.php`, `app/Policies/ClientServicePolicy.php`
- `app/Livewire/Admin/Products/Index.php`, `resources/views/livewire/admin/products/index.blade.php`
- `app/Livewire/Admin/Services/Index.php`, `resources/views/livewire/admin/services/index.blade.php`
- `tests/Feature/ProductsTest.php`, `tests/Feature/ClientServicesTest.php`
- `docs/plans/2026-06-23-1902-phase-3-products-services.md` (plan)

### Changed
- `app/Models/Client.php` — added `services()` HasMany.
- `app/Models/Company.php` — added `productGroups()`, `products()`, `clientServices()` HasMany.
- `app/Livewire/Admin/Clients/Show.php` — added `services()` computed for the profile.
- `resources/views/livewire/admin/clients/show.blade.php` — real Services count + services table (replaced the placeholder zero).
- `routes/web.php` — `admin.products` and `admin.services` routes in the `/admin` group.
- `resources/views/layouts/app/sidebar.blade.php` — enabled Products + Services nav items, gated on `services.view`.
- `database/seeders/DatabaseSeeder.php` — calls `ProductSeeder` after `ClientSeeder`.

## Validation

- `php artisan test --compact` — **87 passed, 188 assertions** (Phase 2 baseline was 60; +27 new across
  Products and ClientServices). Coverage: tenant isolation (cross-company invisibility, `company_id`
  auto-stamp), permission gates (`viewAny`/`create`/`update`/`delete` for products, groups and services),
  product CRUD with pricing sync, duplicate-cycle rejection, group CRUD, service CRUD, `expires_at >=
  starts_at` validation, product-pricing pre-fill on the service form, expiring/expired filtering, and the
  expiry-urgency colour matrix (`urgencyColor()` / `isExpired()`).
- `php artisan migrate:fresh --seed` — clean; migrations run in FK order (groups → products → pricings →
  client_services); `ProductSeeder` produces 3 groups, 8 products (monthly + annual pricing each) and 5 demo
  services with staggered expiries.
- `vendor/bin/pint --dirty` — clean.
- **Manual verification by user:** create/edit/delete product group, product, and pricing; assign/edit/delete
  a service; expiry urgency colours; client-profile services card — confirmed working in the browser.

## Discovery Notes / Deviations

- **Currency default changed to INR.** The user edited the pricings migration default to `INR` during
  implementation; the rest of the module (service currency default, factories, form defaults) was aligned to
  INR for consistency.
- **Product-group authorization bug (found during verification, fixed):** group edit/delete were initially
  routed through `ProductPolicy` with a class string (`authorize('update', Product::class)`), but those
  policy methods require a `Product` instance, throwing `ArgumentCountError`. Groups have no dedicated policy
  (they share `services.*`), so group create/edit/delete now gate on the permission string directly via an
  `authorizePermission()` helper (`abort_unless($user->can(...), 403)`), consistent with the
  "gates check permissions, never role names" rule. Added 3 group tests (edit/delete/forbidden-edit) that
  reproduce and guard the fix; the original suite had only exercised group *create* (a single-arg policy
  method), so it didn't surface the bug.
- **`company_id` not in `$fillable`** on the catalog models (matching Phase 2's `ClientNote`). Mass-assigning
  it via `create([...])` is silently dropped; it is instead stamped by the `BelongsToCompany` creating hook
  (under auth) or set as a direct attribute by factories/seeder. Tests that build pricing in setup use the
  factory (which sets the attribute directly) rather than a guarded `create()`.
- **Product deletion preserves history:** `client_services.product_id` is nullable + `nullOnDelete`, and
  price/cycle are snapshotted on the service row, so deleting a product never destroys a client's service
  history.
- **Manual dates** (not auto-derived). `BillingCycle::months()` exists to *hint* default dates but no forced
  derivation is applied — the admin enters all three dates and `expires_at` must be `>= starts_at`.
- No sub-agents were used; the codebase was small enough to inspect directly.

## Postponed Items

- Invoices/Tickets counts on the client profile remain placeholder zeros until Phases 4–5.
- No automated renewal/invoice generation from `next_due_date` yet — that lands with Phase 4 (Invoices) and
  Phase 7 (reminders consume the `expires_at` shapes defined here).
- Multi-currency display/formatting is raw (`CUR 0.00`); no money/locale formatting layer in v1.
- Bulk actions, CSV import/export, and per-group reminder rules are out of the Phase 3 scope.

## Follow-up Recommendations

- When Phase 7 (reminders) lands, query `client_services.expires_at` (already indexed) and reuse
  `daysUntilExpiry()` / `urgencyColor()` so thresholds stay in one place.
- The Phase 1 follow-up (extract per-company role seeding into an action invoked on UI company creation)
  remains open — new tenants created via the UI still get no roles/permissions until seeded, so the Products/
  Services screens would 403 for them.
- Consider a dedicated `ProductGroupPolicy` if group authorization grows beyond the flat `services.*` set, to
  retire the `authorizePermission()` helper in favour of the standard policy path.
- As later tenant models are added, continue the `BelongsToCompany` + isolation-test pattern established here
  and in Phase 2.
