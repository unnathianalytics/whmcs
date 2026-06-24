<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\TaxRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxRate>
 */
class TaxRateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $rate = fake()->randomElement([5, 12, 18, 28]);

        return [
            'company_id' => Company::factory(),
            'name' => "GST {$rate}%",
            'rate' => $rate,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the tax rate is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
