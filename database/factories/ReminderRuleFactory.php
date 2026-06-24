<?php

namespace Database\Factories;

use App\Enums\ReminderResourceType;
use App\Models\Company;
use App\Models\ReminderRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReminderRule>
 */
class ReminderRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'resource_type' => ReminderResourceType::Service,
            'days_before' => fake()->randomElement([30, 14, 7, 1]),
            'subject' => '{product_name} expires in {days_left} days',
            'body' => "Hi {client_name},\n\nYour {product_name} expires on {expires_at} ({days_left} days left).",
            'notify_client' => true,
            'notify_admin' => false,
            'is_active' => true,
        ];
    }

    /**
     * Indicate the rule targets domains rather than services.
     */
    public function forDomains(): static
    {
        return $this->state(fn (array $attributes): array => [
            'resource_type' => ReminderResourceType::Domain,
            'subject' => '{domain_name} expires in {days_left} days',
            'body' => "Hi {client_name},\n\nYour domain {domain_name} expires on {expires_at} ({days_left} days left).",
        ]);
    }

    /**
     * Indicate the rule fires the given number of days before expiry.
     */
    public function daysBefore(int $days): static
    {
        return $this->state(fn (array $attributes): array => ['days_before' => $days]);
    }

    /**
     * Indicate the rule also notifies the company admin contact.
     */
    public function notifyingAdmin(): static
    {
        return $this->state(fn (array $attributes): array => ['notify_admin' => true]);
    }

    /**
     * Indicate the rule is disabled.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
