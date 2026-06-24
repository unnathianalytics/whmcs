<?php

use App\Models\Client;
use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What: Creates the tenant-scoped `invoices` table — a billing document raised against a client.
 * Why: The header carries the client, a per-company sequential `number`, the status, the issue/due dates,
 *      and cached money totals (`subtotal`, `tax_total`, `total`) recalculated from the line items so list
 *      and report queries never re-sum on the fly. `paid_at` records when the balance was cleared.
 * When: Run during Phase 4. Migrated after `clients` (FK target) and before `invoice_items`/`transactions`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Client::class)->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->string('status')->default('draft');
            $table->date('issue_date');
            $table->date('due_date');
            $table->string('currency', 3)->default('INR');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Drives the list filters and the (Phase 7) overdue/renewal scans.
            $table->index('status');
            $table->index('due_date');
            $table->index('client_id');
            // One invoice number per tenant.
            $table->unique(['company_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
