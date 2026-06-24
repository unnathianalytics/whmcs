# Phase 7 — Expiry Reminders (Completion Report)

**Date:** 2026-06-24
**Plan:** `docs/plans/2026-06-24-1148-phase-7-expiry-reminders.md`
**Status:** ✅ Completed & user-verified

## Summary

Delivered the **Expiry Reminders** module — automated, configurable, deduped notifications sent before a
client service or a domain expires, plus the daily auto-expiry of lapsed resources:

- **Reminder rules** (`/admin/reminders`, Rules tab) — tenant-scoped `ReminderRule` records carrying
  `resource_type` (service | domain), `days_before`, an email `subject`/`body` template, the channel flags
  (`notify_client`, `notify_admin`) and `is_active`. Flux-table CRUD in modals, gated on `reminders.manage`.
- **Sent log** (`/admin/reminders`, Sent Log tab) — read-only `ReminderLog` history (client, resource,
  interval, channel, recipient, sent_at) for auditing what actually went out.
- **`reminders:send` command** — scans every tenant's `expires_at`, dispatches the configured reminders
  (deduped so the same resource + interval + channel is never re-sent), then **auto-expires** Active
  services/domains whose expiry has passed (the Phase 6 deferral). Scheduled daily at **08:00**.
- **`ReminderDispatcher`** — the shared engine used by both the command and the manual action. It runs
  without an authenticated tenant, so it scopes every query explicitly per company and bypasses the
  `BelongsToCompany` global scope with `withoutGlobalScopes()` (the seeder pattern).
- **Queued `ExpiryReminderMail`** + markdown template, with `{client_name}`, `{product_name}` /
  `{domain_name}`, `{expires_at}`, `{days_left}` substitution via the shared `ReminderTemplate` helper.
- **Manual "Send reminder" action** on the Services and Domains row menus (gated `reminders.manage`) —
  forces a send across the company's active rules of that type, ignoring the interval gate.
