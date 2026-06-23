<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What: Adds `is_saas_admin` and `company_id` to the existing `users` table.
 * Why: A single `User` model serves both tiers — the platform owner (`is_saas_admin = true`, no
 *      company) and per-tenant admins (`company_id` set). `company_id` also resolves the spatie
 *      permission team id so roles are isolated per company.
 * When: Run during the Phase 1 schema build; read by `EnsureSaasAdmin` / `EnsureCompanyAdmin`
 *       middleware and the post-login redirect.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_saas_admin')->default(false)->after('email');
            $table->foreignId('company_id')->nullable()->after('is_saas_admin')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn('is_saas_admin');
        });
    }
};
