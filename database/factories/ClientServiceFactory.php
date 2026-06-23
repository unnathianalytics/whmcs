<?php

namespace Database\Factories;

use App\Enums\BillingCycle;
use App\Enums\ServiceStatus;
use App\Models\Client;
use App\Models\ClientService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientService>
 */
class ClientServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('-1 year', 'now');
        $cycle = fake()->randomElement(BillingCycle::cases());
        $months = $cycle->months();

        $expiresAt = $months === null
            ? null
            : (clone $startsAt)->modify("+{$months} months");

        return [
            'client_id' => Client::factory(),
            // Inherit the client's company so the service shares its tenant.
            'company_id' => fn (array $attributes): int => Client::withoutGlobalScopes()
                ->findOrFail($attributes['client_id'])->company_id,
            'product_id' => null,
            'label' => fake()->optional()->domainName(),
            'status' => fake()->randomElement(ServiceStatus::cases()),
            'billing_cycle' => $cycle,
            'price' => fake()->randomElement([199, 499, 999, 1999, 4999]),
            'currency' => 'INR',
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'next_due_date' => $expiresAt,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the service is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ServiceStatus::Active,
        ]);
    }

    /**
     * Indicate that the service expires within the given number of days from today.
     */
    public function expiringInDays(int $days): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->addDays($days)->toDateString(),
            'next_due_date' => now()->addDays($days)->toDateString(),
        ]);
    }
}
