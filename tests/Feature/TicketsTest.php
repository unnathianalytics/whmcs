<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Livewire\Admin\Tickets\Index;
use App\Livewire\Admin\Tickets\Show;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketDepartment;
use App\Models\TicketReply;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

describe('tenant isolation', function () {
    test('the company scope hides tickets belonging to another company', function () {
        $admin = grantPermissions(companyAdmin(), ['tickets.view']);
        $other = companyAdmin();

        Ticket::factory()->for(Client::factory()->for($admin->company))->count(2)->create([
            'company_id' => $admin->company_id,
        ]);
        Ticket::factory()->for(Client::factory()->for($other->company))->count(3)->create([
            'company_id' => $other->company_id,
        ]);

        actingAs($admin);

        expect(Ticket::count())->toBe(2);
    });

    test('a ticket created as a company admin auto-stamps the company id, number, and opening reply', function () {
        $admin = grantPermissions(companyAdmin(), ['tickets.view', 'tickets.create']);
        $client = Client::factory()->for($admin->company)->create();
        $department = TicketDepartment::factory()->create(['company_id' => $admin->company_id]);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->set('clientId', (string) $client->id)
            ->set('departmentId', (string) $department->id)
            ->set('subject', 'Cannot log in')
            ->set('message', 'I keep getting an error.')
            ->call('create')
            ->assertHasNoErrors();

        $ticket = Ticket::withoutGlobalScopes()->firstWhere('client_id', $client->id);

        expect($ticket)->not->toBeNull()
            ->and($ticket->company_id)->toBe($admin->company_id)
            ->and($ticket->number)->toBe('TKT-000001')
            ->and($ticket->status)->toBe(TicketStatus::Open)
            ->and($ticket->last_reply_at)->not->toBeNull()
            ->and($ticket->replies()->count())->toBe(1)
            ->and($ticket->replies()->first()->body)->toBe('I keep getting an error.')
            ->and($ticket->replies()->first()->is_internal_note)->toBeFalse();
    });
});

describe('access control', function () {
    test('a company admin without tickets.view is forbidden the list', function () {
        actingAs(companyAdmin())
            ->get(route('admin.tickets'))
            ->assertForbidden();
    });

    test('a company admin with tickets.view can see the list', function () {
        $admin = grantPermissions(companyAdmin(), ['tickets.view']);

        actingAs($admin)
            ->get(route('admin.tickets'))
            ->assertOk();
    });

    test('creating a ticket without tickets.create is forbidden', function () {
        $admin = grantPermissions(companyAdmin(), ['tickets.view']);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->assertForbidden();
    });

    test('posting a reply without tickets.update is forbidden', function () {
        $admin = grantPermissions(companyAdmin(), ['tickets.view']);
        $ticket = Ticket::factory()->for(Client::factory()->for($admin->company))->create([
            'company_id' => $admin->company_id,
        ]);

        actingAs($admin);

        Livewire::test(Show::class, ['ticket' => $ticket])
            ->set('body', 'Hello')
            ->call('postReply')
            ->assertForbidden();
    });
});

describe('validation', function () {
    test('a ticket requires a client, department, subject and message', function () {
        $admin = grantPermissions(companyAdmin(), ['tickets.view', 'tickets.create']);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->set('clientId', '')
            ->set('departmentId', '')
            ->set('subject', '')
            ->set('message', '')
            ->call('create')
            ->assertHasErrors([
                'clientId' => 'required',
                'departmentId' => 'required',
                'subject' => 'required',
                'message' => 'required',
            ]);
    });

    test('a reply requires a body', function () {
        $admin = grantPermissions(companyAdmin(), ['tickets.view', 'tickets.update']);
        $ticket = Ticket::factory()->for(Client::factory()->for($admin->company))->create([
            'company_id' => $admin->company_id,
        ]);

        actingAs($admin);

        Livewire::test(Show::class, ['ticket' => $ticket])
            ->set('body', '')
            ->call('postReply')
            ->assertHasErrors(['body' => 'required']);
    });
});

