# Phase 3 — Products & Services (Plan)

**Date:** 2026-06-23
**Phase:** 3 of `docs/idea.md`
**Depends on:** Phase 1 (foundation/RBAC) ✅, Phase 2 (Clients + `BelongsToCompany`) ✅

## Goal

Deliver the **Products & Services** module:

1. A tenant-scoped **product catalog** — `ProductGroup` → `Product` → `ProductPricing` (one pricing row
   per billing cycle), managed from a Products admin screen.
2. **Client services** — `ClientService` instances that subscribe a client to a product, with
   manually-entered `starts_at`, `expires_at`, `next_due_date`, a `billing_cycle`, and a lifecycle
   status. Expiry is surfaced on the service list with **color-coded urgency**, and the Client profile's
   "Services" placeholder card becomes a real count + list.

Per the user's Phase 3 decisions:
- **Full phase** (catalog + services).
- **Separate `ProductPricing` rows** per cycle (not an inline price).
- **Fully manual** service dates — admin enters `starts_at`, `expires_at`, `next_due_date` directly
  (sensible defaults pre-filled, but no forced auto-derivation from the cycle).

## Affected Items

### New files
- **Enums**
  - `app/Enums/BillingCycle.php` — `Monthly`, `Quarterly`, `SemiAnnual`, `Annual`, `Biennial`, `Triennial`, `OneTime` (value, `label()`, `months()` for default-date hints).
  - `app/Enums/ServiceStatus.php` — `Pending`, `Active`, `Suspended`, `Cancelled`, `Expired` (value, `label()`, `color()`).
- **Models**
  - `app/Models/ProductGroup.php` (`BelongsToCompany`, `LogsActivity`, `SoftDeletes`, `products()` HasMany)
  - `app/Models/Product.php` (`BelongsToCompany`, `LogsActivity`, `SoftDeletes`, `group()` BelongsTo, `pricings()` HasMany, `services()` HasMany)
  - `app/Models/ProductPricing.php` (`BelongsToCompany`; `product()` BelongsTo; `cycle` cast to `BillingCycle`)
  - `app/Models/ClientService.php` (`BelongsToCompany`, `LogsActivity`, `SoftDeletes`; `client()`, `product()` BelongsTo; date casts; status/cycle enum casts; `isExpired()`, `daysUntilExpiry()`, `urgencyColor()` helpers)
- **Migrations** (timestamped so FK targets exist first: groups → products → pricings → client_services)
  - `…_create_product_groups_table.php`
  - `…_create_products_table.php`
  - `…_create_product_pricings_table.php`
  - `…_create_client_services_table.php`
- **Factories**: `ProductGroupFactory`, `ProductFactory`, `ProductPricingFactory`, `ClientServiceFactory`
- **Seeder**: `ProductSeeder` (3 product groups, 8 products w/ pricings, a handful of client services on the demo company per idea.md sample data)
- **Policies**: `ProductPolicy` (gates on `services.*`), `ClientServicePolicy` (gates on `services.*`)
- **Livewire** (class-based, `app/Livewire/Admin/...`)
  - `Products/Index.php` + `resources/views/livewire/admin/products/index.blade.php` — groups + products with create/edit/delete modals, nested pricing rows.
  - `Services/Index.php` + `resources/views/livewire/admin/services/index.blade.php` — all client services list with search, status filter, expiry urgency badges, assign/edit/delete modals.
- **Tests**: `tests/Feature/ProductsTest.php`, `tests/Feature/ClientServicesTest.php`

### Changed files
- `app/Models/Client.php` — add `services()` HasMany.
- `app/Models/Company.php` — add `productGroups()`, `products()`, `clientServices()` HasMany (consistency with `clients()`).
- `routes/web.php` — `admin.products`, `admin.services` routes in the `/admin` group.
- `resources/views/layouts/app/sidebar.blade.php` — enable **Services** nav item (gated on `services.view`); the Products catalog lives under the same Services area (decision: single "Services" sidebar entry with a tab/section for the catalog, OR a second "Products" item — see Open Questions).
- `resources/views/livewire/admin/clients/show.blade.php` — replace the Services placeholder `0` card with a real count and a small services table for the client.
- `app/Livewire/Admin/Clients/Show.php` — expose the client's services (computed) for the profile.
- `database/seeders/DatabaseSeeder.php` — call `ProductSeeder` after `ClientSeeder`.

### Permissions
- `services.view/create/update/delete` **already exist** in `RolesAndPermissionsSeeder::PERMISSIONS` and are
  already granted to `manager` (all) and read-only/billing (`services.view`). **No permission catalog change needed.**

## Implementation Steps

