# Phase 5 — Support Tickets (Plan)

**Date:** 2026-06-24
**Phase:** 5 (per `docs/idea.md`)
**Builds on:** Phase 1 (tenant foundation + RBAC), Phase 2 (Clients), Phase 3 (Products & Services), Phase 4 (Invoices & Billing)

## Goal

Deliver the **Support Tickets** module: a lightweight internal helpdesk with departments, per-tenant
sequential ticket numbers, a threaded reply view (with private internal notes and file attachments), and
status/priority/assignment management — all tenant-isolated and gated on the already-seeded `tickets.*`
permission set.

## Clarified Decisions (from user)

1. **File attachments:** **In scope this phase.** Replies support uploaded files (stored on the `local`
   private disk), validated by mime/size, listed under each reply, downloaded via an authorized route.
2. **Replies:** **Admin-only.** Every reply is authored by an admin `User`. Each reply carries an
   `is_internal_note` flag (private admin note vs. a normal reply). No client-author concept and **no
   `Customer-Reply` status** in v1 — there is no client portal (out of scope), so inbound/customer messages
   are deferred with email piping.
3. **Client link:** **Required.** Every ticket belongs to a `Client` (matches the client-profile Tickets
   card and WHMCS behavior). Department, priority, and optional assignee are set on create.

## Affected Items

### New files

**Enums**
- `app/Enums/TicketStatus.php` — `Open`, `Answered`, `Closed` (`label()` + `color()`). *(Customer-Reply
  intentionally omitted — see Decision 2.)*
- `app/Enums/TicketPriority.php` — `Low`, `Medium`, `High`, `Urgent` (`label()` + `color()`).

**Migrations** (FK order: ticket_departments → tickets → ticket_replies → ticket_attachments)
- `*_create_ticket_departments_table.php`
- `*_create_tickets_table.php`
- `*_create_ticket_replies_table.php`
- `*_create_ticket_attachments_table.php`

**Models** (all `BelongsToCompany` + `LogsActivity`; soft deletes on `TicketDepartment` and `Ticket`)
- `app/Models/TicketDepartment.php`
- `app/Models/Ticket.php`
- `app/Models/TicketReply.php`
- `app/Models/TicketAttachment.php`

**Policies** (gate on `tickets.*`; departments + replies + attachments share the `tickets.*` set — see Risks)
- `app/Policies/TicketPolicy.php`
- `app/Policies/TicketDepartmentPolicy.php`

**Livewire**
- `app/Livewire/Admin/Tickets/Index.php` + view — list (search, status/department/priority/assignee
  filters, sort, paginate) + create modal (client, department, priority, assignee, subject, first message)
  + delete modal.
- `app/Livewire/Admin/Tickets/Show.php` + view — the ticket thread: header (status/priority/department/
  assignee edit), chronological reply thread (internal notes visually distinguished), reply composer
  (message + `is_internal_note` toggle + file attachments), attachment list with download links, delete
  reply.
- `app/Livewire/Admin/TicketDepartments/Index.php` + view — Flux-table CRUD (name, active) in modals.

**Attachment download**
- `app/Http/Controllers/Admin/TicketAttachmentController.php` — single-action; authorizes `view` on the
  parent ticket, streams the stored file from the private disk as a download.

**Factories & Seeder**
- `database/factories/TicketDepartmentFactory.php`, `TicketFactory.php` (states: `open`, `answered`,
  `closed`), `TicketReplyFactory.php` (state: `internalNote`).
- `database/seeders/TicketSeeder.php` — seed 3 departments (Sales, Technical, Billing) + ~15 tickets in
  mixed open/answered/closed states with a few replies each, scoped to the demo company.

**Tests**
- `tests/Feature/TicketsTest.php`
- `tests/Feature/TicketDepartmentsTest.php`

### Changed files
- `routes/web.php` — `admin.tickets`, `admin.tickets.show`, `admin.ticket-attachments.download` (or
  `admin.tickets.attachments.download`), `admin.ticket-departments`.
- `resources/views/layouts/app/sidebar.blade.php` — enable **Tickets** (Management, gated `tickets.view`)
  and add **Departments** (System, gated `tickets.view`). Replace the disabled Tickets placeholder.
- `app/Models/Client.php` — add `tickets()` HasMany.
- `app/Models/Company.php` — add `ticketDepartments()`, `tickets()` HasMany.
- `app/Models/User.php` — add `assignedTickets()` HasMany (FK `assigned_to`) for the assignee filter/display.
- `app/Livewire/Admin/Clients/Show.php` — add `tickets()` computed.
- `resources/views/livewire/admin/clients/show.blade.php` — replace the Tickets placeholder zero with a real
  count + tickets table.
- `database/seeders/DatabaseSeeder.php` — call `TicketSeeder` after `InvoiceSeeder`.

## Data Model

**ticket_departments**: `id, company_id, name, is_active (default true), timestamps, softDeletes`.

**tickets**: `id, company_id, client_id (cascade), department_id (restrict/nullOnDelete — see Risks),
assigned_to (nullable, FK users, nullOnDelete), number (string), subject, status, priority,
last_reply_at (nullable), closed_at (nullable), timestamps, softDeletes`.
Indexes: `status`, `priority`, `department_id`, `assigned_to`, `client_id`; unique `(company_id, number)`.
Number generated per company: `TKT-` + zero-padded next sequence (`max existing incl. trashed + 1`),
mirroring `Invoice::nextNumber()`.

**ticket_replies**: `id, company_id, ticket_id (cascade), user_id (nullable, FK users, nullOnDelete —
authoring admin), body (text), is_internal_note (bool, default false), timestamps`.
Index: `ticket_id`.

