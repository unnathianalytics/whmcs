<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What: Creates the tenant-scoped `reminder_rules` table — a configurable "send a reminder N days before
 *       this resource type expires" rule.
 * Why: Phase 7 reminders are driven by admin-defined rules rather than hard-coded lead times. A rule is
 *       scoped by `resource_type` (service | domain) only in v1; `days_before` is the trigger interval, and
 *       the channel flags (`notify_client`, `notify_admin`) plus the subject/body templates configure what
 *       is sent. `is_active` disables a rule without deleting it. `(resource_type, is_active)` is indexed
 *       because the daily dispatcher loads active rules per type.
 * When: Run during Phase 7. Migrated after `companies` so the foreign key resolves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->string('resource_type');
            $table->unsignedInteger('days_before');
            $table->string('subject');
            $table->text('body');
            $table->boolean('notify_client')->default(true);
            $table->boolean('notify_admin')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index(['resource_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_rules');
    }
};
