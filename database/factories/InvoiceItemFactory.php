<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = fake()->randomElement([199, 499, 999, 1999, 4999]);
        $taxRate = fake()->randomElement([0, 18]);

        $subtotal = round($quantity * $unitPrice, 2);
        $taxAmount = round($subtotal * ($taxRate / 100), 2);

        return [
            'invoice_id' => Invoice::factory(),
            // Inherit the invoice's company so the line shares its tenant.
            'company_id' => fn (array $attributes): int => Invoice::withoutGlobalScopes()
                ->findOrFail($attributes['invoice_id'])->company_id,
            'tax_rate_id' => null,
            'description' => fake()->sentence(3),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'tax_rate' => $taxRate,
            'line_subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'line_total' => $subtotal + $taxAmount,
        ];
    }
}
