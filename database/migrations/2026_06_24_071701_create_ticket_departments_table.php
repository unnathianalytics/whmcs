<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What: Creates the tenant-scoped `ticket_departments` table — helpdesk routing buckets (e.g. Sales,
 *      Technical, Billing).
 * Why: Tickets are grouped by department so admins can filter the queue. Departments are per-company and
 *      soft-deletable; an inactive department stays selectable on existing tickets but is hidden from the
 *      create form.
 * When: Run during Phase 5. Migrated before `tickets` (FK target).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_departments', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_departments');
    }
};
