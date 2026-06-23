<?php

use App\Models\Company;
use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What: Creates the tenant-scoped `product_pricings` table — one price row per billing cycle per product.
 * Why: A product (e.g. "Pro Hosting") can be sold monthly, annually, biennially, etc., each at a different
 *      price. Modelling pricing as rows (rather than inline columns) keeps the catalog flexible and matches
 *      the idea.md DB design. `company_id` carried for direct queries and the tenant global scope.
 * When: Run during Phase 3. Migrated after `products` and before `client_services`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_pricings', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Product::class)->constrained()->cascadeOnDelete();
            $table->string('cycle');
            $table->decimal('price', 12, 2)->default(0);
            $table->string('currency', 3)->default('INR');
            $table->timestamps();

            // One price per cycle per product.
            $table->unique(['product_id', 'cycle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_pricings');
    }
};