describe('reply thread', function () {
    test('a public reply moves an open ticket to answered and bumps last_reply_at', function () {
        $admin = grantPermissions(companyAdmin(), ['tickets.view', 'tickets.update']);
        $ticket = Ticket::factory()->for(Client::factory()->for($admin->company))->open()->create([
            'company_id' => $admin->company_id,
            'last_reply_at' => now()->subDays(3),
        ]);
        $before = $ticket->last_reply_at;

        actingAs($admin);

        Livewire::test(Show::class, ['ticket' => $ticket])
            ->set('body', 'Here is the fix.')
            ->set('isInternalNote', false)
            ->call('postReply')
            ->assertHasNoErrors();

        $ticket->refresh();

        expect($ticket->status)->toBe(TicketStatus::Answered)
            ->and($ticket->last_reply_at->greaterThan($before))->toBeTrue()
            ->and($ticket->replies()->count())->toBe(1);
    });

    test('an internal note does not change the status or last_reply_at', function () {
        $admin = grantPermissions(companyAdmin(), ['tickets.view', 'tickets.update']);
        $ticket = Ticket::factory()->for(Client::factory()->for($admin->company))->open()->create([
            'company_id' => $admin->company_id,
            'last_reply_at' => now()->subDays(3),
        ]);
        $before = $ticket->last_reply_at;

        actingAs($admin);

        Livewire::test(Show::class, ['ticket' => $ticket])
            ->set('body', 'Note to self: check DNS.')
            ->set('isInternalNote', true)
            ->call('postReply')
            ->assertHasNoErrors();

        $ticket->refresh();

        expect($ticket->status)->toBe(TicketStatus::Open)
            ->and($ticket->last_reply_at->equalTo($before))->toBeTrue()
            ->and($ticket->replies()->where('is_internal_note', true)->count())->toBe(1);
    });
});

describe('header management', function () {
    test('closing a ticket stamps closed_at and reopening clears it', function () {
        $admin = grantPermissions(companyAdmin(), ['tickets.view', 'tickets.update']);
        $department = TicketDepartment::factory()->create(['company_id' => $admin->company_id]);
        $ticket = Ticket::factory()->for(Client::factory()->for($admin->company))->open()->create([
            'company_id' => $admin->company_id,
            'department_id' => $department->id,
        ]);

        actingAs($admin);

        Livewire::test(Show::class, ['ticket' => $ticket])
            ->call('openHeaderModal')
            ->set('status', TicketStatus::Closed->value)
            ->call('saveHeader')
            ->assertHasNoErrors();

        $ticket->refresh();
        expect($ticket->status)->toBe(TicketStatus::Closed)
            ->and($ticket->closed_at)->not->toBeNull();

        Livewire::test(Show::class, ['ticket' => $ticket])
            ->call('openHeaderModal')
            ->set('status', TicketStatus::Open->value)
            ->call('saveHeader')
            ->assertHasNoErrors();

        $ticket->refresh();
        expect($ticket->status)->toBe(TicketStatus::Open)
            ->and($ticket->closed_at)->toBeNull();
    });

    test('the priority can be changed from the header', function () {
        $admin = grantPermissions(companyAdmin(), ['tickets.view', 'tickets.update']);
        $ticket = Ticket::factory()->for(Client::factory()->for($admin->company))->create([
            'company_id' => $admin->company_id,
            'priority' => TicketPriority::Low,
        ]);

        actingAs($admin);

        Livewire::test(Show::class, ['ticket' => $ticket])
            ->call('openHeaderModal')
            ->set('priority', TicketPriority::Urgent->value)
            ->call('saveHeader')
            ->assertHasNoErrors();

        expect($ticket->fresh()->priority)->toBe(TicketPriority::Urgent);
    });
});

