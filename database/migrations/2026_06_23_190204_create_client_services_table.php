<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What: Creates the tenant-scoped `client_services` table — a client's subscription to a product.
 * Why: This is the operational heart of billing & renewals: each row tracks status, the billing cycle, a
 *      price snapshot, and the key dates (`starts_at`, `expires_at`, `next_due_date`) that drive expiry
 *      urgency on the UI and the Phase 7 reminder system. `product_id` is nullable + null-on-delete so
 *      removing a product never destroys a client's service history (price/cycle are snapshotted here).
 * When: Run during Phase 3. Migrated last so its `clients` and `products` FK targets already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_services', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Client::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Product::class)->nullable()->constrained()->nullOnDelete();
            $table->string('label')->nullable();
            $table->string('status')->default('pending');
            $table->string('billing_cycle');
            $table->decimal('price', 12, 2)->default(0);
            $table->string('currency', 3)->default('INR');
            $table->date('starts_at');
            $table->date('expires_at')->nullable();
            $table->date('next_due_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Drives the "expiring soon" / "expired" queries and the reminder scheduler.
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_services');
    }
};
