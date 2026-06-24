<?php

use App\Livewire\Admin\ActivityLog\Index;
use App\Models\Client;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;

describe('access control', function () {
    test('a company admin without settings.view is forbidden', function () {
        actingAs(companyAdmin())
            ->get(route('admin.activity-log'))
            ->assertForbidden();
    });

    test('a company admin with settings.view can see the log', function () {
        $admin = grantPermissions(companyAdmin(), ['settings.view']);

        actingAs($admin)
            ->get(route('admin.activity-log'))
            ->assertOk();
    });
});

describe('tenant scoping', function () {
    test('the feed only shows activities caused by the tenant own admins', function () {
        $admin = grantPermissions(companyAdmin(), ['settings.view', 'clients.create']);
        $other = grantPermissions(companyAdmin(), ['clients.create']);

        // An activity caused by this tenant's admin.
        actingAs($admin);
        Client::factory()->create(['company_id' => $admin->company_id]);

        // An activity caused by another tenant's admin.
        actingAs($other);
        Client::factory()->create(['company_id' => $other->company_id]);

        // Mirror EnsureCompanyAdmin: rebind the spatie team id to the acting admin's company so
        // permission checks resolve against the right team (the middleware does this per request).
        actingAs($admin);
        app(PermissionRegistrar::class)->setPermissionsTeamId($admin->company_id);

        $component = Livewire::test(Index::class)->assertOk();
        $rows = $component->instance()->activities();

        expect($rows->total())->toBeGreaterThan(0)
            ->and($rows->getCollection()->every(fn ($activity) => $activity->causer_id === $admin->id))->toBeTrue();
    });

    test('the event filter narrows the feed', function () {
        $admin = grantPermissions(companyAdmin(), ['settings.view', 'clients.create']);

        actingAs($admin);
        Client::factory()->create(['company_id' => $admin->company_id]);

        Livewire::test(Index::class)
            ->set('event', 'created')
            ->assertOk()
            ->assertSee('Created');
    });
});
