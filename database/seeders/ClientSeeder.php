<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\ClientNote;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * What: Seeds demo clients (and a few notes) for a company so the Clients module has data on first run.
 * Why: The idea doc's development sample data calls for ~10 fake clients on the demo tenant; this gives
 *      a populated list/profile to verify the UI without manual entry.
 * When: Called from DatabaseSeeder after the demo company exists. Sets `company_id` explicitly because
 *       the seeder runs without an authenticated user (the BelongsToCompany scope no-ops there).
 */
class ClientSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('slug', 'demo-company')->first();

        if ($company === null) {
            return;
        }

        $author = User::where('company_id', $company->id)->first();

        Client::factory()
            ->count(10)
            ->for($company)
            ->create()
            ->each(function (Client $client) use ($company, $author): void {
                ClientNote::factory()
                    ->count(fake()->numberBetween(0, 2))
                    ->for($client)
                    ->create([
                        'company_id' => $company->id,
                        'user_id' => $author?->id,
                    ]);
            });
    }
}
