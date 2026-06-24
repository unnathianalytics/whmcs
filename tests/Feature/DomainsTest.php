<?php

use App\Enums\DomainStatus;
use App\Livewire\Admin\Clients\Show as ClientShow;
use App\Livewire\Admin\Domains\Index;
use App\Models\Client;
use App\Models\Domain;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

describe('tenant isolation', function () {
    test('the company scope hides domains belonging to another company', function () {
        $admin = grantPermissions(companyAdmin(), ['domains.view']);
        $other = companyAdmin();

        Domain::factory()->for(Client::factory()->for($admin->company))->count(2)->create([
            'company_id' => $admin->company_id,
        ]);
        Domain::factory()->for(Client::factory()->for($other->company))->count(3)->create([
            'company_id' => $other->company_id,
        ]);

        actingAs($admin);

        expect(Domain::count())->toBe(2);
    });

    test('a domain created as a company admin auto-stamps the company id', function () {
        $admin = grantPermissions(companyAdmin(), ['domains.view', 'domains.create']);
        $client = Client::factory()->for($admin->company)->create();

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->set('clientId', (string) $client->id)
            ->set('domainName', 'example.com')
            ->set('registeredAt', now()->subYear()->toDateString())
            ->set('expiresAt', now()->addYear()->toDateString())
            ->call('save')
            ->assertHasNoErrors();

        $domain = Domain::withoutGlobalScopes()->firstWhere('domain_name', 'example.com');

        expect($domain)->not->toBeNull()
            ->and($domain->company_id)->toBe($admin->company_id)
            ->and($domain->client_id)->toBe($client->id)
            ->and($domain->status)->toBe(DomainStatus::Active);
    });
});

describe('access control', function () {
    test('a company admin without domains.view is forbidden the list', function () {
        actingAs(companyAdmin())
            ->get(route('admin.domains'))
            ->assertForbidden();
    });

    test('a company admin with domains.view can see the list', function () {
        $admin = grantPermissions(companyAdmin(), ['domains.view']);

        actingAs($admin)
            ->get(route('admin.domains'))
            ->assertOk();
    });

    test('creating a domain without domains.create is forbidden', function () {
        $admin = grantPermissions(companyAdmin(), ['domains.view']);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->assertForbidden();
    });

    test('deleting a domain without domains.delete is forbidden', function () {
        $admin = grantPermissions(companyAdmin(), ['domains.view']);
        $domain = Domain::factory()->for(Client::factory()->for($admin->company))->create([
            'company_id' => $admin->company_id,
        ]);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('confirmDelete', $domain->id)
            ->assertForbidden();
    });
});

describe('create & edit validation', function () {
    test('client and domain name are required', function () {
        $admin = grantPermissions(companyAdmin(), ['domains.view', 'domains.create']);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->set('clientId', '')
            ->set('domainName', '')
            ->call('save')
            ->assertHasErrors(['clientId', 'domainName']);
    });

    test('expiry must be on or after the registration date', function () {
        $admin = grantPermissions(companyAdmin(), ['domains.view', 'domains.create']);
        $client = Client::factory()->for($admin->company)->create();

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->set('clientId', (string) $client->id)
            ->set('domainName', 'late.com')
            ->set('registeredAt', now()->toDateString())
            ->set('expiresAt', now()->subDay()->toDateString())
            ->call('save')
            ->assertHasErrors(['expiresAt']);
    });

    test('editing a domain updates its fields', function () {
        $admin = grantPermissions(companyAdmin(), ['domains.view', 'domains.update']);
        $domain = Domain::factory()->for(Client::factory()->for($admin->company))->create([
            'company_id' => $admin->company_id,
            'registrar' => 'GoDaddy',
        ]);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openEditModal', $domain->id)
            ->set('registrar', 'Namecheap')
            ->set('statusField', DomainStatus::PendingTransfer->value)
            ->call('save')
            ->assertHasNoErrors();

        $domain->refresh();

        expect($domain->registrar)->toBe('Namecheap')
            ->and($domain->status)->toBe(DomainStatus::PendingTransfer);
    });
});

