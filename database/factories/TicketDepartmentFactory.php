<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\TicketDepartment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketDepartment>
 */
class TicketDepartmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->randomElement(['Sales', 'Technical', 'Billing', 'Abuse', 'General']),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the department is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
