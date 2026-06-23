<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What: Defines the `company_subscriptions` table — a company's assignment to a SaaS plan over time.
 * Why: Separating the subscription from `companies.plan_id` lets the platform track billing windows
 *      and status transitions (trialing → active → past_due → cancelled) without losing history.
 * When: Run during the Phase 1 schema build; `EnsureCompanyAdmin` middleware reads `status` to decide
 *       whether a tenant may use the panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saas_plan_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('trialing'); // trialing | active | past_due | cancelled
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_subscriptions');
    }
};
