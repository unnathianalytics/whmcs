<?php

use App\Models\Client;
use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What: Creates the tenant-scoped `domains` table — a domain registration tracked against a client.
 * Why: Phase 6 tracks domains manually (no live registrar API in v1). The row carries the registrar, status,
 *      the key dates (`registered_at`, `expires_at`) that feed the urgency badge and the Phase 7 reminder
 *      system, manual renewal logging (`last_renewed_at`, `renewal_cost`), the four nameservers, and free-text
 *      WHOIS notes. `expires_at` is indexed for the daily reminder scan and the expiry filters.
 * When: Run during Phase 6. Migrated after `clients`/`companies` so its foreign keys resolve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Client::class)->constrained()->cascadeOnDelete();
            $table->string('domain_name');
            $table->string('registrar')->nullable();
            $table->string('status')->default('active');
            $table->date('registered_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->date('last_renewed_at')->nullable();
            $table->decimal('renewal_cost', 10, 2)->nullable();
            $table->string('currency', 3)->default('INR');
            $table->string('ns1')->nullable();
            $table->string('ns2')->nullable();
            $table->string('ns3')->nullable();
            $table->string('ns4')->nullable();
            $table->text('whois_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Drives the queue filters, the expiry urgency badge, and the (Phase 7) reminder scan.
            $table->index('domain_name');
            $table->index('status');
            $table->index('expires_at');
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