describe('expiry helpers', function () {
    test('urgency colour reflects days until expiry', function () {
        $client = Client::factory()->create();

        $red = Domain::factory()->for($client)->expiringInDays(3)->create();
        $yellow = Domain::factory()->for($client)->expiringInDays(20)->create();
        $green = Domain::factory()->for($client)->expiringInDays(90)->create();
        $expired = Domain::factory()->for($client)->expired()->create();
        $none = Domain::factory()->for($client)->create(['expires_at' => null]);

        expect($red->urgencyColor())->toBe('red')
            ->and($yellow->urgencyColor())->toBe('yellow')
            ->and($green->urgencyColor())->toBe('green')
            ->and($expired->urgencyColor())->toBe('red')
            ->and($expired->isExpired())->toBeTrue()
            ->and($none->urgencyColor())->toBe('zinc')
            ->and($none->daysUntilExpiry())->toBeNull();
    });
});

describe('filters', function () {
    test('the expired and expiring filters return the right rows', function () {
        $admin = grantPermissions(companyAdmin(), ['domains.view']);
        $client = Client::factory()->for($admin->company)->create();

        Domain::factory()->for($client)->expired()->create(['company_id' => $admin->company_id]);
        Domain::factory()->for($client)->expiringInDays(10)->create(['company_id' => $admin->company_id]);
        Domain::factory()->for($client)->expiringInDays(200)->create(['company_id' => $admin->company_id]);

        actingAs($admin);

        Livewire::test(Index::class)
            ->set('expiry', 'expired')
            ->assertCount('domains', 1);

        Livewire::test(Index::class)
            ->set('expiry', 'expiring')
            ->assertCount('domains', 1);
    });

    test('the status filter returns only matching domains', function () {
        $admin = grantPermissions(companyAdmin(), ['domains.view']);
        $client = Client::factory()->for($admin->company)->create();

        Domain::factory()->for($client)->count(2)->create(['company_id' => $admin->company_id, 'status' => DomainStatus::Active]);
        Domain::factory()->for($client)->create(['company_id' => $admin->company_id, 'status' => DomainStatus::Cancelled]);

        actingAs($admin);

        Livewire::test(Index::class)
            ->set('status', DomainStatus::Cancelled->value)
            ->assertCount('domains', 1);
    });
});

describe('renewal', function () {
    test('renewing stamps the renewal date, advances expiry, and reactivates an expired domain', function () {
        $admin = grantPermissions(companyAdmin(), ['domains.view', 'domains.update']);
        $domain = Domain::factory()->for(Client::factory()->for($admin->company))->expired()->create([
            'company_id' => $admin->company_id,
        ]);

        $newExpiry = now()->addYear()->toDateString();

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openRenewModal', $domain->id)
            ->set('renewExpiresAt', $newExpiry)
            ->set('renewCost', '999')
            ->call('renew')
            ->assertHasNoErrors();

        $domain->refresh();

        expect($domain->last_renewed_at->toDateString())->toBe(now()->toDateString())
            ->and($domain->expires_at->toDateString())->toBe($newExpiry)
            ->and((float) $domain->renewal_cost)->toBe(999.0)
            ->and($domain->status)->toBe(DomainStatus::Active);
    });

    test('renewing without domains.update is forbidden', function () {
        $admin = grantPermissions(companyAdmin(), ['domains.view']);
        $domain = Domain::factory()->for(Client::factory()->for($admin->company))->create([
            'company_id' => $admin->company_id,
        ]);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openRenewModal', $domain->id)
            ->assertForbidden();
    });
});

describe('soft delete', function () {
    test('deleting a domain hides it from the list', function () {
        $admin = grantPermissions(companyAdmin(), ['domains.view', 'domains.delete']);
        $domain = Domain::factory()->for(Client::factory()->for($admin->company))->create([
            'company_id' => $admin->company_id,
        ]);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('confirmDelete', $domain->id)
            ->call('delete')
            ->assertHasNoErrors();

        expect(Domain::count())->toBe(0)
            ->and(Domain::withTrashed()->count())->toBe(1);
    });
});

describe('client profile', function () {
    test('the domains computed returns only that client\'s domains', function () {
        $admin = grantPermissions(companyAdmin(), ['clients.view', 'domains.view']);
        $client = Client::factory()->for($admin->company)->create();
        $other = Client::factory()->for($admin->company)->create();

        Domain::factory()->for($client)->count(2)->create(['company_id' => $admin->company_id]);
        Domain::factory()->for($other)->create(['company_id' => $admin->company_id]);

        actingAs($admin);

        Livewire::test(ClientShow::class, ['client' => $client])
            ->assertCount('domains', 2);
    });
});
