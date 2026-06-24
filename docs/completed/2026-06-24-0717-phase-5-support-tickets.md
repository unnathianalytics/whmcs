# Phase 5 — Support Tickets (Completion Report)

**Date:** 2026-06-24
**Plan:** `docs/plans/2026-06-24-0717-phase-5-support-tickets.md`
**Status:** ✅ Completed & user-verified

## Summary

Delivered the **Support Tickets** module — a lightweight internal helpdesk on the Phase 1–4 tenant-isolation
foundation:

- **Departments** (`/admin/ticket-departments`) — per-company routing buckets (name + active flag), Flux-table
  CRUD in modals, with a live ticket count per department.
- **Tickets** — `Ticket` headers raised against a **required** client, carrying a per-company sequential
  `number` (`TKT-000001`…), subject, status, priority, optional department and assignee, plus
  `last_reply_at` (queue ordering) and `closed_at`. Created from `/admin/tickets` (header + first message),
  then worked on a dedicated thread page.
- **Threaded replies** — `TicketReply` rows authored by an admin `User` (no client portal in v1). Each reply
  is either a client-facing message or a private **internal note** (`is_internal_note`). The opening message
  is stored as the first reply. Posting a public reply bumps `last_reply_at` and moves an **Open** ticket to
  **Answered**; internal notes never advance the ticket.
- **File attachments** — `TicketAttachment` rows for files uploaded against a reply, stored on the private
  `local` disk (never web-served) under `tickets/{ticket_id}/…`. Validated by mime/size (≤5 MB, ≤5 files),
  listed under each reply, and retrieved only through an authorized download route. Deleting a reply removes
  its stored files via a model `deleting` hook.
- **Header management** — status (Open/Answered/Closed), priority, department and assignee are editable from
  the thread; moving to **Closed** stamps `closed_at`, moving away clears it.
- **Enums** — `TicketStatus` (Open/Answered/Closed; `label()` + `color()`) and `TicketPriority`
  (Low/Medium/High/Urgent; `label()` + `color()`). `Customer-Reply` intentionally omitted (no client portal).
- **RBAC** — all actions gate on the already-seeded `tickets.*` permission set via `TicketPolicy` /
  `TicketDepartmentPolicy`; replies/attachments authorize through the parent ticket's `update`/`view`.
- **Client profile + sidebar** — the Tickets placeholder card now shows the real count plus a tickets table;
  sidebar enables **Tickets** (Management) and **Departments** (System), gated on `tickets.view`.

## Clarified Decisions (from user)

1. **File attachments — included** this phase (private `local` disk + authorized download).
2. **Replies — admin-only**, each with an optional internal-note flag; no client-author concept and no
   `Customer-Reply` status (no client portal in v1).
3. **Client link — required** on every ticket.

## Changelog

### Added
- `app/Enums/TicketStatus.php`, `app/Enums/TicketPriority.php`
- `app/Models/TicketDepartment.php`, `app/Models/Ticket.php`, `app/Models/TicketReply.php`, `app/Models/TicketAttachment.php`
- `database/migrations/2026_06_24_071701_create_ticket_departments_table.php`
- `database/migrations/2026_06_24_071702_create_tickets_table.php`
- `database/migrations/2026_06_24_071703_create_ticket_replies_table.php`
- `database/migrations/2026_06_24_071704_create_ticket_attachments_table.php`
- `app/Policies/TicketPolicy.php`, `app/Policies/TicketDepartmentPolicy.php`
- `app/Livewire/Admin/Tickets/Index.php`, `resources/views/livewire/admin/tickets/index.blade.php`
- `app/Livewire/Admin/Tickets/Show.php`, `resources/views/livewire/admin/tickets/show.blade.php`
- `app/Livewire/Admin/TicketDepartments/Index.php`, `resources/views/livewire/admin/ticket-departments/index.blade.php`
- `app/Http/Controllers/Admin/TicketAttachmentController.php`
- `database/factories/TicketDepartmentFactory.php`, `TicketFactory.php`, `TicketReplyFactory.php`, `TicketAttachmentFactory.php`
- `database/seeders/TicketSeeder.php`
- `tests/Feature/TicketsTest.php`, `tests/Feature/TicketDepartmentsTest.php`
- `docs/plans/2026-06-24-0717-phase-5-support-tickets.md` (plan)

### Changed
- `app/Models/Client.php` — added `tickets()` HasMany.
- `app/Models/Company.php` — added `ticketDepartments()`, `tickets()` HasMany.
- `app/Models/User.php` — added `assignedTickets()` HasMany (FK `assigned_to`).
- `app/Livewire/Admin/Clients/Show.php` — added `tickets()` computed.
- `resources/views/livewire/admin/clients/show.blade.php` — real Tickets count + tickets table (replaced the placeholder zero).
- `routes/web.php` — `admin.tickets`, `admin.tickets.show`, `admin.ticket-attachments.download`, `admin.ticket-departments`.
- `resources/views/layouts/app/sidebar.blade.php` — enabled Tickets + added Departments nav items, gated on `tickets.view`.
- `database/seeders/DatabaseSeeder.php` — calls `TicketSeeder` after `InvoiceSeeder`.

