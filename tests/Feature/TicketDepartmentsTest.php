<?php

use App\Livewire\Admin\TicketDepartments\Index;
use App\Models\TicketDepartment;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

describe('tenant isolation', function () {
    test('the company scope hides departments belonging to another company', function () {
        $admin = grantPermissions(companyAdmin(), ['tickets.view']);
        $other = companyAdmin();

        TicketDepartment::factory()->count(2)->create(['company_id' => $admin->company_id]);
        TicketDepartment::factory()->count(3)->create(['company_id' => $other->company_id]);

        actingAs($admin);

        expect(TicketDepartment::count())->toBe(2);
    });

    test('a department created as a company admin auto-stamps the company id', function () {
        $admin = grantPermissions(companyAdmin(), ['tickets.view', 'tickets.create']);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->set('name', 'Technical')
            ->call('save')
            ->assertHasNoErrors();

        $department = TicketDepartment::withoutGlobalScopes()->firstWhere('name', 'Technical');

        expect($department)->not->toBeNull()
            ->and($department->company_id)->toBe($admin->company_id)
            ->and($department->is_active)->toBeTrue();
    });
});

describe('access control', function () {
    test('a company admin without tickets.view is forbidden the list', function () {
        actingAs(companyAdmin())
            ->get(route('admin.ticket-departments'))
            ->assertForbidden();
    });

    test('a company admin with tickets.view can see the list', function () {
        $admin = grantPermissions(companyAdmin(), ['tickets.view']);

        actingAs($admin)
            ->get(route('admin.ticket-departments'))
            ->assertOk();
    });

    test('creating a department without tickets.create is forbidden', function () {
        $admin = grantPermissions(companyAdmin(), ['tickets.view']);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->assertForbidden();
    });
});

describe('validation', function () {
    test('a department requires a name', function () {
        $admin = grantPermissions(companyAdmin(), ['tickets.view', 'tickets.create']);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name' => 'required']);
    });
});

describe('crud', function () {
    test('an admin can edit a department', function () {
        $admin = grantPermissions(companyAdmin(), ['tickets.view', 'tickets.update']);
        $department = TicketDepartment::factory()->create(['company_id' => $admin->company_id, 'name' => 'Old']);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openEditModal', $department->id)
            ->set('name', 'New')
            ->set('isActive', false)
            ->call('save')
            ->assertHasNoErrors();

        $department->refresh();

        expect($department->name)->toBe('New')
            ->and($department->is_active)->toBeFalse();
    });

    test('an admin can soft-delete a department', function () {
        $admin = grantPermissions(companyAdmin(), ['tickets.view', 'tickets.delete']);
        $department = TicketDepartment::factory()->create(['company_id' => $admin->company_id]);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('confirmDelete', $department->id)
            ->call('delete')
            ->assertHasNoErrors();

        expect(TicketDepartment::find($department->id))->toBeNull()
            ->and(TicketDepartment::withTrashed()->find($department->id)?->trashed())->toBeTrue();
    });
});
