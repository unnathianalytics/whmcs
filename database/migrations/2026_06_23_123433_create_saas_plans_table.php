<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What: Defines the `saas_plans` table — platform-level subscription tiers (Starter, Pro, Agency).
 * Why: The SaaS Admin sells tenancy via plans; each plan carries a price and JSON feature limits
 *      (max clients, max admins, etc.) that gate what a company may do.
 * When: Run during the Phase 1 schema build; referenced by `companies.plan_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('interval')->default('monthly'); // monthly | annual
            $table->json('limits')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_plans');
    }
};
