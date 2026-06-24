<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What: Creates the tenant-scoped `tax_rates` table — a per-company catalog of named tax percentages.
 * Why: Phase 4 applies tax per invoice line item; admins maintain a reusable catalog (e.g. "GST 18%") so
 *      they pick a rate per line rather than retyping a percent. The percent is snapshotted onto the
 *      invoice item, so a rate stored here only seeds new lines. `company_id` carries the tenant scope.
 * When: Run during Phase 4. Migrated first so `invoice_items.tax_rate_id` can reference it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('rate', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
