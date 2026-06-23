<?php

use App\Enums\BillingCycle;
use App\Livewire\Admin\Products\Index;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductPricing;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

describe('tenant isolation', function () {
    test('the company scope hides product groups belonging to another company', function () {
        $admin = grantPermissions(companyAdmin(), ['services.view']);
        $other = companyAdmin();

        ProductGroup::factory()->for($admin->company)->count(2)->create();
        ProductGroup::factory()->for($other->company)->count(3)->create();

        actingAs($admin);

        expect(ProductGroup::count())->toBe(2);
    });

    test('a group created as a company admin auto-stamps the company id', function () {
        $admin = grantPermissions(companyAdmin(), ['services.view', 'services.create']);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateGroupModal')
            ->set('groupName', 'Shared Hosting')
            ->call('saveGroup')
            ->assertHasNoErrors();

        $group = ProductGroup::withoutGlobalScopes()->firstWhere('name', 'Shared Hosting');

        expect($group)->not->toBeNull()
            ->and($group->company_id)->toBe($admin->company_id);
    });
});

describe('access control', function () {
    test('a company admin without services.view is forbidden the catalog', function () {
        actingAs(companyAdmin())
            ->get(route('admin.products'))
            ->assertForbidden();
    });

    test('a company admin with services.view can see the catalog', function () {
        $admin = grantPermissions(companyAdmin(), ['services.view']);

        actingAs($admin)
            ->get(route('admin.products'))
            ->assertOk();
    });

    test('creating a product without services.create is forbidden', function () {
        $admin = grantPermissions(companyAdmin(), ['services.view']);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateProductModal')
            ->assertForbidden();
    });
});

describe('groups', function () {
    test('an admin can edit a product group', function () {
        $admin = grantPermissions(companyAdmin(), ['services.view', 'services.update']);
        $group = ProductGroup::factory()->for($admin->company)->create(['name' => 'Old Group']);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openEditGroupModal', $group->id)
            ->set('groupName', 'New Group')
            ->call('saveGroup')
            ->assertHasNoErrors();

        expect($group->fresh()->name)->toBe('New Group');
    });

    test('an admin can soft-delete a product group', function () {
        $admin = grantPermissions(companyAdmin(), ['services.view', 'services.delete']);
        $group = ProductGroup::factory()->for($admin->company)->create();

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('confirmDeleteGroup', $group->id)
            ->call('delete')
            ->assertHasNoErrors();

        expect(ProductGroup::find($group->id))->toBeNull();
    });

    test('editing a group without services.update is forbidden', function () {
        $admin = grantPermissions(companyAdmin(), ['services.view']);
        $group = ProductGroup::factory()->for($admin->company)->create();

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openEditGroupModal', $group->id)
            ->assertForbidden();
    });
});

describe('crud', function () {
    test('a product can be created with pricing rows', function () {
        $admin = grantPermissions(companyAdmin(), ['services.view', 'services.create']);
        $group = ProductGroup::factory()->for($admin->company)->create();

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateProductModal', $group->id)
            ->set('productName', 'Pro Hosting')
            ->set('setupFee', '0')
            ->set('pricings', [
                ['id' => null, 'cycle' => BillingCycle::Monthly->value, 'price' => '499', 'currency' => 'INR'],
                ['id' => null, 'cycle' => BillingCycle::Annual->value, 'price' => '4999', 'currency' => 'INR'],
            ])
            ->call('saveProduct')
            ->assertHasNoErrors();

        $product = Product::withoutGlobalScopes()->with('pricings')->firstWhere('name', 'Pro Hosting');

        expect($product)->not->toBeNull()
            ->and($product->company_id)->toBe($admin->company_id)
            ->and($product->pricings)->toHaveCount(2);
    });

    test('a product rejects duplicate billing cycles', function () {
        $admin = grantPermissions(companyAdmin(), ['services.view', 'services.create']);
        $group = ProductGroup::factory()->for($admin->company)->create();

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateProductModal', $group->id)
            ->set('productName', 'Dup Cycles')
            ->set('pricings', [
                ['id' => null, 'cycle' => BillingCycle::Monthly->value, 'price' => '499', 'currency' => 'INR'],
                ['id' => null, 'cycle' => BillingCycle::Monthly->value, 'price' => '999', 'currency' => 'INR'],
            ])
            ->call('saveProduct')
            ->assertHasErrors('pricings');
    });

    test('editing a product syncs its pricing rows', function () {
        $admin = grantPermissions(companyAdmin(), ['services.view', 'services.update']);
        $group = ProductGroup::factory()->for($admin->company)->create();
        $product = Product::factory()->for($admin->company)->for($group, 'group')->create();
        ProductPricing::factory()->for($product)->cycle(BillingCycle::Monthly)->create([
            'company_id' => $admin->company_id,
        ]);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openEditProductModal', $product->id)
            ->set('pricings', [
                ['id' => null, 'cycle' => BillingCycle::Annual->value, 'price' => '4999', 'currency' => 'INR'],
            ])
            ->call('saveProduct')
            ->assertHasNoErrors();

        $product->refresh()->load('pricings');

        expect($product->pricings)->toHaveCount(1)
            ->and($product->pricings->first()->cycle)->toBe(BillingCycle::Annual);
    });

    test('an admin can soft-delete a product', function () {
        $admin = grantPermissions(companyAdmin(), ['services.view', 'services.delete']);
        $group = ProductGroup::factory()->for($admin->company)->create();
        $product = Product::factory()->for($admin->company)->for($group, 'group')->create();

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('confirmDeleteProduct', $product->id)
            ->call('delete')
            ->assertHasNoErrors();

        expect(Product::find($product->id))->toBeNull()
            ->and(Product::withTrashed()->find($product->id)?->trashed())->toBeTrue();
    });
});
