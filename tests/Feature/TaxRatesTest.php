<?php

use App\Livewire\Admin\TaxRates\Index;
use App\Models\TaxRate;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

describe('tenant isolation', function () {
    test('the company scope hides tax rates belonging to another company', function () {
        $admin = grantPermissions(companyAdmin(), ['invoices.view']);
        $other = companyAdmin();

        TaxRate::factory()->count(2)->create(['company_id' => $admin->company_id]);
        TaxRate::factory()->count(3)->create(['company_id' => $other->company_id]);

        actingAs($admin);

        expect(TaxRate::count())->toBe(2);
    });

    test('a tax rate created as a company admin auto-stamps the company id', function () {
        $admin = grantPermissions(companyAdmin(), ['invoices.view', 'invoices.create']);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->set('name', 'VAT 20%')
            ->set('rate', '20')
            ->call('save')
            ->assertHasNoErrors();

        $taxRate = TaxRate::withoutGlobalScopes()->firstWhere('name', 'VAT 20%');

        expect($taxRate)->not->toBeNull()
            ->and($taxRate->company_id)->toBe($admin->company_id);
    });
});

describe('access control', function () {
    test('a company admin without invoices.view is forbidden the list', function () {
        actingAs(companyAdmin())
            ->get(route('admin.tax-rates'))
            ->assertForbidden();
    });

    test('a company admin with invoices.view can see the list', function () {
        $admin = grantPermissions(companyAdmin(), ['invoices.view']);

        actingAs($admin)
            ->get(route('admin.tax-rates'))
            ->assertOk();
    });

    test('creating a tax rate without invoices.create is forbidden', function () {
        $admin = grantPermissions(companyAdmin(), ['invoices.view']);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->assertForbidden();
    });
});

describe('crud', function () {
    test('a tax rate requires a name and a valid rate', function () {
        $admin = grantPermissions(companyAdmin(), ['invoices.view', 'invoices.create']);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->set('name', '')
            ->set('rate', '150')
            ->call('save')
            ->assertHasErrors(['name' => 'required', 'rate' => 'max']);
    });

    test('an admin can edit a tax rate', function () {
        $admin = grantPermissions(companyAdmin(), ['invoices.view', 'invoices.update']);
        $taxRate = TaxRate::factory()->create(['company_id' => $admin->company_id]);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openEditModal', $taxRate->id)
            ->set('name', 'Updated Rate')
            ->set('rate', '9')
            ->call('save')
            ->assertHasNoErrors();

        expect($taxRate->fresh()->name)->toBe('Updated Rate')
            ->and((float) $taxRate->fresh()->rate)->toBe(9.0);
    });

    test('an admin can soft-delete a tax rate', function () {
        $admin = grantPermissions(companyAdmin(), ['invoices.view', 'invoices.delete']);
        $taxRate = TaxRate::factory()->create(['company_id' => $admin->company_id]);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('confirmDelete', $taxRate->id)
            ->call('delete')
            ->assertHasNoErrors();

        expect(TaxRate::find($taxRate->id))->toBeNull()
            ->and(TaxRate::withTrashed()->find($taxRate->id)?->trashed())->toBeTrue();
    });
});
