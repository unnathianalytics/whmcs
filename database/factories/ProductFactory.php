<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $group = ProductGroup::factory();

        return [
            // Inherit the group's company so the product shares its tenant.
            'company_id' => fn (array $attributes): int => ProductGroup::withoutGlobalScopes()
                ->findOrFail($attributes['product_group_id'])->company_id,
            'product_group_id' => $group,
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'setup_fee' => fake()->randomElement([0, 0, 0, 499, 999]),
            'is_active' => true,
        ];
    }
}
