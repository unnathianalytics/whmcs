<?php

use App\Models\Company;
use App\Models\ProductGroup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What: Creates the tenant-scoped `products` table — the sellable plans within a product group.
 * Why: Products define what a client can be subscribed to (name, optional setup fee). Pricing lives in a
 *      separate `product_pricings` table so a product can offer several billing cycles. Every row is
 *      owned by a company (`company_id`) for tenant isolation.
 * When: Run during Phase 3. Migrated after `product_groups` and before `product_pricings`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(ProductGroup::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->decimal('setup_fee', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
