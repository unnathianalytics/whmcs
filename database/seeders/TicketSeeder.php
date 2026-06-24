<?php

namespace Database\Seeders;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * What: Seeds the three default helpdesk departments plus ~15 demo tickets (mix of open/answered/closed)
 *       with a few replies each for the demo company so the Tickets module has data on first run.
 * Why: The idea.md development sample data calls for 15 support tickets; this populates the queue and the
 *      client profiles without manual entry. The opening message is stored as the first reply, matching how
 *      the UI creates tickets.
 * When: Called from DatabaseSeeder after InvoiceSeeder. Sets `company_id` explicitly because the seeder
 *       runs without an authenticated user (the BelongsToCompany scope no-ops there).
 */
class TicketSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('slug', 'demo-company')->first();

        if ($company === null) {
            return;
        }

        $departments = collect(['Sales', 'Technical', 'Billing'])->map(
            fn (string $name): TicketDepartment => TicketDepartment::create([
                'company_id' => $company->id,
                'name' => $name,
                'is_active' => true,
            ]),
        );

        $clients = Client::where('company_id', $company->id)->get();
        $admin = User::where('company_id', $company->id)->first();

        if ($clients->isEmpty()) {
            return;
        }

        $subjects = [
            'Unable to access cPanel',
            'Invoice query — double charged',
            'Request to upgrade hosting plan',
            'SSL certificate not installing',
            'Email delivery delayed',
            'Domain transfer assistance',
            'Website showing 500 error',
            'How do I add an addon domain?',
        ];

        for ($i = 1; $i <= 15; $i++) {
            $client = $clients->random();
            $state = $i % 3;

            $ticket = Ticket::create([
                'company_id' => $company->id,
                'client_id' => $client->id,
                'department_id' => $departments->random()->id,
                'assigned_to' => $state === 0 ? $admin?->id : null,
                'number' => Ticket::nextNumber($company->id),
                'subject' => $subjects[array_rand($subjects)],
                'status' => match ($state) {
                    0 => TicketStatus::Closed,
                    1 => TicketStatus::Answered,
                    default => TicketStatus::Open,
                },
                'priority' => fake()->randomElement(TicketPriority::cases()),
                'last_reply_at' => now()->subDays($i),
                'closed_at' => $state === 0 ? now()->subDays($i - 1) : null,
            ]);

            // Opening message from the client (recorded by the admin), then an admin reply on non-open ones.
            $ticket->replies()->create([
                'company_id' => $company->id,
                'user_id' => $admin?->id,
                'body' => fake()->paragraph(),
                'is_internal_note' => false,
            ]);

            if ($state !== 2) {
                $ticket->replies()->create([
                    'company_id' => $company->id,
                    'user_id' => $admin?->id,
                    'body' => fake()->paragraph(),
                    'is_internal_note' => false,
                ]);
            }
        }
    }
}
