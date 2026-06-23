<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What: Creates `client_notes` — internal, admin-only notes attached to a client.
 * Why: Admins need a private running log on a client (context, history) that clients never see.
 *      Carries `company_id` for the same tenant-isolation guarantees as the parent client.
 * When: Run during Phase 2 (Clients) via `php artisan migrate`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Client::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_notes');
    }
};
