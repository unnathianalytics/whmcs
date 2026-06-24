<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\TicketDepartment;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What: Creates the tenant-scoped `tickets` table — a support thread raised against a client.
 * Why: The header carries the client, the routing department, the assigned admin, a per-company sequential
 *      `number`, the subject, and the status/priority that drive the queue filters. `last_reply_at` orders
 *      the queue by activity; `closed_at` records when the thread was resolved. The thread messages live in
 *      `ticket_replies`.
 * When: Run during Phase 5. Migrated after `clients`/`ticket_departments`/`users` and before `ticket_replies`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Client::class)->constrained()->cascadeOnDelete();
            // Keep the ticket if its department is removed — it just shows as unassigned.
            $table->foreignIdFor(TicketDepartment::class, 'department_id')->nullable()->constrained('ticket_departments')->nullOnDelete();
            // The admin currently handling the ticket; cleared if that user is deleted.
            $table->foreignIdFor(User::class, 'assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number');
            $table->string('subject');
            $table->string('status')->default('open');
            $table->string('priority')->default('medium');
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Drives the queue filters and the (Phase 8) open-tickets widget.
            $table->index('status');
            $table->index('priority');
            $table->index('department_id');
            $table->index('assigned_to');
            $table->index('client_id');
            // One ticket number per tenant.
            $table->unique(['company_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
