<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientNote>
 */
class ClientNoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            // Inherit the parent client's company so the note shares its tenant.
            'company_id' => fn (array $attributes): int => Client::withoutGlobalScopes()
                ->findOrFail($attributes['client_id'])->company_id,
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