**ticket_attachments**: `id, company_id, ticket_reply_id (cascade), disk, path, original_name, mime_type,
size (unsigned int), timestamps`. Index: `ticket_reply_id`.

### Status / thread logic (on `Ticket`)
- The **first message** is captured on create as the opening `TicketReply` (admin-authored). Subject lives
  on the ticket; the body is the first reply.
- Posting a **non-internal** reply bumps `last_reply_at = now()`, and (per WHMCS semantics) moves status
  `Open → Answered` when an admin responds. Internal notes never change status or `last_reply_at`.
- Status is also directly admin-editable (`Open`/`Answered`/`Closed`); moving to `Closed` stamps
  `closed_at`, moving away clears it.
- `isOpen()` helper (`status !== Closed`) for dashboard/“open tickets” counts later (Phase 8).

### Attachment handling
- Uploaded via Livewire `WithFileUploads` on the reply composer. On save: store each file on the `local`
  disk under `tickets/{ticket_id}/{uuid}.{ext}`, persist a `TicketAttachment` row with original name, mime,
  size. Validation: `max:5120` (5 MB) each, mime allowlist (images, pdf, txt, log, zip) — tunable.
- Download route authorizes `view` on the parent ticket, then `Storage::disk($disk)->download($path,
  $original_name)`. Files are never web-public (private disk).
- Deleting a reply deletes its attachment rows **and** the stored files (model `deleting` hook or explicit
  cleanup in the component).

## Implementation Steps

1. Enums (`TicketStatus`, `TicketPriority`).
2. Migrations (4) in FK order; `php artisan migrate`.
3. Models + relationships + activity log + `nextNumber()`/status helpers; add HasMany on
   `Client`/`Company`/`User`.
4. Policies (2) gating on `tickets.*`.
5. Livewire: `TicketDepartments/Index`, then `Tickets/Index`, then `Tickets/Show` + views.
6. Attachment download controller.
7. Routes + sidebar + client-profile integration.
8. Factories + `TicketSeeder` + wire into `DatabaseSeeder`.
9. Feature tests (`TicketsTest`, `TicketDepartmentsTest`).
10. `vendor/bin/pint --dirty --format agent`; full `php artisan test --compact`.

## Verification / Testing Plan

- **Automated** (`php artisan test --compact`): tenant isolation (cross-company invisibility + `company_id`
  auto-stamp) for departments/tickets/replies/attachments; permission gates for every action
  (`viewAny`/`create`/`update`/`delete`); ticket create with generated number + `Open` status + opening
  reply; create validation (client/department/subject required, priority enum); reply posting flips
  `Open → Answered` and bumps `last_reply_at`; internal note does **not** change status or `last_reply_at`;
  status edit to `Closed` stamps `closed_at` and back clears it; attachment upload stores a file + row
  (`Storage::fake`), download route authorized (200) vs forbidden (403), and reply deletion removes the
  stored file; department CRUD; ticket soft-delete.
- **`migrate:fresh --seed`** runs clean and produces 3 departments + demo tickets in mixed states with
  replies.
- **Manual (halt for user):** create a ticket, post a public reply (status → Answered) and an internal note,
  upload + download an attachment, reassign/close the ticket, department CRUD, client-profile Tickets card.

## Risks & Assumptions

- **Permission catalog unchanged.** The `tickets.*` set (`view`/`create`/`update`/`delete`) is already
  seeded and granted to `manager`/`support` (and `.view` to `read-only`). Departments, replies, and
  attachments all gate on `tickets.*` (view→`tickets.view`, create/update/delete→matching) — no re-seed
  needed. The `support` role already has `tickets.view/create/update` but **not** `tickets.delete`, so
  delete actions stay manager-only by design.
- **Reply authorization** goes through the parent ticket's `update` policy (`tickets.update`) — no separate
  `TicketReplyPolicy`, mirroring the Phase 4 decision where payments authorized through the invoice
  (one fewer file, identical gate).
- **`department_id` on delete.** Departments use soft deletes and tickets reference them; deleting a
  department must not orphan/break tickets. Plan: `nullOnDelete` at the DB level (ticket keeps showing
  “— (deleted department)”), or block deletion when tickets reference it. **Default: `nullOnDelete`** for
  simplicity, consistent with how `tax_rate_id` was `nullOnDelete` in Phase 4. Revisit if a “reassign on
  delete” flow is wanted.
- **Ticket number race.** Sequence computed at create time; the unique `(company_id, number)` index is the
  backstop. Same single-admin-panel assumption as invoices.
- **Attachment storage.** Files live on the `local` (private) disk, never web-served; downloads always run
  through the authorized controller. `Storage::fake` is used in tests so nothing touches the real disk.
- **Soft-deleted clients.** `tickets.client_id` is `cascadeOnDelete` at the DB level, but clients soft-delete
  (Phase 2/3 pattern), so tickets survive a client soft-delete (history preserved).
- **Controller authorization.** Like the Phase 4 PDF controller, the base `Controller` lacks
  `AuthorizesRequests`; the attachment controller calls `Gate::authorize('view', $ticket)`.

## Out of Scope (this phase)
- Client portal / client-authored replies and the `Customer-Reply` status (no client login in v1).
- Email piping (inbound email → auto-ticket) and outbound ticket-reply email notifications (Phase 7 mail).
- SLA timers, canned responses, ticket merging/escalation, per-department permissions/routing.
- Dashboard “open tickets” widget wiring (Phase 8) — the `isOpen()` helper is provided now for later reuse.
