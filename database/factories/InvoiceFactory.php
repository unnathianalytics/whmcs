<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $issueDate = fake()->dateTimeBetween('-3 months', 'now');
        $dueDate = (clone $issueDate)->modify('+14 days');

        return [
            'client_id' => Client::factory(),
            // Inherit the client's company so the invoice shares its tenant.
            'company_id' => fn (array $attributes): int => Client::withoutGlobalScopes()
                ->findOrFail($attributes['client_id'])->company_id,
            'number' => 'INV-'.Str::upper(Str::random(8)),
            'status' => InvoiceStatus::Unpaid,
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'currency' => 'INR',
            'subtotal' => 0,
            'tax_total' => 0,
            'total' => 0,
            'paid_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the invoice is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InvoiceStatus::Draft,
        ]);
    }

    /**
     * Indicate that the invoice is fully paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    /**
     * Indicate that the invoice is unpaid and past its due date.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InvoiceStatus::Unpaid,
            'issue_date' => now()->subMonth(),
            'due_date' => now()->subWeek(),
        ]);
    }
}
