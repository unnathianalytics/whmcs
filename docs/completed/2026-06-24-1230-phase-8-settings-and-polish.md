# Phase 8 — Settings & Polish — Completion Report

**Date:** 2026-06-24
**Plan:** `docs/plans/2026-06-24-1230-phase-8-settings-and-polish.md`
**Status:** ✅ Completed (user-verified)

## Summary

Delivered all seven Phase 8 items from `docs/idea.md`:

1. **Settings key/value store** — a tenant-scoped `settings` table fronted by a typed `CompanySettings`
   service with a canonical default map, per-request memoisation, and transparent encryption of secret
   keys (SMTP password, Stripe/PayPal secrets).
2. **Settings UI** — a single tabbed Livewire screen (Company / Billing / Email / Localisation /
   Gateways / Reminders). Secrets are write-only: the masked field never wipes a stored secret when left
   blank. Read gated on `settings.view`, saves on `settings.manage`.
3. **Reminder settings** — global on/off switch plus default lead-times, normalised to unique,
   descending integers.
4. **Dashboard charts** — a Chart.js (CDN) six-month revenue bar chart, pre-aggregated server-side.
5. **Dashboard expiry widgets** — live "Expiring Soon (7 days)" and "Already Expired" watchlists that
   merge services and domains, plus the four stat cards wired to real tenant counts/revenue.
6. **Role–Permission UI** — `/admin/roles`: create/rename/delete tenant roles and sync permissions via a
   module-grouped checkbox matrix. Team-scoped, so a tenant can only see/touch its own roles.
7. **Activity-log viewer** — `/admin/activity-log`: a paginated audit feed scoped to the tenant's own
   causers, with event and admin filters.

Sub-agents were **not** used; the work was implemented directly after inspecting sibling modules
(`TaxRates`, `Reminders`) for conventions.

## Changelog

### Added
- `database/migrations/2026_06_24_092716_create_settings_table.php` — tenant-scoped key/value table, unique `(company_id, key)`.
- `app/Models/Setting.php` — `Setting` model (`BelongsToCompany`, JSON `value` cast; not activity-logged).
- `app/Services/Settings/CompanySettings.php` — typed settings facade (defaults, memoisation, encryption of secret keys).
- `app/Livewire/Admin/Settings/Index.php` + `resources/views/livewire/admin/settings/index.blade.php` — tabbed settings screen.
- `app/Livewire/Admin/Roles/Index.php` + `resources/views/livewire/admin/roles/index.blade.php` — role/permission matrix screen.
- `app/Livewire/Admin/ActivityLog/Index.php` + `resources/views/livewire/admin/activity-log/index.blade.php` — activity-log viewer.
- `database/seeders/SettingsSeeder.php` — seeds default settings per company.
- `tests/Feature/SettingsStoreTest.php`, `tests/Feature/RolesTest.php`, `tests/Feature/ActivityLogTest.php` — new feature tests.
- `docs/plans/2026-06-24-1230-phase-8-settings-and-polish.md` — plan.

### Changed
- `app/Livewire/Admin/Dashboard.php` — replaced placeholder `stats()` with live queries; added `expiringSoon()`, `expired()`, `revenueSeries()` computed props.
- `resources/views/livewire/admin/dashboard.blade.php` — rendered the expiry watchlists and the Chart.js revenue chart.
- `routes/web.php` — registered `admin.settings`, `admin.roles`, `admin.activity-log`.
- `resources/views/layouts/app/sidebar.blade.php` — enabled Roles + Settings nav items (gated), added Activity Log item.
- `database/seeders/DatabaseSeeder.php` — registered `SettingsSeeder`.
- `tests/Feature/DashboardTest.php` — added dashboard data-wiring + tenant-isolation tests.
- `docs/idea.md` — marked Phase 8 complete with a summary.

### Deleted
- None. (A `RolePolicy` was drafted then removed in favour of direct `can()` checks, consistent with the
  Settings/Roles/ActivityLog screens — none ended up in the tree.)

## Validation

- **Automated:** `php artisan test --compact` → **201 passed**, 471 assertions (174 baseline + 27 net new).
- **Formatting:** `vendor/bin/pint --dirty` → clean.
- **Seeding:** `php artisan migrate:fresh --seed` runs end-to-end; 15 settings rows seeded; defaults verified via tinker (currency `USD`, prefix `INV-`, lead times `[30,14,7,1]`).
- **Static analysis:** New files add only the same categories already present in the project's 40-error PHPStan baseline (untyped Livewire `render()`, Collection template-covariance). No new real issues.
- **Bug fixed during testing:** `Dashboard::revenueSeries()` looped with `$cursor->addMonth()` on a
  `CarbonImmutable` instance (no mutation → infinite loop, OOM). Reworked to reassign the cursor.
- **Manual:** User verified the Settings tabs persist, the dashboard widgets + revenue chart render, the
  Roles matrix works, and the Activity Log lists actions.

## Postponed Items

- **Live mail/gateway wiring** — settings are stored (and secrets encrypted) but not yet *applied*: SMTP
  settings don't override the runtime mailer, and gateway keys aren't consumed by a payment flow (no
  gateway integration exists in v1). The invoice prefix / due-days / currency are stored for future use.
- **Reminder lead-times → rule generation** — the default lead times are stored and surfaced, but creating
  reminder rules from them is still a manual action on the Reminders screen.
- **Activity-log subject-side scoping** — the feed is scoped by causer (this tenant's users). Activities
  with no causer (console/seeder/queue) are intentionally excluded from the admin view.

## Follow-up Recommendations

- Apply stored Email settings to the runtime mailer (a per-tenant mailer config / `Mail::mailer()` wrapper)
  so reminder + invoice mail honour tenant SMTP.
- Consume billing settings (`currency`, `invoice_prefix`, `invoice_due_days`, `tax_label`) in the invoice
  builder/generator instead of hard-coded defaults.
- Consider a small PHPStan baseline file to lock the existing noise and gate new code at level 7.
- Theme the Chart.js colors to dark/light via the Flux theme tokens rather than fixed emerald.
- Phase 9 (SaaS Admin area) is the next phase.
