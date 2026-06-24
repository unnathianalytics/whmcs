<?php

namespace Database\Factories;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Client;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            // Inherit the client's company so the ticket shares its tenant.
            'company_id' => fn (array $attributes): int => Client::withoutGlobalScopes()
                ->findOrFail($attributes['client_id'])->company_id,
            'department_id' => null,
            'assigned_to' => null,
            'number' => 'TKT-'.Str::upper(Str::random(8)),
            'subject' => fake()->sentence(6),
            'status' => TicketStatus::Open,
            'priority' => fake()->randomElement(TicketPriority::cases()),
            'last_reply_at' => now(),
            'closed_at' => null,
        ];
    }

    /**
     * Indicate that the ticket is open.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TicketStatus::Open,
            'closed_at' => null,
        ]);
    }

    /**
     * Indicate that the ticket has been answered by an admin.
     */
    public function answered(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TicketStatus::Answered,
            'closed_at' => null,
        ]);
    }

    /**
     * Indicate that the ticket is closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TicketStatus::Closed,
            'closed_at' => now(),
        ]);
    }
}
