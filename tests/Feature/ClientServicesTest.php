<?php

use App\Enums\BillingCycle;
use App\Enums\ServiceStatus;
use App\Livewire\Admin\Services\Index;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductPricing;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

describe('tenant isolation', function () {
    test('the company scope hides services belonging to another company', function () {
        $admin = grantPermissions(companyAdmin(), ['services.view']);
        $other = companyAdmin();

        ClientService::factory()->for(Client::factory()->for($admin->company))->count(2)->create([
            'company_id' => $admin->company_id,
        ]);
        ClientService::factory()->for(Client::factory()->for($other->company))->count(3)->create([
            'company_id' => $other->company_id,
        ]);

        actingAs($admin);

        expect(ClientService::count())->toBe(2);
    });

    test('a service created as a company admin auto-stamps the company id', function () {
        $admin = grantPermissions(companyAdmin(), ['services.view', 'services.create']);
        $client = Client::factory()->for($admin->company)->create();

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->set('clientId', (string) $client->id)
            ->set('billingCycle', BillingCycle::Monthly->value)
            ->set('price', '499')
            ->set('startsAt', now()->toDateString())
            ->call('save')
            ->assertHasNoErrors();

        $service = ClientService::withoutGlobalScopes()->firstWhere('client_id', $client->id);

        expect($service)->not->toBeNull()
            ->and($service->company_id)->toBe($admin->company_id);
    });
});

describe('access control', function () {
    test('a company admin without services.view is forbidden the list', function () {
        actingAs(companyAdmin())
            ->get(route('admin.services'))
            ->assertForbidden();
    });

    test('a company admin with services.view can see the list', function () {
        $admin = grantPermissions(companyAdmin(), ['services.view']);

        actingAs($admin)
            ->get(route('admin.services'))
            ->assertOk();
    });

    test('assigning a service without services.create is forbidden', function () {
        $admin = grantPermissions(companyAdmin(), ['services.view']);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->assertForbidden();
    });
});

describe('crud', function () {
    test('a service requires a client and a start date', function () {
        $admin = grantPermissions(companyAdmin(), ['services.view', 'services.create']);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->set('clientId', '')
            ->set('startsAt', '')
            ->call('save')
            ->assertHasErrors(['clientId' => 'required', 'startsAt' => 'required']);
    });

    test('expiry must not precede the start date', function () {
        $admin = grantPermissions(companyAdmin(), ['services.view', 'services.create']);
        $client = Client::factory()->for($admin->company)->create();

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->set('clientId', (string) $client->id)
            ->set('startsAt', now()->toDateString())
            ->set('expiresAt', now()->subDay()->toDateString())
            ->call('save')
            ->assertHasErrors('expiresAt');
    });

    test('selecting a product pre-fills price and cycle from its pricing', function () {
        $admin = grantPermissions(companyAdmin(), ['services.view', 'services.create']);
        $group = ProductGroup::factory()->for($admin->company)->create();
        $product = Product::factory()->for($admin->company)->for($group, 'group')->create();
        ProductPricing::factory()->for($product)->cycle(BillingCycle::Annual)->create([
            'company_id' => $admin->company_id,
            'price' => 4999,
            'currency' => 'INR',
        ]);

        actingAs($admin);

        $component = Livewire::test(Index::class)
            ->call('openCreateModal')
            ->set('productId', (string) $product->id);

        expect($component->get('price'))->toBe('4999.00')
            ->and($component->get('billingCycle'))->toBe(BillingCycle::Annual->value);
    });

    test('an admin can edit a service', function () {
        $admin = grantPermissions(companyAdmin(), ['services.view', 'services.update']);
        $client = Client::factory()->for($admin->company)->create();
        $service = ClientService::factory()->for($client)->create([
            'company_id' => $admin->company_id,
            'status' => ServiceStatus::Pending,
        ]);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openEditModal', $service->id)
            ->set('statusField', ServiceStatus::Active->value)
            ->call('save')
            ->assertHasNoErrors();

        expect($service->fresh()->status)->toBe(ServiceStatus::Active);
    });

    test('an admin can soft-delete a service', function () {
        $admin = grantPermissions(companyAdmin(), ['services.view', 'services.delete']);
        $client = Client::factory()->for($admin->company)->create();
        $service = ClientService::factory()->for($client)->create(['company_id' => $admin->company_id]);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('confirmDelete', $service->id)
            ->call('delete')
            ->assertHasNoErrors();

        expect(ClientService::find($service->id))->toBeNull()
            ->and(ClientService::withTrashed()->find($service->id)?->trashed())->toBeTrue();
    });

    test('the list can be filtered to expiring services', function () {
        $admin = grantPermissions(companyAdmin(), ['services.view']);
        $client = Client::factory()->for($admin->company)->create();

        ClientService::factory()->for($client)->expiringInDays(5)->create(['company_id' => $admin->company_id]);
        ClientService::factory()->for($client)->expiringInDays(120)->create(['company_id' => $admin->company_id]);

        actingAs($admin);

        $component = Livewire::test(Index::class)->set('expiry', 'expiring');

        expect($component->instance()->services()->total())->toBe(1);
    });
});

describe('expiry urgency', function () {
    test('a service expiring within a week is red', function () {
        $service = ClientService::factory()->make(['expires_at' => now()->addDays(3)]);

        expect($service->urgencyColor())->toBe('red')
            ->and($service->isExpired())->toBeFalse();
    });

    test('an already-expired service is red and flagged expired', function () {
        $service = ClientService::factory()->make(['expires_at' => now()->subDay()]);

        expect($service->urgencyColor())->toBe('red')
            ->and($service->isExpired())->toBeTrue();
    });

    test('a service expiring in three weeks is yellow', function () {
        $service = ClientService::factory()->make(['expires_at' => now()->addDays(21)]);

        expect($service->urgencyColor())->toBe('yellow');
    });

    test('a service with no expiry is neutral', function () {
        $service = ClientService::factory()->make(['expires_at' => null]);

        expect($service->urgencyColor())->toBe('zinc')
            ->and($service->isExpired())->toBeFalse();
    });
});
