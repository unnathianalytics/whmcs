<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\ReminderRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What: Creates the tenant-scoped `reminder_logs` table — the immutable history of reminders actually sent.
 * Why: A log row guarantees the same notice is never sent twice for the same resource + interval + channel
 *       (the unique key), which is what makes the daily `reminders:send` command idempotent. The morph
 *       columns point at the expiring resource (a ClientService or Domain); `client_id` is denormalised so
 *       the log viewer can list/group by client without a polymorphic join. The rule FK nulls on delete so
 *       deleting a rule never erases history.
 * When: Run during Phase 7. Migrated after `reminder_rules`, `clients` and `companies` so its keys resolve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(ReminderRule::class)->nullable()->constrained()->nullOnDelete();
            $table->morphs('remindable');
            $table->foreignIdFor(Client::class)->constrained()->cascadeOnDelete();
            $table->unsignedInteger('days_before');
            $table->string('channel');
            $table->string('recipient');
            $table->timestamp('sent_at');
            $table->timestamps();

            // The dedupe guarantee: each resource gets each interval's client/admin notice at most once.
            $table->unique(['remindable_type', 'remindable_id', 'days_before', 'channel'], 'reminder_logs_dedupe_unique');
            $table->index('company_id');
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_logs');
    }
};
