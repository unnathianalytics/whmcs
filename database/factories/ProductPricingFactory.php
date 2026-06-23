<?php

namespace Database\Factories;

use App\Enums\BillingCycle;
use App\Models\Product;
use App\Models\ProductPricing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductPricing>
 */
class ProductPricingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Inherit the product's company so the pricing shares its tenant.
            'company_id' => fn (array $attributes): int => Product::withoutGlobalScopes()
                ->findOrFail($attributes['product_id'])->company_id,
            'product_id' => Product::factory(),
            'cycle' => fake()->randomElement(BillingCycle::cases()),
            'price' => fake()->randomElement([199, 499, 999, 1999, 4999]),
            'currency' => 'INR',
        ];
    }

    /**
     * Set a specific billing cycle for the pricing row.
     */
    public function cycle(BillingCycle $cycle): static
    {
        return $this->state(fn (array $attributes): array => [
            'cycle' => $cycle,
        ]);
    }
}
