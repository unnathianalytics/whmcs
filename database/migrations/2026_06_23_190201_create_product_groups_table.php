<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What: Creates the tenant-scoped `product_groups` table — categories for a company's products
 *       (e.g. Shared Hosting, VPS, Domains).
 * Why: Products are grouped for catalog organisation and (Phase 7) per-group reminder rules. Every row is
 *      owned by a company (`company_id`) to enforce single-database tenant isolation.
 * When: Run during Phase 3 (Products & Services). Migrated before `products` so the FK target exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Slug is unique per tenant, not globally.
            $table->unique(['company_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_groups');
    }
};
