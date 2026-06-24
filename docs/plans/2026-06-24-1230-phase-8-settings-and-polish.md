# Phase 8 — Settings & Polish

**Date:** 2026-06-24 12:30
**Status:** Planning

## Goal

Close out Phase 8 from `docs/idea.md`: give each tenant a real settings store and UI, wire the
company dashboard to live data (stat cards, expiry watchlists, revenue chart), and ship the two
remaining admin-governance screens — the **Role–Permission UI** and the **Activity Log viewer**.
After this phase the company-admin panel is feature-complete except for the SaaS-admin area (Phase 9).

## Deliverables (from idea.md Phase 8 checklist)

1. Settings key/value store (tenant-scoped).
2. Company / billing / email / localisation / payment-gateway settings UI.
3. Reminder settings section (default lead times, global on/off).
4. Dashboard charts wired to real data (revenue, last 6 months).
5. Dashboard expiry widgets wired to real data (expiring-soon + already-expired).
6. **Role–Permission UI** at `/admin/roles` — create roles, list permissions, sync via checkbox matrix.
7. Admin activity-log viewer (spatie/laravel-activitylog).

## Affected Items

### New files
- `database/migrations/XXXX_create_settings_table.php` — `id, company_id, key, value (text/json), timestamps`, unique `(company_id, key)`.
- `app/Models/Setting.php` — `BelongsToCompany`, casts, no activity log (config noise).
- `app/Services/Settings/CompanySettings.php` — typed accessor/repository (`get`, `set`, `all`, defaults, cache per request) so callers never touch raw keys.
- `app/Enums/SettingGroup.php` (optional) — groups for the settings UI tabs.
- `app/Livewire/Admin/Settings/Index.php` + `resources/views/livewire/admin/settings/index.blade.php` — tabbed settings screen (Company / Billing / Email / Localisation / Gateways / Reminders).
- `app/Livewire/Admin/Roles/Index.php` + view — role list, create/edit/delete, permission checkbox matrix.
- `app/Livewire/Admin/ActivityLog/Index.php` + view — paginated, filterable activity feed.
- `app/Policies/SettingPolicy.php` (or gate) — `settings.view` / `settings.manage`.
- `app/Policies/RolePolicy.php` — `roles.view` / `roles.manage` (spatie `Role` model).
- Tests: `tests/Feature/Admin/Settings/*`, `Roles/*`, `ActivityLog/*`, `Dashboard*`.

### Changed files
- `app/Livewire/Admin/Dashboard.php` + `resources/views/livewire/admin/dashboard.blade.php` — replace placeholder `stats()` with real tenant queries; add `expiringSoon()`, `expired()`, `revenueSeries()` computed props; render Chart.js bar chart via Alpine + CDN.
- `routes/web.php` — add `admin.settings`, `admin.roles`, `admin.activity-log` routes.
- `resources/views/layouts/app/sidebar.blade.php` — enable the disabled **Roles** + **Settings** items (gated on `roles.view` / `settings.view`), add **Activity Log** item.
- `database/seeders/DatabaseSeeder.php` (or new `SettingsSeeder`) — seed sensible default settings per demo company.
- `database/seeders/RolesAndPermissionsSeeder.php` — no new permissions needed (`roles.*`, `settings.*` already exist); confirm only.

### Database tables
- New: `settings`.
- Read-only consumers: `invoices`, `transactions`, `client_services`, `domains` (dashboard); `activity_log` (viewer); spatie `roles` / `permissions` / `role_has_permissions` (roles UI).

## Implementation Steps

1. **Settings store**
   - Migration + `Setting` model (tenant-scoped via `BelongsToCompany`, `value` cast to handle string/bool/int/json).
   - `CompanySettings` service: declared defaults map (currency, tax_label, invoice_prefix, due_days, smtp_*, from_name/email, date_format, timezone, default_language, gateway toggles/keys, reminder defaults `reminder_lead_times`, `reminders_enabled`). `get()` falls back to default; `set()`/`fill()` upsert; encrypt gateway API-key values via Laravel `encrypt()`.
