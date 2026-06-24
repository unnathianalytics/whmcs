<?php

namespace Database\Factories;

use App\Models\TicketAttachment;
use App\Models\TicketReply;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketAttachment>
 */
class TicketAttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->word().'.png';

        return [
            'ticket_reply_id' => TicketReply::factory(),
            // Inherit the reply's company so the attachment shares its tenant.
            'company_id' => fn (array $attributes): int => TicketReply::withoutGlobalScopes()
                ->findOrFail($attributes['ticket_reply_id'])->company_id,
            'disk' => 'local',
            'path' => 'tickets/test/'.fake()->uuid().'.png',
            'original_name' => $name,
            'mime_type' => 'image/png',
            'size' => fake()->numberBetween(1024, 1048576),
        ];
    }
}
