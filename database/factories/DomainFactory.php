<?php

namespace Database\Factories;

use App\Enums\DomainStatus;
use App\Models\Client;
use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Domain>
 */
class DomainFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $registeredAt = fake()->dateTimeBetween('-3 years', '-1 month');
        $expiresAt = (clone $registeredAt)->modify('+1 year');

        return [
            'client_id' => Client::factory(),
            // Inherit the client's company so the domain shares its tenant.
            'company_id' => fn (array $attributes): int => Client::withoutGlobalScopes()
                ->findOrFail($attributes['client_id'])->company_id,
            'domain_name' => fake()->unique()->domainName(),
            'registrar' => fake()->randomElement(['GoDaddy', 'Namecheap', 'Cloudflare', 'Google Domains', 'BigRock']),
            'status' => DomainStatus::Active,
            'registered_at' => $registeredAt,
            'expires_at' => $expiresAt,
            'last_renewed_at' => null,
            'renewal_cost' => fake()->randomElement([799, 999, 1299, 1499]),
            'currency' => 'INR',
            'ns1' => 'ns1.'.fake()->domainName(),
            'ns2' => 'ns2.'.fake()->domainName(),
            'ns3' => null,
            'ns4' => null,
            'whois_notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the domain expires within the given number of days from today.
     */
    public function expiringInDays(int $days): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->addDays($days)->toDateString(),
            'status' => DomainStatus::Active,
        ]);
    }

    /**
     * Indicate that the domain has already expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subDays(10)->toDateString(),
            'status' => DomainStatus::Expired,
        ]);
    }
}
