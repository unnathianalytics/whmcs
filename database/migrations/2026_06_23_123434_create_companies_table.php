<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What: Defines the `companies` table — the tenant record at the heart of the multi-tenant model.
 * Why: Every tenant-scoped resource (clients, invoices, tickets, roles) hangs off a company via
 *      `company_id`. Suspension and trial state live here so middleware can gate access cheaply.
 * When: Run during the Phase 1 schema build; the `plan_id` snapshot points at the company's current
 *       SaaS plan while `company_subscriptions` keeps the billing history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->foreignId('plan_id')->nullable()->constrained('saas_plans')->nullOnDelete();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
