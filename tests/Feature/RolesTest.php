<?php

use App\Livewire\Admin\Roles\Index;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;

describe('access control', function () {
    test('a company admin without roles.view is forbidden the screen', function () {
        actingAs(companyAdmin())
            ->get(route('admin.roles'))
            ->assertForbidden();
    });

    test('a company admin with roles.view can see the screen', function () {
        $admin = grantPermissions(companyAdmin(), ['roles.view']);

        actingAs($admin)
            ->get(route('admin.roles'))
            ->assertOk();
    });

    test('creating a role without roles.manage is forbidden', function () {
        $admin = grantPermissions(companyAdmin(), ['roles.view']);
        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->assertForbidden();
    });
});

describe('crud and permission sync', function () {
    test('a role can be created with synced permissions scoped to the tenant', function () {
        $admin = grantPermissions(companyAdmin(), ['roles.view', 'roles.manage', 'clients.view', 'invoices.view']);
        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->set('name', 'auditor')
            ->set('selectedPermissions', ['clients.view', 'invoices.view'])
            ->call('save')
            ->assertHasNoErrors();

        app(PermissionRegistrar::class)->setPermissionsTeamId($admin->company_id);
        $role = Role::where('team_id', $admin->company_id)->where('name', 'auditor')->first();

        expect($role)->not->toBeNull()
            ->and($role->team_id)->toBe($admin->company_id)
            ->and($role->permissions->pluck('name')->sort()->values()->all())
            ->toBe(['clients.view', 'invoices.view']);
    });

    test('the role name must be unique within the company', function () {
        $admin = grantPermissions(companyAdmin(), ['roles.view', 'roles.manage']);
        actingAs($admin);

        app(PermissionRegistrar::class)->setPermissionsTeamId($admin->company_id);
        Role::create(['name' => 'existing', 'guard_name' => 'web']);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->set('name', 'existing')
            ->call('save')
            ->assertHasErrors(['name']);
    });

    test('editing a role updates its name and permissions', function () {
        $admin = grantPermissions(companyAdmin(), ['roles.view', 'roles.manage', 'clients.view']);
        actingAs($admin);

        app(PermissionRegistrar::class)->setPermissionsTeamId($admin->company_id);
        $role = Role::create(['name' => 'temp', 'guard_name' => 'web']);

        Livewire::test(Index::class)
            ->call('openEditModal', $role->id)
            ->set('name', 'renamed')
            ->set('selectedPermissions', ['clients.view'])
            ->call('save')
            ->assertHasNoErrors();

        $role->refresh();

        expect($role->name)->toBe('renamed')
            ->and($role->permissions->pluck('name')->all())->toBe(['clients.view']);
    });

    test('a role belonging to another company cannot be edited', function () {
        $admin = grantPermissions(companyAdmin(), ['roles.view', 'roles.manage']);
        $other = companyAdmin();

        app(PermissionRegistrar::class)->setPermissionsTeamId($other->company_id);
        $foreignRole = Role::create(['name' => 'foreign', 'guard_name' => 'web']);

        actingAs($admin);

        // The cross-tenant id must not resolve; the edit modal never populates with the foreign role.
        $component = null;
        try {
            $component = Livewire::test(Index::class)->call('openEditModal', $foreignRole->id);
        } catch (Throwable) {
            // findOrFail rejected the out-of-tenant id — the desired outcome.
        }

        if ($component !== null) {
            expect($component->get('name'))->not->toBe('foreign');
        } else {
            expect(true)->toBeTrue();
        }
    });

    test('a role can be deleted', function () {
        $admin = grantPermissions(companyAdmin(), ['roles.view', 'roles.manage']);
        actingAs($admin);

        app(PermissionRegistrar::class)->setPermissionsTeamId($admin->company_id);
        $role = Role::create(['name' => 'disposable', 'guard_name' => 'web']);

        Livewire::test(Index::class)
            ->call('confirmDelete', $role->id)
            ->call('delete')
            ->assertHasNoErrors();

        expect(Role::where('id', $role->id)->exists())->toBeFalse();
    });
});
