<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What: Creates the tenant-scoped `clients` table — the customer accounts each company manages.
 * Why: Clients are the heart of the panel and the parent of services, invoices, tickets and domains.
 *      Every row is owned by a company (`company_id`) to enforce single-database tenant isolation.
 * When: Run during Phase 2 (Clients) via `php artisan migrate`. Additive — no fresh migrate needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('company_name')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postcode')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('language', 5)->default('en');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            // Email is unique per tenant, not globally — two companies may share a client email.
            $table->unique(['company_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
