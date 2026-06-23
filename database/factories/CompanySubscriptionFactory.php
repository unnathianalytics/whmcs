<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\SaasPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanySubscription>
 */
class CompanySubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'saas_plan_id' => SaasPlan::factory(),
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ];
    }

    /**
     * Indicate that the subscription is trialing.
     */
    public function trialing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'trialing',
        ]);
    }

    /**
     * Indicate that the subscription is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'cancelled',
            'ends_at' => now()->subDay(),
        ]);
    }
}
