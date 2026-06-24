<?php

use App\Models\Company;
use App\Models\Invoice;
use App\Models\TaxRate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What: Creates the tenant-scoped `invoice_items` table — one priced line per invoice.
 * Why: Each line carries a description, quantity and unit price plus an optional tax rate. The tax percent
 *      is snapshotted onto `tax_rate` (and `tax_rate_id` is nullOnDelete) so editing or deleting a catalog
 *      rate never rewrites a historical invoice — the same snapshot pattern used for service pricing. The
 *      derived `line_subtotal`, `tax_amount` and `line_total` are stored so totals never re-compute on read.
 * When: Run during Phase 4. Migrated after `invoices` and `tax_rates`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Invoice::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(TaxRate::class)->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 8, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