describe('attachments', function () {
    test('a reply stores its uploaded files on the local disk and records attachment rows', function () {
        Storage::fake('local');

        $admin = grantPermissions(companyAdmin(), ['tickets.view', 'tickets.update']);
        $ticket = Ticket::factory()->for(Client::factory()->for($admin->company))->create([
            'company_id' => $admin->company_id,
        ]);

        actingAs($admin);

        Livewire::test(Show::class, ['ticket' => $ticket])
            ->set('body', 'See attached screenshot.')
            ->set('attachments', [UploadedFile::fake()->image('screenshot.png')])
            ->call('postReply')
            ->assertHasNoErrors();

        $attachment = TicketAttachment::withoutGlobalScopes()->first();

        expect($attachment)->not->toBeNull()
            ->and($attachment->original_name)->toBe('screenshot.png')
            ->and($attachment->company_id)->toBe($admin->company_id);

        Storage::disk('local')->assertExists($attachment->path);
    });

    test('an admin with tickets.view can download an attachment', function () {
        Storage::fake('local');

        $admin = grantPermissions(companyAdmin(), ['tickets.view']);
        $ticket = Ticket::factory()->for(Client::factory()->for($admin->company))->create([
            'company_id' => $admin->company_id,
        ]);
        $reply = TicketReply::factory()->for($ticket)->create(['company_id' => $admin->company_id]);
        Storage::disk('local')->put('tickets/'.$ticket->id.'/file.txt', 'hello');
        $attachment = TicketAttachment::factory()->for($reply, 'reply')->create([
            'company_id' => $admin->company_id,
            'disk' => 'local',
            'path' => 'tickets/'.$ticket->id.'/file.txt',
            'original_name' => 'file.txt',
        ]);

        actingAs($admin)
            ->get(route('admin.ticket-attachments.download', $attachment))
            ->assertOk();
    });

    test('a company admin without tickets.view is forbidden the attachment download', function () {
        Storage::fake('local');

        $admin = companyAdmin();
        $ticket = Ticket::factory()->for(Client::factory()->for($admin->company))->create([
            'company_id' => $admin->company_id,
        ]);
        $reply = TicketReply::factory()->for($ticket)->create(['company_id' => $admin->company_id]);
        $attachment = TicketAttachment::factory()->for($reply, 'reply')->create([
            'company_id' => $admin->company_id,
        ]);

        actingAs($admin)
            ->get(route('admin.ticket-attachments.download', $attachment))
            ->assertForbidden();
    });

    test('deleting a reply removes its stored attachment files', function () {
        Storage::fake('local');

        $admin = grantPermissions(companyAdmin(), ['tickets.view', 'tickets.update']);
        $ticket = Ticket::factory()->for(Client::factory()->for($admin->company))->create([
            'company_id' => $admin->company_id,
        ]);
        $reply = TicketReply::factory()->for($ticket)->create(['company_id' => $admin->company_id]);
        Storage::disk('local')->put('tickets/'.$ticket->id.'/file.txt', 'hello');
        $attachment = TicketAttachment::factory()->for($reply, 'reply')->create([
            'company_id' => $admin->company_id,
            'disk' => 'local',
            'path' => 'tickets/'.$ticket->id.'/file.txt',
        ]);

        actingAs($admin);

        Livewire::test(Show::class, ['ticket' => $ticket])
            ->call('confirmDeleteReply', $reply->id)
            ->call('deleteReply')
            ->assertHasNoErrors();

        Storage::disk('local')->assertMissing($attachment->path);
        expect(TicketAttachment::withoutGlobalScopes()->find($attachment->id))->toBeNull();
    });
});

describe('crud', function () {
    test('an admin can soft-delete a ticket', function () {
        $admin = grantPermissions(companyAdmin(), ['tickets.view', 'tickets.delete']);
        $ticket = Ticket::factory()->for(Client::factory()->for($admin->company))->create([
            'company_id' => $admin->company_id,
        ]);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('confirmDelete', $ticket->id)
            ->call('delete')
            ->assertHasNoErrors();

        expect(Ticket::find($ticket->id))->toBeNull()
            ->and(Ticket::withTrashed()->find($ticket->id)?->trashed())->toBeTrue();
    });
});
