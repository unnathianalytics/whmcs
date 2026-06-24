<?php

use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What: Creates the tenant-scoped `transactions` table — a recorded payment against an invoice.
 * Why: Payments are logged manually in v1 (no gateway), so each row captures the amount, method, an
 *      optional external reference and when it was paid. The sum of transactions drives an invoice's
 *      amount-paid / balance and flips it to Paid once cleared. Cascades with its parent invoice.
 * When: Run during Phase 4. Migrated last so its `invoices` FK target already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Invoice::class)->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('method')->default('bank-transfer');
            $table->string('reference')->nullable();
            $table->date('paid_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
