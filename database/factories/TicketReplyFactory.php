<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketReply>
 */
class TicketReplyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            // Inherit the ticket's company so the reply shares its tenant.
            'company_id' => fn (array $attributes): int => Ticket::withoutGlobalScopes()
                ->findOrFail($attributes['ticket_id'])->company_id,
            'user_id' => null,
            'body' => fake()->paragraph(),
            'is_internal_note' => false,
        ];
    }

    /**
     * Indicate that the reply is a private internal note.
     */
    public function internalNote(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_internal_note' => true,
        ]);
    }
}
