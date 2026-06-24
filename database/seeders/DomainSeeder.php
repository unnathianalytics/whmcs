<?php

namespace Database\Seeders;

use App\Enums\DomainStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Domain;
use Illuminate\Database\Seeder;

/**
 * What: Seeds 5 demo domain registrations for the demo company so the Domains module has data on first run.
 * Why: The idea.md development sample data calls for 5 domains. A spread of expiry dates (already expired,
 *      expiring soon, and comfortably future) exercises the urgency badge and the expiry filters out of the box.
 * When: Called from DatabaseSeeder after TicketSeeder. Sets `company_id` explicitly because the seeder runs
 *       without an authenticated user (the BelongsToCompany scope no-ops there).
 */
class DomainSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('slug', 'demo-company')->first();

        if ($company === null) {
            return;
        }

        $clients = Client::where('company_id', $company->id)->get();

        if ($clients->isEmpty()) {
            return;
        }

        // A spread of expiry offsets (days from today) so the urgency badge shows red/yellow/green and one expired.
        $expiryOffsets = [-12, 5, 25, 120, 300];

        foreach ($expiryOffsets as $offset) {
            $expiresAt = now()->addDays($offset);

            Domain::create([
                'company_id' => $company->id,
                'client_id' => $clients->random()->id,
                'domain_name' => fake()->unique()->domainName(),
                'registrar' => fake()->randomElement(['GoDaddy', 'Namecheap', 'Cloudflare', 'BigRock']),
                'status' => $offset < 0 ? DomainStatus::Expired : DomainStatus::Active,
                'registered_at' => $expiresAt->copy()->subYear(),
                'expires_at' => $expiresAt,
                'renewal_cost' => fake()->randomElement([799, 999, 1299]),
                'currency' => 'INR',
                'ns1' => 'ns1.'.fake()->domainName(),
                'ns2' => 'ns2.'.fake()->domainName(),
                'whois_notes' => fake()->optional()->sentence(),
            ]);
        }
    }
}