2. **Settings UI** — tabbed `flux:tabs` screen; one Livewire component, properties bound per group; `save()` per tab; authorize `settings.manage`; Reminders tab exposes `reminders_enabled` + default `days_before` lead times consumed by the reminders module/seeder.
3. **Dashboard wiring**
   - `stats`: clients count, active services count, open tickets count, revenue this month (sum of `transactions.amount` where `paid_at` in current month, tenant-scoped).
   - `expiringSoon`: services + domains with `expires_at` within 7 days (merged, sorted, status Active).
   - `expired`: services + domains already past `expires_at` and still Active.
   - `revenueSeries`: last 6 months of paid transaction totals → Chart.js bar chart (Alpine `x-data`, Chart.js CDN, theme-aware colors).
4. **Role–Permission UI** — `/admin/roles`: list tenant roles (spatie team scope = `company_id`), create/rename/delete role (block deleting built-in `manager`? — keep editable but warn), permission matrix grouped by module; `syncPermissions()` on save; set permission team id to current company before queries; authorize `roles.manage`.
5. **Activity-log viewer** — `/admin/activity-log`: paginated `Activity` query filtered to subjects belonging to the tenant (filter by `causer`, `event`, date); show description, causer, subject, changes; authorize `settings.view` or a dedicated check (reuse `roles.view`? decide: gate on `settings.view`).
6. **Sidebar + routes** — enable Settings/Roles, add Activity Log, register routes.
7. **Seeders** — default settings per demo company; verify roles seeder still aligns.
8. **Tests + Pint** — feature tests for each screen (auth/permission gates, CRUD, tenant isolation, dashboard numbers); run `vendor/bin/pint --dirty --format agent`; run `php artisan test --compact`.

## Verification / Testing Plan

- New Pest feature tests:
  - Settings: save round-trips a value; tenant isolation; `settings.manage` gate denies read-only; gateway key stored encrypted.
  - Dashboard: stats reflect seeded data; expiring/expired lists contain the right rows; revenue series sums paid transactions.
  - Roles: create role, sync permissions, tenant scoping (role from company A invisible to company B); `roles.manage` gate.
  - Activity log: only tenant's activities listed; filters work; gate enforced.
- Run full suite (`php artisan test --compact`) — expect current 174 still green + new tests.
- Manual: log in as demo company admin, walk each new screen, confirm chart renders and toggling a setting persists.

## Risks & Assumptions

- **spatie team scoping**: role queries must call `setPermissionsTeamId($company->id)` (as the seeder does) or roles leak/disappear across tenants. The Roles UI is the main risk area.
- **Activity scope**: `activity_log` has no `company_id`; tenant filtering must go through `subject` belonging to the company (or join on causer's company). Need to confirm a reliable scoping path — may filter by `causer_id in (company users)` plus subjects, or add a query constraint. Will verify during step 5; if unreliable, scope by causer (company users) which is safe and sufficient for an admin audit trail.
- **Chart.js via CDN**: matches idea.md's "no extra package" decision; must render inside Livewire without re-init issues on navigate (use `wire:ignore` + Alpine init).
- **Gateway keys**: encrypt at rest; never echo decrypted secret back into the field (show masked placeholder).
- **No new dependencies** assumed; DomPDF already present, Chart.js is CDN.

## Discovery Notes (from codebase inspection)

- `settings.*` and `roles.*` permissions already seeded in `RolesAndPermissionsSeeder::PERMISSIONS` — no permission additions required.
- Sidebar already has disabled placeholder items for **Roles** and **Settings** (`sidebar.blade.php:90-91`) — just enable + gate them.
- `Dashboard.php` `stats()` returns hard-coded zeros; expiry cards + revenue card are static text — all placeholders to replace.
- Convention for admin screens: class-based Livewire `Index` with `WithPagination`, `#[Url]` search, `#[Computed]` data, `Flux::toast`, modal create/edit/delete, `authorize()` against a Policy, tenant isolation via `BelongsToCompany` global scope. Mirror `TaxRates/Index.php`.
- `ClientService` already has `isExpired()`, `daysUntilExpiry()`, `urgencyColor()`, and an activity-log fields list — reuse for dashboard widgets. `Domain` has the same helpers (per Phase 6 report).
- Enums exist for `InvoiceStatus`, `ServiceStatus`, `DomainStatus`, etc. — use them for dashboard filters.
