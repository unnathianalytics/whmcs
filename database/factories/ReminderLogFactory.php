<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientService;
use App\Models\ReminderLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReminderLog>
 */
class ReminderLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => fn (array $attributes): int => Client::withoutGlobalScopes()
                ->where('id', $attributes['client_id'])->value('company_id'),
            'reminder_rule_id' => null,
            'remindable_type' => (new ClientService)->getMorphClass(),
            'remindable_id' => fn () => ClientService::factory(),
            'client_id' => Client::factory(),
            'days_before' => fake()->randomElement([30, 14, 7, 1]),
            'channel' => ReminderLog::CHANNEL_CLIENT,
            'recipient' => fake()->safeEmail(),
            'sent_at' => now(),
        ];
    }
}
