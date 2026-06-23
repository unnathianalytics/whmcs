<?php

use App\Enums\ClientStatus;
use App\Livewire\Admin\Clients\Index;
use App\Livewire\Admin\Clients\Show;
use App\Models\Client;
use App\Models\ClientNote;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;

describe('tenant isolation', function () {
    test('the company scope hides clients belonging to another company', function () {
        $admin = grantPermissions(companyAdmin(), ['clients.view']);
        $other = companyAdmin();

        Client::factory()->for($admin->company)->count(2)->create();
        Client::factory()->for($other->company)->count(3)->create();

        actingAs($admin);

        // The global scope filters to the acting admin's company.
        expect(Client::count())->toBe(2);
    });

    test('a client created as a company admin auto-stamps the company id', function () {
        $admin = grantPermissions(companyAdmin(), ['clients.view', 'clients.create']);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->set('name', 'Jane Customer')
            ->set('email', 'jane@example.test')
            ->call('save')
            ->assertHasNoErrors();

        $client = Client::withoutGlobalScopes()->firstWhere('email', 'jane@example.test');

        expect($client)->not->toBeNull()
            ->and($client->company_id)->toBe($admin->company_id);
    });

    test('an admin cannot open another companys client', function () {
        $admin = grantPermissions(companyAdmin(), ['clients.view']);
        $foreignClient = Client::factory()->for(companyAdmin()->company)->create();

        actingAs($admin)
            ->get(route('admin.clients.show', $foreignClient))
            ->assertNotFound();
    });
});

describe('access control', function () {
    test('a company admin without clients.view is forbidden the list', function () {
        $admin = companyAdmin();

        actingAs($admin)
            ->get(route('admin.clients'))
            ->assertForbidden();
    });

    test('a company admin with clients.view can see the list', function () {
        $admin = grantPermissions(companyAdmin(), ['clients.view']);

        actingAs($admin)
            ->get(route('admin.clients'))
            ->assertOk();
    });

    test('a livewire action authorizes even without route middleware binding the team id', function () {
        // Regression: Livewire updates post to /livewire/update and skip the company_admin middleware,
        // so the spatie team id must be (re)bound on auth resolution, not only in route middleware.
        $admin = grantPermissions(companyAdmin(), ['clients.view', 'clients.create']);

        actingAs($admin);

        // Simulate a fresh request where no middleware has set the team id yet.
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->set('name', 'Team Bound')
            ->set('email', 'team-bound@example.test')
            ->call('save')
            ->assertHasNoErrors();

        expect(Client::withoutGlobalScopes()->where('email', 'team-bound@example.test')->exists())->toBeTrue();
    });

    test('creating without clients.create is forbidden', function () {
        $admin = grantPermissions(companyAdmin(), ['clients.view']);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->assertForbidden();
    });
});

describe('crud', function () {
    test('a client requires a name and email', function () {
        $admin = grantPermissions(companyAdmin(), ['clients.view', 'clients.create']);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->set('name', '')
            ->set('email', '')
            ->call('save')
            ->assertHasErrors(['name' => 'required', 'email' => 'required']);
    });

    test('client email must be unique within the company', function () {
        $admin = grantPermissions(companyAdmin(), ['clients.view', 'clients.create']);
        Client::factory()->for($admin->company)->create(['email' => 'dup@example.test']);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->set('name', 'Dup')
            ->set('email', 'dup@example.test')
            ->call('save')
            ->assertHasErrors(['email']);
    });

    test('the same email may exist in two different companies', function () {
        $admin = grantPermissions(companyAdmin(), ['clients.view', 'clients.create']);
        Client::factory()->for(companyAdmin()->company)->create(['email' => 'shared@example.test']);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->set('name', 'Shared')
            ->set('email', 'shared@example.test')
            ->call('save')
            ->assertHasNoErrors();
    });

    test('an admin can edit a client', function () {
        $admin = grantPermissions(companyAdmin(), ['clients.view', 'clients.update']);
        $client = Client::factory()->for($admin->company)->create(['name' => 'Old Name']);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openEditModal', $client->id)
            ->set('name', 'New Name')
            ->call('save')
            ->assertHasNoErrors();

        expect($client->fresh()->name)->toBe('New Name');
    });

    test('an admin can soft-delete a client', function () {
        $admin = grantPermissions(companyAdmin(), ['clients.view', 'clients.delete']);
        $client = Client::factory()->for($admin->company)->create();

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('confirmDelete', $client->id)
            ->call('delete')
            ->assertHasNoErrors();

        // Soft-deleted: excluded from default queries, still present when including trashed.
        expect(Client::find($client->id))->toBeNull()
            ->and(Client::withTrashed()->find($client->id)?->trashed())->toBeTrue();
    });

    test('the list can be filtered by status', function () {
        $admin = grantPermissions(companyAdmin(), ['clients.view']);
        Client::factory()->for($admin->company)->create(['status' => ClientStatus::Active]);
        Client::factory()->for($admin->company)->create(['status' => ClientStatus::Closed]);

        actingAs($admin);

        $component = Livewire::test(Index::class)->set('status', ClientStatus::Closed->value);

        expect($component->instance()->clients()->total())->toBe(1);
    });
});

describe('notes', function () {
    test('adding a note attaches the author, client and company', function () {
        $admin = grantPermissions(companyAdmin(), ['clients.view', 'clients.update']);
        $client = Client::factory()->for($admin->company)->create();

        actingAs($admin);

        Livewire::test(Show::class, ['client' => $client])
            ->set('noteBody', 'Called the customer today.')
            ->call('addNote')
            ->assertHasNoErrors();

        $note = ClientNote::withoutGlobalScopes()->firstWhere('client_id', $client->id);

        expect($note)->not->toBeNull()
            ->and($note->user_id)->toBe($admin->id)
            ->and($note->company_id)->toBe($admin->company_id)
            ->and($note->body)->toBe('Called the customer today.');
    });

    test('a note requires a body', function () {
        $admin = grantPermissions(companyAdmin(), ['clients.view', 'clients.update']);
        $client = Client::factory()->for($admin->company)->create();

        actingAs($admin);

        Livewire::test(Show::class, ['client' => $client])
            ->set('noteBody', '')
            ->call('addNote')
            ->assertHasErrors(['noteBody' => 'required']);
    });
});