1. **Enums** — `BillingCycle`, `ServiceStatus` (mirror `ClientStatus` structure: `label()`, `color()`; `BillingCycle::months()` returns the cycle length for date pre-fill hints).
2. **Migrations** — create the four tables, all with `company_id` FK (cascade), soft deletes where models soft-delete:
   - `product_groups`: name, slug (unique per company), description, sort_order, is_active.
   - `products`: product_group_id FK, name, description, setup_fee (decimal), is_active.
   - `product_pricings`: product_id FK, cycle (string), price (decimal), currency (3). Unique (`product_id`, `cycle`).
   - `client_services`: client_id FK, product_id FK (nullable on delete-set-null so deleting a product doesn't orphan history), label/domain (string, the service identifier e.g. domain), status, billing_cycle, price (decimal snapshot), currency, starts_at, expires_at (nullable), next_due_date (nullable), notes (text). Index `expires_at` for the reminder/urgency queries.
3. **Models** — add `BelongsToCompany` + relations + casts + activity log options (match `Client` conventions). `ClientService` helper methods: `isExpired()`, `daysUntilExpiry()`, `urgencyColor()` (red ≤7 days / expired, yellow ≤30, green otherwise).
4. **Factories + ProductSeeder** — realistic demo catalog and a few services on demo clients (explicit `company_id`, mirroring `ClientSeeder`).
5. **Policies** — `ProductPolicy` + `ClientServicePolicy` checking `services.*`; register if the project doesn't auto-discover (Laravel 13 auto-discovers `App\Policies\{Model}Policy` — verify).
6. **Products Livewire UI** — Flux table of groups/products, create/edit/delete modals, pricing rows managed within the product modal (add/remove cycle rows). Tenant-isolated via the scope; authorize on mount + every action.
7. **Services Livewire UI** — Flux table across all client services: columns client, product, cycle, price, status badge, **expiry with urgency badge**. Search (client/product/label), status filter, expiry filter (expiring-soon / expired). Assign modal: pick client → pick product → pricing pre-fills price+cycle (editable) → manual `starts_at`/`expires_at`/`next_due_date`. Edit + delete modals.
8. **Client profile integration** — Show.php computes `$client->services`; the Services card shows the real count and a compact list with status + expiry badge linking to the service.
9. **Routes + sidebar** — wire `admin.products` / `admin.services`; enable the Services sidebar item gated on `services.view`.
10. **Tests** — Pest feature tests mirroring `ClientsTest` structure:
    - **tenant isolation**: products/services of another company are invisible; route 404 on foreign records; `company_id` auto-stamp on create.
    - **access control**: `services.view` required for lists; `services.create`/`update`/`delete` gate the actions.
    - **crud**: create product w/ pricing, edit, soft-delete; assign service to client, edit, delete.
    - **expiry**: a service expiring within 7 days reports the right urgency color / surfaces under the "expiring soon" filter.
11. **Pint** — `vendor/bin/pint --dirty --format agent`.
12. **Run** — `php artisan test --compact` (expect green incl. Phase 1/2 baseline of 60) and `php artisan migrate:fresh --seed`.

## Verification / Testing Plan

- `php artisan test --compact` — all existing 60 tests stay green; new Products + ClientServices tests pass.
- `php artisan migrate:fresh --seed` — migrates cleanly in FK order; `ProductSeeder` produces the demo catalog + services.
- `vendor/bin/pint --dirty` — clean.
- **Manual (halt for user):** create/edit/delete a product group, product, and pricing; assign a service to a client; confirm expiry urgency colors; confirm the Client profile Services card shows the real count/list; confirm tenant isolation by logging in as another company.

## Risks & Assumptions

- **Money handling:** store prices as `decimal(12,2)`; no money-object library in v1 (consistent with the lightweight approach). Currency is a 3-char code per product pricing / service snapshot.
- **Product deletion vs. history:** `client_services.product_id` is nullable + `nullOnDelete` so soft-deleting/removing a product never destroys a client's service history (price/cycle are snapshotted on the service row).
- **`BelongsToCompany` on `ProductPricing`/`ClientService`:** pricings are reached via product (already scoped) but still carry `company_id` for direct queries and the global scope, matching `ClientNote`.
- **Auto-discovery of policies** assumed (Laravel 13). Will verify; register manually in a service provider only if needed.
- **Reminders (Phase 7)** depend on `expires_at` shapes defined here — keeping `expires_at` + `next_due_date` nullable and indexed now avoids a later migration.

## Open Questions (will confirm before/at implementation)

1. **Sidebar layout:** single "Services" entry (with the product catalog reachable from a tab/sub-nav) vs. two entries ("Products" + "Services"). Leaning two entries for clarity, both gated on `services.view`. — _will default to two items unless told otherwise._

## Discovery Notes

- Reused conventions verified against Phase 2: `BelongsToCompany` global scope + creating hook, `LogsActivity`
  with `logOnlyDirty()`, enum `label()`/`color()` pattern, Flux table + modal CRUD, `grantPermissions()` /
  `companyAdmin()` test helpers, `AuthCompanyTeamResolver` makes in-action permission checks resolve the team id.
- `services.*` permissions already seeded — no RBAC catalog change.
- No sub-agents used; the codebase was small enough to inspect directly.
