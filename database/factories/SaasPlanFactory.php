<?php

namespace Database\Factories;

use App\Models\SaasPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SaasPlan>
 */
class SaasPlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(['Starter', 'Pro', 'Agency', 'Enterprise']);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'price' => fake()->randomElement([9.00, 29.00, 99.00]),
            'interval' => 'monthly',
            'limits' => [
                'max_clients' => fake()->randomElement([50, 500, null]),
                'max_admins' => fake()->randomElement([2, 10, null]),
            ],
            'is_active' => true,
        ];
    }
}
