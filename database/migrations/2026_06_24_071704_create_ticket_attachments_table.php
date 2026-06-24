<?php

use App\Models\Company;
use App\Models\TicketReply;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What: Creates the tenant-scoped `ticket_attachments` table — files uploaded against a ticket reply.
 * Why: Admins attach screenshots/logs to a reply. The file itself lives on the private `local` disk (never
 *      web-served); this row stores its `disk`/`path` plus the original name, mime type and byte size so the
 *      authorized download route can stream it back with the right filename.
 * When: Run during Phase 5. Migrated after `ticket_replies`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(TicketReply::class)->constrained()->cascadeOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();

            $table->index('ticket_reply_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_attachments');
    }
};
