<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            // Inherit the invoice's company so the payment shares its tenant.
            'company_id' => fn (array $attributes): int => Invoice::withoutGlobalScopes()
                ->findOrFail($attributes['invoice_id'])->company_id,
            'amount' => fake()->randomElement([499, 999, 1999, 4999]),
            'method' => fake()->randomElement(PaymentMethod::cases()),
            'reference' => fake()->optional()->bothify('TXN-####-????'),
            'paid_at' => fake()->dateTimeBetween('-2 months', 'now'),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