- **Re-arming on renewal** — renewing a domain (or pushing a service's `expires_at` out) clears that
  resource's prior `reminder_logs` so the next cycle's intervals fire again.

## Clarified Decisions (from user)

1. **Rule scope — global per company** (by `resource_type` only; no per-product-group targeting in v1).
2. **Mail delivery — real queued Mailable** (`ExpiryReminderMail implements ShouldQueue`; `Mail::fake()` in
   tests; locally the `log` mailer, so nothing leaves).
3. **Auto-expiry — included** in `reminders:send` for both services and domains.
4. **Admin copy recipient — `Company.email`** (single contact address; skipped when null).

## Changelog

### Added
- `app/Enums/ReminderResourceType.php`
- `app/Models/ReminderRule.php`, `app/Models/ReminderLog.php`
- `database/migrations/2026_06_24_051502_create_reminder_rules_table.php`
- `database/migrations/2026_06_24_051503_create_reminder_logs_table.php`
- `database/factories/ReminderRuleFactory.php`, `database/factories/ReminderLogFactory.php`
- `database/seeders/ReminderRuleSeeder.php`
- `app/Policies/ReminderRulePolicy.php`
- `app/Support/ReminderTemplate.php`, `app/Support/ReminderDispatcher.php`
- `app/Console/Commands/SendExpiryReminders.php` (`reminders:send`)
- `app/Mail/ExpiryReminderMail.php`, `resources/views/mail/expiry-reminder.blade.php`
- `app/Livewire/Admin/Reminders/Index.php`, `resources/views/livewire/admin/reminders/index.blade.php`
- `tests/Feature/RemindersTest.php`
- `docs/plans/2026-06-24-1148-phase-7-expiry-reminders.md` (plan)

### Changed
- `routes/web.php` — registered the `admin.reminders` Livewire route.
- `routes/console.php` — scheduled `reminders:send` daily at 08:00.
- `resources/views/layouts/app/sidebar.blade.php` — enabled the Reminders nav item, gated on
  `reminders.view`.
- `app/Livewire/Admin/Services/Index.php` + `resources/views/livewire/admin/services/index.blade.php` —
  added the "Send reminder" row action; `save()` clears reminder logs when `expires_at` changes.
- `app/Livewire/Admin/Domains/Index.php` + `resources/views/livewire/admin/domains/index.blade.php` —
  added the "Send reminder" row action; `renew()` clears reminder logs to re-arm the next cycle.
- `database/seeders/DatabaseSeeder.php` — calls `ReminderRuleSeeder` after `DomainSeeder`.

## Validation

- `php artisan test --compact` — **174 passed, 418 assertions** (Phase 6 baseline was 154; **+20 new**).
  Coverage: tenant isolation (rules invisible cross-company; `company_id` auto-stamp); RBAC (list gated on
  `reminders.view`; CRUD + manual send gated on `reminders.manage`); rule validation + edit + soft delete;
  template substitution for both resource types; the command (client mail queued on the exact interval +
  logged; admin copy to `Company.email` when `notify_admin`; **dedupe** on a second run; inactive rules /
  wrong intervals / `notify_client=false` send nothing); auto-expiry (past-due Active → Expired, future and
  Cancelled untouched, both resource types); forced manual send regardless of interval; renewal clears
  reminder logs.
- `vendor/bin/phpstan analyse … --memory-limit=512M` — **0 errors** on all new files (level 7).
- `vendor/bin/pint --dirty` — clean.
- `php artisan migrate:fresh --seed` — clean; `ReminderRuleSeeder` produces 8 default rules per company.
- `php artisan route:list --path=admin/reminders` — registered; `php artisan schedule:list` — `reminders:send`
  daily at 08:00.
- **Manual verification by user:** rules CRUD, the Sent Log tab, the per-row "Send reminder" action, and the
  sidebar link — confirmed working in the browser.

## Discovery Notes / Deviations

- **`reminders.*` already seeded.** `RolesAndPermissionsSeeder::PERMISSIONS` already contained
  `reminders.view` / `reminders.manage` (granted to `manager`; `read-only` gets `reminders.view`). No role
  re-seed was needed — same situation as `domains.*` in Phase 6.
- **First console/mail/support code.** This phase introduced the app's first `app/Console/Commands`,
  `app/Mail` and `app/Support` directories, under the standard Laravel structure.
- **`company_id` is guarded on `ReminderLog`** (not in `$fillable`, matching the project convention), so the
  dispatcher sets it on the instance directly via `firstOrNew(...)->company_id = …` rather than through the
  mass-assignable arrays of `updateOrCreate()`.
- **`sent_at` typed `CarbonImmutable`.** The app casts dates to `Carbon\CarbonImmutable`; the `ReminderLog`
  PHPDoc reflects that so the direct `$log->sent_at = now()` assignment type-checks at phpstan level 7.
- **Dedupe vs. renewal handled.** As flagged in the plan, the dedupe unique key would otherwise block a
  renewed resource's next cycle; the domain `renew()` and the service `save()` (on expiry change) now delete
  that resource's `reminder_logs`.
- **Pre-existing phpstan note:** `DomainFactory.php:26` (committed in Phase 6) carries a
  `property.notFound` on the `company_id` closure; the new `ReminderLogFactory` was written to avoid it
  (`->where('id', …)->value('company_id')`). The existing file was left untouched.
- No sub-agents were used; the codebase was small enough to inspect directly.

## Postponed Items

- **Per-product-group reminder targeting** — rules are company-global by `resource_type` in v1.
- **Reminder settings section** (default lead times, global on/off) — Phase 8.
- **Dashboard expiry widgets** (combined services + domains "expiring soon / expired") — Phase 8; the
  `urgencyColor()` / `isExpired()` helpers and the auto-expiry are ready to feed them.
- **In-app (notification bell) channel** — only email channels (client / admin) are wired; idea.md lists an
  in-app channel as a future option.
- **Queue worker / production cron** — the schedule registration is in place; running the scheduler and a
  queue worker in production is an ops concern outside this phase.

## Follow-up Recommendations

- Wire the Phase 8 dashboard "Expiring soon (7 days)" and "Already expired" widgets from the combined
  `ClientService` + `Domain` expiry helpers, now that auto-expiry keeps statuses honest.
- Add the in-app notification channel to `ReminderDispatcher` (a third `channel` value) when the Flux
  notification bell lands.
- The Phase 1 follow-up (extract per-company role + default-rule seeding into an action invoked on UI
  company creation) remains open — new tenants created via the UI get neither roles nor default reminder
  rules until seeded.
- Consider a `reminder_logs` retention/clean command (mirroring `activitylog:clean`) once the table grows.