## Validation

- `php artisan test --compact` — **138 passed, 327 assertions** (Phase 4 baseline was 113; +25 new across
  Tickets and TicketDepartments). Coverage: tenant isolation (cross-company invisibility, `company_id`
  auto-stamp) for departments/tickets/replies/attachments; permission gates
  (`viewAny`/`create`/`update`/`delete`); ticket create with generated number + Open status + opening reply;
  create validation (client/department/subject/message required); reply validation (body required); public
  reply flipping Open→Answered and bumping `last_reply_at`; internal note leaving status/`last_reply_at`
  untouched; close stamping `closed_at` and reopen clearing it; priority change; attachment upload storing a
  file + row (`Storage::fake`), authorized download (200) vs forbidden (403), and reply deletion removing the
  stored file; department CRUD; ticket soft-delete.
- `php artisan migrate:fresh --seed` — clean; migrations run in FK order (ticket_departments → tickets →
  ticket_replies → ticket_attachments); `TicketSeeder` produces 3 departments, 15 tickets (mixed
  open/answered/closed — 5 closed) and 25 replies.
- `vendor/bin/pint --dirty` — clean.
- **Manual verification by user:** ticket list filters/search/sort, create ticket → thread, public reply
  (→ Answered) + internal note, attachment upload + download, reassign/close/reopen, department CRUD,
  client-profile Tickets card — confirmed working in the browser.

## Discovery Notes / Deviations

- **`foreignIdFor` column name.** `foreignIdFor(TicketDepartment::class)` defaults the column to
  `ticket_department_id`; the model/factory use `department_id`, so the migration was corrected to
  `foreignIdFor(TicketDepartment::class, 'department_id')->constrained('ticket_departments')`. Surfaced and
  fixed during the first test run.
- **No separate reply/attachment policies.** Replies and attachments only exist within a ticket, so they
  authorize through the parent ticket's `update`/`view` (same pattern Phase 4 used for payments through the
  invoice) — fewer files, identical gate.
- **Departments reuse `tickets.*`.** The seeded catalog has no `ticket_departments.*` set and idea.md groups
  departments under the helpdesk module, so `TicketDepartmentPolicy` gates on `tickets.*` (the Phase 4 tax
  decision repeated). No role re-seed required.
- **Support role keeps delete manager-only.** The seeded `support` role has `tickets.view/create/update` but
  not `tickets.delete`, so support agents can work tickets but not delete them — by design (flagged to the
  user, left as-is).
- **Attachment controller uses the `Gate` facade.** The base `Controller` lacks `AuthorizesRequests`, so the
  download controller calls `Gate::authorize('view', $ticket)` (same as the Phase 4 PDF controller).
- **`company_id` not in `$fillable`** on the new models (matching the Phase 2–4 convention). It is stamped by
  the `BelongsToCompany` creating hook under auth, or set explicitly by factories/seeder; replies and
  attachments created via the builder set `company_id` from the parent before saving.
- **Ticket number generation** is computed per tenant (`max existing incl. trashed + 1`), backed by a unique
  `(company_id, number)` index — same single-admin-panel assumption as invoices.
- **`department_id` on delete** is `nullOnDelete`: removing a department leaves existing tickets intact and
  unassigned rather than blocking the delete.
- No sub-agents were used; the codebase was small enough to inspect directly.

## Postponed Items

- **Client portal / client-authored replies** and the `Customer-Reply` status — deferred (no client login in
  v1).
- **Email piping** (inbound email → auto-ticket) and **outbound ticket-reply notifications** — Phase 7 mail.
- **SLA timers, canned responses, ticket merging/escalation, per-department routing/permissions.**
- Domains count on the client profile remains a placeholder until Phase 6.
- Dashboard "open tickets" widget wiring (Phase 8) — the `Ticket::isOpen()` helper is provided now for reuse.

## Follow-up Recommendations

- When the dashboard widgets are wired (Phase 8), source "Open tickets" from `Ticket::isOpen()` and surface
  recent/urgent tickets alongside the expiring/overdue lists.
- When Phase 7 (mail) lands, send a reply notification to the client and reuse the same Mailable/queue
  pattern as the reminder system; that is also the natural place to add the `Customer-Reply` status if a
  client portal is ever built.
- The Phase 1 follow-up (extract per-company role seeding into an action invoked on UI company creation)
  remains open — new tenants created via the UI still get no roles/permissions until seeded, so the
  Tickets/Departments screens would 403 for them.
- Consider an attachment retention/cleanup policy and a max-total-size-per-ticket guard before this ships to
  larger tenants; downloads already run through the authorized controller so files stay private.
- If department configuration later needs its own role separate from ticketing, split out a
  `ticket_departments.*` permission set and a dedicated gate.
