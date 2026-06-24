<?php

use App\Models\Company;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What: Creates the tenant-scoped `ticket_replies` table — the messages in a ticket thread.
 * Why: A ticket is a conversation; each reply is authored by an admin `User` (no client portal in v1).
 *      `is_internal_note` marks a private admin note that is not part of the client-facing conversation,
 *      so the thread can mix public replies and internal notes. The opening message of a ticket is stored
 *      as the first reply.
 * When: Run during Phase 5. Migrated after `tickets`/`users` and before `ticket_attachments`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Ticket::class)->constrained()->cascadeOnDelete();
            // The authoring admin; kept null if that user is later deleted so history survives.
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->boolean('is_internal_note')->default(false);
            $table->timestamps();

            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_replies');
    }
};
