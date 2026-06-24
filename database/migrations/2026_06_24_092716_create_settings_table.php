<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What: Creates the tenant-scoped `settings` table — a flexible key/value store per company.
 * Why: Phase 8 needs configurable per-tenant settings (currency, invoice prefix, SMTP, timezone,
 *      gateway keys, reminder defaults). A key/value table keeps the schema flexible as new settings
 *      are added without further migrations; `value` is JSON-encoded so it can hold strings, bools,
 *      ints, or arrays. `company_id` carries the tenant scope and `(company_id, key)` is unique so a
 *      key resolves to exactly one value per tenant.
 * When: Run during Phase 8. Read/written through the CompanySettings service, never raw.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
